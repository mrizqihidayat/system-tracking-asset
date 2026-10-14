<?php

namespace Database\Seeders;

use App\Enums\AssetCondition;
use App\Enums\AssetStatus;
use App\Enums\DamageConfirmation;
use App\Enums\DeletionReason;
use App\Enums\EventType;
use App\Enums\LoanStatus;
use App\Models\Asset;
use App\Models\AssetType;
use App\Models\Department;
use App\Models\Loan;
use App\Models\Room;
use App\Models\User;
use App\Services\CodeGenerator;
use Carbon\CarbonInterface;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class AssetSeeder extends Seeder
{
    private User $staff;

    public function __construct(private CodeGenerator $codes) {}

    public function run(): void
    {
        $this->staff = User::where('username', 'staf1')->firstOrFail();

        $assets = $this->registerAssets();

        $this->seedLoans($assets);
        $this->seedServiceAndReports($assets);
    }

    /**
     * @return array<string, Asset> key = alias aset untuk skenario
     */
    private function registerAssets(): array
    {
        $shd = Department::where('name', 'SHD')->value('id');
        $sit = Department::where('name', 'SIT')->value('id');
        $sga = Department::where('name', 'SGA')->value('id');

        // alias => [jenis, nama, merek, ruangan, sumber, perolehan (bulan lalu), servis berikutnya (hari dari sekarang)]
        $rows = [
            'prj1' => ['Proyektor', 'Proyektor Epson EB-X51', 'Epson', 'Ruang Kelas 1', $shd, 30, 120],
            'prj2' => ['Proyektor', 'Proyektor Epson EB-X51', 'Epson', 'Ruang Kelas 2', $shd, 30, 120],
            'prj3' => ['Proyektor', 'Proyektor BenQ MS560', 'BenQ', 'Ruang Rapat', $shd, 18, -5],
            'prj4' => ['Proyektor', 'Proyektor Epson EB-E01', 'Epson', 'Auditorium', $sit, 12, 10],
            'prj5' => ['Proyektor', 'Proyektor BenQ MS560', 'BenQ', 'Gudang IHS', $shd, 24, 90],
            'lpt1' => ['Laptop', 'Laptop Lenovo ThinkPad E14', 'Lenovo', 'Gudang IHS', $shd, 20, null],
            'lpt2' => ['Laptop', 'Laptop Lenovo ThinkPad E14', 'Lenovo', 'Gudang IHS', $shd, 20, null],
            'lpt3' => ['Laptop', 'Laptop ASUS Vivobook 14', 'ASUS', 'Gudang MH', $sit, 10, null],
            'lpt4' => ['Laptop', 'Laptop ASUS Vivobook 14', 'ASUS', 'Gudang MH', $sit, 10, null],
            'spk1' => ['Speaker Aktif', 'Speaker Aktif Huper 15HA400', 'Huper', 'Auditorium', $shd, 36, null],
            'spk2' => ['Speaker Aktif', 'Speaker Aktif Polytron PAS 8B28', 'Polytron', 'Ruang Diskusi', $shd, 14, null],
            'mic1' => ['Mikrofon Nirkabel', 'Mikrofon Shure BLX24', 'Shure', 'Auditorium', $shd, 36, null],
            'mic2' => ['Mikrofon Nirkabel', 'Mikrofon Shure BLX24', 'Shure', 'Auditorium', $shd, 36, null],
            'mic3' => ['Mikrofon Nirkabel', 'Mikrofon Sennheiser XSW 1', 'Sennheiser', 'Gudang IHS', $shd, 8, null],
            'lyr1' => ['Layar Proyektor', 'Layar Proyektor Tripod 70"', 'Brite', 'Ruang Kelas 1', $shd, 30, null],
            'lyr2' => ['Layar Proyektor', 'Layar Proyektor Tripod 70"', 'Brite', 'Ruang Kelas 2', $shd, 30, null],
            'mja1' => ['Meja Peserta', 'Meja Lipat Peserta', 'Chitose', 'Ruang Kelas 1', $sga, 40, null],
            'mja2' => ['Meja Peserta', 'Meja Lipat Peserta', 'Chitose', 'Ruang Kelas 1', $sga, 40, null],
            'mja3' => ['Meja Peserta', 'Meja Lipat Peserta', 'Chitose', 'Ruang Kelas 2', $sga, 40, null],
            'krs1' => ['Kursi', 'Kursi Kuliah Lipat', 'Chitose', 'Ruang Rapat', $sga, 40, null],
            'krs2' => ['Kursi', 'Kursi Kuliah Lipat', 'Chitose', 'Ruang Rapat', $sga, 40, null],
            'ptl1' => ['Papan Tulis', 'Papan Tulis Whiteboard 120x240', 'Sakana', 'Ruang Kelas 1', $shd, 48, null],
            'ptl2' => ['Papan Tulis', 'Papan Tulis Whiteboard 90x180', 'Sakana', 'Ruang Diskusi', $shd, 24, null],
            'flp1' => ['Flipchart', 'Flipchart Stand Magnetik', 'Sakana', 'Ruang Kelas 2', $shd, 24, null],
            'flp2' => ['Flipchart', 'Flipchart Stand Magnetik', 'Sakana', 'Gudang MH', $shd, 24, null],
            'flp3' => ['Flipchart', 'Flipchart Stand Lama', 'Sakana', 'Gudang IHS', $shd, 72, null],
        ];

        $types = AssetType::pluck('id', 'name');
        $rooms = Room::pluck('id', 'name');
        $assets = [];

        foreach ($rows as $alias => [$type, $name, $brand, $room, $source, $monthsAgo, $serviceInDays]) {
            $assets[$alias] = DB::transaction(function () use ($types, $rooms, $type, $name, $brand, $room, $source, $monthsAgo, $serviceInDays, $alias) {
                $assetType = AssetType::findOrFail($types[$type]);
                $acquired = today()->subMonths($monthsAgo);

                $asset = Asset::create([
                    'code' => $this->codes->nextAssetCode($assetType),
                    'asset_type_id' => $assetType->id,
                    'name' => $name,
                    'brand' => $brand,
                    'serial_number' => strtoupper($alias).'-'.$acquired->format('Ym'),
                    'source_department_id' => $source,
                    'room_id' => $rooms[$room],
                    'pic_name' => 'Staf SHD',
                    'acquisition_date' => $acquired,
                    'next_service_date' => $serviceInDays === null ? null : today()->addDays($serviceInDays),
                    'created_by' => $this->staff->id,
                ]);

                $this->event($asset, EventType::Registrasi, $acquired, ['party' => 'SHD', 'condition' => AssetCondition::Baik, 'to_room_id' => $asset->room_id]);

                return $asset;
            });
        }

        return $assets;
    }

    /**
     * @param  array<string, Asset>  $assets
     */
    private function seedLoans(array $assets): void
    {
        $rooms = Room::pluck('id', 'name');

        // Aktif: terlambat, jatuh tempo hari ini, masih berjalan.
        $this->borrow($assets['lpt1'], 'Andi Pratama', 'SSO', 'Pelatihan operator smelter', today()->subDays(5), today()->subDays(2), $rooms['Ruang Diskusi']);
        $this->borrow($assets['prj5'], 'Siti Rahmawati', 'SSO', 'Sosialisasi K3 bulanan', today()->subDays(1), today(), null, 'Aula Pabrik Reduksi');
        $this->borrow($assets['mic3'], 'Budi Santoso', 'SIT', 'Town hall divisi', today(), today()->addDays(3), $rooms['Auditorium']);

        // Selesai: dikembalikan Baik.
        $loan = $this->borrow($assets['lpt2'], 'Rina Kartika', 'SGA', 'Rapat anggaran', today()->subDays(14), today()->subDays(10), $rooms['Ruang Rapat']);
        $this->giveBack($loan, today()->subDays(10), AssetCondition::Baik);

        $loan = $this->borrow($assets['spk2'], 'Dedi Kurniawan', 'SGA', 'Acara HUT perusahaan', today()->subDays(20), today()->subDays(18), null, 'Lapangan Kantor Pusat');
        $this->giveBack($loan, today()->subDays(17), AssetCondition::Baik);

        // Dibatalkan.
        $loan = $this->borrow($assets['lpt3'], 'Yusuf Hidayat', 'SIT', 'Workshop digitalisasi', today()->subDays(7), today()->subDays(4), $rooms['Ruang Kelas 2']);
        $this->cancel($loan, today()->subDays(7), 'Kegiatan diundur');

        // Kembali rusak → Perbaikan, menunggu konfirmasi peminjam.
        $loan = $this->borrow($assets['lpt4'], 'Fajar Nugroho', 'SSO', 'Inspeksi lapangan', today()->subDays(6), today()->subDays(3), null, 'Area Potline');
        $this->giveBack($loan, today()->subDays(2), AssetCondition::RusakRingan, 'Engsel layar retak');
    }

    /**
     * @param  array<string, Asset>  $assets
     */
    private function seedServiceAndReports(array $assets): void
    {
        // Riwayat servis (kondisi Baik → tetap Tersedia).
        $asset = $assets['prj1'];
        $this->event($asset, EventType::Servis, today()->subMonths(2), ['party' => 'CV Mitra Proyeksi', 'condition' => AssetCondition::Baik, 'note' => 'Bersihkan filter & lensa']);

        // Lapor rusak berat → Perbaikan.
        $asset = $assets['spk1'];
        $asset->update(['condition' => AssetCondition::RusakBerat, 'status' => AssetStatus::Perbaikan]);
        $this->event($asset, EventType::LaporRusak, today()->subDays(4), ['party' => 'SHD', 'condition' => AssetCondition::RusakBerat, 'note' => 'Tidak ada suara, amplifier mati']);

        // Lapor hilang.
        $asset = $assets['mic2'];
        $asset->update(['status' => AssetStatus::Hilang]);
        $this->event($asset, EventType::LaporHilang, today()->subDays(9), ['party' => 'SHD', 'note' => 'Tidak ditemukan setelah acara di Auditorium']);

        // Pindah lokasi asal.
        $asset = $assets['flp2'];
        $from = $asset->room_id;
        $asset->update(['room_id' => Room::where('name', 'Ruang Diskusi')->value('id')]);
        $this->event($asset, EventType::PindahLokasi, today()->subDays(3), ['party' => 'SHD', 'from_room_id' => $from, 'to_room_id' => $asset->room_id]);

        // Hapus (soft delete beralasan); riwayat tetap ada.
        $asset = $assets['flp3'];
        $asset->update([
            'deletion_reason' => DeletionReason::TidakDigunakan,
            'deletion_note' => 'Rangka patah, tidak ekonomis diperbaiki',
            'deleted_by' => $this->staff->id,
        ]);
        $this->event($asset, EventType::Dihapus, today()->subDays(15), ['party' => 'SHD', 'note' => 'Tidak digunakan: rangka patah']);
        $asset->delete();
    }

    private function borrow(Asset $asset, string $borrower, string $unit, string $purpose, CarbonInterface $borrowDate, CarbonInterface $dueDate, ?int $roomId, ?string $destinationNote = null): Loan
    {
        $loan = Loan::create([
            'asset_id' => $asset->id,
            'borrower_name' => $borrower,
            'borrower_unit' => $unit,
            'purpose' => $purpose,
            'destination_room_id' => $roomId,
            'destination_note' => $destinationNote,
            'borrow_date' => $borrowDate,
            'due_date' => $dueDate,
            'condition_out' => $asset->condition,
            'approval_number' => 'ND-'.$borrowDate->format('Ymd').'-'.$asset->id,
            'approval_date' => $borrowDate,
            'borrowed_by' => $this->staff->id,
            'status' => LoanStatus::Dipinjam,
        ]);

        $asset->update(['status' => AssetStatus::Dipinjam]);
        $this->event($asset, EventType::Dipinjam, $borrowDate, ['party' => $borrower, 'condition' => $asset->condition, 'loan_id' => $loan->id, 'to_room_id' => $roomId]);

        return $loan;
    }

    private function giveBack(Loan $loan, CarbonInterface $returnDate, AssetCondition $condition, ?string $note = null): void
    {
        $asset = $loan->asset;
        $damaged = $condition !== AssetCondition::Baik;

        $loan->update([
            'status' => LoanStatus::Dikembalikan,
            'return_date' => $returnDate,
            'return_condition' => $condition,
            'return_room_id' => $asset->room_id,
            'received_by' => $this->staff->id,
            'damage_confirmation' => $damaged ? DamageConfirmation::MenungguKonfirmasi : null,
            'note' => $note,
        ]);

        $asset->update(['condition' => $condition, 'status' => $damaged ? AssetStatus::Perbaikan : AssetStatus::Tersedia]);
        $this->event($asset, EventType::Dikembalikan, $returnDate, ['party' => $loan->borrower_name, 'condition' => $condition, 'loan_id' => $loan->id, 'to_room_id' => $asset->room_id, 'note' => $note]);
    }

    private function cancel(Loan $loan, CarbonInterface $date, string $reason): void
    {
        $loan->update(['status' => LoanStatus::Dibatalkan, 'cancel_reason' => $reason]);
        $loan->asset->update(['status' => AssetStatus::Tersedia]);
        $this->event($loan->asset, EventType::Dibatalkan, $date, ['party' => $loan->borrower_name, 'loan_id' => $loan->id, 'note' => $reason]);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function event(Asset $asset, EventType $type, CarbonInterface $date, array $attributes = []): void
    {
        $asset->events()->create([
            'event_type' => $type,
            'event_date' => $date,
            'recorded_by' => $this->staff->id,
            ...$attributes,
        ]);
    }
}
