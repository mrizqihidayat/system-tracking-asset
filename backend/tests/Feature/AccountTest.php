<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\Employee;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Pengaturan > Akun (API) dan saran pegawai (T33, SPEC §15).
 */
class AccountTest extends TestCase
{
    use RefreshDatabase;

    private User $staff;

    protected function setUp(): void
    {
        parent::setUp();

        Department::create(['name' => 'SHD', 'is_internal' => true]);
        Department::create(['name' => 'HRM', 'is_internal' => false]);
        // Pegawai SHD di salinan lokal (dipakai login setelah Reset password = NIK).
        Employee::factory()->inDepartment('SHD')->create(['nik' => '90100002']);
        // Kelola akun hanya untuk akun pegawai SHD yang sudah ganti password.
        $this->staff = User::factory()->employee('90100003')->create(['name' => 'Staf Pegawai SHD']);
        $this->actingAs($this->staff);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function payload(array $overrides = []): array
    {
        return [
            'username' => 'bersama.hrm',
            'name' => 'Akun Bersama HRM',
            'department' => 'HRM',
            'email' => 'bersama.hrm@contoh.test',
            'password' => 'Bersama-Uji-1',
            'password_confirmation' => 'Bersama-Uji-1',
            ...$overrides,
        ];
    }

    public function test_staff_creates_shared_account_that_logs_in_without_forced_change(): void
    {
        $this->postJson('/api/accounts', $this->payload(['username' => ' Bersama.HRM ']))->assertCreated()
            ->assertJsonPath('data.username', 'bersama.hrm')
            ->assertJsonPath('data.source', 'manual')
            ->assertJsonMissingPath('data.password');

        $this->app['auth']->forgetGuards();
        $this->postJson('/api/login', ['username' => 'bersama.hrm', 'password' => 'Bersama-Uji-1'])->assertOk()
            ->assertJsonPath('user.department', 'HRM');
    }

    public function test_external_account_takes_free_text_unit_and_optional_note(): void
    {
        $this->postJson('/api/accounts', $this->payload(['department' => 'Unit Di Luar SIMHC', 'note' => 'Akun untuk Departemen SGA']))->assertCreated()
            ->assertJsonPath('data.department', 'Unit Di Luar SIMHC');
        $this->postJson('/api/accounts', $this->payload(['username' => 'tanpa.keterangan', 'email' => 'lain@contoh.test']))->assertCreated();

        $this->assertSame('Akun untuk Departemen SGA', User::where('username', 'bersama.hrm')->value('note'));
        $this->assertNull(User::where('username', 'tanpa.keterangan')->value('note'));
    }

    public function test_shared_account_validation(): void
    {
        User::factory()->create(['username' => 'staf1']);
        $this->postJson('/api/accounts', $this->payload(['username' => '90100001']))->assertUnprocessable()->assertJsonValidationErrors('username');
        $this->postJson('/api/accounts', $this->payload(['username' => 'STAF1']))->assertUnprocessable()->assertJsonValidationErrors('username');
        $this->postJson('/api/accounts', $this->payload(['username' => 'ada spasi']))->assertUnprocessable()->assertJsonValidationErrors('username');
        $this->postJson('/api/accounts', $this->payload(['password' => 'bersama.hrm', 'password_confirmation' => 'bersama.hrm']))
            ->assertUnprocessable()->assertJsonPath('errors.password.0', 'Password tidak boleh sama dengan username.');
        $this->postJson('/api/accounts', $this->payload(['password_confirmation' => 'Lain-Uji-2']))->assertUnprocessable()->assertJsonValidationErrors('password');
        $this->postJson('/api/accounts', $this->payload(['password' => 'pendek', 'password_confirmation' => 'pendek']))->assertUnprocessable()->assertJsonValidationErrors('password');
        $this->postJson('/api/accounts', $this->payload(['department' => '']))->assertUnprocessable()->assertJsonValidationErrors('department');
        $this->postJson('/api/accounts', $this->payload(['department' => str_repeat('a', 101)]))->assertUnprocessable()->assertJsonValidationErrors('department');
        $this->postJson('/api/accounts', $this->payload(['note' => str_repeat('a', 151)]))->assertUnprocessable()->assertJsonValidationErrors('note');

        $this->assertSame(2, User::count());
    }

    public function test_list_shows_api_and_manual_accounts_with_active_flag(): void
    {
        User::factory()->employee('90100002')->create(['name' => 'Akun Pegawai']);
        User::factory()->create(['name' => 'Zulu Nonaktif'])->delete();

        $this->getJson('/api/accounts')->assertOk()
            ->assertJsonCount(3, 'data')
            ->assertJsonPath('data.0.name', 'Akun Pegawai')
            ->assertJsonPath('data.0.source', 'api')
            ->assertJsonPath('data.2.name', 'Zulu Nonaktif')
            ->assertJsonPath('data.2.status', 'nonaktif')
            ->assertJsonPath('data.2.is_deactivated', true);
    }

    public function test_staff_updates_shared_account_password_and_revokes_its_sessions(): void
    {
        $shared = User::factory()->create(['username' => 'bersama.hrm', 'password' => 'Lama-Uji-2026']);
        $shared->createToken('web');

        $this->putJson("/api/accounts/{$shared->id}/password", ['password' => 'Baru-Uji-2026', 'password_confirmation' => 'Baru-Uji-2026'])
            ->assertOk();

        $this->assertSame(0, $shared->tokens()->count());
        $this->app['auth']->forgetGuards();
        $this->postJson('/api/login', ['username' => 'bersama.hrm', 'password' => 'Baru-Uji-2026'])->assertOk();
    }

    public function test_update_password_is_for_manual_accounts_and_reset_for_api_accounts(): void
    {
        $employee = User::factory()->employee('90100002')->create();
        $shared = User::factory()->create();
        $body = ['password' => 'Baru-Uji-2026', 'password_confirmation' => 'Baru-Uji-2026'];

        $this->putJson("/api/accounts/{$employee->id}/password", $body)->assertUnprocessable()->assertJsonValidationErrors('account');
        $this->postJson("/api/accounts/{$shared->id}/reset")->assertUnprocessable()->assertJsonValidationErrors('account');
    }

    public function test_reset_api_account_returns_password_to_nik(): void
    {
        $employee = User::factory()->employee('90100002')->create(['password' => 'Lama-Uji-2026']);
        $employee->createToken('web');

        $this->postJson("/api/accounts/{$employee->id}/reset")->assertOk();
        $this->assertSame(0, $employee->tokens()->count());

        $this->app['auth']->forgetGuards();
        $this->postJson('/api/login', ['username' => '90100002', 'password' => 'Lama-Uji-2026'])->assertUnauthorized();
        $this->postJson('/api/login', ['username' => '90100002', 'password' => '90100002'])->assertOk();
    }

    public function test_deactivate_and_restore_account(): void
    {
        $shared = User::factory()->create(['username' => 'bersama.hrm', 'password' => 'Bersama-Uji-1']);
        $shared->createToken('web');

        $this->deleteJson("/api/accounts/{$shared->id}")->assertOk()->assertJsonPath('data.is_active', false);
        $this->assertSoftDeleted($shared);
        $this->assertSame(0, $shared->tokens()->count());

        $this->app['auth']->forgetGuards();
        $this->postJson('/api/login', ['username' => 'bersama.hrm', 'password' => 'Bersama-Uji-1'])->assertForbidden();

        $this->actingAs($this->staff);
        $this->postJson("/api/accounts/{$shared->id}/restore")->assertOk()->assertJsonPath('data.is_active', true);
        $this->app['auth']->forgetGuards();
        $this->postJson('/api/login', ['username' => 'bersama.hrm', 'password' => 'Bersama-Uji-1'])->assertOk();
    }

    public function test_staff_cannot_deactivate_or_reset_own_account(): void
    {
        $this->deleteJson("/api/accounts/{$this->staff->id}")->assertUnprocessable()->assertJsonPath('errors.account.0', 'Tidak bisa menonaktifkan akun sendiri.');

        $this->postJson("/api/accounts/{$this->staff->id}/reset")->assertUnprocessable()->assertJsonValidationErrors('account');

        $this->assertNotSoftDeleted($this->staff);
    }

    public function test_shared_account_cannot_manage_accounts(): void
    {
        $other = User::factory()->create(['username' => 'bersama.hrm']);
        $this->actingAs(User::factory()->create(['username' => 'staf1']));

        $this->getJson('/api/accounts')->assertForbidden()->assertJsonPath('code', 'employee_account_required');
        $this->postJson('/api/accounts', $this->payload(['username' => 'bersama.baru']))->assertForbidden();
        $this->putJson("/api/accounts/{$other->id}/password", ['password' => 'Baru-Uji-2026', 'password_confirmation' => 'Baru-Uji-2026'])->assertForbidden();
        $this->postJson("/api/accounts/{$this->staff->id}/reset")->assertForbidden();
        $this->deleteJson("/api/accounts/{$other->id}")->assertForbidden();
        $this->postJson("/api/accounts/{$other->id}/restore")->assertForbidden();

        $this->assertDatabaseMissing('users', ['username' => 'bersama.baru']);
        $this->assertNotSoftDeleted($other);
        // Fitur lain tetap bisa dipakai akun bersama.
        $this->getJson('/api/employees?search=andi')->assertOk();
    }

    public function test_accounts_require_login(): void
    {
        $this->app['auth']->forgetGuards();

        $this->getJson('/api/accounts')->assertUnauthorized();
        $this->getJson('/api/employees?search=andi')->assertUnauthorized();
    }

    public function test_employee_search_returns_only_public_fields_across_departments(): void
    {
        Employee::factory()->inDepartment('SHD')->create(['nik' => '90100001', 'name' => 'ANDI PRATAMA', 'email' => 'andi@contoh.invalid']);
        Employee::factory()->inDepartment('HRM')->create(['nik' => '90100004', 'name' => 'DEWI ANGGRAINI']);
        Employee::factory()->count(12)->create(['name' => 'ANTON']);

        $this->getJson('/api/employees?search=a')->assertUnprocessable();

        $response = $this->getJson('/api/employees?search=an')->assertOk();
        $this->assertCount(10, $response->json('data'));
        foreach ($response->json('data') as $row) {
            $this->assertSame(['name', 'department', 'position'], array_keys($row));
        }

        $content = $response->getContent();
        foreach (['nik', '90100001', '90100004', 'email', 'contoh.invalid', 'group_name'] as $private) {
            $this->assertStringNotContainsString($private, $content);
        }
    }

    public function test_employee_search_matches_names_only_not_nik(): void
    {
        // T45 (UAT K1): NIK = password awal, jadi NIK lengkap maupun awalannya tidak boleh mengembalikan pemiliknya.
        Employee::factory()->inDepartment('SHD')->create(['nik' => '90100001', 'name' => 'ANDI PRATAMA']);
        $external = User::factory()->create(['username' => 'sga1']);

        foreach ([$this->staff, $external] as $account) {
            $this->actingAs($account);
            $this->getJson('/api/employees?search=90100001')->assertOk()->assertJsonCount(0, 'data');
            $this->getJson('/api/employees?search=9010')->assertOk()->assertJsonCount(0, 'data');
            $this->getJson('/api/employees?search=pratama')->assertOk()->assertJsonPath('data.0.name', 'ANDI PRATAMA');
        }
    }

    public function test_employee_search_department_fallback(): void
    {
        Employee::factory()->inDepartment('HRM')->create(['name' => 'DEWI KARTIKA']);
        Employee::factory()->create(['name' => 'YUDHA KUSUMA', 'department_code' => null, 'division' => 'PME', 'group_name' => 'GPO', 'position' => 'Kepala Divisi']);
        Employee::factory()->create(['name' => 'BENNY HASIBUAN', 'department_code' => null, 'division' => null, 'group_name' => 'GPO']);
        Employee::factory()->create(['name' => 'TANPA UNIT', 'department_code' => null, 'division' => null, 'group_name' => null]);

        $this->getJson('/api/employees?search=Dewi Kartika')->assertOk()->assertJsonPath('data.0.department', 'HRM');
        // departemen kosong → divisi
        $this->getJson('/api/employees?search=Yudha')->assertOk()
            ->assertJsonPath('data.0', ['name' => 'YUDHA KUSUMA', 'department' => 'PME', 'position' => 'Kepala Divisi']);
        // departemen & divisi kosong → grup
        $this->getJson('/api/employees?search=Benny')->assertOk()->assertJsonPath('data.0.department', 'GPO');
        $this->getJson('/api/employees?search=Tanpa')->assertOk()->assertJsonPath('data.0.department', '-');
    }

    public function test_employee_search_skips_inactive_employees_and_reads_only_the_local_copy(): void
    {
        Employee::factory()->inactive()->create(['name' => 'PEGAWAI LAMA']);
        Employee::factory()->create(['name' => 'PEGAWAI AKTIF']);
        Http::fake();

        $this->getJson('/api/employees?search=pegawai')->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'PEGAWAI AKTIF');
        Http::assertNothingSent();
    }
}
