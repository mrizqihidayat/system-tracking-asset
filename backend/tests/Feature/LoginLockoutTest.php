<?php

namespace Tests\Feature;

use App\Models\Employee;
use App\Models\User;
use App\Services\SettingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * Penguncian akun setelah salah password berkali-kali dan panjang minimal password dari Pengaturan > Keamanan
 * (T40 tahap 2, SPEC §15).
 */
class LoginLockoutTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private User $employee;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create(['username' => 'staf.shd']);
        $this->employee = User::factory()->employee('90100003')->create();
    }

    private function login(string $username = 'staf.shd', string $password = 'password'): TestResponse
    {
        // Setiap percobaan dari IP berbeda: yang teruji penguncian per akun, bukan batas 20/menit per IP.
        static $ip = 0;
        $ip++;

        return $this->withServerVariables(['REMOTE_ADDR' => '10.1.'.intdiv($ip, 250).'.'.($ip % 250 + 1)])
            ->postJson('/api/login', ['username' => $username, 'password' => $password]);
    }

    /**
     * @param  array<string, int>  $values
     */
    private function setSecurity(array $values): void
    {
        app(SettingService::class)->update($values, $this->employee);
    }

    public function test_account_is_locked_after_max_wrong_passwords_even_from_other_ips(): void
    {
        foreach (range(1, 4) as $attempt) {
            $this->login('staf.shd', 'salah')->assertUnauthorized();
        }

        $locked = $this->login('staf.shd', 'salah')->assertStatus(429)
            ->assertJsonPath('code', 'account_locked')
            ->assertJsonPath('message', 'Akun dikunci sementara karena terlalu banyak salah password. Coba lagi dalam 15 menit.');
        $this->assertSame('900', $locked->headers->get('Retry-After'));

        // Password benar pun ditolak selama terkunci; akun lain tidak terpengaruh.
        $this->login('staf.shd', 'password')->assertStatus(429);
        User::factory()->create(['username' => 'lain']);
        $this->login('lain')->assertOk();
    }

    public function test_lock_ignores_username_case_and_spaces(): void
    {
        foreach (['staf.shd', 'STAF.SHD', ' Staf.Shd ', 'staf.shd', 'STAF.shd'] as $username) {
            $this->login($username, 'salah');
        }

        $this->login('Staf.SHD')->assertStatus(429);
    }

    public function test_lock_opens_after_the_configured_minutes(): void
    {
        foreach (range(1, 5) as $attempt) {
            $this->login('staf.shd', 'salah');
        }

        $this->travel(14)->minutes();
        $this->login()->assertStatus(429)->assertJsonPath('message', 'Akun dikunci sementara karena terlalu banyak salah password. Coba lagi dalam 1 menit.');

        $this->travel(61)->seconds();
        $this->login()->assertOk();
    }

    public function test_limit_and_duration_follow_security_settings(): void
    {
        $this->setSecurity(['max_login_attempts' => 3, 'lockout_minutes' => 60]);

        $this->login('staf.shd', 'salah')->assertUnauthorized();
        $this->login('staf.shd', 'salah')->assertUnauthorized();
        $this->login('staf.shd', 'salah')->assertStatus(429);

        $this->travel(59)->minutes();
        $this->login()->assertStatus(429);

        $this->travel(2)->minutes();
        $this->login()->assertOk();
    }

    public function test_successful_login_resets_the_wrong_password_count(): void
    {
        foreach (range(1, 4) as $attempt) {
            $this->login('staf.shd', 'salah')->assertUnauthorized();
        }
        $this->login()->assertOk();

        foreach (range(1, 4) as $attempt) {
            $this->login('staf.shd', 'salah')->assertUnauthorized();
        }
        $this->login()->assertOk();
    }

    public function test_unknown_username_gets_the_same_lock_so_accounts_are_not_revealed(): void
    {
        foreach (range(1, 4) as $attempt) {
            $this->login('tidak.ada', 'salah')->assertUnauthorized()->assertJsonPath('message', 'NIK/Username atau password salah');
        }

        $this->login('tidak.ada', 'salah')->assertStatus(429)->assertJsonPath('code', 'account_locked');
    }

    public function test_inactive_account_with_correct_password_is_not_counted(): void
    {
        $this->user->delete();

        foreach (range(1, 6) as $attempt) {
            $this->login()->assertForbidden();
        }
    }

    public function test_reset_by_shd_staff_unlocks_an_employee_account(): void
    {
        Employee::factory()->inDepartment('SHD')->create(['nik' => '90100002']);
        $target = User::factory()->employee('90100002')->create();

        foreach (range(1, 5) as $attempt) {
            $this->login('90100002', 'salah');
        }
        $this->login('90100002', 'password')->assertStatus(429);

        $this->actingAs($this->employee)->postJson("/api/accounts/{$target->id}/reset")->assertOk();

        $this->app['auth']->forgetGuards();
        $this->login('90100002', '90100002')->assertOk();
    }

    public function test_changing_shared_account_password_unlocks_it(): void
    {
        foreach (range(1, 5) as $attempt) {
            $this->login('staf.shd', 'salah');
        }

        $this->actingAs($this->employee)->putJson("/api/accounts/{$this->user->id}/password", [
            'password' => 'Sandi-Baru-1', 'password_confirmation' => 'Sandi-Baru-1',
        ])->assertOk();

        $this->app['auth']->forgetGuards();
        $this->login('staf.shd', 'Sandi-Baru-1')->assertOk();
    }

    public function test_minimum_password_length_follows_security_settings(): void
    {
        $this->actingAs($this->user);
        $change = fn (string $new): TestResponse => $this->putJson('/api/user/password', [
            'current_password' => 'password', 'password' => $new, 'password_confirmation' => $new,
        ]);

        $this->setSecurity(['password_min_length' => 12]);

        $change('Sandi-Baru')->assertUnprocessable()->assertJsonValidationErrors('password');
        $this->getJson('/api/settings/public')->assertJsonPath('data.password_min_length', 12);
        $change('Sandi-Baru-12')->assertOk();
    }
}
