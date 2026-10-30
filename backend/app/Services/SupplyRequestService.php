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
    public function __construct(private RoomReadinessService $readiness) {}

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
     * Diajukan → Diterima | Dibatalkan; Diterima → Dikembalikan (hanya jika katalog "perlu dikembalikan") | Dibatalkan.
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

            if ($to === RequestStatus::Dikembalikan && ! $request->catalog?->must_return) {
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
     * Hapus item katalog; ditolak bila masih dipakai (permintaan, stok, standar ruangan).
     */
    public function deleteCatalog(RequestCatalog $catalog): void
    {
        DB::transaction(function () use ($catalog) {
            $catalog = RequestCatalog::query()->whereKey($catalog->getKey())->lockForUpdate()->firstOrFail();

            $used = collect([
                'permintaan' => $catalog->supplyRequests()->count(),
                'barang habis pakai' => $catalog->consumables()->count(),
                'standar ruangan' => $catalog->roomStandards()->count(),
            ])->filter()->map(fn (int $count, string $label) => "{$count} {$label}");

            if ($used->isNotEmpty()) {
                throw ValidationException::withMessages(['id' => 'Item katalog masih dipakai: '.$used->implode(', ').'.']);
            }

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

        $items = $this->roomSuggestions()
            ->concat($this->stockSuggestions())
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
        $standards = RoomStandard::query()
            ->whereNotNull('catalog_id')
            ->with(['catalog.providerDepartment', 'room.building'])
            ->orderBy('room_id')
            ->orderBy('id')
            ->get();

        return $this->readiness->fillQrCounts($standards)
            ->filter(fn (RoomStandard $standard) => ($standard->shortage() ?? 0) > 0)
            ->map(fn (RoomStandard $standard) => [
                'source' => 'kekurangan_ruangan',
                'item_name' => $standard->catalog->item_name,
                'quantity' => $standard->shortage(),
                'catalog_id' => $standard->catalog_id,
                'room' => ['id' => $standard->room_id, 'name' => $standard->room->name, 'building' => $standard->room->building->name],
                'room_standard_id' => $standard->id,
                'consumable_id' => null,
                'provider_department' => $this->department($standard->catalog->providerDepartment),
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
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function resolve(array $data): array
    {
        if (! empty($data['catalog_id'])) {
            $catalog = RequestCatalog::query()->findOrFail($data['catalog_id']);
            $data['item_name'] = ($data['item_name'] ?? null) ?: $catalog->item_name;
            $data['provider_department_id'] = $catalog->provider_department_id;
        }

        $data['request_date'] = ($data['request_date'] ?? null) ?: today()->toDateString();

        return $data;
    }

    private function lock(SupplyRequest $request): SupplyRequest
    {
        return SupplyRequest::query()->with('catalog')->whereKey($request->getKey())->lockForUpdate()->firstOrFail();
    }
}
