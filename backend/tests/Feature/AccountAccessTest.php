<?php

namespace Tests\Feature;

use App\Models\AccountEvent;
use App\Models\Department;
use App\Models\Employee;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Pengaturan > Akun: daftar akses, status, riwayat aksi (T41, SPEC §15).
 */
class AccountAccessTest extends TestCase
{
    use RefreshDatabase;

    private User $staff;

    protected function setUp(): void
    {
        parent::setUp();

        Department::create(['name' => 'SHD', 'is_internal' => true]);
        Employee::factory()->inDepartment('SHD')->create(['nik' => '90100003', 'name' => 'Staf Pegawai SHD']);
        $this->staff = User::factory()->employee('90100003')->create(['name' => 'Staf Pegawai SHD', 'password_changed_at' => now()]);
        $this->actingAs($this->staff);
    }

    /**
     * @return array<string, mixed>|null
     */
    private function row(string $name, string $query = ''): ?array
    {
        $rows = $this->getJson('/api/accounts?per_page=100'.$query)->assertOk()->json('data');

        return collect($rows)->firstWhere('name', $name);
    }

    public function test_shared_account_is_rejected_with_403(): void
    {
        $shared = User::factory()->create(['username' => 'bersama.hrm']);

        $this->actingAs($shared)->getJson('/api/accounts')->assertForbidden()->assertJsonPath('code', 'employee_account_required');
        $this->actingAs($shared)->getJson("/api/accounts/{$this->staff->id}/events")->assertForbidden();
    }

    public function test_shd_employee_without_account_is_listed_as_never_logged_in(): void
    {
        Employee::factory()->inDepartment('SHD')->create(['nik' => '90100010', 'name' => 'Pegawai Baru', 'position' => 'Staf Gudang']);
        Employee::factory()->inDepartment('HRM')->create(['nik' => '90100011', 'name' => 'Pegawai HRM']);
        Employee::factory()->inactive()->inDepartment('SHD')->create(['nik' => '90100012', 'name' => 'Pegawai Pensiun']);

        $row = $this->row('Pegawai Baru');

        $this->assertSame('belum', $row['status']);
        $this->assertNull($row['id']);
        $this->assertSame('Staf Gudang', $row['position']);
        $this->assertSame('SHD', $row['department']);
        $this->assertArrayNotHasKey('section', $row);
        $this->assertNull($row['last_login_at']);
        $this->assertNull($this->row('Pegawai HRM'));
        $this->assertNull($this->row('Pegawai Pensiun'));
    }

    public function test_external_account_row_shows_note_and_origin_unit(): void
    {
        User::factory()->create(['name' => 'Akun SGA', 'username' => 'sga', 'department' => 'SGA', 'note' => 'Akun untuk Departemen SGA']);
        User::factory()->create(['name' => 'Akun Tanpa Ket', 'username' => 'tanpa', 'department' => 'SIT']);

        $row = $this->row('Akun SGA');
        $this->assertSame('SGA', $row['department']);
        $this->assertSame('Akun untuk Departemen SGA', $row['note']);
        $this->assertNull($this->row('Akun Tanpa Ket')['note']);
    }

    public function test_employee_removed_from_api_is_inactive_and_cannot_log_in(): void
    {
        $employee = Employee::factory()->inDepartment('SHD')->create(['nik' => '90100020', 'name' => 'Pegawai Pindah']);
        User::factory()->employee('90100020')->create(['name' => 'Pegawai Pindah']);

        $this->assertSame('aktif', $this->row('Pegawai Pindah')['status']);

        $employee->update(['is_active' => false]);
        $row = $this->row('Pegawai Pindah');
        $this->assertSame('nonaktif', $row['status']);
        $this->assertTrue($row['employee_inactive']);
        $this->assertFalse($row['is_deactivated']);

        $this->app['auth']->forgetGuards();
        $this->postJson('/api/login', ['username' => '90100020', 'password' => 'password'])->assertForbidden()->assertJsonPath('message', 'Hanya pegawai Departemen SHD');
    }

    public function test_deactivated_account_is_inactive_and_default_password_flag_follows_password_changes(): void
    {
        $default = User::factory()->employee('90100030')->create(['name' => 'Masih Bawaan', 'password' => '90100030']);
        Employee::factory()->inDepartment('SHD')->create(['nik' => '90100030']);
        $shared = User::factory()->create(['name' => 'Akun Bersama', 'username' => 'bersama.hrm']);
        $off = User::factory()->create(['name' => 'Akun Mati', 'username' => 'mati']);
        $off->delete();

        $this->assertTrue($this->row('Masih Bawaan')['password_default']);
        $this->assertFalse($this->row('Staf Pegawai SHD')['password_default']);
        $this->assertNull($this->row('Akun Bersama')['password_default']);
        $this->assertSame('nonaktif', $this->row('Akun Mati')['status']);
        $this->assertTrue($this->row('Akun Mati')['is_deactivated']);

        // Reset oleh staf mengembalikan penanda bawaan; ganti password sendiri menghapusnya.
        $this->actingAs($this->staff);
        $this->postJson("/api/accounts/{$default->id}/reset")->assertOk();
        $this->assertTrue($this->row('Masih Bawaan')['password_default']);

        $default->forceFill(['password_changed_at' => now()])->save();
        $this->assertFalse($this->row('Masih Bawaan')['password_default']);
        $this->assertNotNull($shared->fresh());
    }

    public function test_default_order_filters_search_and_pagination(): void
    {
        Employee::factory()->inDepartment('SHD')->create(['nik' => '90100040', 'name' => 'Belum Masuk']);
        User::factory()->create(['name' => 'Zeta Bersama', 'username' => 'zeta']);
        User::factory()->create(['name' => 'Alfa Mati', 'username' => 'alfa'])->delete();

        $names = collect($this->getJson('/api/accounts')->assertOk()->json('data'))->pluck('name')->all();
        $this->assertSame(['Staf Pegawai SHD', 'Zeta Bersama', 'Belum Masuk', 'Alfa Mati'], $names);

        $this->getJson('/api/accounts?type=bersama')->assertJsonCount(2, 'data');
        $this->getJson('/api/accounts?type=pegawai')->assertJsonCount(2, 'data');
        $this->getJson('/api/accounts?status=belum')->assertJsonCount(1, 'data')->assertJsonPath('data.0.name', 'Belum Masuk');
        // T45 (UAT K4): cari nama atau username saja, NIK tidak bisa dipakai mencari.
        $this->getJson('/api/accounts?search=9010004')->assertJsonCount(0, 'data');
        $this->getJson('/api/accounts?search=belum')->assertJsonCount(1, 'data');
        $this->getJson('/api/accounts?search=ZETA')->assertJsonCount(1, 'data');
        $this->getJson('/api/accounts?per_page=2&page=2')->assertJsonCount(2, 'data')->assertJsonPath('meta.total', 4)->assertJsonPath('meta.last_page', 2);
        $this->getJson('/api/accounts?status=salah')->assertUnprocessable();
    }

    public function test_response_never_contains_password(): void
    {
        $response = $this->getJson('/api/accounts')->assertOk();

        $this->assertStringNotContainsString('password"', $response->getContent());
        $this->assertStringNotContainsString('$2y$', $response->getContent());
        $response->assertJsonMissingPath('data.0.password');
    }

    public function test_response_never_contains_nik_but_keeps_stable_row_keys(): void
    {
        // T45 (UAT K4): NIK = password awal, jadi tidak dikirim, baik untuk akun maupun pegawai yang belum masuk.
        Employee::factory()->inDepartment('SHD')->create(['nik' => '90100060', 'name' => 'Belum Pernah Masuk']);
        User::factory()->create(['name' => 'Akun Eksternal SGA', 'username' => 'sga.eksternal']);

        $response = $this->getJson('/api/accounts')->assertOk();

        $this->assertStringNotContainsString('90100003', $response->getContent());
        $this->assertStringNotContainsString('90100060', $response->getContent());
        foreach ($response->json('data') as $row) {
            $this->assertArrayNotHasKey('nik', $row);
            $this->assertMatchesRegularExpression('/^(akun|pegawai)-\d+$/', $row['key']);
        }
        $this->assertSame('sga.eksternal', $this->row('Akun Eksternal SGA')['username']);
        $this->assertStringStartsWith('pegawai-', $this->row('Belum Pernah Masuk')['key']);
    }

    public function test_cannot_deactivate_or_reset_own_account(): void
    {
        $this->deleteJson("/api/accounts/{$this->staff->id}")->assertUnprocessable()->assertJsonValidationErrors('account');
        $this->postJson("/api/accounts/{$this->staff->id}/reset")->assertUnprocessable()->assertJsonValidationErrors('account');
        $this->assertNull($this->staff->fresh()->deleted_at);
        $this->assertSame(0, AccountEvent::count());
    }

    public function test_actions_are_recorded_and_listed_newest_first_up_to_ten(): void
    {
        $target = User::factory()->employee('90100050')->create(['name' => 'Target']);
        Employee::factory()->inDepartment('SHD')->create(['nik' => '90100050']);

        $this->postJson("/api/accounts/{$target->id}/reset")->assertOk();
        $this->deleteJson("/api/accounts/{$target->id}")->assertOk();
        $this->postJson("/api/accounts/{$target->id}/restore")->assertOk();

        $events = $this->getJson("/api/accounts/{$target->id}/events")->assertOk()->assertJsonCount(3, 'data')->json('data');
        $this->assertSame(['restored', 'deactivated', 'reset'], array_column($events, 'event'));
        $this->assertSame('Staf Pegawai SHD', $events[0]['actor_name']);

        foreach (range(1, 12) as $i) {
            AccountEvent::create(['user_id' => $target->id, 'actor_id' => $this->staff->id, 'event' => 'email_updated', 'created_at' => now()->addMinutes($i)]);
        }
        $this->getJson("/api/accounts/{$target->id}/events")->assertJsonCount(10, 'data');
    }

    public function test_shared_account_creation_is_recorded_and_login_sets_last_login(): void
    {
        $this->postJson('/api/accounts', [
            'username' => 'bersama.hrm', 'name' => 'Akun Bersama HRM', 'department' => 'SHD', 'email' => 'b@contoh.test',
            'password' => 'Bersama-Uji-1', 'password_confirmation' => 'Bersama-Uji-1',
        ])->assertCreated();

        $account = User::where('username', 'bersama.hrm')->firstOrFail();
        $this->assertNotNull($account->password_changed_at);
        $this->assertSame('created', AccountEvent::where('user_id', $account->id)->value('event'));
        $this->assertNull($this->row('Akun Bersama HRM')['last_login_at']);

        $this->app['auth']->forgetGuards();
        $this->postJson('/api/login', ['username' => 'bersama.hrm', 'password' => 'Bersama-Uji-1'])->assertOk();

        $this->assertNotNull($account->fresh()->last_login_at);
    }
}
