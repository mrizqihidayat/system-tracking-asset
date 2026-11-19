<?php

namespace App\Services;

use App\Models\AccountEvent;
use App\Models\Employee;
use App\Models\User;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

/**
 * Daftar akses aplikasi dan riwayat aksi akun (T41, SPEC §15).
 * Daftar = semua akun di `users` (termasuk nonaktif) + pegawai SHD aktif di salinan pegawai yang belum punya akun
 * (status "Belum pernah masuk"). Jumlahnya kecil (satu departemen), jadi digabung, disaring, dan dihalaman di PHP.
 */
class AccountService
{
    public const STATUS_ACTIVE = 'aktif';

    public const STATUS_PENDING = 'belum';

    public const STATUS_INACTIVE = 'nonaktif';

    private const STATUS_ORDER = [self::STATUS_ACTIVE => 0, self::STATUS_PENDING => 1, self::STATUS_INACTIVE => 2];

    /**
     * @param  array{search?: string|null, type?: string|null, status?: string|null}  $filters
     * @return LengthAwarePaginator<int, array<string, mixed>>
     */
    public function paginate(array $filters, int $perPage, int $page): LengthAwarePaginator
    {
        $rows = $this->filter($this->rows(), $filters)
            ->sort(fn (array $a, array $b) => [self::STATUS_ORDER[$a['status']], mb_strtolower($a['name']), (string) ($a['nik'] ?? $a['username'])]
                <=> [self::STATUS_ORDER[$b['status']], mb_strtolower($b['name']), (string) ($b['nik'] ?? $b['username'])])
            ->values();

        return new LengthAwarePaginator($rows->forPage($page, $perPage)->values(), $rows->count(), $perPage, $page, ['path' => LengthAwarePaginator::resolveCurrentPath()]);
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    public function rows(): Collection
    {
        $shd = (string) config('employees.shd_department');
        $synced = Employee::query()->exists();
        $users = User::withTrashed()->get();
        $niks = $users->pluck('nik')->filter()->map(fn ($nik) => (string) $nik)->values();
        $employees = Employee::query()->whereIn('nik', $niks)->get()->keyBy('nik');

        $accounts = $users->map(fn (User $user) => $this->accountRow($user, $employees->get($user->nik), $synced, $shd));

        $pending = Employee::query()->active()
            ->whereRaw('LOWER(department_code) = ?', [mb_strtolower($shd)])
            ->whereNotIn('nik', $niks)
            ->get()
            ->map(fn (Employee $employee) => $this->pendingRow($employee));

        return $accounts->concat($pending)->values();
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $rows
     * @param  array{search?: string|null, type?: string|null, status?: string|null}  $filters
     * @return Collection<int, array<string, mixed>>
     */
    private function filter(Collection $rows, array $filters): Collection
    {
        $search = mb_strtolower(trim((string) ($filters['search'] ?? '')));

        // Cari nama atau username saja: NIK tidak dicari dan tidak dikirim (T45, temuan UAT K4).
        return $rows
            ->when($search !== '', fn (Collection $rows) => $rows->filter(
                fn (array $row) => str_contains(mb_strtolower($row['name'].' '.$row['username']), $search),
            ))
            ->when($filters['type'] ?? null, fn (Collection $rows, string $type) => $rows->where('source', $type === 'pegawai' ? User::SOURCE_API : User::SOURCE_MANUAL))
            ->when($filters['status'] ?? null, fn (Collection $rows, string $status) => $rows->where('status', $status));
    }

    /**
     * Akun pegawai dianggap nonaktif di data pegawai bila salinan pegawai sudah ada tetapi orangnya tidak ada lagi,
     * tidak aktif, atau bukan SHD (sama dengan aturan login di AuthService).
     *
     * @return array<string, mixed>
     */
    private function accountRow(User $user, ?Employee $employee, bool $synced, string $shd): array
    {
        $employeeInactive = $user->isApi() && $synced && ! $employee?->isActiveIn($shd);

        return [
            'key' => 'akun-'.$user->id,
            'id' => $user->id,
            'name' => $user->name,
            'nik' => $user->nik,
            'username' => $user->username,
            'email' => $user->email,
            'source' => $user->source,
            // Akun pegawai: jabatan + departemen dari salinan pegawai. Akun eksternal: keterangan + unit asal.
            'position' => $employee?->position,
            'department' => $user->isApi() ? ($employee?->department_code ?? $user->department) : $user->department,
            'note' => $user->note,
            'status' => $user->trashed() || $employeeInactive ? self::STATUS_INACTIVE : self::STATUS_ACTIVE,
            'is_deactivated' => $user->trashed(),
            'employee_inactive' => $employeeInactive,
            // Akun eksternal memakai password pilihan staf, jadi tidak ada penanda "bawaan".
            'password_default' => $user->isApi() ? $user->password_changed_at === null : null,
            'last_login_at' => $user->last_login_at,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function pendingRow(Employee $employee): array
    {
        return [
            'key' => 'pegawai-'.$employee->id,
            'id' => null,
            'name' => $employee->name,
            'nik' => $employee->nik,
            'username' => null,
            'email' => null,
            'source' => User::SOURCE_API,
            'position' => $employee->position,
            'department' => $employee->department_code,
            'note' => null,
            'status' => self::STATUS_PENDING,
            'is_deactivated' => false,
            'employee_inactive' => false,
            'password_default' => true,
            'last_login_at' => null,
        ];
    }

    /** Catat aksi pada akun (siapa, kapan, apa). */
    public function record(User $account, User $actor, string $event): void
    {
        AccountEvent::query()->create(['user_id' => $account->id, 'actor_id' => $actor->id, 'event' => $event, 'created_at' => now()]);
    }

    /**
     * Aktivitas terakhir akun, terbaru dulu.
     *
     * @return Collection<int, AccountEvent>
     */
    public function recentEvents(User $account, int $limit = 10): Collection
    {
        return AccountEvent::query()->with('actor')->where('user_id', $account->id)
            ->orderByDesc('created_at')->orderByDesc('id')->limit($limit)->get();
    }
}
