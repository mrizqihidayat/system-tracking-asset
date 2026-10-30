<?php

namespace App\Http\Controllers;

use App\Http\Resources\AssetEventResource;
use App\Models\Asset;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class AssetEventController extends Controller
{
    public const DEFAULT_PER_PAGE = 20;

    /**
     * Riwayat aset, terbaru dulu (dipaginasi; riwayat terus bertambah).
     */
    public function index(Request $request, Asset $asset): AnonymousResourceCollection
    {
        $validated = $request->validate([
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $events = $asset->events()
            ->with(['fromRoom.building', 'toRoom.building', 'recorder'])
            ->orderByDesc('event_date')
            ->orderByDesc('id')
            ->paginate($validated['per_page'] ?? self::DEFAULT_PER_PAGE)
            ->withQueryString();

        return AssetEventResource::collection($events);
    }
}
