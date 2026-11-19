<?php

namespace Tests\Feature;

use App\Enums\AssetStatus;
use App\Models\Asset;
use App\Models\Consumable;
use App\Models\Department;
use App\Models\Loan;
use App\Models\Room;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use OpenSpout\Reader\XLSX\Reader;
use Tests\TestCase;

/**
 * T45 (temuan UAT D1): aplikasi memakai WIB. Pukul 00.00–06.59 WIB tanggal "hari ini" harus tanggal WIB,
 * bukan tanggal UTC (kemarin), dan jam di laporan Excel harus jam WIB.
 */
class TimezoneTest extends TestCase
{
    use RefreshDatabase;

    private Department $shd;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAs(User::factory()->create(['name' => 'Staf SHD']));
        $this->shd = Department::factory()->internal()->create(['name' => 'SHD']);
        // 19 Nov 2026 06.30 WIB = 18 Nov 2026 23.30 UTC: saat inilah tanggal UTC dan WIB berbeda.
        $this->travelTo(CarbonImmutable::parse('2026-11-19 06:30', 'Asia/Jakarta'));
    }

    public function test_application_timezone_is_wib(): void
    {
        $this->assertSame('Asia/Jakarta', config('app.timezone'));
        $this->assertSame('2026-11-19', today()->toDateString());
        $this->assertSame('06:30', now()->format('H:i'));
    }

    public function test_overdue_and_due_today_use_the_wib_date(): void
    {
        $asset = fn () => Asset::factory()->create(['status' => AssetStatus::Dipinjam, 'source_department_id' => $this->shd->id]);
        $late = Loan::factory()->create(['asset_id' => $asset()->id, 'borrow_date' => '2026-11-15', 'due_date' => '2026-11-18']);
        $dueToday = Loan::factory()->create(['asset_id' => $asset()->id, 'borrow_date' => '2026-11-15', 'due_date' => '2026-11-19']);

        $this->getJson("/api/loans/{$late->id}")->assertOk()->assertJsonPath('data.is_overdue', true);
        $this->getJson("/api/loans/{$dueToday->id}")->assertOk()
            ->assertJsonPath('data.is_overdue', false)
            ->assertJsonPath('data.is_due_today', true);
        $this->getJson('/api/dashboard/cards')->assertOk()
            ->assertJsonPath('data.active_loans.overdue', 1)
            ->assertJsonPath('data.active_loans.due_today', 1);
    }

    public function test_today_in_wib_is_not_rejected_as_a_future_date(): void
    {
        $asset = Asset::factory()->create(['status' => AssetStatus::Dipinjam, 'source_department_id' => $this->shd->id]);
        $loan = Loan::factory()->create(['asset_id' => $asset->id, 'borrow_date' => '2026-11-17', 'due_date' => '2026-11-20']);

        $this->patchJson("/api/loans/{$loan->id}/return", [
            'result' => 'kembali', 'return_date' => '2026-11-19', 'return_condition' => 'Baik',
        ])->assertOk();

        $this->postJson("/api/assets/{$asset->id}/services", ['service_date' => '2026-11-19', 'vendor' => 'CV Servis', 'condition' => 'Baik'])
            ->assertOk();
        $this->postJson("/api/assets/{$asset->id}/services", ['service_date' => '2026-11-20', 'vendor' => 'CV Servis', 'condition' => 'Baik'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('service_date');
    }

    public function test_stock_transaction_is_dated_with_the_wib_date(): void
    {
        $storage = Room::factory()->create(['is_storage' => true]);
        $item = Consumable::factory()->create(['quantity' => 0, 'room_id' => $storage->id, 'provider_department_id' => $this->shd->id]);

        $this->postJson("/api/consumables/{$item->id}/transactions", ['type' => 'Barang Masuk', 'quantity' => 5])->assertCreated();

        $this->assertSame('2026-11-19', substr((string) $item->transactions()->latest('id')->value('transaction_date'), 0, 10));
    }

    public function test_excel_print_time_is_wib(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'xlsx');
        file_put_contents($path, $this->get('/api/reports/assets')->assertOk()->streamedContent());

        $reader = new Reader;
        $reader->open($path);
        $rows = [];
        foreach ($reader->getSheetIterator() as $sheet) {
            foreach ($sheet->getRowIterator() as $row) {
                $rows[] = $row->toArray();
            }
            break;
        }
        $reader->close();
        @unlink($path);

        $this->assertContains('Dicetak 19/11/2026 06:30 oleh Staf SHD', array_merge(...$rows));
    }
}
