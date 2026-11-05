<?php

namespace App\Http\Controllers;

use App\Enums\CountMethod;
use App\Http\Requests\SaveRoomCheckRequest;
use App\Http\Requests\StoreRoomStandardRequest;
use App\Http\Resources\RoomResource;
use App\Http\Resources\RoomStandardResource;
use App\Models\Room;
use App\Models\RoomStandard;
use App\Services\RoomReadinessService;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Kelengkapan ruangan (SPEC §8): aset di ruangan, standar per jenis/barang, simpan hasil cek, ambil dari stok.
 */
class RoomStandardController extends Controller
{
    public function __construct(private RoomReadinessService $readiness) {}

    public function checklist(Room $room): JsonResponse
    {
        return $this->checklistResponse($room);
    }

    public function store(StoreRoomStandardRequest $request, Room $room): JsonResponse
    {
        $standard = $room->standards()->create($request->standardData());

        return RoomStandardResource::make($this->prepare($standard))->response()->setStatusCode(201);
    }

    /**
     * Ubah definisi standar saja; jumlah Ada tetap selama barangnya sama.
     */
    public function update(StoreRoomStandardRequest $request, RoomStandard $roomStandard): RoomStandardResource
    {
        $data = $request->standardData();

        if ((int) $data['consumable_id'] !== (int) $roomStandard->consumable_id) {
            $data['actual_quantity'] = null;
        }

        $roomStandard->update($data);

        return RoomStandardResource::make($this->prepare($roomStandard));
    }

    public function destroy(RoomStandard $roomStandard): JsonResponse
    {
        $name = $roomStandard->item_name;
        $roomStandard->delete();

        return response()->json(['message' => "Standar {$name} dihapus."]);
    }

    public function check(SaveRoomCheckRequest $request, Room $room): JsonResponse
    {
        $room = $this->readiness->saveCheck($room, $request->validated('items'), $request->user());

        return $this->checklistResponse($room);
    }

    public function fillFromStock(Request $request, RoomStandard $roomStandard): JsonResponse
    {
        $result = $this->readiness->fillFromStock($roomStandard, $request->user());

        return response()->json([
            'message' => $result['remaining_shortage'] > 0
                ? "Diambil {$result['filled']}, masih kurang {$result['remaining_shortage']}."
                : "Diambil {$result['filled']}, sudah sesuai standar.",
            'filled' => $result['filled'],
            'remaining_shortage' => $result['remaining_shortage'],
            'stock_remaining' => $result['stock_remaining'],
            'data' => RoomStandardResource::make($this->prepare($result['standard'])),
        ]);
    }

    private function checklistResponse(Room $room): JsonResponse
    {
        $standards = $this->readiness->checklist($room);

        return response()->json([
            'data' => [
                'assets' => $this->readiness->assetGroups($room, $standards),
                'consumables' => RoomStandardResource::collection(
                    $standards->filter(fn (RoomStandard $standard) => $standard->count_method === CountMethod::Manual)->values(),
                ),
                'stored_stock' => $this->readiness->storedStock($room),
            ],
            'meta' => [
                'room' => RoomResource::make($room->load(['building', 'lastCheckedBy'])),
                'last_checked_by' => $room->lastCheckedBy?->name,
                'summary' => $this->readiness->summarize($standards),
            ],
        ]);
    }

    private function prepare(RoomStandard $standard): RoomStandard
    {
        $standard->load(RoomReadinessService::RELATIONS);

        if ($standard->count_method === CountMethod::Qr) {
            $this->readiness->fillQrCounts(new Collection([$standard]));
        }

        return $standard;
    }
}
