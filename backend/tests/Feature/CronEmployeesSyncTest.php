<?php

namespace Tests\Feature;

use App\Models\Employee;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Vercel Cron (T38): GET /api/internal/cron/employees-sync dan API pegawai tiruan GET /api/mock/employees.
 */
class CronEmployeesSyncTest extends TestCase
{
    use RefreshDatabase;

    private const CRON_URL = '/api/internal/cron/employees-sync';

    private const MOCK_URL = '/api/mock/employees';

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'employees.api_url' => 'https://api-pegawai.test/data',
            'employees.api_key' => 'kunci-uji-rahasia',
            'employees.api_key_header' => 'X-API-Key',
            'employees.data_key' => null,
            'employees.shd_department' => 'SHD',
            'employees.cron_secret' => 'rahasia-cron-uji-123',
            'employees.mock_enabled' => false,
        ]);
    }

    public function test_cron_is_disabled_without_secret(): void
    {
        config(['employees.cron_secret' => null]);

        $this->getJson(self::CRON_URL)->assertNotFound();
        $this->getJson(self::CRON_URL, ['Authorization' => 'Bearer '])->assertNotFound();
    }

    public function test_cron_rejects_missing_or_wrong_secret(): void
    {
        Http::fake();

        $this->getJson(self::CRON_URL)->assertUnauthorized();
        $this->getJson(self::CRON_URL, ['Authorization' => 'Bearer salah'])->assertUnauthorized();

        Http::assertNothingSent();
    }

    public function test_cron_runs_sync_with_valid_secret(): void
    {
        $fixture = json_decode((string) file_get_contents(base_path('tests/Fixtures/employees.json')), true);
        Http::fake(['api-pegawai.test/*' => Http::response($fixture)]);

        $this->getJson(self::CRON_URL, ['Authorization' => 'Bearer rahasia-cron-uji-123'])
            ->assertOk()
            ->assertJsonPath('status', 'berhasil');

        $this->assertGreaterThan(0, Employee::count());
    }

    public function test_cron_reports_failure_when_api_fails(): void
    {
        Http::fake(['api-pegawai.test/*' => Http::response(['message' => 'x'], 500)]);

        $this->getJson(self::CRON_URL, ['Authorization' => 'Bearer rahasia-cron-uji-123'])
            ->assertStatus(502);
    }

    public function test_mock_api_is_off_by_default(): void
    {
        $this->getJson(self::MOCK_URL, ['X-API-Key' => 'kunci-uji-rahasia'])->assertNotFound();
    }

    public function test_mock_api_requires_api_key_when_enabled(): void
    {
        config(['employees.mock_enabled' => true]);

        $this->getJson(self::MOCK_URL)->assertUnauthorized();
        $this->getJson(self::MOCK_URL, ['X-API-Key' => 'salah'])->assertUnauthorized();
    }

    public function test_mock_api_returns_employee_list_and_is_accepted_by_sync(): void
    {
        config(['employees.mock_enabled' => true]);

        $this->getJson(self::MOCK_URL, ['X-API-Key' => 'kunci-uji-rahasia'])
            ->assertOk()
            ->assertJsonStructure([['nik', 'nama', 'departemen']]);

        $rows = $this->getJson(self::MOCK_URL, ['X-API-Key' => 'kunci-uji-rahasia'])->json();
        Http::fake(['api-pegawai.test/*' => Http::response($rows)]);

        $this->getJson(self::CRON_URL, ['Authorization' => 'Bearer rahasia-cron-uji-123'])
            ->assertOk()
            ->assertJsonPath('status', 'berhasil');
    }
}
