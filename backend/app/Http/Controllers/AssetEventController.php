<?php

namespace App\Http\Controllers;

use App\Http\Resources\AssetEventResource;
use App\Models\Asset;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class AssetEventController extends Controller
{
    /**
     * Riwayat aset, terbaru dulu.
     */
    public function index(Asset $asset): AnonymousResourceCollection
    {
        $events = $asset->events()
            ->with(['fromRoom.building', 'toRoom.building', 'recorder'])
            ->orderByDesc('event_date')
            ->orderByDesc('id')
            ->get();

        return AssetEventResource::collection($events);
    }
}
