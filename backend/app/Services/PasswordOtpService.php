<?php

namespace App\Services;

use App\Mail\PasswordOtpMail;
use App\Models\PasswordOtp;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Throwable;

/**
 * Lupa password via OTP (T40, SPEC §15): minta OTP → cek OTP → buat password baru.
 * - OTP 6 digit dan token reset hanya disimpan sebagai hash, sekali pakai.
 * - Maksimal 5 kali salah per OTP, kirim ulang paling cepat tiap 60 detik (dicatat di tabel, bukan cache),
 *   masa berlaku dari Pengaturan > Keamanan.
 * - Tanpa login: tidak ada jawaban yang membedakan email terdaftar dan tidak terdaftar.
 * - Dengan login (T42, "Lupa password?"): `issueForUser`, `verifyForUser`, `resetPasswordKeepingSession`
 *   memakai tabel dan aturan yang sama, tetapi menjawab alasan gagal dengan jelas.
 */
class PasswordOtpService
{
    public const MAX_ATTEMPTS = 5;

    public const RESEND_SECONDS = 60;

    /** Setelah OTP benar, password baru harus dibuat dalam waktu ini. */
    public const RESET_MINUTES = 10;

    public function __construct(
        private SettingService $settings,
        private AuthService $auth,
        private LoginLockout $lockout,
        private AccountService $accounts,
    ) {}

    /**
     * Buat dan kirim OTP bila email milik akun aktif dan kiriman terakhir sudah lewat 60 detik. Dipanggil
     * setelah respons terkirim, jadi lama proses tidak membocorkan apakah email terdaftar.
     */
    public function issue(string $email, ?string $ip = null): void
    {
        $user = $this->findUser($email);
        if (! $user) {
            return;
        }

        [$otp] = $this->createOtp($user);

        if ($otp === null) {
            return;
        }

        Log::channel('security')->info('password_otp.sent', ['user_id' => $user->id, 'ip' => $ip]);
        $this->send($user, $otp);
    }

    /**
     * Varian untuk pengguna yang sudah login (tanpa mengetik email lagi). Tidak ada risiko membocorkan email,
     * jadi kiriman terlalu cepat dan gagal kirim dijawab apa adanya.
     *
     * @return array{status: 'sent'|'wait'|'failed', seconds: int}
     */
    public function issueForUser(User $user, ?string $ip = null): array
    {
        [$otp, $wait] = $this->createOtp($user);

        if ($otp === null) {
            return ['status' => 'wait', 'seconds' => $wait];
        }

        Log::channel('security')->info('password_otp.sent', ['user_id' => $user->id, 'ip' => $ip]);

        if (! $this->send($user, $otp)) {
            // Kode yang tidak sampai tidak boleh menahan permintaan berikutnya selama 60 detik.
            PasswordOtp::query()->where('user_id', $user->id)->delete();

            return ['status' => 'failed', 'seconds' => 0];
        }

        return ['status' => 'sent', 'seconds' => self::RESEND_SECONDS];
    }

    /**
     * Buat OTP baru: [OTP, 0], atau [null, sisa detik] bila kiriman terakhir belum 60 detik.
     *
     * @return array{0: ?string, 1: int}
     */
    private function createOtp(User $user): array
    {
        return DB::transaction(function () use ($user): array {
            $row = PasswordOtp::query()->where('user_id', $user->id)->lockForUpdate()->first();
            $nextAt = $row?->last_sent_at->copy()->addSeconds(self::RESEND_SECONDS);

            if ($nextAt?->isFuture()) {
                return [null, max(1, (int) ceil(now()->diffInSeconds($nextAt, true)))];
            }

            $otp = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);

            PasswordOtp::query()->updateOrCreate(['user_id' => $user->id], [
                'otp_hash' => Hash::make($otp),
                'attempts' => 0,
                'expires_at' => now()->addMinutes($this->settings->int('otp_ttl_minutes')),
                'last_sent_at' => now(),
                'used_at' => null,
                'reset_token_hash' => null,
                'reset_expires_at' => null,
            ]);

            return [$otp, 0];
        });
    }

    /**
     * MAIL_MAILER=log menulis isi email (termasuk OTP) ke storage/logs: hanya boleh di lingkungan lokal.
     * Di lingkungan lain email tidak dikirim dan kesalahan konfigurasi dicatat tanpa OTP.
     */
    private function send(User $user, string $otp): bool
    {
        if (config('mail.default') === 'log' && ! app()->environment('local', 'testing')) {
            Log::error('password_otp.mailer_not_configured: MAIL_MAILER=log hanya untuk APP_ENV=local. Isi MAIL_* dengan SMTP.');

            return false;
        }

        try {
            Mail::to($user->email)->send(new PasswordOtpMail(
                $otp,
                $this->settings->int('otp_ttl_minutes'),
                $this->settings->text('site_title'),
                $this->settings->publicPayload()['logo_url'],
            ));
        } catch (Throwable $e) {
            // Pesan kesalahan SMTP saja; OTP dan alamat email tidak dicatat.
            Log::error('password_otp.send_failed: '.$e::class);

            return false;
        }

        return true;
    }

    /**
     * Cek OTP. Benar → OTP hangus dan token reset sekali pakai dikembalikan; selain itu null (salah, kedaluwarsa,
     * sudah dipakai, sudah 5 kali salah, atau email tidak terdaftar: semuanya dijawab sama).
     */
    public function verify(string $email, string $otp): ?string
    {
        $user = $this->findUser($email);

        if (! $user) {
            // Samakan waktu respons dengan pengecekan hash.
            Hash::check($otp, AuthService::DUMMY_PASSWORD_HASH);

            return null;
        }

        return $this->check($user, $otp)['token'];
    }

    /**
     * Cek OTP untuk pengguna yang sudah login. Alasan gagal dikembalikan agar pesannya jelas: `none` (belum ada
     * kode), `used`, `expired`, `locked` (sudah 5 kali salah), atau `wrong` (dengan sisa percobaan).
     *
     * @return array{token: ?string, reason: ?string, remaining: int}
     */
    public function verifyForUser(User $user, string $otp): array
    {
        return $this->check($user, $otp);
    }

    /**
     * @return array{token: ?string, reason: ?string, remaining: int}
     */
    private function check(User $user, string $otp): array
    {
        $result = DB::transaction(function () use ($user, $otp): array {
            $row = PasswordOtp::query()->where('user_id', $user->id)->lockForUpdate()->first();

            $reason = match (true) {
                ! $row => 'none',
                $row->used_at !== null => 'used',
                $row->expires_at->isPast() => 'expired',
                $row->attempts >= self::MAX_ATTEMPTS => 'locked',
                default => null,
            };

            if ($reason !== null) {
                // Samakan waktu respons dengan pengecekan hash.
                Hash::check($otp, AuthService::DUMMY_PASSWORD_HASH);

                return ['token' => null, 'reason' => $reason, 'remaining' => 0];
            }

            if (! Hash::check($otp, $row->otp_hash)) {
                $row->increment('attempts');

                return ['token' => null, 'reason' => 'wrong', 'remaining' => max(0, self::MAX_ATTEMPTS - $row->attempts)];
            }

            $token = Str::random(64);
            $row->update([
                'used_at' => now(),
                'reset_token_hash' => hash('sha256', $token),
                'reset_expires_at' => now()->addMinutes(self::RESET_MINUTES),
            ]);

            return ['token' => $token, 'reason' => null, 'remaining' => 0];
        });

        if ($result['reason'] === 'wrong') {
            Log::channel('security')->info('password_otp.wrong', ['user_id' => $user->id, 'remaining' => $result['remaining']]);
        }

        return $result;
    }

    /** Akun pemilik token reset yang masih berlaku, atau null. */
    public function userForResetToken(string $email, string $token): ?User
    {
        $user = $this->findUser($email);

        return $user && $this->userHasResetToken($user, $token) ? $user : null;
    }

    /** Apakah token reset itu milik `$user` dan masih berlaku. */
    public function userHasResetToken(User $user, string $token): bool
    {
        $row = PasswordOtp::query()->where('user_id', $user->id)->first();

        return (bool) ($row && $row->reset_token_hash && $row->reset_expires_at?->isFuture()
            && hash_equals($row->reset_token_hash, hash('sha256', $token)));
    }

    /**
     * Simpan password baru: token reset hangus, semua sesi dicabut, kunci salah password dibuka, dan
     * kewajiban ganti password (setelah reset staf) dianggap terpenuhi.
     */
    public function resetPassword(User $user, string $password, ?string $ip = null): void
    {
        DB::transaction(function () use ($user, $password): void {
            PasswordOtp::query()->where('user_id', $user->id)->delete();
            $user->forceFill(['password' => $password, 'must_change_password' => false, 'password_changed_at' => now()])->save();
            $user->tokens()->delete();
        });

        $this->lockout->clear($user->loginId());
        Log::channel('security')->info('password.reset_by_otp', ['user_id' => $user->id, 'ip' => $ip]);
    }

    /**
     * Simpan password baru tanpa password lama untuk pengguna yang sedang login (T42): seperti ganti password
     * biasa (`password_changed_at` terisi, kewajiban ganti terpenuhi), sesi saat ini tetap hidup dan sesi lain
     * dicabut. Aksinya masuk ke riwayat akun.
     */
    public function resetPasswordKeepingSession(User $user, string $password, ?string $ip = null): void
    {
        DB::transaction(function () use ($user, $password): void {
            PasswordOtp::query()->where('user_id', $user->id)->delete();
            $this->auth->setPassword($user, $password, $user);
            $this->accounts->record($user, $user, 'password_changed_via_otp');
        });

        $this->lockout->clear($user->loginId());
        Log::channel('security')->info('password.reset_by_otp', ['user_id' => $user->id, 'ip' => $ip, 'session_kept' => true]);
    }

    /** Akun aktif (bukan nonaktif) dengan email itu. */
    private function findUser(string $email): ?User
    {
        return User::query()->where('email', mb_strtolower(trim($email)))->first();
    }
}
