<?php

namespace Database\Seeders;

use App\Models\AssetType;
use App\Models\Building;
use App\Models\ConsumableCategory;
use App\Models\Department;
use App\Services\CodeGenerator;
use Illuminate\Database\Seeder;

class MasterDataSeeder extends Seeder
{
    /**
     * Departemen, gedung → ruangan, kategori → jenis aset.
     */
    public function run(CodeGenerator $codes): void
    {
        Department::create(['name' => 'SHD', 'is_internal' => true]);

        foreach (['SGA', 'SIT', 'SSO'] as $name) {
            Department::create(['name' => $name, 'is_internal' => false]);
        }

        $buildings = [
            'Gedung IHS' => ['Ruang Kelas 1', 'Ruang Kelas 2', 'Ruang Rapat', 'Gudang IHS'],
            'Millennials Hub' => ['Ruang Diskusi', 'Auditorium', 'Gudang MH'],
        ];

        foreach ($buildings as $buildingName => $rooms) {
            $building = Building::create(['name' => $buildingName]);

            foreach ($rooms as $roomName) {
                $building->rooms()->create(['name' => $roomName]);
            }
        }

        foreach (['ATK', 'Printer', 'Elektronik', 'Pelatihan', 'Kebersihan', 'Konsumsi', 'K3'] as $name) {
            ConsumableCategory::create(['name' => $name]);
        }

        // Kode null = otomatis dari nama; Proyektor memakai kode yang diedit (contoh SPEC).
        $types = [
            'Proyektor' => 'PRJ', 'Laptop' => null, 'Speaker Aktif' => null, 'Mikrofon Nirkabel' => null, 'Layar Proyektor' => null,
            'Meja Peserta' => null, 'Kursi' => null, 'Papan Tulis' => null, 'Flipchart' => null,
        ];

        foreach ($types as $typeName => $code) {
            AssetType::create([
                'name' => $typeName,
                'code' => $code ?? $codes->assetTypeCode($typeName),
            ]);
        }
    }
}
