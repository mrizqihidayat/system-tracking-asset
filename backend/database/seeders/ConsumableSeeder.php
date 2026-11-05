<?php

namespace Database\Seeders;

use App\Models\Consumable;
use App\Models\ConsumableCategory;
use App\Models\Department;
use Illuminate\Database\Seeder;

/**
 * Stok habis pakai (barang yang disimpan SHD): campuran Aman, Menipis, Habis.
 */
class ConsumableSeeder extends Seeder
{
    public function run(): void
    {
        $departments = Department::pluck('id', 'name');
        $categories = ConsumableCategory::pluck('id', 'name');

        // [nama, kategori, satuan, jumlah, minimum, minta ke, lokasi simpan]
        $rows = [
            ['Pulpen', 'ATK', 'pcs', 120, 30, 'SGA', 'Gudang IHS'],
            ['Pensil 2B', 'ATK', 'pcs', 25, 30, 'SGA', 'Gudang IHS'],
            ['Spidol Whiteboard', 'ATK', 'pcs', 8, 12, 'SGA', 'Gudang IHS'],
            ['Kertas HVS A4', 'ATK', 'rim', 15, 5, 'SGA', 'Gudang MH'],
            ['Kertas Flipchart', 'ATK', 'pak', 0, 2, 'SGA', 'Gudang MH'],
            ['Notes Peserta', 'ATK', 'pcs', 60, 20, 'SGA', 'Gudang IHS'],
            ['Solasi Bening', 'ATK', 'roll', 10, 4, 'SGA', 'Gudang IHS'],
            ['Tinta Printer Hitam', 'Printer', 'botol', 2, 2, 'SIT', 'Gudang MH'],
            ['Baterai AA', 'Elektronik', 'pcs', 0, 8, 'SIT', 'Gudang IHS'],
            ['Name Tag Peserta', 'Pelatihan', 'pcs', 80, 25, 'SGA', 'Main Office SHD'],
        ];

        foreach ($rows as [$name, $category, $unit, $quantity, $minimum, $provider, $location]) {
            Consumable::create([
                'name' => $name,
                'consumable_category_id' => $categories[$category],
                'unit' => $unit,
                'quantity' => $quantity,
                'min_quantity' => $minimum,
                'provider_department_id' => $departments[$provider],
                'storage_location' => $location,
            ]);
        }
    }
}
