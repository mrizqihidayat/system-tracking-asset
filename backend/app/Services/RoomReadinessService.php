<?php

namespace App\Services;

use App\Enums\AssetStatus;
use App\Enums\ChecklistResult;
use App\Enums\CountMethod;
use App\Models\Asset;
use App\Models\Building;
use App\Models\Room;
use App\Models\RoomStandard;
use App\Models\User;
use App\Support\AppCache;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Kelengkapan ruangan (SPEC §8): checklist, simpan hasil cek, isi dari stok, ringkasan kesiapan.
 * summarize() dipakai ulang T14 (saran permintaan) & T15 (dashboard): Belum dicek bukan kekurangan.
 */
class RoomReadinessService
{
    /**
     * Status aset yang tidak dihitung "Ada" pada baris QR.
     */
    public const EXCLUDED_STATUSES = [
        AssetStatus::Dipinjam,
        AssetStatus::Perbaikan,
        AssetStatus::Hilang,
        AssetStatus::TidakLayakPakai,
    ];

    public const RELATIONS = ['sourceDepartment', 'assetType', 'consumable.providerDepartment', 'catalog'];

    public function __construct(private ConsumableService $consumables) {}

    /**
     * @return Collection<int, RoomStandard>
     */
    public function checklist(Room $room): Collection
    {
        return $this->fillQrCounts($room->standards()->with(self::RELATIONS)->orderBy('id')->get());
    }

    /**
     * Isi jumlah "Ada", kode aset, dan jumlah aset yang tidak dihitung pada baris QR dengan satu query
     * (dikelompokkan per ruangan & jenis aset). Tidak Layak Pakai tidak diambil: tidak ditampilkan sama sekali.
     *
     * @param  Collection<int, RoomStandard>  $standards
     * @return Collection<int, RoomStandard>
     */
    public function fillQrCounts(Collection $standards): Collection
    {
        $qr = $standards->filter(fn (RoomStandard $s) => $s->count_method === CountMethod::Qr);

        if ($qr->isEmpty()) {
            return $standards;
        }

        $assets = Asset::query()
            ->whereIn('room_id', $qr->pluck('room_id')->unique()->values())
            ->whereIn('asset_type_id', $qr->pluck('asset_type_id')->filter()->unique()->values())
            ->where('status', '!=', AssetStatus::TidakLayakPakai->value)
            ->orderBy('code')
            ->select('id', 'code', 'status', 'room_id', 'asset_type_id')
            ->toBase()
            ->get()
            ->groupBy(fn ($asset) => "{$asset->room_id}:{$asset->asset_type_id}");

        $excluded = array_map(fn (AssetStatus $s) => $s->value, self::EXCLUDED_STATUSES);

        foreach ($qr as $standard) {
            [$notCounted, $counted] = ($assets["{$standard->room_id}:{$standard->asset_type_id}"] ?? collect())
                ->partition(fn ($asset) => in_array($asset->status, $excluded, true));

            $standard->qrPresentQuantity = $counted->count();
            $standard->qrAssets = $counted->map(fn ($asset) => ['id' => (int) $asset->id, 'code' => $asset->code])->values()->all();
            $standard->qrNotCounted = $notCounted->countBy('status')->all();
        }

        return $standards;
    }

    /**
     * @param  iterable<RoomStandard>  $standards
     * @return array{total_items: int, shortage_items: int, unchecked_items: int, is_complete: bool}
     */
    public function summarize(iterable $standards): array
    {
        $total = $shortage = $unchecked = 0;

        foreach ($standards as $standard) {
            $total++;
            match ($standard->checklistResult()) {
                ChecklistResult::Kurang => $shortage++,
                ChecklistResult::BelumDicek => $unchecked++,
                ChecklistResult::Sesuai => null,
            };
        }

        return [
            'total_items' => $total,
            'shortage_items' => $shortage,
            'unchecked_items' => $unchecked,
            'is_complete' => $shortage === 0,
        ];
    }

    /**
     * Gedung → ruangan beserta standar (jumlah QR sudah diisi), untuk ringkasan kesiapan.
     *
     * @return Collection<int, Building>
     */
    public function readiness(): Collection
    {
        $buildings = Building::query()
            ->with(['rooms' => fn ($q) => $q->orderBy('name')->with(['standards', 'lastCheckedBy'])])
            ->orderBy('name')
            ->get();

        $this->fillQrCounts(new Collection($buildings->flatMap(fn (Building $b) => $b->rooms->flatMap->standards)->all()));

        return $buildings;
    }

    /**
     * Ringkasan kesiapan per gedung → ruangan (array, di-cache grup ops); dipakai halaman kelengkapan
     * dan dashboard. Ruangan tanpa standar = lengkap.
     *
     * @return list<array{id: int, name: string, slug: string, rooms_count: int, incomplete_rooms_count: int, rooms: list<array<string, mixed>>}>
     */
    public function summary(): array
    {
        return AppCache::remember(AppCache::GROUP_OPS, 'room-readiness', fn () => $this->readiness()
            ->map(function (Building $building) {
                $rooms = $building->rooms->map(fn (Room $room) => [
                    'id' => $room->id,
                    'name' => $room->name,
                    'slug' => $room->slug,
                    'last_checked_at' => $room->last_checked_at?->toIso8601String(),
                    'last_checked_by' => $room->lastCheckedBy?->name,
                    ...$this->summarize($room->standards),
                ]);

                return [
                    'id' => $building->id,
                    'name' => $building->name,
                    'slug' => $building->slug,
                    'rooms_count' => $rooms->count(),
                    'incomplete_rooms_count' => $rooms->where('is_complete', false)->count(),
                    'rooms' => $rooms->values()->all(),
                ];
            })
            ->values()
            ->all());
    }

    /**
     * Simpan hasil cek: jumlah Ada baris Manual + waktu & staf pengecek (hanya hasil terakhir).
     *
     * @param  list<array{id: int, actual_quantity: int}>  $rows
     */
    public function saveCheck(Room $room, array $rows, User $user): Room
    {
        return DB::transaction(function () use ($room, $rows, $user) {
            $room = Room::query()->whereKey($room->getKey())->lockForUpdate()->firstOrFail();
            $standards = $room->standards()->whereKey(array_column($rows, 'id'))->lockForUpdate()->get()->keyBy('id');

            foreach ($rows as $row) {
                $standard = $standards->get($row['id']);

                if (! $standard || $standard->count_method !== CountMethod::Manual) {
                    throw ValidationException::withMessages(['items' => 'Hanya baris Manual ruangan ini yang bisa diisi.']);
                }

                $standard->update(['actual_quantity' => $row['actual_quantity']]);
            }

            $room->update(['last_checked_at' => now(), 'last_checked_by' => $user->id]);

            return $room;
        });
    }

    /**
     * Isi dari stok SHD: jumlah = min(kekurangan, stok). Stok berkurang & Ada bertambah dalam satu transaksi;
     * sisa kekurangan tetap tercatat.
     *
     * @return array{standard: RoomStandard, filled: int, remaining_shortage: int, stock_remaining: int}
     */
    public function fillFromStock(RoomStandard $standard): array
    {
        return DB::transaction(function () use ($standard) {
            $standard = RoomStandard::query()->whereKey($standard->getKey())->lockForUpdate()->firstOrFail();

            if ($standard->count_method !== CountMethod::Manual || ! $standard->consumable_id) {
                throw ValidationException::withMessages(['standard' => 'Baris ini tidak ditautkan ke stok SHD.']);
            }

            $shortage = $standard->shortage();

            if ($shortage === null) {
                throw ValidationException::withMessages(['standard' => 'Simpan hasil cek ruangan dulu.']);
            }

            if ($shortage === 0) {
                throw ValidationException::withMessages(['standard' => 'Jumlah sudah sesuai standar.']);
            }

            $consumable = $standard->consumable()->lockForUpdate()->firstOrFail();
            $filled = min($shortage, $consumable->quantity);

            if ($filled <= 0) {
                throw ValidationException::withMessages(['standard' => "Stok {$consumable->name} habis."]);
            }

            $consumable = $this->consumables->adjust($consumable, -$filled);
            $standard->update(['actual_quantity' => $standard->actual_quantity + $filled]);

            return [
                'standard' => $standard,
                'filled' => $filled,
                'remaining_shortage' => $shortage - $filled,
                'stock_remaining' => $consumable->quantity,
            ];
        });
    }
}
