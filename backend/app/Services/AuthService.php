<?php

namespace App\Services;

use App\Exceptions\LoginRejected;
use App\Models\User;
use App\Services\EmployeeDirectory\Employee;
use App\Services\EmployeeDirectory\EmployeeDirectory;
use App\Services\EmployeeDirectory\EmployeeDirectoryUnavailable;
use Closure;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Laravel\Sanctum\PersonalAccessToken;

/**
 * Login & password akun (T33, SPEC §15).
 * - Teks login berupa angka saja = NIK (akun pegawai SHD, source api); selain itu username (akun manual).
 * - Akun api dibuat otomatis saat login pertama. Selama must_change_password (login pertama / setelah reset),
 *   password yang diterima adalah NIK itu sendiri, lalu wajib ganti password (T33 R1).
 * - Status akun (nonaktif, bukan SHD) baru dicek setelah password benar agar tidak membocorkan data.
 */
class AuthService
{
    /**
     * Hash bcrypt dari teks acak yang tidak disimpan, cost sama dengan BCRYPT_ROUNDS production. Dicek saat
     * akun tidak ada / password tidak memakai hash agar waktu respons sama dengan password salah.
     */
    public const DUMMY_PASSWORD_HASH = '$2y$12$/EiSREyNv2h5l53DfYHk.Oh2/aftrBei6rhmUff4VYP.xjypXGnRm';

    public const MSG_WRONG = 'NIK/Username atau password salah';

    public const MSG_NOT_SHD = 'Hanya pegawai Departemen SHD';

    public const MSG_INACTIVE = 'Akun dinonaktifkan. Hubungi staf SHD.';

    public const MSG_DIRECTORY_DOWN = 'Data pegawai tidak bisa diakses. Coba lagi nanti.';

    public function __construct(private EmployeeDirectory $directory) {}

    public static function isNik(string $loginId): bool
    {
        return $loginId !== '' && ctype_digit($loginId);
    }

    /**
     * @throws LoginRejected
     */
    public function attempt(string $loginId, string $password): User
    {
        return self::isNik($loginId)
            ? $this->attemptEmployee($loginId, $password)
            : $this->attemptManual($loginId, $password);
    }

    private function attemptManual(string $username, string $password): User
    {
        $user = User::withTrashed()->where('source', User::SOURCE_MANUAL)->where('username', $username)->first();

        if (! Hash::check($password, $user?->password ?? self::DUMMY_PASSWORD_HASH) || ! $user) {
            throw new LoginRejected(self::MSG_WRONG, 401, 'wrong_credentials');
        }
        if ($user->trashed()) {
            throw new LoginRejected(self::MSG_INACTIVE, 403, 'inactive');
        }

        return $user;
    }

    private function attemptEmployee(string $nik, string $password): User
    {
        $user = User::withTrashed()->where('nik', $nik)->first();
        $usesStoredPassword = $user && ! $user->must_change_password;

        try {
            $employee = $this->directory->findByNik($nik);
            $directoryDown = false;
        } catch (EmployeeDirectoryUnavailable $e) {
            $employee = null;
            $directoryDown = true;
        }

        if ($usesStoredPassword) {
            $valid = Hash::check($password, $user->password);
        } else {
            // Login pertama / setelah reset: password = NIK; pegawai & departemen SHD tetap dicek ke API.
            if ($directoryDown) {
                throw new LoginRejected(self::MSG_DIRECTORY_DOWN, 503, 'directory_unavailable');
            }
            Hash::check($password, self::DUMMY_PASSWORD_HASH);
            $valid = $employee !== null && hash_equals($nik, $password);
        }

        if (! $valid) {
            throw new LoginRejected(self::MSG_WRONG, 401, 'wrong_credentials');
        }
        if ($user?->trashed()) {
            throw new LoginRejected(self::MSG_INACTIVE, 403, 'inactive');
        }

        if ($directoryDown) {
            // Hanya akun aktif yang sudah pernah ganti password yang sampai di sini.
            Log::channel('security')->warning('login.directory_unavailable', ['user_id' => $user->id, 'nik' => $nik]);

            return $user;
        }

        if (! $employee || ! $employee->isInDepartment(config('employees.shd_department'))) {
            throw new LoginRejected(self::MSG_NOT_SHD, 403, 'not_shd');
        }

        return $this->syncEmployeeAccount($user, $employee);
    }

    /** Buat akun api saat login pertama, atau perbarui nama & departemen dari API. */
    private function syncEmployeeAccount(?User $user, Employee $employee): User
    {
        if ($user) {
            $user->update(['name' => mb_substr($employee->name, 0, 100), 'department' => $employee->department]);

            return $user;
        }

        return User::create([
            'nik' => $employee->nik,
            'name' => mb_substr($employee->name, 0, 100),
            'department' => $employee->department,
            'source' => User::SOURCE_API,
            // Tidak bisa dipakai login: selama must_change_password yang dicek tanggal lahir.
            'password' => Str::random(64),
            'must_change_password' => true,
        ]);
    }

    /** Password saat ini untuk Ganti password: NIK selama akun api wajib ganti, selain itu password tersimpan. */
    public function checkCurrentPassword(User $user, string $password): bool
    {
        if ($user->isApi() && $user->must_change_password) {
            return hash_equals((string) $user->nik, $password);
        }

        return Hash::check($password, $user->password);
    }

    /**
     * Aturan password baru: minimal 8, terkonfirmasi, tidak sama dengan NIK/username (tanpa beda huruf besar/kecil).
     *
     * @return list<mixed>
     */
    public function newPasswordRules(?User $user, ?string $username = null): array
    {
        return [
            'required', 'string', 'min:8', 'max:72', 'confirmed',
            function (string $attribute, mixed $value, Closure $fail) use ($user, $username): void {
                $loginId = $user?->loginId() ?? (string) $username;
                if ($loginId !== '' && mb_strtolower((string) $value) === mb_strtolower($loginId)) {
                    $fail($user?->isApi() ? 'Password tidak boleh sama dengan NIK.' : 'Password tidak boleh sama dengan username.');
                }
            },
        ];
    }

    /**
     * Simpan password baru, lepas wajib ganti, dan cabut token akun itu kecuali token yang sedang dipakai
     * oleh pelaku (`$actor`), agar sesi yang mengganti tidak ikut keluar.
     */
    public function setPassword(User $user, string $password, User $actor): void
    {
        $user->forceFill(['password' => $password, 'must_change_password' => false])->save();

        $current = $actor->currentAccessToken();
        $keepId = $current instanceof PersonalAccessToken ? $current->getKey() : null;
        $user->tokens()->when($keepId, fn ($query) => $query->whereKeyNot($keepId))->delete();
    }
}
