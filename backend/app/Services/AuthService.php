<?php

namespace App\Services;

use App\Exceptions\LoginRejected;
use App\Models\Employee;
use App\Models\User;
use Closure;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Laravel\Sanctum\PersonalAccessToken;

/**
 * Login & password akun (T33, SPEC §15).
 * - Teks login berupa angka saja = NIK (akun pegawai SHD, source api); selain itu username (akun manual).
 * - Akun api dibuat otomatis saat login pertama dengan password = NIK, dan password tersimpan = NIK
 *   sampai pegawai menggantinya sendiri (opsional, T33 R2). Reset mengembalikan password ke NIK.
 * - Hanya pegawai departemen SHD di direktori pegawai; status akun (nonaktif, bukan SHD) baru dicek
 *   setelah password benar agar tidak membocorkan data.
 */
class AuthService
{
    /**
     * Hash bcrypt dari teks acak yang tidak disimpan, cost sama dengan BCRYPT_ROUNDS production. Dicek saat
     * akun tidak ada agar waktu respons sama dengan password salah.
     */
    public const DUMMY_PASSWORD_HASH = '$2y$12$/EiSREyNv2h5l53DfYHk.Oh2/aftrBei6rhmUff4VYP.xjypXGnRm';

    public const MSG_WRONG = 'NIK/Username atau password salah';

    public const MSG_NOT_SHD = 'Hanya pegawai Departemen SHD';

    public const MSG_INACTIVE = 'Akun dinonaktifkan. Hubungi staf SHD.';

    public const MSG_NO_EMPLOYEE_DATA = 'Data pegawai belum tersedia. Hubungi admin.';

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
        // Salinan lokal pegawai (diisi employees:sync); login tidak memanggil API.
        $employee = Employee::query()->where('nik', $nik)->first();
        $synced = Employee::query()->exists();

        if ($user) {
            $valid = Hash::check($password, $user->password);
        } else {
            // Login pertama: password = NIK; pegawai harus ada di salinan lokal dan berdepartemen SHD.
            if (! $synced) {
                throw new LoginRejected(self::MSG_NO_EMPLOYEE_DATA, 503, 'employees_not_synced');
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

        if (! $synced) {
            // Belum pernah sinkron: akun yang sudah ada tetap bisa masuk, cek SHD dilewati.
            Log::channel('security')->warning('login.employees_not_synced', ['user_id' => $user->id, 'nik' => $nik]);

            return $user;
        }

        if (! $employee || ! $employee->isActiveIn((string) config('employees.shd_department'))) {
            throw new LoginRejected(self::MSG_NOT_SHD, 403, 'not_shd');
        }

        return $this->syncEmployeeAccount($user, $employee);
    }

    /** Buat akun api saat login pertama (password = NIK), atau perbarui nama & departemen dari salinan pegawai. */
    private function syncEmployeeAccount(?User $user, Employee $employee): User
    {
        $name = mb_substr($employee->name, 0, 100);

        if ($user) {
            $user->update(['name' => $name, 'department' => $employee->departmentLabel()]);

            return $user;
        }

        return User::create([
            'nik' => $employee->nik,
            'name' => $name,
            'department' => $employee->departmentLabel(),
            'source' => User::SOURCE_API,
            'password' => $employee->nik,
        ]);
    }

    public function checkCurrentPassword(User $user, string $password): bool
    {
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
     * Simpan password baru dan cabut token akun itu kecuali token yang sedang dipakai oleh pelaku (`$actor`),
     * agar sesi yang mengganti tidak ikut keluar.
     */
    public function setPassword(User $user, string $password, User $actor): void
    {
        $user->forceFill(['password' => $password])->save();

        $current = $actor->currentAccessToken();
        $keepId = $current instanceof PersonalAccessToken ? $current->getKey() : null;
        $user->tokens()->when($keepId, fn ($query) => $query->whereKeyNot($keepId))->delete();
    }

    /** Reset akun pegawai SHD: password kembali ke NIK, semua sesinya dicabut. */
    public function resetEmployeePassword(User $user): void
    {
        $user->forceFill(['password' => (string) $user->nik])->save();
        $user->tokens()->delete();
    }
}
