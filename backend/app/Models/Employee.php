<?php

namespace App\Models;

use Database\Factories\EmployeeFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Salinan lokal pegawai dari API pegawai (T34, diisi `employees:sync`). Hanya kolom yang dibutuhkan;
 * data pribadi lain tidak disimpan. Pegawai yang hilang dari API dinonaktifkan, tidak dihapus.
 */
#[Fillable(['nik', 'name', 'group_name', 'division', 'department_code', 'section', 'position', 'email', 'is_active', 'synced_at'])]
class Employee extends Model
{
    /** @use HasFactory<EmployeeFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'synced_at' => 'datetime',
        ];
    }

    /**
     * @param  Builder<Employee>  $query
     */
    #[Scope]
    protected function active(Builder $query): void
    {
        $query->where('is_active', true);
    }

    /**
     * Departemen untuk isi otomatis: departemen, jika kosong divisi, jika kosong grup, selain itu "-".
     */
    public function departmentLabel(): string
    {
        foreach ([$this->department_code, $this->division, $this->group_name] as $value) {
            if ($value !== null && $value !== '') {
                return $value;
            }
        }

        return '-';
    }

    /** Pegawai aktif yang departemennya (kode) sama dengan `$code`, tanpa beda huruf besar/kecil. */
    public function isActiveIn(string $code): bool
    {
        return $this->is_active && $this->department_code !== null && strcasecmp($this->department_code, $code) === 0;
    }
}
