<?php

namespace App\Services\EmployeeDirectory;

/**
 * Data pegawai yang dipakai aplikasi: nik, nama, departemen, jabatan (SPEC §15).
 * Field lain dari API (tglLahir, agama, phone, email, ...) dibuang di fromApi() dan tidak pernah disimpan.
 * NIK hanya dipakai di backend (login & akun); saran pegawai tidak mengirim NIK (EmployeeResource).
 */
final class Employee
{
    private function __construct(
        public readonly string $nik,
        public readonly string $name,
        public readonly string $department,
        public readonly string $position,
    ) {}

    /**
     * @param  array<string, mixed>  $row  satu baris dari API pegawai
     */
    public static function fromApi(array $row): self
    {
        return new self(
            nik: trim((string) ($row['nik'] ?? '')),
            name: trim((string) ($row['nama'] ?? '')),
            department: self::resolveDepartment($row),
            position: trim((string) ($row['jabatan'] ?? '')),
        );
    }

    /**
     * Departemen untuk isi otomatis: departemen, jika "-" divisi, jika "-" grup.
     *
     * @param  array<string, mixed>  $row
     */
    private static function resolveDepartment(array $row): string
    {
        foreach (['departemen', 'divisi', 'grup'] as $key) {
            $value = trim((string) ($row[$key] ?? ''));
            if ($value !== '' && $value !== '-') {
                return $value;
            }
        }

        return '-';
    }

    public function isInDepartment(string $department): bool
    {
        return strcasecmp($this->department, $department) === 0;
    }
}
