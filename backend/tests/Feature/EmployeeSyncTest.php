<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\Employee;
use App\Models\EmployeeSyncRun;
use App\Models\User;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Factory;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * employees:sync (T34): satu GET ke EMPLOYEE_API_URL dengan API key, upsert salinan pegawai & departemen.
 * Memakai Http::fake dengan data fiktif (tests/Fixtures/employees.json), tidak bergantung pada mock yang berjalan.
 */
class EmployeeSyncTest extends TestCase
{
    use RefreshDatabase;

    private const URL = 'https://api-pegawai.test/data';

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'employees.api_url' => self::URL,
            'employees.api_key' => 'kunci-uji-rahasia',
            'employees.api_key_header' => 'X-API-Key',
            'employees.data_key' => null,
            'employees.shd_department' => 'SHD',
        ]);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function fixture(): array
    {
        return json_decode(file_get_contents(base_path('tests/Fixtures/employees.json')), true);
    }

    private function fakeApi(mixed $body, int $status = 200): void
    {
        // Http::fake memakai stub pertama yang cocok; ganti pabrik agar fake terbaru yang berlaku.
        Http::swap(new Factory);
        Http::fake([self::URL => Http::response($body, $status)]);
    }

    private function sync(): EmployeeSyncRun
    {
        $this->artisan('employees:sync');

        return EmployeeSyncRun::query()->latest('id')->firstOrFail();
    }

    public function test_request_goes_to_the_exact_url_with_the_api_key_header(): void
    {
        $this->fakeApi($this->fixture());

        $this->artisan('employees:sync')->assertSuccessful();

        Http::assertSentCount(1);
        Http::assertSent(fn (Request $request) => $request->url() === self::URL
            && $request->method() === 'GET'
            && $request->header('X-API-Key') === ['kunci-uji-rahasia']);
    }

    public function test_api_key_header_name_is_configurable(): void
    {
        config(['employees.api_key_header' => 'Authorization-Key']);
        $this->fakeApi($this->fixture());

        $this->artisan('employees:sync')->assertSuccessful();

        Http::assertSent(fn (Request $request) => $request->header('Authorization-Key') === ['kunci-uji-rahasia'] && ! $request->hasHeader('X-API-Key'));
    }

    public function test_plain_array_and_wrapped_responses_are_both_read(): void
    {
        $this->fakeApi($this->fixture());
        $this->assertSame(9, $this->sync()->total);

        Employee::query()->delete();
        config(['employees.data_key' => 'data']);
        $this->fakeApi(['status' => 'ok', 'data' => $this->fixture()]);
        $this->assertSame(9, $this->sync()->total);
        $this->assertSame(9, Employee::count());
    }

    public function test_wrong_shape_for_configured_data_key_is_a_failure(): void
    {
        config(['employees.data_key' => 'data']);
        $this->fakeApi($this->fixture());

        $run = $this->sync();

        $this->assertSame(EmployeeSyncRun::FAILED, $run->status);
        $this->assertStringContainsString('tidak dikenal', $run->message);
        $this->assertSame(0, Employee::count());
    }

    public function test_sync_stores_only_needed_columns_and_nik_as_string(): void
    {
        $this->fakeApi($this->fixture());

        $run = $this->sync();

        $this->assertSame(EmployeeSyncRun::SUCCESS, $run->status);
        $this->assertSame(['id', 'nik', 'name', 'group_name', 'division', 'department_code', 'section', 'position', 'email', 'is_active', 'synced_at', 'created_at', 'updated_at'], Schema::getColumnListing('employees'));

        $andi = Employee::where('nik', '90100001')->firstOrFail();
        $this->assertSame('90100001', $andi->nik);
        $this->assertIsString($andi->getRawOriginal('nik'));
        $this->assertSame('ANDI PRATAMA SIREGAR', $andi->name);
        $this->assertSame('GHC', $andi->group_name);
        $this->assertSame('HCD', $andi->division);
        $this->assertSame('SHD', $andi->department_code);
        $this->assertSame('Staf', $andi->position);
        $this->assertSame('pegawai001.demo@contoh.invalid', $andi->email);
        $this->assertTrue($andi->is_active);
        $this->assertNotNull($andi->synced_at);

        // Data pribadi lain tidak ikut tersimpan.
        $dump = json_encode(Employee::query()->get()->toArray());
        foreach (['1990-03-14', 'Islam', 'Laki-laki', '2045-01-01', '080000000', 'PKWTT'] as $private) {
            $this->assertStringNotContainsString($private, $dump);
        }
    }

    public function test_dash_values_are_stored_as_null(): void
    {
        $this->fakeApi($this->fixture());
        $this->sync();

        $yudha = Employee::where('nik', '90100025')->firstOrFail();
        $this->assertNull($yudha->department_code);
        $this->assertSame('PME', $yudha->division);
        $this->assertSame('PME', $yudha->departmentLabel());

        $benny = Employee::where('nik', '90100028')->firstOrFail();
        $this->assertNull($benny->division);
        $this->assertNull($benny->department_code);
        $this->assertSame('GPO', $benny->departmentLabel());
        $this->assertNull(Employee::where('nik', '90100001')->value('section'));
    }

    public function test_second_sync_upserts_by_nik_without_duplicates(): void
    {
        $this->fakeApi($this->fixture());
        $first = $this->sync();
        $this->assertSame(9, $first->created);

        $rows = $this->fixture();
        $rows[0]['nama'] = 'ANDI P. SIREGAR';
        $rows[0]['departemen'] = 'SGA';
        $rows[] = ['nik' => 90100099, 'nama' => 'PEGAWAI BARU', 'departemen' => 'HRM', 'jabatan' => 'Staf'];
        $this->fakeApi($rows);

        $second = $this->sync();

        $this->assertSame(10, $second->total);
        $this->assertSame(1, $second->created);
        $this->assertSame(9, $second->updated);
        $this->assertSame(0, $second->deactivated);
        $this->assertSame(10, Employee::count());
        $this->assertSame('ANDI P. SIREGAR', Employee::where('nik', '90100001')->value('name'));
        $this->assertSame('SGA', Employee::where('nik', '90100001')->value('department_code'));
    }

    public function test_employees_missing_from_the_api_become_inactive_not_deleted(): void
    {
        $this->fakeApi($this->fixture());
        $this->sync();

        $this->fakeApi(array_slice($this->fixture(), 1));
        $run = $this->sync();

        $this->assertSame(1, $run->deactivated);
        $this->assertSame(9, Employee::count());
        $this->assertFalse(Employee::where('nik', '90100001')->firstOrFail()->is_active);
        $this->assertTrue(Employee::where('nik', '90100002')->firstOrFail()->is_active);

        // Muncul lagi di API → aktif kembali.
        $this->fakeApi($this->fixture());
        $this->sync();
        $this->assertTrue(Employee::where('nik', '90100001')->firstOrFail()->is_active);
    }

    public function test_api_failure_keeps_old_data_and_is_logged_as_failed(): void
    {
        $this->fakeApi($this->fixture());
        $this->sync();

        foreach ([401, 500] as $status) {
            $this->fakeApi(['message' => 'ditolak'], $status);
            $run = $this->sync();

            $this->assertSame(EmployeeSyncRun::FAILED, $run->status);
            $this->assertStringContainsString("HTTP {$status}", $run->message);
        }

        $this->assertSame(9, Employee::where('is_active', true)->count());
    }

    public function test_unreachable_api_keeps_old_data(): void
    {
        $this->fakeApi($this->fixture());
        $this->sync();

        Http::swap(new Factory);
        Http::fake([self::URL => fn () => throw new ConnectionException('timeout')]);
        $run = $this->sync();

        $this->assertSame(EmployeeSyncRun::FAILED, $run->status);
        $this->assertSame('API pegawai tidak bisa dihubungi.', $run->message);
        $this->assertSame(9, Employee::where('is_active', true)->count());
    }

    public function test_empty_api_list_cancels_the_sync_without_deactivating_anyone(): void
    {
        $this->fakeApi($this->fixture());
        $this->sync();
        $departments = Department::where('is_active', true)->count();

        $this->fakeApi([]);
        $run = $this->sync();

        $this->assertSame(EmployeeSyncRun::CANCELLED, $run->status);
        $this->assertSame(9, Employee::where('is_active', true)->count());
        $this->assertSame($departments, Department::where('is_active', true)->count());
    }

    public function test_rows_without_nik_or_name_are_skipped(): void
    {
        $rows = $this->fixture();
        $rows[] = ['nama' => 'TANPA NIK', 'departemen' => 'HRM'];
        $rows[] = ['nik' => 90100098, 'departemen' => 'HRM'];
        $this->fakeApi($rows);

        $run = $this->sync();

        $this->assertSame(9, $run->total);
        $this->assertSame(2, $run->skipped);
        $this->assertSame(9, Employee::count());
    }

    public function test_missing_url_is_reported_as_failed(): void
    {
        config(['employees.api_url' => null]);

        $run = $this->sync();

        $this->assertSame(EmployeeSyncRun::FAILED, $run->status);
        $this->assertStringContainsString('EMPLOYEE_API_URL', $run->message);
        Http::assertNothingSent();
    }

    public function test_departments_come_from_unique_department_codes_and_skip_dash(): void
    {
        $this->fakeApi($this->fixture());
        $this->sync();

        $this->assertEqualsCanonicalizing(['HRM', 'SGA', 'SHD', 'SIT', 'SSO'], Department::pluck('code')->all());
        $this->assertNotContains('-', Department::pluck('code')->all());
        $shd = Department::where('code', 'SHD')->firstOrFail();
        $this->assertTrue($shd->is_internal);
        $this->assertSame('SHD', $shd->name);
        $this->assertFalse(Department::where('code', 'HRM')->firstOrFail()->is_internal);
    }

    public function test_department_code_is_the_sync_key_and_local_name_is_kept(): void
    {
        $sga = Department::factory()->create(['code' => 'SGA', 'name' => 'Sarana Umum']);
        $this->fakeApi($this->fixture());

        $this->sync();
        $this->sync();

        $this->assertSame(1, Department::where('code', 'SGA')->count());
        $this->assertSame('Sarana Umum', $sga->fresh()->name);
        $this->assertTrue($sga->fresh()->is_active);
    }

    public function test_departments_missing_from_the_api_become_inactive_but_remain(): void
    {
        $old = Department::factory()->create(['code' => 'LAMA', 'name' => 'LAMA']);
        $shd = Department::factory()->internal()->create(['code' => 'SHD', 'name' => 'SHD']);
        // Departemen lama tetap bisa dipakai data lama (FK) walau tidak ada di API.
        $this->fakeApi(array_values(array_filter($this->fixture(), fn (array $row) => $row['departemen'] !== 'SHD')));

        $this->sync();

        $this->assertFalse($old->fresh()->is_active);
        $this->assertModelExists($old);
        // Departemen internal (SHD) tidak pernah dinonaktifkan agar "Milik SHD" tetap bisa dipilih.
        $this->assertTrue($shd->fresh()->is_active);
        // Muncul lagi di API → aktif kembali.
        $this->fakeApi([...$this->fixture(), ['nik' => 90100090, 'nama' => 'KEMBALI', 'departemen' => 'LAMA']]);
        $this->sync();
        $this->assertTrue($old->fresh()->is_active);
    }

    public function test_new_department_name_gets_the_code_suffix_when_name_is_taken(): void
    {
        Department::factory()->create(['code' => 'XYZ', 'name' => 'HRM']);
        $this->fakeApi($this->fixture());

        $this->sync();

        $this->assertSame('HRM (kode HRM)', Department::where('code', 'HRM')->value('name'));
    }

    public function test_a_second_run_is_refused_while_one_is_in_progress(): void
    {
        $this->fakeApi($this->fixture());
        $lock = Cache::lock('employees-sync', 60);
        $this->assertTrue($lock->get());

        $this->artisan('employees:sync')->expectsOutput('Sinkronisasi pegawai sedang berjalan.')->assertFailed();

        $lock->release();
        Http::assertNothingSent();
    }

    public function test_command_reports_failure_exit_code_and_never_prints_the_key(): void
    {
        $this->fakeApi(['message' => 'ditolak'], 401);

        $this->artisan('employees:sync')->assertFailed()->doesntExpectOutputToContain('kunci-uji-rahasia');
    }

    public function test_sync_runs_are_recorded_and_status_endpoint_shows_the_latest(): void
    {
        $this->actingAs(User::factory()->employee('90100001')->create());
        $this->getJson('/api/employees/sync-status')->assertOk()->assertJsonPath('data', null);

        $this->fakeApi($this->fixture());
        $this->sync();
        $this->fakeApi([], 500);
        $this->sync();

        $this->getJson('/api/employees/sync-status')->assertOk()
            ->assertJsonPath('data.status', 'gagal')
            ->assertJsonPath('data.message', 'API pegawai menjawab HTTP 500.');
        $this->assertSame(2, EmployeeSyncRun::count());
    }

    public function test_status_endpoint_is_readable_by_every_staff_account_and_requires_login(): void
    {
        $this->getJson('/api/employees/sync-status')->assertUnauthorized();
        $this->actingAs(User::factory()->create());
        $this->getJson('/api/employees/sync-status')->assertOk();
    }

    public function test_no_manual_sync_endpoint_exists(): void
    {
        $this->actingAs(User::factory()->employee('90100001')->create());

        $this->postJson('/api/employees/sync')->assertNotFound();
    }

    public function test_sync_is_scheduled_daily_at_two_wib(): void
    {
        $event = collect(app(Schedule::class)->events())
            ->first(fn ($event) => str_contains($event->command, 'employees:sync'));

        $this->assertNotNull($event);
        $this->assertSame('0 2 * * *', $event->expression);
        $this->assertSame('Asia/Jakarta', (string) $event->timezone);
        $this->assertTrue($event->withoutOverlapping);
    }
}
