<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreBuildingRequest;
use App\Http\Requests\UpdateBuildingRequest;
use App\Http\Resources\BuildingResource;
use App\Models\Building;
use App\Services\MasterDataService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class BuildingController extends Controller
{
    public function __construct(private MasterDataService $master) {}

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

    public function update(UpdateBuildingRequest $request, Building $building): BuildingResource
    {
        $building->update($request->validated());

        return BuildingResource::make($building->loadCount('rooms'));
    }

    public function destroy(Building $building): JsonResponse
    {
        $name = $building->name;
        $this->master->deleteBuilding($building);

        return response()->json(['message' => "Gedung {$name} dihapus."]);
    }
}
