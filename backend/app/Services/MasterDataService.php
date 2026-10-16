<?php

namespace App\Services;

use App\Models\AssetType;
use App\Models\Building;
use App\Models\Category;
use App\Models\Department;
use App\Models\Room;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Hapus data master (SPEC §4): ditolak 422 bila masih dipakai (semua FK restrict), selain itu hapus permanen.
 */
class MasterDataService
{
    public function deleteDepartment(Department $department): void
    {
        if ($department->is_internal) {
            throw ValidationException::withMessages(['id' => 'Departemen SHD tidak dapat dihapus.']);
        }

        $this->delete($department, 'Departemen', [
            'aset' => ['assets', 'source_department_id'],
            'item katalog' => ['request_catalog', 'provider_department_id'],
            'barang habis pakai' => ['consumables', 'provider_department_id'],
            'permintaan' => ['supply_requests', 'provider_department_id'],
            'standar ruangan' => ['room_standards', 'source_department_id'],
        ]);
    }

    public function deleteBuilding(Building $building): void
    {
        $this->delete($building, 'Gedung', ['ruangan' => ['rooms', 'building_id']]);
    }

    public function deleteRoom(Room $room): void
    {
        $this->delete($room, 'Ruangan', [
            'aset' => ['assets', 'room_id'],
            'peminjaman (tujuan)' => ['loans', 'destination_room_id'],
            'peminjaman (kembali)' => ['loans', 'return_room_id'],
            'riwayat aset (asal)' => ['asset_events', 'from_room_id'],
            'riwayat aset (tujuan)' => ['asset_events', 'to_room_id'],
            'barang habis pakai' => ['consumables', 'room_id'],
            'permintaan' => ['supply_requests', 'room_id'],
            'standar ruangan' => ['room_standards', 'room_id'],
        ]);
    }

    public function deleteCategory(Category $category): void
    {
        $this->delete($category, 'Kategori', ['jenis aset' => ['asset_types', 'category_id']]);
    }

    public function deleteAssetType(AssetType $assetType): void
    {
        $this->delete($assetType, 'Jenis aset', [
            'aset' => ['assets', 'asset_type_id'],
            'standar ruangan' => ['room_standards', 'asset_type_id'],
            'riwayat kode aset' => ['asset_code_histories', 'asset_type_id'],
        ]);
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
