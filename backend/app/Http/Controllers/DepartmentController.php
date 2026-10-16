<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreDepartmentRequest;
use App\Http\Requests\UpdateDepartmentRequest;
use App\Http\Resources\DepartmentResource;
use App\Models\Department;
use App\Services\MasterDataService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\ValidationException;

class DepartmentController extends Controller
{
    public function __construct(private MasterDataService $master) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $departments = $this->applySearch(Department::query(), $request->query('search'))
            ->orderByDesc('is_internal')
            ->orderBy('name')
            ->get();

        return DepartmentResource::collection($departments);
    }

    /**
     * Unit baru dari ketikan nama; selalu bukan SHD.
     */
    public function store(StoreDepartmentRequest $request): JsonResponse
    {
        $department = Department::create([
            'name' => $request->validated('name'),
            'is_internal' => false,
        ]);

        return DepartmentResource::make($department)->response()->setStatusCode(201);
    }

    /**
     * Unit SHD (internal) tidak boleh diubah namanya.
     */
    public function update(UpdateDepartmentRequest $request, Department $department): DepartmentResource
    {
        if ($department->is_internal) {
            throw ValidationException::withMessages(['name' => 'Departemen SHD tidak dapat diubah.']);
        }

        $department->update($request->validated());

        return DepartmentResource::make($department);
    }

    public function destroy(Department $department): JsonResponse
    {
        $name = $department->name;
        $this->master->deleteDepartment($department);

        return response()->json(['message' => "Departemen {$name} dihapus."]);
    }
}
