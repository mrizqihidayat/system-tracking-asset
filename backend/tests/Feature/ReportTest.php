<?php

namespace Tests\Feature;

use App\Enums\AssetStatus;
use App\Enums\CountMethod;
use App\Enums\LoanStatus;
use App\Enums\RequestStatus;
use App\Models\Asset;
use App\Models\AssetType;
use App\Models\Building;
use App\Models\Consumable;
use App\Models\ConsumableCategory;
use App\Models\Department;
use App\Models\Loan;
use App\Models\RequestCatalog;
use App\Models\Room;
use App\Models\RoomStandard;
use App\Models\SupplyRequest;
use App\Models\User;
use DateTimeInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use OpenSpout\Reader\XLSX\Reader;
use Tests\TestCase;

class ReportTest extends TestCase
{
    use RefreshDatabase;

    private const XLSX_MIME = 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet';

    /** Judul, filter, dicetak, header (baris kosong di file dilewati pembaca); data mulai setelahnya. */
    private const HEADER_INDEX = 3;

    private Department $shd;

    private Department $deptA;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAs(User::factory()->create(['name' => 'Staf SHD']));
        $this->shd = Department::factory()->internal()->create(['name' => 'SHD']);
        $this->deptA = Department::factory()->create(['name' => 'Dept A']);
    }

    /**
     * Baca xlsx dari respons: [nama sheet => list baris (array nilai)].
     *
     * @return array<string, list<list<mixed>>>
     */
    private function sheets(TestResponse $response): array
    {
        $path = tempnam(sys_get_temp_dir(), 'xlsx');
        file_put_contents($path, $response->streamedContent());

        $reader = new Reader;
        $reader->open($path);
        $sheets = [];

        foreach ($reader->getSheetIterator() as $sheet) {
            $rows = [];
            foreach ($sheet->getRowIterator() as $row) {
                $rows[] = $row->toArray();
            }
            $sheets[$sheet->getName()] = $rows;
        }

        $reader->close();
        @unlink($path);

        return $sheets;
    }

    /**
     * @param  list<list<mixed>>  $rows
     * @return list<list<mixed>>
     */
    private function dataRows(array $rows): array
    {
        return array_slice($rows, self::HEADER_INDEX + 1);
    }

    private function report(string $type, string $query = ''): TestResponse
    {
        return $this->get("/api/reports/{$type}{$query}");
    }

    private function asset(array $overrides = []): Asset
    {
        return Asset::factory()->create(['source_department_id' => $this->shd->id, ...$overrides]);
    }

    public function test_requires_token(): void
    {
        $this->app['auth']->forgetGuards();

        $this->getJson('/api/reports/assets')->assertUnauthorized();
    }

    public function test_unknown_type_returns_404_and_only_xlsx_format_is_accepted(): void
    {
        $this->getJson('/api/reports/semua')->assertNotFound();
        $this->getJson('/api/reports/assets?format=pdf')->assertUnprocessable()->assertJsonValidationErrors('format');
        $this->get('/api/reports/assets?format=xlsx')->assertOk();
        $this->get('/api/reports/assets')->assertOk();
    }

    public function test_response_is_xlsx_download_with_dated_filename(): void
    {
        $response = $this->report('assets')->assertOk();

        $this->assertSame(self::XLSX_MIME, $response->headers->get('Content-Type'));
        $this->assertStringContainsString('attachment', $response->headers->get('Content-Disposition'));
        $this->assertStringContainsString('laporan-aset-'.now()->format('Ymd').'.xlsx', $response->headers->get('Content-Disposition'));
    }

    public function test_assets_report_has_header_block_columns_and_rows(): void
    {
        $type = AssetType::factory()->create(['name' => 'Proyektor']);
        $building = Building::factory()->create(['name' => 'IHS']);
        $room = Room::factory()->create(['building_id' => $building->id, 'name' => 'Ruang 1']);
        $this->asset([
            'code' => 'SHD-PRJ-001', 'name' => 'Proyektor Epson', 'asset_type_id' => $type->id, 'room_id' => $room->id,
            'brand' => 'Epson', 'next_service_date' => '2026-11-30', 'acquisition_date' => '2026-01-15',
        ]);

        $rows = $this->sheets($this->report('assets'))['Aset'];

        $this->assertSame('Laporan Aset — Aset', $rows[0][0]);
        $this->assertSame('Tanpa filter', $rows[1][0]);
        $this->assertStringContainsString('Staf SHD', $rows[2][0]);
        $this->assertSame('Kode', $rows[self::HEADER_INDEX][0]);
        $this->assertCount(14, $rows[self::HEADER_INDEX]);

        $data = $this->dataRows($rows);
        $this->assertCount(1, $data);
        $this->assertSame('SHD-PRJ-001', $data[0][0]);
        $this->assertSame('Proyektor', $data[0][2]);
        $this->assertSame('SHD', $data[0][5]);
        $this->assertSame('IHS', $data[0][6]);
        $this->assertSame('Ruang 1', $data[0][7]);
        $this->assertSame('Baik', $data[0][9]);
        $this->assertSame('Tersedia', $data[0][10]);
        $this->assertInstanceOf(DateTimeInterface::class, $data[0][11]);
        $this->assertSame('2026-11-30', $data[0][12]->format('Y-m-d'));
    }

    public function test_assets_report_filters_match_the_list_and_include_not_feasible(): void
    {
        $this->asset(['code' => 'SHD-AAA-001', 'name' => 'Laptop A']);
        $this->asset(['code' => 'SHD-AAA-002', 'name' => 'Laptop B', 'status' => AssetStatus::TidakLayakPakai]);
        $this->asset(['code' => 'SHD-BBB-001', 'name' => 'Meja', 'source_department_id' => $this->deptA->id]);

        $codes = fn (string $query) => array_column($this->dataRows($this->sheets($this->report('assets', $query))['Aset']), 0);

        $this->assertSame(['SHD-AAA-001', 'SHD-AAA-002', 'SHD-BBB-001'], $codes(''));
        $this->assertSame(['SHD-AAA-001', 'SHD-AAA-002'], $codes('?search=laptop'));
        $this->assertSame(['SHD-AAA-002'], $codes('?status=Tidak Layak Pakai'));
        $this->assertSame(['SHD-BBB-001'], $codes("?source_department_id={$this->deptA->id}"));

        foreach (['?search=laptop', '?status=Tersedia', "?source_department_id={$this->deptA->id}"] as $query) {
            $list = collect($this->getJson('/api/assets'.$query.'&per_page=100')->json('data'))->pluck('code')->all();
            $this->assertSame($list, $codes($query), "Laporan berbeda dari daftar untuk {$query}");
        }

        $summary = $this->sheets($this->report('assets', '?status=Tersedia&search=lap'))['Aset'][1][0];
        $this->assertSame('Status: Tersedia · Pencarian: lap', $summary);
    }

    public function test_text_cells_starting_with_equals_are_not_formulas(): void
    {
        $this->asset(['code' => 'SHD-AAA-001', 'name' => '=HYPERLINK("http://evil","x")', 'brand' => '+1+1']);

        $row = $this->dataRows($this->sheets($this->report('assets'))['Aset'])[0];

        $this->assertSame('=HYPERLINK("http://evil","x")', $row[1]);
        $this->assertSame('+1+1', $row[3]);
    }

    public function test_loans_report_period_status_and_columns(): void
    {
        $inPeriod = Loan::factory()->create([
            'asset_id' => $this->asset(['code' => 'SHD-LPT-001', 'name' => 'Laptop', 'status' => AssetStatus::Dipinjam])->id,
            'borrower_name' => 'Budi', 'borrower_unit' => 'SGA', 'borrow_date' => '2026-10-05', 'due_date' => today()->subDay(),
            'destination_note' => 'Ruang rapat',
        ]);
        Loan::factory()->create([
            'asset_id' => $this->asset(['code' => 'SHD-LPT-002'])->id,
            'borrow_date' => '2026-08-01', 'due_date' => '2026-08-05', 'status' => LoanStatus::Dikembalikan,
        ]);

        $sheet = fn (string $query) => $this->sheets($this->report('loans', $query))['Peminjaman'];

        $all = $this->dataRows($sheet(''));
        $this->assertCount(2, $all);
        $this->assertSame('Semua periode', $sheet('')[1][0]);

        $period = $sheet('?from=2026-10-01&to=2026-10-31');
        $this->assertSame('Periode tanggal pinjam: 01/10/2026 – 31/10/2026', $period[1][0]);
        $data = $this->dataRows($period);
        $this->assertCount(1, $data);
        $this->assertSame('SHD-LPT-001', $data[0][0]);
        $this->assertSame('Budi', $data[0][2]);
        $this->assertSame('SGA', $data[0][3]);
        $this->assertSame('Ruang rapat', $data[0][5]);
        $this->assertSame('2026-10-05', $data[0][6]->format('Y-m-d'));
        $this->assertSame('Dipinjam', $data[0][9]);
        $this->assertSame('Ya', $data[0][10]);

        $this->assertCount(1, $this->dataRows($sheet('?overdue=1')));
        $this->assertSame($inPeriod->id, Loan::query()->overdue()->value('id'));
        $this->getJson('/api/reports/loans?from=2026-10-31&to=2026-10-01')->assertUnprocessable();
    }

    public function test_rooms_report_has_summary_and_detail_sheets(): void
    {
        $building = Building::factory()->create(['name' => 'IHS']);
        $short = Room::factory()->create(['building_id' => $building->id, 'name' => 'Kelas 1']);
        $ok = Room::factory()->create(['building_id' => $building->id, 'name' => 'Kelas 2']);
        Room::factory()->create(['building_id' => $building->id, 'name' => 'Gudang']);
        $other = Room::factory()->create(['name' => 'Ruang Lain']);

        $spidol = Consumable::factory()->create(['name' => 'Spidol', 'quantity' => 7, 'unit' => 'pcs']);
        $tisu = Consumable::factory()->create(['name' => 'Tisu']);
        RoomStandard::factory()->create(['room_id' => $short->id, 'consumable_id' => $spidol->id, 'standard_quantity' => 5, 'actual_quantity' => 2]);
        RoomStandard::factory()->create(['room_id' => $short->id, 'consumable_id' => $tisu->id, 'standard_quantity' => 3, 'actual_quantity' => null]);
        $type = AssetType::factory()->create(['name' => 'Kursi']);
        RoomStandard::factory()->create(['room_id' => $ok->id, 'standard_quantity' => 4, 'count_method' => CountMethod::Qr, 'asset_type_id' => $type->id]);
        foreach (range(1, 4) as $i) {
            $this->asset(['room_id' => $ok->id, 'asset_type_id' => $type->id, 'code' => "SHD-KRS-00{$i}"]);
        }
        RoomStandard::factory()->create(['room_id' => $other->id, 'standard_quantity' => 1, 'actual_quantity' => 1]);

        $sheets = $this->sheets($this->report('rooms', "?building_id={$building->id}"));

        $this->assertSame(['Ringkasan', 'Detail'], array_keys($sheets));
        $this->assertSame('Gedung: IHS', $sheets['Ringkasan'][1][0]);

        $summary = collect($this->dataRows($sheets['Ringkasan']))->keyBy(1);
        $this->assertCount(3, $summary);
        $this->assertSame('Kurang 1 item', $summary['Kelas 1'][5]);
        $this->assertSame(2, $summary['Kelas 1'][2]);
        $this->assertSame(1, $summary['Kelas 1'][3]);
        $this->assertSame(1, $summary['Kelas 1'][4]);
        $this->assertSame('Lengkap', $summary['Kelas 2'][5]);
        $this->assertSame('Belum ada standar', $summary['Gudang'][5]);

        $detail = collect($this->dataRows($sheets['Detail']))->keyBy(3);
        $this->assertCount(3, $detail);
        $this->assertSame(['IHS', 'Kelas 1', 'Barang habis pakai', 'Spidol', '', 5, 2, -3, 'Kurang', 'Stok: 7 pcs'], $detail['Spidol']);
        $this->assertSame('Belum dicek', $detail['Tisu'][8]);
        $this->assertSame('', $detail['Tisu'][6]);
        $this->assertSame(['Aset', 4, 4, 4, 0, 'Sesuai', 'SHD-KRS-001, SHD-KRS-002, SHD-KRS-003, SHD-KRS-004'], [$detail['Kursi'][2], $detail['Kursi'][4], $detail['Kursi'][5], $detail['Kursi'][6], $detail['Kursi'][7], $detail['Kursi'][8], $detail['Kursi'][9]]);

        $everything = $this->sheets($this->report('rooms'));
        $this->assertSame('Tanpa filter', $everything['Ringkasan'][1][0]);
        $this->assertCount(4, $this->dataRows($everything['Ringkasan']));
        $this->assertCount(4, $this->dataRows($everything['Detail']));
    }

    public function test_requests_report_shows_unresponded_status_and_filters_by_period(): void
    {
        $creator = User::factory()->create(['name' => 'Pencatat']);
        $catalog = RequestCatalog::factory()->create([
            'provider_department_id' => $this->deptA->id, 'lead_time_days' => 2,
        ]);

        SupplyRequest::factory()->create([
            'item_name' => 'Tisu', 'quantity' => 5, 'catalog_id' => $catalog->id, 'provider_department_id' => $this->deptA->id,
            'request_date' => today()->subDays(5), 'created_by' => $creator->id, 'request_method' => 'Memo',
        ]);
        SupplyRequest::factory()->create([
            'item_name' => 'Kabel', 'provider_department_id' => $this->deptA->id, 'status' => RequestStatus::Diterima,
            'request_date' => '2026-01-10', 'received_at' => '2026-01-11 09:30:00', 'created_by' => $creator->id,
        ]);

        $sheet = fn (string $query) => $this->sheets($this->report('requests', $query))['Permintaan'];

        $data = $this->dataRows($sheet(''));
        $this->assertSame(['Tisu', 'Kabel'], array_column($data, 1));
        $this->assertSame('Belum direspons', $data[0][7]);
        $this->assertSame('Memo', $data[0][6]);
        $this->assertSame('Dept A', $data[0][5]);
        $this->assertSame('Pencatat', $data[0][11]);
        $this->assertSame(5, $data[0][2]);
        $this->assertSame('Diterima', $data[1][7]);
        $this->assertSame('2026-01-11 09:30', $data[1][9]->format('Y-m-d H:i'));

        $this->assertSame(['Tisu'], array_column($this->dataRows($sheet('?unresponded=1')), 1));
        $this->assertSame(['Kabel'], array_column($this->dataRows($sheet('?from=2026-01-01&to=2026-01-31')), 1));
        $this->assertSame(['Kabel'], array_column($this->dataRows($sheet('?status=Diterima')), 1));
        $this->assertSame('Semua periode · Belum direspons: Ya', $sheet('?unresponded=1')[1][0]);
    }

    public function test_consumables_report_status_and_filters(): void
    {
        $atk = ConsumableCategory::factory()->create(['name' => 'ATK'])->id;
        $arsip = ConsumableCategory::factory()->create(['name' => 'Arsip'])->id;

        Consumable::factory()->create(['name' => 'Spidol', 'consumable_category_id' => $atk, 'unit' => 'pcs', 'quantity' => 0, 'min_quantity' => 10, 'provider_department_id' => $this->deptA->id, 'storage_location' => 'Gudang IHS']);
        Consumable::factory()->create(['name' => 'Kertas', 'consumable_category_id' => $atk, 'unit' => 'rim', 'quantity' => 4, 'min_quantity' => 10, 'provider_department_id' => $this->deptA->id]);
        Consumable::factory()->create(['name' => 'Map', 'consumable_category_id' => $arsip, 'unit' => 'pcs', 'quantity' => 50, 'min_quantity' => 10, 'provider_department_id' => $this->shd->id]);

        $sheet = fn (string $query) => $this->sheets($this->report('consumables', $query))['Stok'];

        $data = $this->dataRows($sheet(''));
        $this->assertSame(['Kertas', 'Map', 'Spidol'], array_column($data, 0));
        $this->assertSame(['Menipis', 'Aman', 'Habis'], array_column($data, 5));
        $this->assertSame(['ATK', 'rim', 4, 10], [$data[0][1], $data[0][2], $data[0][3], $data[0][4]]);
        $this->assertSame('Gudang IHS', $data[2][7]);

        $this->assertSame(['Spidol'], array_column($this->dataRows($sheet('?status=Habis')), 0));
        $this->assertSame(['Kertas', 'Spidol'], array_column($this->dataRows($sheet('?category=atk')), 0));
        $this->assertSame(['Map'], array_column($this->dataRows($sheet("?provider_department_id={$this->shd->id}")), 0));
        $this->assertSame('Status: Habis', $sheet('?status=Habis')[1][0]);
    }

    public function test_empty_reports_still_return_a_valid_file(): void
    {
        foreach (['assets', 'loans', 'requests', 'consumables'] as $type) {
            $sheets = $this->sheets($this->report($type)->assertOk());

            $this->assertCount(1, $sheets);
            $this->assertSame([], $this->dataRows(array_values($sheets)[0]), $type);
        }
    }

    public function test_invalid_filters_return_422(): void
    {
        $this->getJson('/api/reports/assets?status=Salah')->assertUnprocessable()->assertJsonValidationErrors('status');
        $this->getJson('/api/reports/loans?from=2026-10-31&to=2026-10-01')->assertUnprocessable()->assertJsonValidationErrors('to');
        $this->getJson('/api/reports/requests?status=Salah')->assertUnprocessable();
        $this->getJson('/api/reports/consumables?status=Salah')->assertUnprocessable();
        $this->getJson('/api/reports/rooms?building_id=abc')->assertUnprocessable();
    }
}
