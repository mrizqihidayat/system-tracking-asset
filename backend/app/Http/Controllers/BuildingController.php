<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreBuildingRequest;
use App\Http\Resources\BuildingResource;
use App\Models\Building;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class BuildingController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $buildings = $this->applySearch(Building::query(), $request->query('search'))
            ->withCount('rooms')
            ->orderBy('name')
            ->get();

        return BuildingResource::collection($buildings);
    }

    public function store(StoreBuildingRequest $request): JsonResponse
    {
        $building = Building::create($request->validated())->loadCount('rooms');

        return BuildingResource::make($building)->response()->setStatusCode(201);
    }
}
