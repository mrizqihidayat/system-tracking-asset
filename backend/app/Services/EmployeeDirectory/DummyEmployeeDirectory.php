<?php

namespace App\Services\EmployeeDirectory;

/**
 * Data pegawai fiktif untuk demo (database/data/employees.json), bentuknya sama dengan API asli.
 */
class DummyEmployeeDirectory implements EmployeeDirectory
{
    /** @var list<Employee>|null */
    private ?array $employees = null;

    public function __construct(private string $path) {}

    public function search(string $query, int $limit = 10): array
    {
        $query = mb_strtolower(trim($query));

        $matches = array_filter(
            $this->all(),
            fn (Employee $e): bool => str_starts_with($e->nik, $query) || str_contains(mb_strtolower($e->name), $query),
        );
        usort($matches, fn (Employee $a, Employee $b): int => strcmp($a->name, $b->name));

        return array_slice($matches, 0, $limit);
    }

    public function findByNik(string $nik): ?Employee
    {
        foreach ($this->all() as $employee) {
            if ($employee->nik === $nik) {
                return $employee;
            }
        }

        return null;
    }

    /** @return list<Employee> */
    private function all(): array
    {
        if ($this->employees !== null) {
            return $this->employees;
        }

        $json = is_file($this->path) ? file_get_contents($this->path) : false;
        $rows = $json === false ? null : json_decode($json, true);
        if (! is_array($rows)) {
            throw new EmployeeDirectoryUnavailable('File data pegawai dummy tidak terbaca.');
        }

        return $this->employees = array_values(array_map(fn (array $row) => Employee::fromApi($row), $rows));
    }
}
