<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\CachesMasterList;
use App\Http\Requests\StoreRoomRequest;
use App\Http\Requests\UpdateRoomRequest;
use App\Http\Resources\RoomResource;
use App\Models\Building;
use App\Models\Room;
use App\Services\MasterDataService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RoomController extends Controller
{
    use CachesMasterList;

    public function __construct(private MasterDataService $master) {}

    /**
     * Semua ruangan (untuk combobox), urut gedung lalu ruangan; filter building_id & search.
     */
    public function index(Request $request): JsonResponse
    {
        $request->validate(['building_id' => ['nullable', 'integer']]);
        $buildingId = $request->integer('building_id');

        return $this->masterList($request, "rooms:{$buildingId}", fn () => RoomResource::collection(
            $this->applySearch(Room::query(), $request->query('search'), 'rooms.name')
                ->select('rooms.*')
                ->join('buildings', 'buildings.id', '=', 'rooms.building_id')
                ->when($buildingId, fn ($query) => $query->where('rooms.building_id', $buildingId))
                ->with('building')
                ->orderBy('buildings.name')
                ->orderBy('rooms.name')
                ->get()
        )->resolve($request));
    }

    public function indexForBuilding(Request $request, Building $building): JsonResponse
    {
        return $this->masterList($request, "building-rooms:{$building->id}", fn () => RoomResource::collection(
            $this->applySearch($building->rooms(), $request->query('search'))
                ->orderBy('name')
                ->get()
        )->resolve($request));
    }

    public function store(StoreRoomRequest $request, Building $building): JsonResponse
    {
        $room = $building->rooms()->create($request->validated())->load('building');

        return RoomResource::make($room)->response()->setStatusCode(201);
    }

    /**
     * Nama & tanda gudang; slug tetap (URL frontend stabil).
     */
    public function update(UpdateRoomRequest $request, Room $room): RoomResource
    {
        $room->update($request->validated());

        return RoomResource::make($room->load('building'));
    }

    /**
     * Pemakaian sebelum hapus (popup Data Master): rincian, total, can_delete.
     */
    public function usage(Room $room): JsonResponse
    {
        return response()->json(['data' => $this->master->usage($room)]);
    }

    public function destroy(Room $room): JsonResponse
    {
        $name = $room->name;
        $this->master->deleteRoom($room);

        return response()->json(['message' => "Ruangan {$name} dihapus."]);
    }
}
