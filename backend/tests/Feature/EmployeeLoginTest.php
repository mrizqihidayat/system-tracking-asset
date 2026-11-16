<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\AuthService;
use App\Services\EmployeeDirectory\Employee;
use App\Services\EmployeeDirectory\EmployeeDirectory;
use App\Services\EmployeeDirectory\EmployeeDirectoryUnavailable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * Login NIK pegawai SHD dari direktori pegawai dummy (database/data/employees.json, data fiktif), T33 R2:
 * password awal / setelah reset = NIK, langsung masuk tanpa wajib ganti; ganti password opsional.
 */
class EmployeeLoginTest extends TestCase
{
    use RefreshDatabase;

    /** Pegawai SHD fiktif (lahir 1990-03-14 di data dummy; tanggal lahir tidak dipakai lagi). */
    private const SHD_NIK = '90100001';

    /** Pegawai HRM (bukan SHD). */
    private const OTHER_NIK = '90100004';

    private const NEW_PASSWORD = 'Baru-Uji-2026';

    private string $logDir;

    protected function setUp(): void
    {
        parent::setUp();

        $this->logDir = sys_get_temp_dir().'/employee-login-test-'.uniqid();
        File::ensureDirectoryExists($this->logDir);
        $channel = (require config_path('logging.php'))['channels']['security'];
        config(['logging.channels.security' => ['path' => "{$this->logDir}/security.log"] + $channel]);
    }

    protected function tearDown(): void
    {
        Log::forgetChannel('security');
        File::deleteDirectory($this->logDir);

        parent::tearDown();
    }

    private function login(string $id, string $password): TestResponse
    {
        return $this->postJson('/api/login', ['username' => $id, 'password' => $password]);
    }

    private function logged(): string
    {
        return implode('', array_map(file_get_contents(...), glob("{$this->logDir}/*.log") ?: []));
    }

    private function directoryDown(): void
    {
        $this->app->instance(EmployeeDirectory::class, new class implements EmployeeDirectory
        {
            public function search(string $query, int $limit = 10): array
            {
                throw new EmployeeDirectoryUnavailable('mati');
            }

            public function findByNik(string $nik): ?Employee
            {
                throw new EmployeeDirectoryUnavailable('mati');
            }
        });
    }

    /** Akun api yang sudah pernah ganti password. */
    private function activeEmployeeAccount(string $nik = self::SHD_NIK): User
    {
        return User::factory()->employee($nik)->create(['password' => self::NEW_PASSWORD]);
    }

    public function test_first_login_with_nik_creates_api_account_and_goes_straight_in(): void
    {
        $response = $this->login(self::SHD_NIK, self::SHD_NIK)->assertOk()
            ->assertJsonPath('user.nik', self::SHD_NIK)
            ->assertJsonPath('user.name', 'ANDI PRATAMA SIREGAR')
            ->assertJsonPath('user.department', 'SHD')
            ->assertJsonPath('user.source', 'api')
            ->assertJsonMissingPath('user.must_change_password');

        $this->assertStringNotContainsString('1990-03-14', $response->getContent());
        $user = User::where('nik', self::SHD_NIK)->firstOrFail();
        $this->assertTrue(Hash::check(self::SHD_NIK, $user->password));

        // Tidak ada pemblokiran: token langsung bisa dipakai untuk semua fitur.
        $token = $response->json('token');
        $this->withToken($token)->getJson('/api/assets')->assertOk();
        $this->withToken($token)->getJson('/api/accounts')->assertOk();

        // Login berikutnya tetap dengan NIK selama belum diganti.
        $this->login(self::SHD_NIK, self::SHD_NIK)->assertOk();
    }

    public function test_wrong_password_birth_date_or_unknown_nik_is_rejected_without_creating_account(): void
    {
        $this->login(self::SHD_NIK, '12345678')->assertUnauthorized()->assertJsonPath('message', AuthService::MSG_WRONG);
        // Tanggal lahir (DDMMYYYY) tidak lagi diterima.
        $this->login(self::SHD_NIK, '14031990')->assertUnauthorized();
        $this->login('99999999', '99999999')->assertUnauthorized()->assertJsonPath('message', AuthService::MSG_WRONG);

        $this->assertDatabaseCount('users', 0);
    }

    public function test_non_shd_employee_is_rejected_with_403(): void
    {
        $this->login(self::OTHER_NIK, self::OTHER_NIK)->assertForbidden()->assertJsonPath('message', 'Hanya pegawai Departemen SHD');

        $this->assertDatabaseCount('users', 0);
    }

    public function test_api_account_whose_employee_left_shd_is_rejected(): void
    {
        $this->activeEmployeeAccount(self::OTHER_NIK);

        $this->login(self::OTHER_NIK, self::NEW_PASSWORD)->assertForbidden()->assertJsonPath('message', AuthService::MSG_NOT_SHD);
        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_optional_password_change_replaces_nik(): void
    {
        $token = $this->login(self::SHD_NIK, self::SHD_NIK)->json('token');

        $this->withToken($token)->putJson('/api/user/password', [
            'current_password' => self::SHD_NIK,
            'password' => self::NEW_PASSWORD,
            'password_confirmation' => self::NEW_PASSWORD,
        ])->assertOk();

        $this->login(self::SHD_NIK, self::NEW_PASSWORD)->assertOk();
        $this->login(self::SHD_NIK, self::SHD_NIK)->assertUnauthorized();
    }

    public function test_new_password_rules(): void
    {
        $user = User::factory()->employee(self::SHD_NIK)->create(['password' => self::SHD_NIK]);
        $this->actingAs($user);

        $change = fn (string $password, ?string $confirmation = null, string $current = self::SHD_NIK) => $this->putJson('/api/user/password', [
            'current_password' => $current,
            'password' => $password,
            'password_confirmation' => $confirmation ?? $password,
        ]);

        $change('Pendek1')->assertUnprocessable()->assertJsonValidationErrors('password');
        $change(self::SHD_NIK)->assertUnprocessable()->assertJsonPath('errors.password.0', 'Password tidak boleh sama dengan NIK.');
        $change(self::NEW_PASSWORD, 'Lain-Uji-2026')->assertUnprocessable()->assertJsonValidationErrors('password');
        $change(self::NEW_PASSWORD, null, '14031990')->assertUnprocessable()->assertJsonPath('errors.current_password.0', 'Password saat ini salah.');

        $change(self::NEW_PASSWORD)->assertOk();
    }

    public function test_password_change_does_not_need_directory(): void
    {
        $this->actingAs(User::factory()->employee(self::SHD_NIK)->create(['password' => self::SHD_NIK]));
        $this->directoryDown();

        $this->putJson('/api/user/password', [
            'current_password' => self::SHD_NIK,
            'password' => self::NEW_PASSWORD,
            'password_confirmation' => self::NEW_PASSWORD,
        ])->assertOk();
    }

    public function test_manual_account_password_cannot_equal_username(): void
    {
        $this->actingAs(User::factory()->create(['username' => 'bersama.hrm', 'password' => 'Lama-Uji-2026']));

        $this->putJson('/api/user/password', [
            'current_password' => 'Lama-Uji-2026',
            'password' => 'BERSAMA.HRM',
            'password_confirmation' => 'BERSAMA.HRM',
        ])->assertUnprocessable()->assertJsonPath('errors.password.0', 'Password tidak boleh sama dengan username.');
    }

    public function test_changing_password_revokes_other_sessions_but_keeps_current(): void
    {
        $this->activeEmployeeAccount();
        $current = $this->login(self::SHD_NIK, self::NEW_PASSWORD)->json('token');
        $other = $this->login(self::SHD_NIK, self::NEW_PASSWORD)->json('token');

        $this->withToken($current)->putJson('/api/user/password', [
            'current_password' => self::NEW_PASSWORD,
            'password' => 'Kedua-Uji-2026',
            'password_confirmation' => 'Kedua-Uji-2026',
        ])->assertOk();

        $this->app['auth']->forgetGuards();
        $this->withToken($other)->getJson('/api/user')->assertUnauthorized();
        $this->app['auth']->forgetGuards();
        $this->withToken($current)->getJson('/api/user')->assertOk();
    }

    public function test_deactivated_api_account_cannot_login_even_if_still_shd(): void
    {
        $this->activeEmployeeAccount()->delete();

        $this->login(self::SHD_NIK, self::NEW_PASSWORD)->assertForbidden()->assertJsonPath('message', AuthService::MSG_INACTIVE);
        // Tidak membuat akun baru untuk NIK yang sama.
        $this->login(self::SHD_NIK, self::SHD_NIK)->assertUnauthorized();
        $this->assertSame(1, User::withTrashed()->count());
    }

    public function test_directory_down_lets_active_api_account_login_and_logs_warning(): void
    {
        $user = $this->activeEmployeeAccount();
        $this->directoryDown();

        $this->login(self::SHD_NIK, self::NEW_PASSWORD)->assertOk();

        $this->assertMatchesRegularExpression('/WARNING: login\.directory_unavailable .*"user_id":'.$user->id.'/', $this->logged());
    }

    public function test_directory_down_rejects_first_login_but_not_existing_or_manual_accounts(): void
    {
        // Akun yang sudah ada (termasuk yang masih memakai NIK, mis. setelah reset) memakai password tersimpan.
        User::factory()->employee('90100002')->create(['password' => '90100002']);
        User::factory()->create(['username' => 'bersama.shd', 'password' => 'Bersama-Uji-1']);
        $this->directoryDown();

        $this->login(self::SHD_NIK, self::SHD_NIK)->assertStatus(503)->assertJsonPath('message', AuthService::MSG_DIRECTORY_DOWN);
        $this->login('90100002', '90100002')->assertOk();
        $this->login('bersama.shd', 'Bersama-Uji-1')->assertOk();
        $this->assertDatabaseMissing('users', ['nik' => self::SHD_NIK]);
    }

    public function test_login_syncs_name_and_department_from_directory(): void
    {
        $user = User::factory()->employee(self::SHD_NIK)->create(['name' => 'Nama Lama', 'department' => 'Lama', 'password' => self::NEW_PASSWORD]);

        $this->login(self::SHD_NIK, self::NEW_PASSWORD)->assertOk();

        $this->assertSame('ANDI PRATAMA SIREGAR', $user->fresh()->name);
        $this->assertSame('SHD', $user->fresh()->department);
    }

    public function test_passwords_and_private_fields_never_reach_logs(): void
    {
        $this->login(self::SHD_NIK, self::SHD_NIK)->assertOk();
        $this->login(self::SHD_NIK, 'Salah-Uji-1')->assertUnauthorized();
        $this->login(self::OTHER_NIK, self::OTHER_NIK)->assertForbidden();

        $log = $this->logged();
        $this->assertStringContainsString('"reason":"not_shd"', $log);
        $this->assertStringNotContainsString('"password"', $log);
        foreach (['Salah-Uji-1', '1990-03-14', 'contoh.invalid', 'Islam'] as $secret) {
            $this->assertStringNotContainsString($secret, $log);
        }
    }
}
