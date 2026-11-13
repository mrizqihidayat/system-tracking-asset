<?php

namespace App\Services\EmployeeDirectory;

use Carbon\CarbonImmutable;

/**
 * Data pegawai yang boleh dipakai aplikasi: nik, nama, departemen, jabatan (SPEC §15).
 * Tanggal lahir hanya disimpan privat untuk verifikasi login pertama; tidak ikut
 * json_encode, var_dump, serialize, atau log. Field lain dari API (agama, phone, email, ...) dibuang di fromApi().
 */
final class Employee
{
    private function __construct(
        public readonly string $nik,
        public readonly string $name,
        public readonly string $department,
        public readonly string $position,
        private readonly ?string $birthDate,
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
            birthDate: isset($row['tglLahir']) ? (string) $row['tglLahir'] : null,
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

    /** Cocokkan teks dengan tanggal lahir format DDMMYYYY. */
    public function matchesBirthDate(string $value): bool
    {
        if ($this->birthDate === null) {
            return false;
        }

        try {
            $expected = CarbonImmutable::createFromFormat('!Y-m-d', $this->birthDate)->format('dmY');
        } catch (\Throwable) {
            return false;
        }

        return hash_equals($expected, $value);
    }

    /** @return array{nik: string, name: string, department: string, position: string} */
    public function toArray(): array
    {
        return ['nik' => $this->nik, 'name' => $this->name, 'department' => $this->department, 'position' => $this->position];
    }

    /** @return array{nik: string, name: string, department: string, position: string} */
    public function __debugInfo(): array
    {
        return $this->toArray();
    }

    /** @return array{nik: string, name: string, department: string, position: string} */
    public function __serialize(): array
    {
        return $this->toArray();
    }
}
