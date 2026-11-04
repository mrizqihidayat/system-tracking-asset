<?php

namespace App\Services;

use App\Enums\ConsumableStatus;
use App\Enums\CountMethod;
use App\Enums\RequestStatus;
use App\Models\Consumable;
use App\Models\RequestCatalog;
use App\Models\RoomStandard;
use App\Models\SupplyRequest;
use App\Models\User;
use App\Support\AppCache;
use App\Support\DbInteger;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Permintaan ke unit lain (SPEC §9): catat, ubah status (transisi terkunci, ikut mengubah stok / Ada ruangan),
 * saran permintaan, hapus katalog.
 */
class SupplyRequestService
{
    public function __construct(private ConsumableService $consumables) {}

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
            $this->ensureTransition($request, $to);

            $effect = $this->stockEffect($request, $to, lock: true);
            $this->applyEffect($request, $to, $effect);

            $request->status = $to;
            $request->statusImpact = $this->impact($effect);

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
     * Pratinjau dampak perubahan status ke stok / Ada ruangan (popup sebelum menyimpan).
     *
     * @return array{message: string, clamped: bool}
     */
    public function statusImpact(SupplyRequest $request, RequestStatus $to): array
    {
        $this->ensureTransition($request, $to);

        return $this->impact($this->stockEffect($request, $to));
    }

    private function ensureTransition(SupplyRequest $request, RequestStatus $to): void
    {
        $allowed = match ($request->status) {
            RequestStatus::Diajukan => [RequestStatus::Diterima, RequestStatus::Dibatalkan],
            RequestStatus::Diterima => [RequestStatus::Dikembalikan, RequestStatus::Dibatalkan],
            default => [],
        };

        if (! in_array($to, $allowed, true)) {
            throw ValidationException::withMessages(['status' => "Status {$request->status->value} tidak dapat diubah ke {$to->value}."]);
        }

        if ($to === RequestStatus::Dikembalikan && ! $request->must_return) {
            throw ValidationException::withMessages(['status' => 'Barang ini tidak perlu dikembalikan.']);
        }
    }

    /**
     * Sasaran & jumlah perubahan. Diterima: barang stok → stok, barang unit lain → Ada baris standar yang ditautkan.
     * Dikembalikan/Dibatalkan setelah Diterima: kurangi tujuan yang tercatat, tidak sampai di bawah 0.
     *
     * @return array{target: Consumable|RoomStandard, adding: bool, amount: int, current: int, clamped: bool}|null
     */
    private function stockEffect(SupplyRequest $request, RequestStatus $to, bool $lock = false): ?array
    {
        if ($to === RequestStatus::Diterima) {
            $target = $this->receiveTarget($request, $lock);

            return $target ? ['target' => $target, 'adding' => true, 'amount' => $request->quantity, 'current' => $this->currentAmount($target), 'clamped' => false] : null;
        }

        if ($request->status !== RequestStatus::Diterima || $request->applied_to === null) {
            return null;
        }

        $target = $request->applied_to === SupplyRequest::APPLIED_TO_STOCK
            ? $this->find(Consumable::query(), $request->consumable_id, $lock)
            : $this->find(RoomStandard::query(), $request->room_standard_id, $lock);

        if (! $target) {
            return null;
        }

        $current = $this->currentAmount($target);

        return ['target' => $target, 'adding' => false, 'amount' => min($request->quantity, $current), 'current' => $current, 'clamped' => $current < $request->quantity];
    }

    private function receiveTarget(SupplyRequest $request, bool $lock): Consumable|RoomStandard|null
    {
        return $this->find(Consumable::query(), $request->consumable_id, $lock)
            ?? $this->find(RoomStandard::query(), $request->room_standard_id, $lock);
    }

    /**
     * @template TModel of Consumable|RoomStandard
     *
     * @param  Builder<TModel>  $query
     * @return TModel|null
     */
    private function find(Builder $query, ?int $id, bool $lock): Consumable|RoomStandard|null
    {
        if (! $id) {
            return null;
        }

        return ($lock ? $query->lockForUpdate() : $query)->find($id);
    }

    private function currentAmount(Consumable|RoomStandard $target): int
    {
        return $target instanceof Consumable ? $target->quantity : (int) $target->actual_quantity;
    }

    /**
     * @param  array{target: Consumable|RoomStandard, adding: bool, amount: int, current: int, clamped: bool}|null  $effect
     */
    private function applyEffect(SupplyRequest $request, RequestStatus $to, ?array $effect): void
    {
        if ($effect !== null && $effect['amount'] > 0) {
            $signed = $effect['adding'] ? $effect['amount'] : -$effect['amount'];
            $target = $effect['target'];

            if ($target instanceof Consumable) {
                $this->consumables->adjust($target, $signed);
            } else {
                if ($effect['current'] + $signed > DbInteger::MAX) {
                    throw ValidationException::withMessages(['status' => 'Jumlah Ada melebihi batas.']);
                }

                $target->update(['actual_quantity' => $effect['current'] + $signed]);
            }
        }

        if ($to === RequestStatus::Diterima) {
            $target = $effect['target'] ?? null;
            $request->applied_to = match (true) {
                $target instanceof Consumable => SupplyRequest::APPLIED_TO_STOCK,
                $target instanceof RoomStandard => SupplyRequest::APPLIED_TO_ROOM,
                default => null,
            };
            $request->room_standard_id = $target instanceof RoomStandard ? $target->id : null;
        } else {
            $request->applied_to = null;
        }
    }

    /**
     * @param  array{target: Consumable|RoomStandard, adding: bool, amount: int, current: int, clamped: bool}|null  $effect
     * @return array{message: string, clamped: bool}
     */
    private function impact(?array $effect): array
    {
        if ($effect === null) {
            return ['message' => 'Tidak mengubah stok atau kelengkapan ruangan.', 'clamped' => false];
        }

        $target = $effect['target'];
        $subject = $target instanceof Consumable
            ? "Stok {$target->name}"
            : "Ada {$target->item_name} di {$target->room->name}";

        if ($effect['adding']) {
            return ['message' => "{$subject} akan bertambah {$effect['amount']}.", 'clamped' => false];
        }

        if ($effect['clamped']) {
            return [
                'message' => $effect['current'] === 0
                    ? "{$subject} sudah 0, tidak ada yang dikurangi. Cek manual selisihnya."
                    : "{$subject} hanya tersisa {$effect['current']}, jadi berkurang {$effect['amount']} sampai 0. Cek manual selisihnya.",
                'clamped' => true,
            ];
        }

        return ['message' => "{$subject} akan berkurang {$effect['amount']}.", 'clamped' => false];
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
        $stock = $this->stockSuggestions()->whereNotIn('consumable_id', $rooms->pluck('consumable_id')->filter()->all());

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
            ->where('count_method', CountMethod::Manual->value)
            ->with(['consumable.catalog', 'consumable.providerDepartment', 'catalog', 'sourceDepartment', 'room.building'])
            ->orderBy('room_id')
            ->orderBy('id')
            ->get()
            ->filter(fn (RoomStandard $standard) => $standard->canRequest())
            ->map(fn (RoomStandard $standard) => [
                'source' => 'kekurangan_ruangan',
                'item_name' => $standard->consumable?->name ?? $standard->item_name,
                'catalog_name' => ($standard->consumable ? $standard->consumable->catalog : $standard->catalog)?->item_name,
                'quantity' => $standard->shortage(),
                'catalog_id' => $standard->consumable ? $standard->consumable->catalog_id : $standard->catalog_id,
                'room' => ['id' => $standard->room_id, 'name' => $standard->room->name, 'building' => $standard->room->building->name],
                'room_standard_id' => $standard->id,
                'consumable_id' => $standard->consumable_id,
                'provider_department' => $this->department($standard->consumable?->providerDepartment ?? $standard->sourceDepartment),
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
                'item_name' => $consumable->name,
                'catalog_name' => $consumable->catalog?->item_name,
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

        // Permintaan tertaut stok dicocokkan per barang (satu katalog bisa dipakai banyak barang).
        if ($request->consumable_id !== null) {
            return (int) $request->consumable_id === (int) $item['consumable_id'];
        }

        if ($request->room_standard_id !== null) {
            return (int) $request->room_standard_id === (int) $item['room_standard_id'];
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
     * Snapshot lead time dari katalog & "perlu dikembalikan" dari pilihan form (default katalog); riwayat tidak
     * berubah saat katalog diedit/dihapus.
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

        // Permintaan tertaut stok memakai nama barang, bukan nama katalog.
        if (! empty($data['consumable_id'])) {
            $data['item_name'] = Consumable::query()->whereKey($data['consumable_id'])->value('name');
            $data['room_standard_id'] = null;
        } elseif (! empty($data['room_standard_id'])) {
            // Barang unit lain: nama & ruangan mengikuti baris standarnya.
            $standard = RoomStandard::query()->findOrFail($data['room_standard_id']);
            $data['item_name'] = $standard->item_name;
            $data['room_id'] = $standard->room_id;
        }

        $data['lead_time_days'] = $catalog?->lead_time_days;
        // Pilihan di form yang disimpan; jika tidak dikirim mengikuti katalog (tanpa katalog: tidak dikembalikan).
        $data['must_return'] = (bool) ($data['must_return'] ?? $catalog?->must_return ?? false);

        $data['request_date'] = ($data['request_date'] ?? null) ?: today()->toDateString();

        return $data;
    }

    private function lock(SupplyRequest $request): SupplyRequest
    {
        return SupplyRequest::query()->with('catalog')->whereKey($request->getKey())->lockForUpdate()->firstOrFail();
    }
}
