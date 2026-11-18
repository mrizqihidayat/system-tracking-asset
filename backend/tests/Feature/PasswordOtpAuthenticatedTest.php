<?php

namespace Tests\Feature;

use App\Mail\PasswordOtpMail;
use App\Models\AccountEvent;
use App\Models\PasswordOtp;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * "Lupa password?" untuk pengguna yang sudah login (T42, SPEC §15): OTP terkirim ke email akun, kode
 * salah/kedaluwarsa ditolak, kirim ulang dibatasi, password diganti tanpa password lama, akun tanpa email
 * mendapat pesan yang tepat.
 */
class PasswordOtpAuthenticatedTest extends TestCase
{
    use RefreshDatabase;

    private const EMAIL = 'staf@contoh.test';

    private User $user;

    private string $token;

    protected function setUp(): void
    {
        parent::setUp();

        Mail::fake();
        $this->user = User::factory()->create(['username' => 'staf.shd', 'email' => self::EMAIL]);
        $this->token = $this->user->createToken('web')->plainTextToken;
    }

    /** Setiap request dari IP berbeda: yang teruji aturan OTP, bukan batas 10/menit per IP. */
    private function authedPost(string $uri, array $data = [], ?string $token = null): TestResponse
    {
        static $ip = 0;
        $ip++;
        $this->app['auth']->forgetGuards();

        return $this->withToken($token ?? $this->token)
            ->withServerVariables(['REMOTE_ADDR' => '10.3.'.intdiv($ip, 250).'.'.($ip % 250 + 1)])
            ->postJson($uri, $data);
    }

    private function sendOtp(?string $token = null): TestResponse
    {
        return $this->authedPost('/api/user/password/otp', [], $token);
    }

    private function verifyOtp(string $otp, ?string $token = null): TestResponse
    {
        return $this->authedPost('/api/user/password/otp/verify', ['otp' => $otp], $token);
    }

    private function resetWith(string $resetToken, string $password = 'Sandi-Baru-1', ?string $token = null): TestResponse
    {
        return $this->authedPost('/api/user/password/otp/reset', [
            'reset_token' => $resetToken, 'password' => $password, 'password_confirmation' => $password,
        ], $token);
    }

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

    public function test_otp_is_sent_to_the_logged_in_accounts_email_without_leaking_it(): void
    {
        $response = $this->sendOtp()->assertOk()
            ->assertExactJson([
                'message' => 'Kode verifikasi sudah dikirim ke email akun Anda.',
                'resend_after_seconds' => 60,
                'expires_in_minutes' => 10,
            ]);

        $this->assertStringNotContainsString('contoh.test', $response->getContent());
        Mail::assertSent(PasswordOtpMail::class, fn (PasswordOtpMail $mail) => $mail->hasTo(self::EMAIL));
        $this->assertMatchesRegularExpression('/^\d{6}$/', $this->sentOtp());
    }

    public function test_requires_login(): void
    {
        $this->app['auth']->forgetGuards();

        $this->postJson('/api/user/password/otp')->assertUnauthorized();
        $this->postJson('/api/user/password/otp/verify', ['otp' => '123456'])->assertUnauthorized();
        $this->postJson('/api/user/password/otp/reset')->assertUnauthorized();
    }

    public function test_account_without_email_gets_the_right_message_and_no_mail(): void
    {
        $this->user->forceFill(['email' => null])->save();
        $message = 'Akun ini belum memiliki email. Minta pegawai SHD mereset password melalui Pengaturan > Akun.';

        $this->sendOtp()->assertUnprocessable()->assertJsonPath('message', $message)->assertJsonPath('code', 'no_email');
        $this->verifyOtp('123456')->assertUnprocessable()->assertJsonPath('code', 'no_email');

        Mail::assertNothingSent();
        $this->assertSame(0, PasswordOtp::count());
    }

    public function test_wrong_code_is_rejected_with_remaining_attempts(): void
    {
        $this->sendOtp();

        $this->verifyOtp($this->wrongOtp())->assertUnprocessable()
            ->assertJsonValidationErrors(['otp' => 'Kode salah. Sisa percobaan: 4.']);
    }

    public function test_code_is_locked_after_five_wrong_attempts_even_with_the_right_code(): void
    {
        $this->sendOtp();
        $otp = $this->sentOtp();
        $wrong = $this->wrongOtp();

        foreach ([4, 3, 2, 1] as $left) {
            $this->verifyOtp($wrong)->assertJsonValidationErrors(['otp' => "Kode salah. Sisa percobaan: {$left}."]);
        }
        $this->verifyOtp($wrong)->assertJsonValidationErrors(['otp' => 'Kode salah dan percobaan habis. Minta kode baru.']);

        $this->verifyOtp($otp)->assertUnprocessable()
            ->assertJsonValidationErrors(['otp' => 'Terlalu banyak percobaan salah. Minta kode baru.']);
    }

    public function test_expired_code_is_rejected_with_a_clear_message(): void
    {
        $this->sendOtp();
        $otp = $this->sentOtp();

        $this->travel(11)->minutes();

        $this->verifyOtp($otp)->assertUnprocessable()
            ->assertJsonValidationErrors(['otp' => 'Kode sudah kedaluwarsa. Minta kode baru.']);
    }

    public function test_verify_without_requesting_a_code_first_is_rejected(): void
    {
        $this->verifyOtp('123456')->assertUnprocessable()
            ->assertJsonValidationErrors(['otp' => 'Belum ada kode yang dikirim. Minta kode dulu.']);
    }

    public function test_otp_must_be_six_digits(): void
    {
        $this->verifyOtp('12345')->assertUnprocessable()->assertJsonValidationErrors(['otp' => 'Kode OTP terdiri dari 6 angka.']);
        $this->verifyOtp('')->assertUnprocessable()->assertJsonValidationErrors('otp');
    }

    public function test_resend_is_limited_to_once_per_minute(): void
    {
        $this->sendOtp()->assertOk();

        $this->travel(20)->seconds();
        $this->sendOtp()->assertStatus(429)
            ->assertJsonPath('code', 'otp_resend_wait')
            ->assertJsonPath('resend_after_seconds', 40)
            ->assertHeader('Retry-After', '40');
        Mail::assertSentCount(1);

        $this->travel(41)->seconds();
        $this->sendOtp()->assertOk();
        Mail::assertSentCount(2);
    }

    public function test_new_code_invalidates_the_previous_one(): void
    {
        $this->sendOtp();
        $first = $this->sentOtp();

        $this->travel(61)->seconds();
        $this->sendOtp();

        // Dua email terkirim; OTP lama hanya lolos bila kebetulan sama dengan yang baru.
        $newest = null;
        Mail::assertSent(PasswordOtpMail::class, function (PasswordOtpMail $mail) use (&$newest): bool {
            $newest = $mail->otp;

            return true;
        });

        if ($first !== $newest) {
            $this->verifyOtp($first)->assertUnprocessable();
        }
        $this->verifyOtp((string) $newest)->assertOk();
    }

    public function test_password_changes_without_the_old_password_and_keeps_only_the_current_session(): void
    {
        $other = $this->user->createToken('hp')->plainTextToken;
        $this->user->forceFill(['must_change_password' => true, 'password_changed_at' => null])->save();

        $this->sendOtp()->assertOk();
        $resetToken = $this->verifyOtp($this->sentOtp())->assertOk()->assertJsonPath('expires_in_minutes', 10)->json('reset_token');
        $this->resetWith($resetToken)->assertOk()
            ->assertJsonPath('must_change_password', false)
            ->assertJsonMissingPath('password');

        $this->user->refresh();
        $this->assertTrue(Hash::check('Sandi-Baru-1', $this->user->password));
        $this->assertNotNull($this->user->password_changed_at);
        $this->assertFalse($this->user->must_change_password);
        $this->assertSame(0, PasswordOtp::count());

        $this->assertSame(1, $this->user->tokens()->count());
        $this->authedPost('/api/user/password/otp', [], $other)->assertUnauthorized();
        $this->app['auth']->forgetGuards();
        $this->withToken($this->token)->getJson('/api/user')->assertOk();
    }

    public function test_reset_is_recorded_in_account_events(): void
    {
        $this->sendOtp();
        $resetToken = $this->verifyOtp($this->sentOtp())->json('reset_token');
        $this->resetWith($resetToken)->assertOk();

        $event = AccountEvent::sole();
        $this->assertSame($this->user->id, $event->user_id);
        $this->assertSame($this->user->id, $event->actor_id);
        $this->assertSame('password_changed_via_otp', $event->event);
    }

    public function test_reset_token_is_single_use_and_checks_password_rules(): void
    {
        $this->sendOtp();
        $resetToken = $this->verifyOtp($this->sentOtp())->json('reset_token');

        $this->resetWith($resetToken, 'pendek')->assertUnprocessable()->assertJsonValidationErrors('password');
        $this->resetWith($resetToken, 'staf.shd')->assertUnprocessable()
            ->assertJsonValidationErrors(['password' => 'Password tidak boleh sama dengan username.']);

        $this->resetWith($resetToken)->assertOk();
        $this->resetWith($resetToken, 'Sandi-Lain-22')->assertUnprocessable()->assertJsonValidationErrors('reset_token');
    }

    public function test_reset_token_must_be_valid_and_belong_to_the_same_account(): void
    {
        $this->sendOtp();
        $this->verifyOtp($this->sentOtp())->assertOk();

        $this->resetWith(str_repeat('a', 64))->assertUnprocessable()
            ->assertJsonValidationErrors(['reset_token' => 'Sesi verifikasi sudah tidak berlaku. Minta kode baru.']);

        $other = User::factory()->create(['email' => 'lain@contoh.test']);
        $otherToken = $other->createToken('web')->plainTextToken;
        $this->resetWith(str_repeat('a', 64), 'Sandi-Baru-1', $otherToken)->assertUnprocessable()->assertJsonValidationErrors('reset_token');
    }

    public function test_other_accounts_otp_cannot_be_used(): void
    {
        $other = User::factory()->create(['email' => 'lain@contoh.test']);
        $otherToken = $other->createToken('web')->plainTextToken;

        $this->sendOtp();
        $otp = $this->sentOtp();

        $this->verifyOtp($otp, $otherToken)->assertUnprocessable()
            ->assertJsonValidationErrors(['otp' => 'Belum ada kode yang dikirim. Minta kode dulu.']);
    }

    public function test_account_that_must_change_password_can_use_the_flow(): void
    {
        $this->user->forceFill(['must_change_password' => true])->save();

        $this->sendOtp()->assertOk();
        $this->authedPost('/api/assets')->assertForbidden()->assertJsonPath('code', 'password_change_required');
    }

    public function test_failed_mail_is_reported_and_does_not_block_the_next_try(): void
    {
        config(['mail.default' => 'log']);
        $this->app['env'] = 'production';

        $this->sendOtp()->assertStatus(503)->assertJsonPath('code', 'otp_send_failed');
        $this->assertSame(0, PasswordOtp::count());

        $this->app['env'] = 'testing';
        $this->sendOtp()->assertOk();
    }

    public function test_change_password_rejects_the_same_password_as_current(): void
    {
        $this->app['auth']->forgetGuards();

        $this->withToken($this->token)->putJson('/api/user/password', [
            'current_password' => 'password', 'password' => 'password', 'password_confirmation' => 'password',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['password' => 'Password baru tidak boleh sama dengan password saat ini.']);
    }
}
