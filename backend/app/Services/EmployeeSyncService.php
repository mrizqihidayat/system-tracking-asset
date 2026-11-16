<?php

namespace App\Services;

use App\Models\Department;
use App\Models\Employee;
use App\Models\EmployeeSyncRun;
use App\Support\AppCache;
use Illuminate\Contracts\Cache\Lock;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

/**
 * Sinkronisasi salinan lokal pegawai & departemen dari API pegawai (T34, `employees:sync`).
 * - Upsert per nik; pegawai yang hilang dari API → is_active = false (tidak dihapus, riwayat tetap utuh).
 * - API gagal → data lama dipakai; API mengembalikan daftar kosong → sinkronisasi dibatalkan (tidak menonaktifkan semua).
 * - Departemen dibentuk dari nilai unik `departemen` (kecuali "-"), kuncinya kode; nama lengkap tidak ditimpa.
 * - Hanya kolom yang dibutuhkan yang disimpan; tglLahir, agama, jenkel, phone, tglMPP, tglPensiun diabaikan.
 */
class EmployeeSyncService
{
    private const CHUNK = 500;

    public function __construct(private EmployeeApiClient $api) {}

    /**
     * Jalankan satu sinkronisasi dan catat hasilnya di employee_sync_runs.
     *
     * @throws RuntimeException jika sinkronisasi lain sedang berjalan
     */
    public function run(): EmployeeSyncRun
    {
        $lock = Cache::lock('employees-sync', 600);

        if (! $lock->get()) {
            throw new RuntimeException('Sinkronisasi pegawai sedang berjalan.');
        }

        $startedAt = now();

        try {
            $rows = $this->api->fetchAll();
        } catch (Throwable $e) {
            return $this->finish($lock, $startedAt, EmployeeSyncRun::FAILED, [], $e->getMessage());
        }

        $employees = $this->normalize($rows, $skipped);

        if ($employees === []) {
            return $this->finish($lock, $startedAt, EmployeeSyncRun::CANCELLED, ['skipped' => $skipped], 'API mengembalikan daftar pegawai kosong; data lama dipertahankan.');
        }

        try {
            $counts = DB::transaction(fn () => $this->apply($employees, $startedAt));
        } catch (Throwable $e) {
            return $this->finish($lock, $startedAt, EmployeeSyncRun::FAILED, ['skipped' => $skipped], 'Gagal menyimpan: '.mb_substr($e->getMessage(), 0, 200));
        }

        AppCache::flush(AppCache::GROUP_MASTER, AppCache::GROUP_OPS);

        return $this->finish($lock, $startedAt, EmployeeSyncRun::SUCCESS, $counts + ['skipped' => $skipped]);
    }

    /**
     * Baris valid per nik (nik & nama wajib; nik kembar → yang terakhir), hanya kolom yang disimpan.
     *
     * @param  list<array<string, mixed>>  $rows
     * @return array<string, array<string, string|null>>
     */
    private function normalize(array $rows, ?int &$skipped): array
    {
        $skipped = 0;
        $employees = [];

        foreach ($rows as $row) {
            $nik = $this->text($row['nik'] ?? null, 30);
            $name = $this->text($row['nama'] ?? null, 150);

            if ($nik === null || $name === null) {
                $skipped++;

                continue;
            }

            $employees[$nik] = [
                'nik' => $nik,
                'name' => $name,
                'group_name' => $this->text($row['grup'] ?? null, 100),
                'division' => $this->text($row['divisi'] ?? null, 100),
                'department_code' => $this->text($row['departemen'] ?? null, 100),
                'section' => $this->text($row['seksi'] ?? null, 100),
                'position' => $this->text($row['jabatan'] ?? null, 150),
                'email' => $this->text($row['email'] ?? null, 150),
            ];
        }

        return $employees;
    }

    /** Teks rapi; kosong atau "-" menjadi null. NIK dari API berupa angka → string. */
    private function text(mixed $value, int $max): ?string
    {
        if (! is_string($value) && ! is_int($value) && ! is_float($value)) {
            return null;
        }

        $text = trim((string) $value);

        return $text === '' || $text === '-' ? null : mb_substr($text, 0, $max);
    }

    /**
     * @param  array<string, array<string, string|null>>  $employees
     * @return array{total: int, created: int, updated: int, deactivated: int}
     */
    private function apply(array $employees, \DateTimeInterface $now): array
    {
        $existing = Employee::query()->pluck('is_active', 'nik');
        $created = count(array_diff_key($employees, $existing->all()));

        foreach (array_chunk(array_values($employees), self::CHUNK) as $chunk) {
            Employee::query()->upsert(
                array_map(fn (array $e) => $e + ['is_active' => true, 'synced_at' => $now, 'created_at' => $now, 'updated_at' => $now], $chunk),
                ['nik'],
                ['name', 'group_name', 'division', 'department_code', 'section', 'position', 'email', 'is_active', 'synced_at', 'updated_at'],
            );
        }

        $gone = $existing->filter(fn (bool $active, string $nik) => $active && ! isset($employees[$nik]))->keys()->all();

        foreach (array_chunk($gone, self::CHUNK) as $chunk) {
            Employee::query()->whereIn('nik', $chunk)->update(['is_active' => false, 'updated_at' => $now]);
        }

        $this->syncDepartments(array_values(array_unique(array_filter(array_column($employees, 'department_code')))));

        return ['total' => count($employees), 'created' => $created, 'updated' => count($employees) - $created, 'deactivated' => count($gone)];
    }

    /**
     * Departemen = nilai unik kolom departemen. Kode jadi kunci; nama lengkap yang diubah admin tidak ditimpa.
     * Departemen yang tidak ada di API (dan bukan SHD/internal) menjadi tidak aktif, tetap tersimpan.
     *
     * @param  list<string>  $codes
     */
    private function syncDepartments(array $codes): void
    {
        $shd = (string) config('employees.shd_department');
        $byCode = Department::query()->whereNotNull('code')->get()->keyBy('code');

        foreach ($codes as $code) {
            $internal = strcasecmp($code, $shd) === 0;
            $department = $byCode->get($code);

            if ($department === null) {
                // Nama harus unik: bila sudah dipakai departemen lain (hasil ubah nama), tambahkan kode agar tetap unik.
                $name = Department::query()->where('name', $code)->exists() ? "{$code} (kode {$code})" : $code;
                Department::create(['code' => $code, 'name' => $name, 'is_internal' => $internal, 'is_active' => true]);

                continue;
            }

            if (! $department->is_active || $department->is_internal !== $internal) {
                $department->update(['is_active' => true, 'is_internal' => $internal]);
            }
        }

        Department::query()
            ->where('is_active', true)
            ->where('is_internal', false)
            ->whereNotIn('code', $codes)
            ->get()
            ->each(fn (Department $department) => $department->update(['is_active' => false]));
    }

    /**
     * @param  array<string, int>  $counts
     */
    private function finish(Lock $lock, \DateTimeInterface $startedAt, string $status, array $counts, ?string $message = null): EmployeeSyncRun
    {
        $run = EmployeeSyncRun::create([
            'status' => $status,
            'started_at' => $startedAt,
            'finished_at' => now(),
            'message' => $message,
        ] + $counts);

        $lock->release();

        // Hanya jumlah dan pesan; API key dan data pegawai tidak pernah dicatat.
        Log::log($status === EmployeeSyncRun::SUCCESS ? 'info' : 'warning', 'employees.sync', ['status' => $status, 'message' => $message] + $counts);

        return $run;
    }
}
