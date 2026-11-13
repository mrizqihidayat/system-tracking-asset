<?php

namespace App\Http\Resources;

use App\Services\EmployeeDirectory\Employee;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Saran pegawai: hanya nik, nama, departemen (aturan fallback), jabatan (SPEC §15).
 *
 * @property Employee $resource
 */
class EmployeeResource extends JsonResource
{
    /**
     * @return array{nik: string, name: string, department: string, position: string}
     */
    public function toArray(Request $request): array
    {
        return $this->resource->toArray();
    }
}
