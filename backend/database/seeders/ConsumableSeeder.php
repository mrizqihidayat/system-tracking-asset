<?php

namespace Database\Seeders;

use App\Models\Consumable;
use App\Models\Department;
use App\Models\RequestCatalog;
use App\Models\Room;
use Illuminate\Database\Seeder;

/**
 * Stok habis pakai (hanya barang yang disimpan SHD): campuran Aman, Menipis, Habis.
 */
class ConsumableSeeder extends Seeder
{
    public function run(): void
    {
        $departments = Department::pluck('id', 'name');
        $catalog = RequestCatalog::pluck('id', 'item_name');
        $rooms = Room::pluck('id', 'name');

        // [nama, kategori, satuan, jumlah, minimum, minta ke, katalog, lokasi simpan]
        $rows = [
            ['Pulpen', 'ATK', 'pcs', 120, 30, 'SGA', 'ATK Isi Ulang', 'Gudang IHS'],
            ['Pensil 2B', 'ATK', 'pcs', 25, 30, 'SGA', 'ATK Isi Ulang', 'Gudang IHS'],
            ['Spidol Whiteboard', 'ATK', 'pcs', 8, 12, 'SGA', 'ATK Isi Ulang', 'Gudang IHS'],
            ['Kertas HVS A4', 'ATK', 'rim', 15, 5, 'SGA', 'ATK Isi Ulang', 'Gudang MH'],
            ['Kertas Flipchart', 'ATK', 'pak', 0, 2, 'SGA', 'ATK Isi Ulang', 'Gudang MH'],
            ['Notes Peserta', 'ATK', 'pcs', 60, 20, 'SGA', 'ATK Isi Ulang', 'Gudang IHS'],
            ['Solasi Bening', 'ATK', 'roll', 10, 4, 'SGA', 'ATK Isi Ulang', 'Gudang IHS'],
            ['Tinta Printer Hitam', 'Printer', 'botol', 2, 2, 'SIT', null, 'Gudang MH'],
            ['Baterai AA', 'Elektronik', 'pcs', 0, 8, 'SIT', null, 'Gudang IHS'],
            ['Name Tag Peserta', 'Pelatihan', 'pcs', 80, 25, 'SGA', null, null],
        ];

        foreach ($rows as [$name, $category, $unit, $quantity, $minimum, $provider, $catalogItem, $room]) {
            Consumable::create([
                'name' => $name,
                'category' => $category,
                'unit' => $unit,
                'quantity' => $quantity,
                'min_quantity' => $minimum,
                'provider_department_id' => $departments[$provider],
                'catalog_id' => $catalogItem === null ? null : $catalog[$catalogItem],
                'room_id' => $room === null ? null : $rooms[$room],
            ]);
        }
    }
}
