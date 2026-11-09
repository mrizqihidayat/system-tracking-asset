<?php

namespace App\Services;

use App\Enums\AssetCondition;
use App\Enums\AssetStatus;
use App\Enums\ChecklistResult;
use App\Enums\CountMethod;
use App\Enums\StockTransactionType;
use App\Models\Asset;
use App\Models\AssetType;
use App\Models\Building;
use App\Models\Consumable;
use App\Models\Room;
use App\Models\RoomStandard;
use App\Models\User;
use App\Support\AppCache;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Kelengkapan ruangan (SPEC §8): aset di ruangan, standar, simpan hasil cek, ambil dari stok, ringkasan kesiapan.
 * summarize() dipakai dashboard & laporan: Belum dicek bukan kekurangan.
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

    public const RELATIONS = ['assetType', 'consumable.providerDepartment'];

    public function __construct(private ConsumableService $consumables) {}

    /**
     * @return Collection<int, RoomStandard>
     */
    public function checklist(Room $room): Collection
    {
        return $this->fillQrCounts($room->standards()->with(self::RELATIONS)->orderBy('id')->get());
    }

    /**
     * Isi jumlah "Ada" baris QR dengan satu query (dikelompokkan per ruangan & jenis aset).
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

        $counts = Asset::query()
            ->whereIn('room_id', $qr->pluck('room_id')->unique()->values())
            ->whereIn('asset_type_id', $qr->pluck('asset_type_id')->unique()->values())
            ->whereNotIn('status', array_map(fn (AssetStatus $s) => $s->value, self::EXCLUDED_STATUSES))
            ->selectRaw('room_id, asset_type_id, count(*) as total')
            ->groupBy('room_id', 'asset_type_id')
            ->toBase()
            ->get()
            ->keyBy(fn ($row) => "{$row->room_id}:{$row->asset_type_id}");

        foreach ($qr as $standard) {
            $standard->qrPresentQuantity = (int) ($counts["{$standard->room_id}:{$standard->asset_type_id}"]->total ?? 0);
        }

        return $standards;
    }

    /**
     * Bagian "Aset di ruangan": aset berlokasi asal di ruangan ini per jenis (Tidak Layak Pakai tidak tampil).
     * Tanpa baris standar berarti standar 0: tampil jumlah Ada tanpa Hasil. Standar > 0 tampil walau asetnya 0.
     * Kondisi per aset dan ringkasan `conditions` (urut Baik → Rusak Berat) dihitung dari query yang sama.
     *
     * @param  Collection<int, RoomStandard>  $standards  sudah melewati checklist()
     * @return list<array<string, mixed>>
     */
    public function assetGroups(Room $room, Collection $standards): array
    {
        $excluded = array_map(fn (AssetStatus $s) => $s->value, self::EXCLUDED_STATUSES);

        $assets = Asset::query()
            ->where('room_id', $room->id)
            ->where('status', '!=', AssetStatus::TidakLayakPakai->value)
            ->orderBy('code')
            ->select('id', 'code', 'status', 'condition', 'asset_type_id')
            ->toBase()
            ->get()
            ->groupBy('asset_type_id');

        $standardsByType = $standards->filter(fn (RoomStandard $s) => $s->count_method === CountMethod::Qr)->keyBy('asset_type_id');

        return AssetType::query()
            ->whereKey($assets->keys()->merge($standardsByType->keys())->unique()->values())
            ->orderBy('name')
            ->get()
            ->map(function (AssetType $type) use ($assets, $standardsByType, $excluded) {
                $rows = $assets->get($type->id, collect());
                $standard = $standardsByType->get($type->id);
                $notCounted = $rows->filter(fn ($asset) => in_array($asset->status, $excluded, true));
                $present = $rows->count() - $notCounted->count();
                $conditionCounts = $rows->countBy('condition');
                $conditions = collect(AssetCondition::cases())
                    ->filter(fn (AssetCondition $c) => $conditionCounts->has($c->value))
                    ->mapWithKeys(fn (AssetCondition $c) => [$c->value => $conditionCounts->get($c->value)]);

                return [
                    'asset_type' => ['id' => $type->id, 'name' => $type->name, 'code' => $type->code],
                    'total' => $rows->count(),
                    'assets' => $rows->map(fn ($asset) => [
                        'id' => (int) $asset->id,
                        'code' => $asset->code,
                        'status' => $asset->status,
                        'condition' => $asset->condition,
                    ])->values()->all(),
                    'not_counted' => (object) $notCounted->countBy('status')->all(),
                    'conditions' => (object) $conditions->all(),
                    'standard_id' => $standard?->id,
                    'standard_quantity' => $standard?->standard_quantity ?? 0,
                    'present_quantity' => $present,
                    'difference' => $standard === null ? null : $present - $standard->standard_quantity,
                    'result' => $standard?->checklistResult()->value,
                ];
            })
            ->all();
    }

    /**
     * Bagian hanya-baca "Stok tersimpan di sini" (khusus gudang): barang yang lokasi simpannya ruangan ini; tidak dihitung sebagai Ada.
     *
     * @return list<array{id: int, name: string, quantity: int, unit: string, status: string}>
     */
    public function storedStock(Room $room): array
    {
        if (! $room->is_storage) {
            return [];
        }

        return Consumable::query()
            ->where('room_id', $room->id)
            ->orderBy('name')
            ->get()
            ->map(fn (Consumable $consumable) => [
                'id' => $consumable->id,
                'name' => $consumable->name,
                'quantity' => $consumable->quantity,
                'unit' => $consumable->unit,
                'status' => $consumable->status()->value,
            ])
            ->all();
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
            'is_complete' => $total > 0 && $shortage === 0,
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
     * dan dashboard. Semua ruangan tampil; ruangan tanpa standar bukan Lengkap dan bukan kurang; gudang tidak dihitung belum lengkap.
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
                    'is_storage' => $room->is_storage,
                    'last_checked_at' => $room->last_checked_at?->toIso8601String(),
                    'last_checked_by' => $room->lastCheckedBy?->name,
                    ...$this->summarize($room->standards),
                ]);

                return [
                    'id' => $building->id,
                    'name' => $building->name,
                    'slug' => $building->slug,
                    'rooms_count' => $rooms->count(),
                    'incomplete_rooms_count' => $rooms->where('is_storage', false)->where('shortage_items', '>', 0)->count(),
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
                    throw ValidationException::withMessages(['items' => 'Hanya barang habis pakai ruangan ini yang bisa diisi.']);
                }

                $standard->update(['actual_quantity' => $row['actual_quantity']]);
            }

            $room->update(['last_checked_at' => now(), 'last_checked_by' => $user->id]);

            return $room;
        });
    }

    /**
     * Isi dari stok: jumlah = `$quantity` (boleh melebihi kekurangan, maks. sisa stok) atau, jika kosong, min(kekurangan, stok).
     * Pemakaian tercatat di kartu stok (dengan keterangan) & Ada bertambah dalam satu transaksi; sisa kekurangan tetap tercatat.
     *
     * @return array{standard: RoomStandard, filled: int, remaining_shortage: int, stock_remaining: int}
     */
    public function fillFromStock(RoomStandard $standard, User $user, ?int $quantity = null, ?string $note = null): array
    {
        return DB::transaction(function () use ($standard, $user, $quantity, $note) {
            $standard = RoomStandard::query()->whereKey($standard->getKey())->lockForUpdate()->firstOrFail();

            if ($standard->count_method !== CountMethod::Manual) {
                throw ValidationException::withMessages(['standard' => 'Baris ini bukan barang stok.']);
            }

            $shortage = $standard->shortage();

            if ($shortage === null) {
                throw ValidationException::withMessages(['standard' => 'Simpan hasil cek ruangan dulu.']);
            }

            if ($shortage === 0) {
                throw ValidationException::withMessages(['standard' => 'Jumlah sudah sesuai standar.']);
            }

            $consumable = $standard->consumable()->lockForUpdate()->firstOrFail();

            if ($consumable->quantity <= 0) {
                throw ValidationException::withMessages(['standard' => "Stok {$consumable->name} habis."]);
            }

            // Melebihi sisa stok ditolak oleh record(): "Stok tidak cukup (tersisa N)." di field quantity.
            $filled = $quantity ?? min($shortage, $consumable->quantity);
            $note = trim((string) $note);

            $transaction = $this->consumables->record($consumable, StockTransactionType::Pemakaian, -$filled, [
                'note' => $note === '' ? 'Isi dari stok' : "Isi dari stok · {$note}",
                'room_id' => $standard->room_id,
            ], $user);
            $standard->update(['actual_quantity' => $standard->actual_quantity + $filled]);

            return [
                'standard' => $standard,
                'filled' => $filled,
                'remaining_shortage' => max(0, $shortage - $filled),
                'stock_remaining' => $transaction->balance_after,
            ];
        });
    }
}
