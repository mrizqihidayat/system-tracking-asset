<?php

namespace App\Services;

use App\Models\AssetType;
use App\Models\Building;
use App\Models\Category;
use App\Models\ConsumableCategory;
use App\Models\Department;
use App\Models\Room;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Hapus data master (SPEC §4): ditolak 422 bila masih dipakai (semua FK restrict), selain itu hapus permanen.
 * usage() memakai daftar pemakaian yang sama untuk popup sebelum hapus.
 */
class MasterDataService
{
    private const SHD_LOCKED = 'Departemen SHD tidak dapat dihapus.';

    public function deleteDepartment(Department $department): void
    {
        if ($department->is_internal) {
            throw ValidationException::withMessages(['id' => self::SHD_LOCKED]);
        }

        $this->delete($department, 'Departemen', $this->usageMap($department));
    }

    public function deleteBuilding(Building $building): void
    {
        $this->delete($building, 'Gedung', $this->usageMap($building));
    }

    public function deleteRoom(Room $room): void
    {
        $this->delete($room, 'Ruangan', $this->usageMap($room));
    }

    public function deleteCategory(Category $category): void
    {
        $this->delete($category, 'Kategori', $this->usageMap($category));
    }

    public function deleteConsumableCategory(ConsumableCategory $category): void
    {
        $this->delete($category, 'Kategori stok', $this->usageMap($category));
    }

    public function deleteAssetType(AssetType $assetType): void
    {
        $this->delete($assetType, 'Jenis aset', $this->usageMap($assetType));
    }

    /**
     * Pemakaian data master: rincian per jenis (hanya yang > 0), total, dan apakah boleh dihapus.
     *
     * @return array{items: list<array{label: string, count: int}>, total: int, can_delete: bool, reason: ?string}
     */
    public function usage(Department|Building|Room|Category|ConsumableCategory|AssetType $model): array
    {
        $items = [];

        foreach ($this->usageMap($model) as $label => [$table, $column]) {
            $count = DB::table($table)->where($column, $model->getKey())->count();

            if ($count > 0) {
                $items[] = ['label' => $label, 'count' => $count];
            }
        }

        $total = array_sum(array_column($items, 'count'));
        $locked = $model instanceof Department && $model->is_internal;

        return [
            'items' => $items,
            'total' => $total,
            'can_delete' => ! $locked && $total === 0,
            'reason' => $locked ? self::SHD_LOCKED : null,
        ];
    }

    /**
     * @return array<string, array{0: string, 1: string}> label => [tabel, kolom FK]
     */
    private function usageMap(Model $model): array
    {
        return match (true) {
            $model instanceof Department => [
                'aset' => ['assets', 'source_department_id'],
                'item katalog' => ['request_catalog', 'provider_department_id'],
                'barang habis pakai' => ['consumables', 'provider_department_id'],
                'permintaan' => ['supply_requests', 'provider_department_id'],
                'standar ruangan' => ['room_standards', 'source_department_id'],
            ],
            $model instanceof Building => ['ruangan' => ['rooms', 'building_id']],
            $model instanceof Room => [
                'aset' => ['assets', 'room_id'],
                'peminjaman (tujuan)' => ['loans', 'destination_room_id'],
                'peminjaman (kembali)' => ['loans', 'return_room_id'],
                'riwayat aset (asal)' => ['asset_events', 'from_room_id'],
                'riwayat aset (tujuan)' => ['asset_events', 'to_room_id'],
                'permintaan' => ['supply_requests', 'room_id'],
                'standar ruangan' => ['room_standards', 'room_id'],
            ],
            $model instanceof Category => ['jenis aset' => ['asset_types', 'category_id']],
            $model instanceof ConsumableCategory => ['barang habis pakai' => ['consumables', 'consumable_category_id']],
            $model instanceof AssetType => [
                'aset' => ['assets', 'asset_type_id'],
                'standar ruangan' => ['room_standards', 'asset_type_id'],
                'riwayat kode aset' => ['asset_code_histories', 'asset_type_id'],
            ],
        };
    }

    /**
     * @param  array<string, array{0: string, 1: string}>  $usages  label => [tabel, kolom FK]
     */
    private function delete(Model $model, string $label, array $usages): void
    {
        DB::transaction(function () use ($model, $label, $usages) {
            $model->newQuery()->whereKey($model->getKey())->lockForUpdate()->firstOrFail();

            $used = [];

            foreach ($usages as $name => [$table, $column]) {
                $count = DB::table($table)->where($column, $model->getKey())->count();

                if ($count > 0) {
                    $used[] = "{$count} {$name}";
                }
            }

            if ($used !== []) {
                throw ValidationException::withMessages(['id' => "{$label} masih dipakai: ".implode(', ', $used).'.']);
            }

            $model->delete();
        });
    }
}
