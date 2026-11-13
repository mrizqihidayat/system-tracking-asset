<?php

namespace App\Http\Resources;

use App\Services\EmployeeDirectory\Employee;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Saran pegawai: hanya nama, departemen (aturan fallback), dan jabatan (SPEC §15).
 * NIK tidak dikirim karena NIK adalah password awal akun pegawai SHD (T33 R1).
 *
 * @property Employee $resource
 */
class EmployeeResource extends JsonResource
{
    /**
     * @return array{name: string, department: string, position: string}
     */
    public function toArray(Request $request): array
    {
        return [
            'name' => $this->resource->name,
            'department' => $this->resource->department,
            'position' => $this->resource->position,
        ];
    }
}
