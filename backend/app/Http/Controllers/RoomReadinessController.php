<?php

namespace App\Http\Controllers;

use App\Models\Building;
use App\Models\Room;
use App\Services\RoomReadinessService;
use Illuminate\Http\JsonResponse;

/**
 * Ringkasan kesiapan ruangan per gedung: "Kurang N item" / "Lengkap" + jumlah Belum dicek terpisah.
 */
class RoomReadinessController extends Controller
{
    public function __invoke(RoomReadinessService $readiness): JsonResponse
    {
        $data = $readiness->readiness()->map(function (Building $building) use ($readiness) {
            $rooms = $building->rooms->map(fn (Room $room) => [
                'id' => $room->id,
                'name' => $room->name,
                'slug' => $room->slug,
                'last_checked_at' => $room->last_checked_at?->toIso8601String(),
                'last_checked_by' => $room->lastCheckedBy?->name,
                ...$readiness->summarize($room->standards),
            ]);

            return [
                'id' => $building->id,
                'name' => $building->name,
                'slug' => $building->slug,
                'rooms_count' => $rooms->count(),
                'incomplete_rooms_count' => $rooms->where('is_complete', false)->count(),
                'rooms' => $rooms->values(),
            ];
        });

        return response()->json(['data' => $data]);
    }
}
