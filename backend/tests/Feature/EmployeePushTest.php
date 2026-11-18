<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\Employee;
use App\Models\EmployeeSyncRun;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * Agen sinkron pegawai (T44): POST /api/internal/employees/push dengan Bearer EMPLOYEE_PUSH_TOKEN.
 * Aturan sinkron sama dengan employees:sync (lihat EmployeeSyncTest); di sini fokus ke batas endpoint.
 */
class EmployeePushTest extends TestCase
{
    use RefreshDatabase;

    private const URL = '/api/internal/employees/push';

    private const TOKEN = 'token-agen-uji-0123456789abcdef0123456789abcdef';

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'employees.push_token' => self::TOKEN,
            'employees.api_url' => null,
            'employees.shd_department' => 'SHD',
        ]);
        Http::preventStrayRequests();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function fixture(): array
    {
        return json_decode((string) file_get_contents(base_path('tests/Fixtures/employees.json')), true);
    }

    /**
     * @param  array<string, mixed>  $body
     */
    private function push(array $body, ?string $token = self::TOKEN): TestResponse
    {
        return $this->postJson(self::URL, $body, $token === null ? [] : ['Authorization' => "Bearer {$token}"]);
    }

    private function latestRun(): EmployeeSyncRun
    {
        return EmployeeSyncRun::query()->latest('id')->firstOrFail();
    }

    public function test_endpoint_is_disabled_without_a_configured_token(): void
    {
        config(['employees.push_token' => null]);

        $this->push(['employees' => $this->fixture()])->assertNotFound()->assertJsonPath('message', 'Data tidak ditemukan.');
        $this->push(['employees' => $this->fixture()], '')->assertNotFound();
        $this->assertSame(0, Employee::count());
    }

    public function test_missing_or_wrong_token_is_rejected_before_validation(): void
    {
        $this->push(['employees' => $this->fixture()], null)->assertUnauthorized()->assertJsonPath('message', 'Tidak diizinkan.');
        $this->push(['employees' => $this->fixture()], 'token-salah')->assertUnauthorized();
        // Body tidak valid dengan token salah tetap 401: isi tidak divalidasi untuk pengirim tak dikenal.
        $this->push([], 'token-salah')->assertUnauthorized();

        $this->assertSame(0, Employee::count());
        $this->assertSame(0, EmployeeSyncRun::count());
    }

    public function test_valid_push_syncs_with_the_same_rules_without_calling_the_employee_api(): void
    {
        $response = $this->push(['employees' => $this->fixture()]);

        $response->assertOk()->assertExactJson([
            'status' => 'berhasil', 'total' => 9, 'created' => 9, 'updated' => 0, 'deactivated' => 0, 'skipped' => 0, 'message' => null,
        ]);
        Http::assertNothingSent();

        $andi = Employee::where('nik', '90100001')->firstOrFail();
        $this->assertSame('ANDI PRATAMA SIREGAR', $andi->name);
        $this->assertSame('SHD', $andi->department_code);
        $this->assertTrue($andi->is_active);
        $this->assertTrue(Department::where('code', 'SHD')->firstOrFail()->is_internal);
        $this->assertSame(EmployeeSyncRun::SOURCE_AGENT, $this->latestRun()->source);

        // Kolom pribadi yang ikut terkirim tetap tidak disimpan.
        $dump = json_encode(Employee::query()->get()->toArray());
        foreach (['1990-03-14', 'Islam', 'Laki-laki', 'PKWTT'] as $private) {
            $this->assertStringNotContainsString($private, $dump);
        }
    }

    public function test_second_push_upserts_and_deactivates_employees_missing_from_the_list(): void
    {
        $this->push(['employees' => $this->fixture()])->assertOk();

        $rows = array_slice($this->fixture(), 1);
        $rows[0]['nama'] = 'NAMA BARU';

        $this->push(['employees' => $rows])->assertOk()
            ->assertJsonPath('status', 'berhasil')
            ->assertJsonPath('created', 0)
            ->assertJsonPath('updated', 8)
            ->assertJsonPath('deactivated', 1);

        $this->assertSame(9, Employee::count());
        $this->assertFalse(Employee::where('nik', '90100001')->firstOrFail()->is_active);
        $this->assertSame('NAMA BARU', Employee::where('nik', (string) $rows[0]['nik'])->value('name'));
    }

    public function test_empty_list_cancels_the_sync_and_keeps_everyone_active(): void
    {
        $this->push(['employees' => $this->fixture()])->assertOk();

        $this->push(['employees' => []])->assertOk()
            ->assertJsonPath('status', 'dibatalkan')
            ->assertJsonPath('deactivated', 0);

        $this->assertSame(9, Employee::where('is_active', true)->count());
    }

    public function test_rows_that_are_not_objects_or_lack_nik_or_name_are_skipped(): void
    {
        $rows = [...$this->fixture(), 'bukan-objek', 5, ['nama' => 'TANPA NIK']];

        $this->push(['employees' => $rows])->assertOk()
            ->assertJsonPath('total', 9)
            ->assertJsonPath('skipped', 1);

        $this->assertSame(9, Employee::count());
    }

    public function test_agent_error_is_recorded_as_failed_and_old_data_is_kept(): void
    {
        $this->push(['employees' => $this->fixture()])->assertOk();

        $this->push(['error' => 'API pegawai tidak bisa dihubungi.'])->assertOk()
            ->assertJsonPath('status', 'gagal')
            ->assertJsonPath('message', 'Agen sinkron: API pegawai tidak bisa dihubungi.');

        $this->assertSame(9, Employee::where('is_active', true)->count());
        $this->assertSame(EmployeeSyncRun::SOURCE_AGENT, $this->latestRun()->source);

        $this->actingAs(User::factory()->employee('90100001')->create());
        $this->getJson('/api/employees/sync-status')->assertOk()
            ->assertJsonPath('data.status', 'gagal')
            ->assertJsonPath('data.source', 'agen')
            ->assertJsonPath('data.message', 'Agen sinkron: API pegawai tidak bisa dihubungi.');
    }

    public function test_body_must_contain_exactly_one_of_employees_or_error(): void
    {
        // Body kosong dengan token benar → 422 tanpa mengubah data (dipakai `--dry-run` agen untuk cek token).
        $this->push([])->assertUnprocessable()->assertJsonValidationErrors('employees');
        $this->push(['employees' => $this->fixture(), 'error' => 'gagal'])->assertUnprocessable()->assertJsonValidationErrors('employees');
        $this->push(['employees' => 'bukan daftar'])->assertUnprocessable()->assertJsonValidationErrors('employees');
        $this->push(['employees' => ['a' => ['nik' => 1, 'nama' => 'X']]])->assertUnprocessable()->assertJsonValidationErrors('employees');
        $this->push(['error' => str_repeat('x', 501)])->assertUnprocessable()->assertJsonValidationErrors('error');

        $this->assertSame(0, EmployeeSyncRun::count());
    }

    public function test_push_is_refused_while_another_sync_is_running(): void
    {
        $lock = Cache::lock('employees-sync', 60);
        $this->assertTrue($lock->get());

        $this->push(['employees' => $this->fixture()])->assertStatus(503)->assertJsonPath('message', 'Sinkronisasi pegawai sedang berjalan.');

        $lock->release();
        $this->assertSame(0, Employee::count());
    }

    public function test_push_is_rate_limited_per_ip(): void
    {
        for ($i = 0; $i < 10; $i++) {
            $this->push([], 'token-salah')->assertUnauthorized();
        }

        $this->push(['employees' => $this->fixture()])->assertTooManyRequests();
    }
}
