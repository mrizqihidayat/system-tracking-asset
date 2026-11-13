<?php

namespace App\Services\EmployeeDirectory;

/**
 * Sumber data pegawai INALUM. Driver dipilih lewat EMPLOYEE_DIRECTORY_DRIVER (dummy | http).
 */
interface EmployeeDirectory
{
    /**
     * Cari pegawai berdasarkan nama atau awalan NIK (semua departemen).
     *
     * @return list<Employee>
     *
     * @throws EmployeeDirectoryUnavailable
     */
    public function search(string $query, int $limit = 10): array;

    /**
     * @throws EmployeeDirectoryUnavailable
     */
    public function findByNik(string $nik): ?Employee;
}
