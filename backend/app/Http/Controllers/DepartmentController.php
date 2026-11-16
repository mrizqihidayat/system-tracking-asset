<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\CachesMasterList;
use App\Http\Requests\UpdateDepartmentRequest;
use App\Http\Resources\DepartmentResource;
use App\Models\Department;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DepartmentController extends Controller
{
    use CachesMasterList;

    /**
     * Semua departemen (aktif dan tidak aktif) untuk pilihan form dan Data Master; `is_active` menandai yang
     * tidak ada lagi di API pegawai (T34). Departemen dibentuk dari API pegawai, bukan ditambah/dihapus manual.
     */
    public function index(Request $request): JsonResponse
    {
        return $this->masterList($request, 'departments', fn () => DepartmentResource::collection(
            $this->applySearch(Department::query(), $request->query('search'))
                ->orderByDesc('is_internal')
                ->orderBy('name')
                ->get()
        )->resolve($request));
    }

    /**
     * Hanya nama lengkap yang bisa diubah (kode dari API pegawai tetap).
     */
    public function update(UpdateDepartmentRequest $request, Department $department): DepartmentResource
    {
        $department->update($request->validated());

        return DepartmentResource::make($department);
    }
}
