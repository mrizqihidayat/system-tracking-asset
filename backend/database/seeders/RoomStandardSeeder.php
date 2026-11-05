<?php

namespace Database\Seeders;

use App\Enums\CountMethod;
use App\Models\AssetType;
use App\Models\Consumable;
use App\Models\Department;
use App\Models\RequestCatalog;
use App\Models\Room;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Standar kelengkapan beberapa ruangan.
 *
 * Tipe Aset = QR (jenis aset). Tipe Stok Habis Pakai = Manual (barang stok, sumber = penyedianya, berisi jumlah Ada).
 * Tipe Barang departemen lain = Manual tanpa barang stok (sumber & katalog dari departemen penyedia, berisi jumlah Ada).
 */
class RoomStandardSeeder extends Seeder
{
    public function run(): void
    {
        $staff = User::where('username', 'staf1')->firstOrFail();
        $departments = Department::pluck('id', 'name');
        $types = AssetType::pluck('id', 'name');
        $consumables = Consumable::all()->keyBy('name');
        $catalog = RequestCatalog::all()->keyBy('item_name');

        $qr = fn (string $item, string $type, int $standard): array => [
            'item_name' => $item, 'source_department_id' => $departments['SHD'], 'standard_quantity' => $standard,
            'count_method' => CountMethod::Qr, 'asset_type_id' => $types[$type],
        ];
        $fromStock = fn (string $item, string $consumable, int $standard, int $actual): array => [
            'item_name' => $item, 'source_department_id' => $consumables[$consumable]->provider_department_id, 'standard_quantity' => $standard,
            'count_method' => CountMethod::Manual, 'actual_quantity' => $actual, 'consumable_id' => $consumables[$consumable]->id,
        ];

        $otherUnit = fn (string $item, int $standard, int $actual): array => [
            'item_name' => $item, 'source_department_id' => $catalog[$item]->provider_department_id, 'standard_quantity' => $standard,
            'count_method' => CountMethod::Manual, 'actual_quantity' => $actual, 'catalog_id' => $catalog[$item]->id,
        ];

        $standards = [
            'Ruang Kelas 1' => [
                $qr('Proyektor', 'Proyektor', 1),
                $qr('Layar Proyektor', 'Layar Proyektor', 1),
                $qr('Meja Peserta', 'Meja Peserta', 3),
                $qr('Papan Tulis', 'Papan Tulis', 1),
                $fromStock('ATK Peserta (pulpen)', 'Pulpen', 20, 15),
                $qr('Kursi Peserta', 'Kursi', 2),
                $otherUnit('Tisu', 2, 0),
            ],
            'Ruang Kelas 2' => [
                $qr('Proyektor', 'Proyektor', 1),
                $qr('Flipchart', 'Flipchart', 1),
                $fromStock('Spidol Whiteboard', 'Spidol Whiteboard', 4, 1),
                $otherUnit('Kotak P3K', 1, 1),
            ],
            'Ruang Rapat' => [
                $qr('Proyektor', 'Proyektor', 1),
                $qr('Kursi', 'Kursi', 10),
                $otherUnit('Kabel HDMI 10 m', 1, 1),
                $otherUnit('Air Mineral Botol', 12, 12),
            ],
            'Auditorium' => [
                $qr('Proyektor', 'Proyektor', 1),
                $qr('Speaker Aktif', 'Speaker Aktif', 2),
                $qr('Mikrofon Nirkabel', 'Mikrofon Nirkabel', 4),
                $fromStock('Name Tag Peserta', 'Name Tag Peserta', 50, 50),
            ],
        ];

        foreach ($standards as $roomName => $rows) {
            $room = Room::where('name', $roomName)->firstOrFail();
            $room->standards()->createMany($rows);
            $room->update(['last_checked_at' => now()->subDays(rand(1, 10)), 'last_checked_by' => $staff->id]);
        }
    }
}
