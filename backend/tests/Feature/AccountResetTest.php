<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\Employee;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Reset oleh staf SHD + wajib ganti password saat login berikutnya (T40 tahap 3, SPEC §15).
 */
class AccountResetTest extends TestCase
{
    use RefreshDatabase;

    private User $staff;

    protected function setUp(): void
    {
        parent::setUp();

        Department::create(['name' => 'SHD', 'is_internal' => true]);
        Employee::factory()->inDepartment('SHD')->create(['nik' => '90100002']);
        $this->staff = User::factory()->employee('90100003')->create();
    }

    /**
     * @return array{token: string, must_change_password: bool}
     */
    private function loginAs(string $username, string $password): array
    {
        $this->app['auth']->forgetGuards();
        $response = $this->postJson('/api/login', ['username' => $username, 'password' => $password])->assertOk();

        return ['token' => $response->json('token'), 'must_change_password' => $response->json('user.must_change_password')];
    }

    public function test_reset_employee_account_forces_password_change_on_next_login(): void
    {
        $target = User::factory()->employee('90100002')->create(['password' => 'Sandi-Lama-1']);

        $this->actingAs($this->staff)->postJson("/api/accounts/{$target->id}/reset")->assertOk()
            ->assertJsonPath('data.must_change_password', true);

        $login = $this->loginAs('90100002', '90100002');
        $this->assertTrue($login['must_change_password']);

        $this->withToken($login['token'])->getJson('/api/user')->assertOk()->assertJsonPath('must_change_password', true);
        foreach (['/api/assets', '/api/dashboard/cards', '/api/accounts', '/api/settings'] as $uri) {
            $this->withToken($login['token'])->getJson($uri)->assertForbidden()
                ->assertJsonPath('code', 'password_change_required')
                ->assertJsonPath('message', 'Ganti password dulu sebelum memakai aplikasi.');
        }

        // Password baru tidak boleh NIK lagi; setelah diganti, akun bisa dipakai.
        $this->withToken($login['token'])->putJson('/api/user/password', [
            'current_password' => '90100002', 'password' => '90100002', 'password_confirmation' => '90100002',
        ])->assertUnprocessable()->assertJsonValidationErrors('password');
        $this->withToken($login['token'])->putJson('/api/user/password', [
            'current_password' => '90100002', 'password' => 'Sandi-Baru-1', 'password_confirmation' => 'Sandi-Baru-1',
        ])->assertOk()->assertJsonPath('must_change_password', false);

        $this->withToken($login['token'])->getJson('/api/assets')->assertOk();
        $this->assertFalse($this->loginAs('90100002', 'Sandi-Baru-1')['must_change_password']);
    }

    public function test_password_changed_by_staff_forces_shared_account_to_change_it(): void
    {
        $shared = User::factory()->create(['username' => 'bersama.hrm']);

        $this->actingAs($this->staff)->putJson("/api/accounts/{$shared->id}/password", [
            'password' => 'Sandi-Staf-1', 'password_confirmation' => 'Sandi-Staf-1',
        ])->assertOk()->assertJsonPath('data.must_change_password', true);

        $login = $this->loginAs('bersama.hrm', 'Sandi-Staf-1');
        $this->assertTrue($login['must_change_password']);
        $this->withToken($login['token'])->getJson('/api/buildings')->assertForbidden()->assertJsonPath('code', 'password_change_required');
        $this->withToken($login['token'])->postJson('/api/logout')->assertOk();
    }

    public function test_first_login_with_nik_as_password_does_not_force_a_change(): void
    {
        $login = $this->loginAs('90100002', '90100002');

        $this->assertFalse($login['must_change_password']);
        $this->withToken($login['token'])->getJson('/api/assets')->assertOk();
    }

    public function test_new_shared_account_and_own_password_change_do_not_set_the_flag(): void
    {
        Department::create(['name' => 'HRM', 'is_internal' => false]);

        $this->actingAs($this->staff)->postJson('/api/accounts', [
            'username' => 'bersama.hrm', 'name' => 'Akun Bersama HRM', 'department' => 'HRM', 'email' => 'hrm@contoh.test',
            'password' => 'Bersama-Uji-1', 'password_confirmation' => 'Bersama-Uji-1',
        ])->assertCreated()->assertJsonPath('data.must_change_password', false);

        $this->putJson('/api/user/password', [
            'current_password' => 'password', 'password' => 'Sandi-Baru-1', 'password_confirmation' => 'Sandi-Baru-1',
        ])->assertOk()->assertJsonPath('must_change_password', false);
    }

    public function test_shared_account_cannot_reset_other_accounts(): void
    {
        $target = User::factory()->employee('90100002')->create();
        $shared = User::factory()->create();

        $this->actingAs($shared)->postJson("/api/accounts/{$target->id}/reset")->assertForbidden()
            ->assertJsonPath('code', 'employee_account_required');

        $this->assertFalse($target->fresh()->must_change_password);
    }

    public function test_forced_change_comes_before_missing_email(): void
    {
        $target = User::factory()->employee('90100002')->create(['email' => null]);
        Employee::query()->where('nik', '90100002')->update(['email' => null]);
        $this->actingAs($this->staff)->postJson("/api/accounts/{$target->id}/reset")->assertOk();

        $login = $this->loginAs('90100002', '90100002');

        $this->withToken($login['token'])->getJson('/api/assets')->assertForbidden()->assertJsonPath('code', 'password_change_required');
        $this->withToken($login['token'])->putJson('/api/user/password', [
            'current_password' => '90100002', 'password' => 'Sandi-Baru-1', 'password_confirmation' => 'Sandi-Baru-1',
        ])->assertOk();
        $this->withToken($login['token'])->getJson('/api/assets')->assertForbidden()->assertJsonPath('code', 'email_required');
    }
}
