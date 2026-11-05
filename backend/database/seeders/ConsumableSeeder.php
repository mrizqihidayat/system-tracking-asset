<?php

namespace Database\Seeders;

use App\Enums\RequestStatus;
use App\Enums\StockTransactionType;
use App\Models\Consumable;
use App\Models\ConsumableCategory;
use App\Models\Department;
use App\Models\Room;
use App\Models\User;
use App\Services\ConsumableService;
use App\Services\SupplyRequestService;
use Illuminate\Database\Seeder;

/**
 * Stok habis pakai: campuran Aman, Menipis, Habis. Semua jumlah lewat ConsumableService agar sama dengan hasil transaksi.
 */
class ConsumableSeeder extends Seeder
{
    public function run(ConsumableService $service, SupplyRequestService $requests): void
    {
        $staff = User::where('username', 'staf1')->firstOrFail();
        $departments = Department::pluck('id', 'name');
        $categories = ConsumableCategory::pluck('id', 'name');
        $rooms = Room::pluck('id', 'name');

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
            // Barang dari departemen lain: dicatat di stok, lalu diambil ke ruangan.
            ['Tisu', 'Kebersihan', 'dus', 1, 2, 'SGA', 'Gudang IHS'],
            ['Air Mineral Botol', 'Konsumsi', 'botol', 24, 12, 'SGA', 'Gudang MH'],
            ['Kotak P3K', 'K3', 'pcs', 2, 1, 'SSO', 'Main Office SHD'],
            ['Kabel HDMI 10 m', 'Elektronik', 'pcs', 3, 1, 'SIT', 'Main Office SHD'],
        ];

        foreach ($rows as [$name, $category, $unit, $quantity, $minimum, $provider, $location]) {
            $service->create([
                'name' => $name,
                'consumable_category_id' => $categories[$category],
                'unit' => $unit,
                'quantity' => $quantity,
                'min_quantity' => $minimum,
                'provider_department_id' => $departments[$provider],
                'room_id' => $rooms[$location],
            ], $staff);
        }

        // Riwayat contoh untuk kartu stok: pemakaian, koreksi, dan penghapusan.
        $pulpen = Consumable::where('name', 'Pulpen')->firstOrFail();
        $service->record($pulpen, StockTransactionType::Pemakaian, -10, ['note' => 'Pelatihan operator smelter'], $staff);
        $service->record($pulpen, StockTransactionType::Koreksi, 2, ['note' => 'Hasil hitung ulang gudang'], $staff);

        // Permintaan dari stok yang baru datang sebagian.
        $pensil = Consumable::where('name', 'Pensil 2B')->firstOrFail();
        $request = $requests->create([
            'consumable_id' => $pensil->id,
            'item_name' => $pensil->name,
            'provider_department_id' => $pensil->provider_department_id,
            'quantity' => 10,
            'request_method' => 'Memo',
        ], $staff);
        $requests->changeStatus($request, RequestStatus::Diterima, null, $staff, 4);
    }
}
