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

/**
 * Kelengkapan ruangan (SPEC §8): standar per ruangan (diisi manual), checklist, simpan hasil cek, isi dari stok.
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
     * Ubah definisi standar saja; jumlah Ada baris Manual tetap (kosong jika cara hitung berubah).
     */
    public function update(StoreRoomStandardRequest $request, RoomStandard $roomStandard): RoomStandardResource
    {
        $data = [
            'asset_type_id' => null,
            'consumable_id' => null,
            'fulfillment_note' => null,
            'catalog_id' => null,
            ...$request->standardData(),
        ];

        if ($data['count_method'] !== $roomStandard->count_method->value) {
            $data['actual_quantity'] = null;
        }

        $roomStandard->update($data);

        return RoomStandardResource::make($this->prepare($roomStandard));
    }

    public function destroy(RoomStandard $roomStandard): JsonResponse
    {
        $roomStandard->delete();

        return response()->json(['message' => "Standar {$roomStandard->item_name} dihapus."]);
    }

    public function check(SaveRoomCheckRequest $request, Room $room): JsonResponse
    {
        $room = $this->readiness->saveCheck($room, $request->validated('items'), $request->user());

        return $this->checklistResponse($room);
    }

    public function fillFromStock(RoomStandard $roomStandard): JsonResponse
    {
        $result = $this->readiness->fillFromStock($roomStandard);

        return response()->json([
            'message' => $result['remaining_shortage'] > 0
                ? "Diisi {$result['filled']}, masih kurang {$result['remaining_shortage']}."
                : "Diisi {$result['filled']}, sudah sesuai standar.",
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
            'data' => RoomStandardResource::collection($standards),
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
