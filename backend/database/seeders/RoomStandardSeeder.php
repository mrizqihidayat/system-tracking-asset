<?php

namespace Database\Seeders;

use App\Enums\CountMethod;
use App\Enums\RoomStockTransactionType;
use App\Models\AssetType;
use App\Models\Consumable;
use App\Models\Room;
use App\Models\User;
use App\Services\RoomStockService;
use Illuminate\Database\Seeder;

/**
 * Standar kelengkapan beberapa ruangan: per jenis aset (Ada dihitung dari aset) dan per barang stok (berisi jumlah Ada).
 */
class RoomStandardSeeder extends Seeder
{
    public function run(): void
    {
        $staff = User::where('username', 'staf1')->firstOrFail();
        $types = AssetType::pluck('id', 'name');
        $consumables = Consumable::pluck('id', 'name');

        $asset = fn (string $type, int $standard): array => [
            'standard_quantity' => $standard, 'count_method' => CountMethod::Qr, 'asset_type_id' => $types[$type],
        ];
        // Ada barang stok dicatat lewat RoomStockService sebagai Saldo awal (bukan langsung ke actual_quantity).
        $stock = fn (string $consumable, int $standard, int $actual): array => [
            'standard_quantity' => $standard, 'count_method' => CountMethod::Manual, 'actual_quantity' => 0, 'consumable_id' => $consumables[$consumable],
            'counted' => $actual,
        ];
        $roomStock = app(RoomStockService::class);

        $standards = [
            'Ruang Kelas 1' => [
                $asset('Proyektor', 1),
                $asset('Layar Proyektor', 1),
                $asset('Meja Peserta', 3),
                $asset('Papan Tulis', 1),
                $asset('Kursi', 2),
                $stock('Pulpen', 20, 15),
                $stock('Tisu', 2, 0),
            ],
            'Ruang Kelas 2' => [
                $asset('Proyektor', 1),
                $asset('Mikrofon Nirkabel', 2),
                $stock('Spidol Whiteboard', 4, 1),
                $stock('Tisu', 1, 1),
            ],
            'Ruang Rapat' => [
                $asset('Proyektor', 1),
                $asset('Kursi', 10),
                $stock('Kabel HDMI 10 m', 1, 1),
                $stock('Air Mineral Botol', 12, 12),
            ],
            'Auditorium' => [
                $asset('Proyektor', 1),
                $asset('Speaker Aktif', 2),
                $asset('Mikrofon Nirkabel', 4),
                $stock('Name Tag Peserta', 50, 50),
            ],
        ];

        foreach ($standards as $roomName => $rows) {
            $room = Room::where('name', $roomName)->firstOrFail();
            $checkedAt = now()->subDays(rand(1, 10));

            foreach ($rows as $row) {
                $counted = $row['counted'] ?? null;
                unset($row['counted']);
                $standard = $room->standards()->create($row + ($counted === null ? [] : ['checked_at' => $checkedAt]));

                if ($counted > 0) {
                    $roomStock->record($standard, RoomStockTransactionType::SaldoAwal, $counted, [], $staff);
                }
            }

            $room->update(['last_checked_at' => $checkedAt, 'last_checked_by' => $staff->id]);
        }
    }
}
