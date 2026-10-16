<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreRoomRequest;
use App\Http\Resources\RoomResource;
use App\Models\Building;
use App\Models\Room;
use App\Http\Requests\UpdateRoomRequest;
use App\Services\MasterDataService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class RoomController extends Controller
{
    public function __construct(private MasterDataService $master) {}

    /**
     * Semua ruangan (untuk combobox), urut gedung lalu ruangan; filter building_id & search.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $request->validate(['building_id' => ['nullable', 'integer']]);

        $rooms = $this->applySearch(Room::query(), $request->query('search'), 'rooms.name')
            ->select('rooms.*')
            ->join('buildings', 'buildings.id', '=', 'rooms.building_id')
            ->when($request->query('building_id'), fn ($query, $buildingId) => $query->where('rooms.building_id', $buildingId))
            ->with('building')
            ->orderBy('buildings.name')
            ->orderBy('rooms.name')
            ->get();

        return RoomResource::collection($rooms);
    }

    public function indexForBuilding(Request $request, Building $building): AnonymousResourceCollection
    {
        $rooms = $this->applySearch($building->rooms(), $request->query('search'))
            ->orderBy('name')
            ->get();

        return RoomResource::collection($rooms);
    }

    public function store(StoreRoomRequest $request, Building $building): JsonResponse
    {
        $room = $building->rooms()->create($request->validated())->load('building');

        return RoomResource::make($room)->response()->setStatusCode(201);
    }

    /**
     * Hanya nama; slug tetap (URL frontend stabil).
     */
    public function update(UpdateRoomRequest $request, Room $room): RoomResource
    {
        $room->update($request->validated());

        return RoomResource::make($room->load('building'));
    }

    public function destroy(Room $room): JsonResponse
    {
        $name = $room->name;
        $this->master->deleteRoom($room);

        return response()->json(['message' => "Ruangan {$name} dihapus."]);
    }
}
