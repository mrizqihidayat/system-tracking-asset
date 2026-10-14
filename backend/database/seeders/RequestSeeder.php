<?php

namespace Database\Seeders;

use App\Enums\RequestMethod;
use App\Enums\RequestStatus;
use App\Models\Department;
use App\Models\RequestCatalog;
use App\Models\Room;
use App\Models\SupplyRequest;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Katalog perlengkapan unit lain + riwayat permintaan.
 */
class RequestSeeder extends Seeder
{
    public function run(): void
    {
        $staff = User::where('username', 'staf1')->firstOrFail();
        $departments = Department::pluck('id', 'name');
        $rooms = Room::pluck('id', 'name');

        // item => [unit penyedia, cara, perlu kembali, lead time (hari), catatan]
        $catalog = [
            'Tisu' => ['SGA', RequestMethod::Memo, false, 2, 'Per dus isi 24 pak'],
            'Air Mineral Botol' => ['SGA', RequestMethod::Form, false, 1, 'Ajukan H-1 kegiatan'],
            'ATK Isi Ulang' => ['SGA', RequestMethod::Memo, false, 3, 'Pulpen, spidol, kertas'],
            'Kabel HDMI 10 m' => ['SIT', RequestMethod::Email, true, 1, null],
            'Laser Pointer' => ['SIT', RequestMethod::Email, true, 1, null],
            'Kotak P3K' => ['SSO', RequestMethod::Form, false, 5, 'Isi ulang tiap 6 bulan'],
        ];

        $items = [];

        foreach ($catalog as $name => [$department, $method, $mustReturn, $leadTime, $note]) {
            $items[$name] = RequestCatalog::create([
                'item_name' => $name,
                'provider_department_id' => $departments[$department],
                'request_method' => $method,
                'must_return' => $mustReturn,
                'lead_time_days' => $leadTime,
                'note' => $note,
            ]);
        }

        // [item, jumlah, kegiatan, ruangan, status, diajukan (hari lalu), diterima (hari lalu), dikembalikan (hari lalu)]
        $requests = [
            ['Tisu', 5, 'Pelatihan operator smelter', 'Ruang Kelas 1', RequestStatus::Diajukan, 6, null, null], // melewati lead time → Belum direspons
            ['Air Mineral Botol', 48, 'Town hall divisi', 'Auditorium', RequestStatus::Diajukan, 0, null, null],
            ['ATK Isi Ulang', 2, 'Stok SHD', null, RequestStatus::Diterima, 10, 7, null],
            ['Kabel HDMI 10 m', 1, 'Rapat anggaran', 'Ruang Rapat', RequestStatus::Dikembalikan, 14, 13, 10],
            ['Laser Pointer', 2, 'Sosialisasi K3 bulanan', null, RequestStatus::Diterima, 2, 1, null],
            ['Kotak P3K', 1, 'Kelengkapan ruang kelas', 'Ruang Kelas 2', RequestStatus::Dibatalkan, 20, null, null],
        ];

        foreach ($requests as [$item, $quantity, $purpose, $room, $status, $requestedAgo, $receivedAgo, $returnedAgo]) {
            SupplyRequest::create([
                'request_date' => today()->subDays($requestedAgo),
                'catalog_id' => $items[$item]->id,
                'item_name' => $item,
                'quantity' => $quantity,
                'purpose' => $purpose,
                'room_id' => $room === null ? null : $rooms[$room],
                'provider_department_id' => $items[$item]->provider_department_id,
                'status' => $status,
                'received_at' => $receivedAgo === null ? null : now()->subDays($receivedAgo),
                'returned_at' => $returnedAgo === null ? null : now()->subDays($returnedAgo),
                'note' => $status === RequestStatus::Dibatalkan ? 'Sudah tersedia dari stok lama' : null,
                'created_by' => $staff->id,
            ]);
        }
    }
}
