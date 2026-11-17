<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\Employee;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Email akun (T40 tahap 2, SPEC §15): wajib saat membuat akun, diubah staf SHD, terisi dari salinan pegawai,
 * dan akun tanpa email wajib mengisinya sebelum memakai aplikasi (kecuali akun yang dikecualikan).
 */
class AccountEmailTest extends TestCase
{
    use RefreshDatabase;

    private User $staff;

    protected function setUp(): void
    {
        parent::setUp();

        Department::create(['name' => 'SHD', 'is_internal' => true]);
        Department::create(['name' => 'HRM', 'is_internal' => false]);
        $this->staff = User::factory()->employee('90100003')->create(['email' => 'staf@contoh.test']);
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
            'email' => 'hrm@contoh.test',
            'password' => 'Bersama-Uji-1',
            'password_confirmation' => 'Bersama-Uji-1',
            ...$overrides,
        ];
    }

    public function test_new_account_stores_email_in_lowercase(): void
    {
        $this->actingAs($this->staff)->postJson('/api/accounts', $this->payload(['email' => ' HRM@Contoh.Test ']))
            ->assertCreated()
            ->assertJsonPath('data.email', 'hrm@contoh.test')
            ->assertJsonPath('data.email_required', false);
    }

    /**
     * @return array<string, array{mixed}>
     */
    public static function invalidEmails(): array
    {
        return [
            'kosong' => [''],
            'tidak dikirim' => [null],
            'bukan email' => ['hrm-contoh'],
            'tanpa domain' => ['hrm@'],
            'terlalu panjang' => [str_repeat('a', 142).'@contoh.test'],
            'dipakai akun lain (beda huruf besar)' => ['STAF@contoh.test'],
            'bukan teks' => [['hrm@contoh.test']],
        ];
    }

    #[DataProvider('invalidEmails')]
    public function test_new_account_requires_a_valid_unique_email(mixed $email): void
    {
        $this->actingAs($this->staff)->postJson('/api/accounts', $this->payload(['email' => $email]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('email');

        $this->assertDatabaseMissing('users', ['username' => 'bersama.hrm']);
    }

    public function test_email_of_a_deactivated_account_stays_reserved(): void
    {
        User::factory()->create(['email' => 'lama@contoh.test'])->delete();

        $this->actingAs($this->staff)->postJson('/api/accounts', $this->payload(['email' => 'lama@contoh.test']))
            ->assertUnprocessable()->assertJsonValidationErrors('email');
    }

    public function test_shd_staff_changes_email_of_any_account(): void
    {
        $shared = User::factory()->create(['email' => 'lama@contoh.test']);
        $employee = User::factory()->employee('90100002')->create(['email' => null]);

        $this->actingAs($this->staff);
        $this->putJson("/api/accounts/{$shared->id}", ['email' => 'Baru@Contoh.Test'])->assertOk()->assertJsonPath('data.email', 'baru@contoh.test');
        $this->putJson("/api/accounts/{$employee->id}", ['email' => 'pegawai@contoh.test'])->assertOk()->assertJsonPath('data.email_required', false);

        // Email sendiri boleh disimpan ulang; email akun lain ditolak.
        $this->putJson("/api/accounts/{$shared->id}", ['email' => 'baru@contoh.test'])->assertOk();
        $this->putJson("/api/accounts/{$shared->id}", ['email' => 'pegawai@contoh.test'])->assertUnprocessable()->assertJsonValidationErrors('email');
        $this->putJson("/api/accounts/{$shared->id}", ['email' => ''])->assertUnprocessable()->assertJsonValidationErrors('email');
    }

    public function test_shared_account_cannot_change_emails_of_other_accounts(): void
    {
        $shared = User::factory()->create();

        $this->actingAs($shared)->putJson("/api/accounts/{$this->staff->id}", ['email' => 'curi@contoh.test'])->assertForbidden();

        $this->assertSame('staf@contoh.test', $this->staff->fresh()->email);
    }

    public function test_account_without_email_is_blocked_until_email_is_filled(): void
    {
        $old = User::factory()->create(['email' => null]);
        $this->actingAs($old);

        $this->getJson('/api/user')->assertOk()->assertJsonPath('email_required', true);

        foreach (['/api/assets', '/api/dashboard/cards', '/api/buildings', '/api/employees?search=an'] as $uri) {
            $this->getJson($uri)->assertForbidden()->assertJsonPath('code', 'email_required');
        }
        $this->postJson('/api/buildings', ['name' => 'Gedung Baru'])->assertForbidden()->assertJsonPath('code', 'email_required');

        $this->putJson('/api/user/email', ['email' => 'staf@contoh.test'])->assertUnprocessable()->assertJsonValidationErrors('email');
        $this->putJson('/api/user/email', ['email' => 'bukan-email'])->assertUnprocessable();
        $this->putJson('/api/user/email', ['email' => 'Lama@Contoh.Test'])->assertOk()
            ->assertJsonPath('email', 'lama@contoh.test')
            ->assertJsonPath('email_required', false);

        $this->getJson('/api/assets')->assertOk();
    }

    public function test_account_without_email_can_still_change_password_and_logout(): void
    {
        $old = User::factory()->create(['email' => null]);
        $token = $old->createToken('web')->plainTextToken;

        $this->withToken($token)->putJson('/api/user/password', [
            'current_password' => 'password', 'password' => 'Sandi-Baru-1', 'password_confirmation' => 'Sandi-Baru-1',
        ])->assertOk();
        $this->withToken($token)->postJson('/api/logout')->assertOk();
    }

    public function test_filled_email_cannot_be_changed_by_the_owner(): void
    {
        $this->actingAs($this->staff)->putJson('/api/user/email', ['email' => 'lain@contoh.test'])
            ->assertUnprocessable()
            ->assertJsonPath('errors.email.0', 'Email sudah terisi. Minta staf SHD mengubahnya di Pengaturan > Akun.');

        $this->assertSame('staf@contoh.test', $this->staff->fresh()->email);
    }

    public function test_exempt_shared_account_from_seeder_is_not_asked_for_email(): void
    {
        // Seeder mengisi departemen sendiri.
        Department::query()->delete();
        $this->seed(DatabaseSeeder::class);
        $staf1 = User::where('username', 'staf1')->sole();

        $this->assertNull($staf1->email);
        $this->actingAs($staf1)->getJson('/api/user')->assertOk()->assertJsonPath('email_required', false);
        $this->getJson('/api/assets')->assertOk();
    }

    public function test_first_employee_login_takes_email_from_employee_copy(): void
    {
        Employee::factory()->inDepartment('SHD')->create(['nik' => '90100009', 'email' => ' Andi.Demo@Contoh.Invalid ']);

        $this->postJson('/api/login', ['username' => '90100009', 'password' => '90100009'])->assertOk()
            ->assertJsonPath('user.email', 'andi.demo@contoh.invalid')
            ->assertJsonPath('user.email_required', false);
    }

    /**
     * @return array<string, array{string|null}>
     */
    public static function unusableEmployeeEmails(): array
    {
        return [
            'API tidak membawa email' => [null],
            'format tidak valid' => ['andi-tanpa-domain'],
            'sudah dipakai akun lain' => ['staf@contoh.test'],
        ];
    }

    #[DataProvider('unusableEmployeeEmails')]
    public function test_employee_without_usable_email_must_fill_it_after_login(?string $email): void
    {
        Employee::factory()->inDepartment('SHD')->create(['nik' => '90100009', 'email' => $email]);

        $token = $this->postJson('/api/login', ['username' => '90100009', 'password' => '90100009'])->assertOk()
            ->assertJsonPath('user.email', null)
            ->assertJsonPath('user.email_required', true)
            ->json('token');

        $this->withToken($token)->getJson('/api/assets')->assertForbidden()->assertJsonPath('code', 'email_required');
    }

    public function test_existing_employee_account_gets_missing_email_on_next_login_but_keeps_its_own(): void
    {
        Employee::factory()->inDepartment('SHD')->create(['nik' => '90100009', 'email' => 'dari.api@contoh.test']);
        $account = User::factory()->employee('90100009')->create(['email' => null]);

        $this->postJson('/api/login', ['username' => '90100009', 'password' => 'password'])->assertOk()
            ->assertJsonPath('user.email', 'dari.api@contoh.test');

        // Email yang sudah diubah staf tidak ditimpa data API.
        $account->update(['email' => 'pilihan.sendiri@contoh.test']);
        $this->postJson('/api/login', ['username' => '90100009', 'password' => 'password'])->assertOk()
            ->assertJsonPath('user.email', 'pilihan.sendiri@contoh.test');
    }
}
