<?php

namespace Tests\Feature;

use App\Mail\PasswordOtpMail;
use App\Models\PasswordOtp;
use App\Models\User;
use App\Services\SettingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * Lupa password via OTP (T40 tahap 3, SPEC §15): berhasil, salah, kedaluwarsa, batas kirim ulang, sekali pakai,
 * tidak membocorkan email, dan mode MAIL_MAILER=log.
 */
class PasswordOtpTest extends TestCase
{
    use RefreshDatabase;

    private const EMAIL = 'staf@contoh.test';

    private const GENERIC = 'Jika email terdaftar, kode OTP sudah dikirim ke email itu.';

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        Mail::fake();
        $this->user = User::factory()->create(['username' => 'staf.shd', 'email' => self::EMAIL]);
    }

    /** Setiap request dari IP berbeda: yang teruji aturan OTP, bukan batas 10/menit per IP. */
    private function send(string $uri, array $data): TestResponse
    {
        static $ip = 0;
        $ip++;

        return $this->withServerVariables(['REMOTE_ADDR' => '10.2.'.intdiv($ip, 250).'.'.($ip % 250 + 1)])->postJson($uri, $data);
    }

    private function forgot(string $email = self::EMAIL): TestResponse
    {
        return $this->send('/api/password/forgot', ['email' => $email]);
    }

    private function verify(string $otp, string $email = self::EMAIL): TestResponse
    {
        return $this->send('/api/password/verify-otp', ['email' => $email, 'otp' => $otp]);
    }

    private function reset(string $token, string $password = 'Sandi-Baru-1', string $email = self::EMAIL): TestResponse
    {
        return $this->send('/api/password/reset', [
            'email' => $email, 'reset_token' => $token, 'password' => $password, 'password_confirmation' => $password,
        ]);
    }

    /** OTP dari email terakhir yang terkirim. */
    private function sentOtp(): string
    {
        $otp = null;
        Mail::assertSent(PasswordOtpMail::class, function (PasswordOtpMail $mail) use (&$otp): bool {
            $otp = $mail->otp;

            return true;
        });

        return (string) $otp;
    }

    private function wrongOtp(): string
    {
        return $this->sentOtp() === '000000' ? '000001' : '000000';
    }

    public function test_full_flow_sets_new_password_and_revokes_sessions(): void
    {
        $this->user->createToken('web');

        $this->forgot(' STAF@Contoh.Test ')->assertOk()
            ->assertExactJson(['message' => self::GENERIC, 'resend_after_seconds' => 60, 'expires_in_minutes' => 10]);

        Mail::assertSent(PasswordOtpMail::class, fn (PasswordOtpMail $mail) => $mail->hasTo(self::EMAIL));
        $otp = $this->sentOtp();
        $this->assertMatchesRegularExpression('/^\d{6}$/', $otp);

        $token = $this->verify($otp)->assertOk()->assertJsonPath('expires_in_minutes', 10)->json('reset_token');
        $this->reset($token)->assertOk()->assertJsonPath('message', 'Password berhasil diganti. Silakan masuk dengan password baru.');

        $this->assertSame(0, $this->user->tokens()->count());
        $this->assertSame(0, PasswordOtp::count());
        $this->postJson('/api/login', ['username' => 'staf.shd', 'password' => 'password'])->assertUnauthorized();
        $this->postJson('/api/login', ['username' => 'staf.shd', 'password' => 'Sandi-Baru-1'])->assertOk();
    }

    public function test_otp_and_reset_token_are_stored_only_as_hashes(): void
    {
        $this->forgot();
        $otp = $this->sentOtp();
        $row = PasswordOtp::sole();

        $this->assertNotSame($otp, $row->otp_hash);
        $this->assertTrue(Hash::check($otp, $row->otp_hash));

        $token = $this->verify($otp)->json('reset_token');
        $row->refresh();

        $this->assertNotSame($token, $row->reset_token_hash);
        $this->assertSame(hash('sha256', $token), $row->reset_token_hash);
    }

    public function test_unknown_email_gets_the_same_answer_and_no_mail(): void
    {
        $this->forgot('tidak.ada@contoh.test')->assertOk()
            ->assertExactJson(['message' => self::GENERIC, 'resend_after_seconds' => 60, 'expires_in_minutes' => 10]);

        Mail::assertNothingSent();
        $this->assertSame(0, PasswordOtp::count());

        // Cek OTP untuk email tak terdaftar dijawab sama dengan OTP salah.
        $known = $this->verify('123456', 'tidak.ada@contoh.test')->assertUnprocessable()->json();
        $this->forgot();
        $this->assertSame($known, $this->verify($this->wrongOtp())->assertUnprocessable()->json());
    }

    public function test_deactivated_account_gets_no_otp(): void
    {
        $this->user->delete();

        $this->forgot()->assertOk()->assertJsonPath('message', self::GENERIC);

        Mail::assertNothingSent();
    }

    public function test_wrong_otp_is_rejected_and_correct_one_still_works_before_the_limit(): void
    {
        $this->forgot();
        $otp = $this->sentOtp();

        foreach (range(1, 4) as $attempt) {
            $this->verify($this->wrongOtp())->assertUnprocessable()
                ->assertJsonPath('errors.otp.0', 'Kode OTP salah atau sudah tidak berlaku. Periksa lagi atau minta kode baru.');
        }
        $this->assertSame(4, PasswordOtp::sole()->attempts);

        $this->verify($otp)->assertOk();
    }

    public function test_otp_is_void_after_five_wrong_attempts(): void
    {
        $this->forgot();
        $otp = $this->sentOtp();

        foreach (range(1, 5) as $attempt) {
            $this->verify($this->wrongOtp())->assertUnprocessable();
        }

        $this->verify($otp)->assertUnprocessable()->assertJsonValidationErrors('otp');
    }

    public function test_expired_otp_is_rejected_and_ttl_follows_security_settings(): void
    {
        app(SettingService::class)->update(['otp_ttl_minutes' => 5], $this->user);

        $this->forgot()->assertJsonPath('expires_in_minutes', 5);
        $otp = $this->sentOtp();
        Mail::assertSent(PasswordOtpMail::class, fn (PasswordOtpMail $mail) => $mail->minutes === 5);

        $this->travel(5)->minutes();
        $this->travel(1)->seconds();

        $this->verify($otp)->assertUnprocessable()->assertJsonValidationErrors('otp');
    }

    public function test_otp_can_be_used_only_once(): void
    {
        $this->forgot();
        $otp = $this->sentOtp();

        $this->verify($otp)->assertOk();
        $this->verify($otp)->assertUnprocessable()->assertJsonValidationErrors('otp');
    }

    public function test_resend_is_ignored_within_60_seconds_and_new_otp_replaces_the_old_one(): void
    {
        $this->forgot();
        $first = $this->sentOtp();

        $this->travel(59)->seconds();
        $this->forgot()->assertOk()->assertJsonPath('message', self::GENERIC);
        Mail::assertSentCount(1);
        $this->verify($this->wrongOtp());

        $this->travel(2)->seconds();
        $this->forgot()->assertOk();
        Mail::assertSentCount(2);

        // OTP baru: hitungan salah kembali 0 dan OTP lama tidak berlaku lagi.
        $row = PasswordOtp::sole();
        $this->assertSame(0, $row->attempts);
        $this->assertFalse(Hash::check($first, $row->otp_hash) && $first !== $this->newestOtp());
        $this->verify($this->newestOtp())->assertOk();
    }

    private function newestOtp(): string
    {
        return Mail::sent(PasswordOtpMail::class)->last()->otp;
    }

    public function test_reset_token_is_single_use_expires_and_is_bound_to_the_email(): void
    {
        $other = User::factory()->create(['email' => 'lain@contoh.test']);
        $this->forgot();
        $token = $this->verify($this->sentOtp())->json('reset_token');

        $this->reset(str_repeat('a', 64))->assertUnprocessable()
            ->assertJsonPath('errors.reset_token.0', 'Sesi reset sudah tidak berlaku. Minta kode OTP baru.');
        $this->reset($token, 'Sandi-Baru-1', 'lain@contoh.test')->assertUnprocessable()->assertJsonValidationErrors('reset_token');
        $this->assertTrue(Hash::check('password', $other->fresh()->password));

        $this->reset($token)->assertOk();
        $this->reset($token, 'Sandi-Lain-22')->assertUnprocessable()->assertJsonValidationErrors('reset_token');

        // Token kedaluwarsa setelah 10 menit.
        $this->travel(61)->seconds();
        $this->forgot();
        $late = $this->verify($this->newestOtp())->json('reset_token');
        $this->travel(11)->minutes();
        $this->reset($late, 'Sandi-Lain-22')->assertUnprocessable()->assertJsonValidationErrors('reset_token');
    }

    public function test_new_password_follows_password_rules_and_keeps_the_token_on_failure(): void
    {
        app(SettingService::class)->update(['password_min_length' => 12], $this->user);
        $this->forgot();
        $token = $this->verify($this->sentOtp())->json('reset_token');

        $this->reset($token, 'Pendek-1')->assertUnprocessable()->assertJsonValidationErrors('password');
        $this->reset($token, 'staf.shd')->assertUnprocessable()->assertJsonValidationErrors('password');
        $this->send('/api/password/reset', ['email' => self::EMAIL, 'reset_token' => $token, 'password' => 'Sandi-Baru-123', 'password_confirmation' => 'beda'])
            ->assertUnprocessable()->assertJsonValidationErrors('password');

        $this->reset($token, 'Sandi-Baru-123')->assertOk();
    }

    public function test_reset_unlocks_a_locked_account_and_clears_forced_password_change(): void
    {
        $this->user->forceFill(['must_change_password' => true])->save();
        foreach (range(1, 5) as $attempt) {
            $this->send('/api/login', ['username' => 'staf.shd', 'password' => 'salah']);
        }
        $this->send('/api/login', ['username' => 'staf.shd', 'password' => 'password'])->assertStatus(429);

        $this->forgot();
        $this->reset($this->verify($this->sentOtp())->json('reset_token'))->assertOk();

        $this->send('/api/login', ['username' => 'staf.shd', 'password' => 'Sandi-Baru-1'])->assertOk()
            ->assertJsonPath('user.must_change_password', false);
    }

    public function test_invalid_input_is_rejected(): void
    {
        $this->forgot('bukan-email')->assertUnprocessable()->assertJsonValidationErrors('email');
        $this->forgot('')->assertUnprocessable()->assertJsonValidationErrors('email');
        $this->verify('12345')->assertUnprocessable()->assertJsonPath('errors.otp.0', 'Kode OTP terdiri dari 6 angka.');
        $this->verify('abcdef')->assertUnprocessable()->assertJsonValidationErrors('otp');
        $this->reset('pendek')->assertUnprocessable()->assertJsonValidationErrors('reset_token');
    }

    public function test_endpoints_are_limited_per_ip(): void
    {
        foreach (range(1, 10) as $attempt) {
            $this->postJson('/api/password/forgot', ['email' => 'tidak.ada@contoh.test'])->assertOk();
        }

        $this->postJson('/api/password/verify-otp', ['email' => self::EMAIL, 'otp' => '123456'])->assertTooManyRequests();
    }

    public function test_email_uses_site_title_logo_and_states_validity_in_indonesian(): void
    {
        Storage::fake('local');
        config(['filesystems.asset_disk' => 'local']);
        $employee = User::factory()->employee('90100003')->create();
        app(SettingService::class)->update(['site_title' => 'Aset Pelatihan SHD', 'otp_ttl_minutes' => 20], $employee);
        app(SettingService::class)->storeFile('logo', UploadedFile::fake()->image('logo.png'), $employee);

        $this->forgot();

        Mail::assertSent(PasswordOtpMail::class, function (PasswordOtpMail $mail): bool {
            $mail->assertHasSubject('Kode OTP reset password Aset Pelatihan SHD');
            $mail->assertSeeInHtml('Aset Pelatihan SHD');
            $mail->assertSeeInHtml('/api/settings/files/logo?v=', false);
            $mail->assertSeeInHtml($mail->otp);
            $mail->assertSeeInHtml('Kode berlaku <strong>20 menit</strong>', false);
            $mail->assertSeeInText('Kode berlaku 20 menit dan hanya bisa dipakai satu kali.');
            $mail->assertSeeInText($mail->otp);

            return true;
        });
    }

    public function test_email_without_logo_shows_only_the_title(): void
    {
        $this->forgot();

        Mail::assertSent(PasswordOtpMail::class, function (PasswordOtpMail $mail): bool {
            $mail->assertDontSeeInHtml('<img', false);
            $mail->assertSeeInHtml('Aset SHD');

            return true;
        });
    }

    public function test_log_mailer_never_sends_otp_outside_local_environment(): void
    {
        config(['mail.default' => 'log']);

        $this->app['env'] = 'production';
        $this->forgot()->assertOk()->assertJsonPath('message', self::GENERIC);
        Mail::assertNothingSent();

        $this->app['env'] = 'local';
        $this->travel(61)->seconds();
        $this->forgot()->assertOk();
        Mail::assertSentCount(1);
    }
}
