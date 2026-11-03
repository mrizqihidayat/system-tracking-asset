<?php

namespace App\Services;

use App\Enums\ConsumableStatus;
use App\Enums\RequestStatus;
use App\Models\Consumable;
use App\Models\RequestCatalog;
use App\Models\RoomStandard;
use App\Models\SupplyRequest;
use App\Models\User;
use App\Support\AppCache;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Permintaan ke unit lain (SPEC §9): catat, ubah status (transisi terkunci), saran permintaan, hapus katalog.
 */
class SupplyRequestService
{
    /**
     * Item & unit penyedia diisi dari katalog bila dikirim; tanggal default hari ini.
     *
     * @param  array<string, mixed>  $data
     */
    public function create(array $data, User $user): SupplyRequest
    {
        return SupplyRequest::create($this->resolve($data) + ['created_by' => $user->id]);
    }

    /**
     * Hanya yang masih Diajukan yang boleh diedit.
     *
     * @param  array<string, mixed>  $data
     */
    public function update(SupplyRequest $request, array $data): SupplyRequest
    {
        return DB::transaction(function () use ($request, $data) {
            $request = $this->lock($request);

            if ($request->status !== RequestStatus::Diajukan) {
                throw ValidationException::withMessages(['status' => 'Hanya permintaan berstatus Diajukan yang dapat diubah.']);
            }

            $request->update($this->resolve($data));

            return $request;
        });
    }

    /**
     * Diajukan → Diterima | Dibatalkan; Diterima → Dikembalikan (hanya jika snapshot "perlu dikembalikan") | Dibatalkan.
     */
    public function changeStatus(SupplyRequest $request, RequestStatus $to, ?string $note = null): SupplyRequest
    {
        return DB::transaction(function () use ($request, $to, $note) {
            $request = $this->lock($request);
            $from = $request->status;

            $allowed = match ($from) {
                RequestStatus::Diajukan => [RequestStatus::Diterima, RequestStatus::Dibatalkan],
                RequestStatus::Diterima => [RequestStatus::Dikembalikan, RequestStatus::Dibatalkan],
                default => [],
            };

            if (! in_array($to, $allowed, true)) {
                throw ValidationException::withMessages(['status' => "Status {$from->value} tidak dapat diubah ke {$to->value}."]);
            }

            if ($to === RequestStatus::Dikembalikan && ! $request->must_return) {
                throw ValidationException::withMessages(['status' => 'Barang ini tidak perlu dikembalikan.']);
            }

            $request->status = $to;

            match ($to) {
                RequestStatus::Diterima => $request->received_at = now(),
                RequestStatus::Dikembalikan => $request->returned_at = now(),
                default => null,
            };

            if ($note !== null && $note !== '') {
                $request->note = $note;
            }

            $request->save();

            return $request;
        });
    }

    /**
     * Pemakaian item katalog (popup sebelum hapus).
     *
     * @return array{supply_requests: int, room_standards: int, consumables: int, total: int}
     */
    public function catalogUsage(RequestCatalog $catalog): array
    {
        $usage = [
            'supply_requests' => $catalog->supplyRequests()->count(),
            'room_standards' => $catalog->roomStandards()->count(),
            'consumables' => $catalog->consumables()->count(),
        ];

        return $usage + ['total' => array_sum($usage)];
    }

    /**
     * Hapus item katalog walau dipakai: tautan catalog_id di permintaan, standar ruangan, dan stok dilepas
     * dalam satu transaksi. Riwayat permintaan tetap utuh (item, unit, cara, lead time, must_return tersimpan).
     */
    public function deleteCatalog(RequestCatalog $catalog): void
    {
        DB::transaction(function () use ($catalog) {
            $catalog = RequestCatalog::query()->whereKey($catalog->getKey())->lockForUpdate()->firstOrFail();

            $catalog->supplyRequests()->update(['catalog_id' => null]);
            $catalog->roomStandards()->update(['catalog_id' => null]);
            $catalog->consumables()->update(['catalog_id' => null]);

            // Observer katalog (deleted) mengosongkan cache master & ops yang memuat tautan tadi.
            $catalog->delete();
        });
    }

    /**
     * Saran permintaan per unit penyedia: kekurangan ruangan (pemenuhan "minta ke", Belum dicek tidak
     * termasuk) + stok Menipis/Habis. has_open_request = sudah ada permintaan Diajukan untuk item itu.
     *
     * @return list<array{provider_department: array{id: int, name: string}, items: list<array<string, mixed>>}>
     */
    public function suggestions(): array
    {
        return AppCache::remember(AppCache::GROUP_OPS, 'supply-suggestions', fn () => $this->buildSuggestions());
    }

    /**
     * @return list<array{provider_department: array{id: int, name: string}, items: list<array<string, mixed>>}>
     */
    private function buildSuggestions(): array
    {
        $open = SupplyRequest::query()->where('status', RequestStatus::Diajukan->value)->get();

        $rooms = $this->roomSuggestions();
        // Barang yang sudah muncul dari kekurangan ruangan tidak diulang sebagai saran stok.
        $stock = $this->stockSuggestions()->whereNotIn('consumable_id', $rooms->pluck('consumable_id'));

        $items = $rooms
            ->concat($stock)
            ->map(function (array $item) use ($open) {
                $item['has_open_request'] = $open->contains(fn (SupplyRequest $request) => $this->matches($request, $item));

                return $item;
            });

        return $items
            ->groupBy(fn (array $item) => $item['provider_department']['id'])
            ->map(fn (Collection $group) => [
                'provider_department' => $group->first()['provider_department'],
                'items' => $group->values()->map(fn (array $item) => collect($item)->except('provider_department')->all())->all(),
            ])
            ->sortBy(fn (array $group) => mb_strtolower($group['provider_department']['name']))
            ->values()
            ->all();
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    private function roomSuggestions(): Collection
    {
        return RoomStandard::query()
            ->whereNotNull('consumable_id')
            ->with(['consumable.catalog', 'consumable.providerDepartment', 'room.building'])
            ->orderBy('room_id')
            ->orderBy('id')
            ->get()
            ->filter(fn (RoomStandard $standard) => $standard->canRequest())
            ->map(fn (RoomStandard $standard) => [
                'source' => 'kekurangan_ruangan',
                'item_name' => $standard->consumable->catalog?->item_name ?? $standard->consumable->name,
                'quantity' => $standard->shortage(),
                'catalog_id' => $standard->consumable->catalog_id,
                'room' => ['id' => $standard->room_id, 'name' => $standard->room->name, 'building' => $standard->room->building->name],
                'room_standard_id' => $standard->id,
                'consumable_id' => $standard->consumable_id,
                'provider_department' => $this->department($standard->consumable->providerDepartment),
            ])
            ->values();
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    private function stockSuggestions(): Collection
    {
        return Consumable::query()
            ->where(fn ($query) => $query->withStatus(ConsumableStatus::Habis)->orWhere(fn ($q) => $q->withStatus(ConsumableStatus::Menipis)))
            ->with(['catalog', 'providerDepartment'])
            ->orderBy('name')
            ->get()
            ->map(fn (Consumable $consumable) => [
                'source' => 'stok',
                'item_name' => $consumable->catalog?->item_name ?? $consumable->name,
                'quantity' => max($consumable->min_quantity - $consumable->quantity, 1),
                'catalog_id' => $consumable->catalog_id,
                'room' => null,
                'room_standard_id' => null,
                'consumable_id' => $consumable->id,
                'stock_status' => $consumable->status()->value,
                'provider_department' => $this->department($consumable->providerDepartment),
            ]);
    }

    /**
     * @param  array<string, mixed>  $item
     */
    private function matches(SupplyRequest $request, array $item): bool
    {
        if ((int) $request->provider_department_id !== (int) $item['provider_department']['id']) {
            return false;
        }

        if ($item['room'] !== null && (int) $request->room_id !== (int) $item['room']['id']) {
            return false;
        }

        return $item['catalog_id'] !== null
            ? (int) $request->catalog_id === (int) $item['catalog_id']
            : mb_strtolower($request->item_name) === mb_strtolower($item['item_name']);
    }

    /**
     * @return array{id: int, name: string}
     */
    private function department(object $department): array
    {
        return ['id' => $department->id, 'name' => $department->name];
    }

    /**
     * Snapshot lead time & "perlu dikembalikan" dari katalog (permintaan tanpa katalog: tanpa lead time,
     * tidak perlu dikembalikan); riwayat tidak berubah saat katalog diedit/dihapus.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function resolve(array $data): array
    {
        $catalog = empty($data['catalog_id']) ? null : RequestCatalog::query()->findOrFail($data['catalog_id']);

        if ($catalog) {
            $data['item_name'] = ($data['item_name'] ?? null) ?: $catalog->item_name;
            $data['provider_department_id'] = $catalog->provider_department_id;
        }

        $data['lead_time_days'] = $catalog?->lead_time_days;
        $data['must_return'] = (bool) $catalog?->must_return;

        $data['request_date'] = ($data['request_date'] ?? null) ?: today()->toDateString();

        return $data;
    }

    private function lock(SupplyRequest $request): SupplyRequest
    {
        return SupplyRequest::query()->with('catalog')->whereKey($request->getKey())->lockForUpdate()->firstOrFail();
    }
}
