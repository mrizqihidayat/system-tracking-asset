<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreDepartmentRequest;
use App\Http\Resources\DepartmentResource;
use App\Models\Department;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class DepartmentController extends Controller
{
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
}
