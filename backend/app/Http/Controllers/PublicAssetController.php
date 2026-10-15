<?php

namespace App\Http\Controllers;

use App\Http\Resources\PublicAssetResource;
use App\Models\Asset;
use Illuminate\Http\JsonResponse;

class PublicAssetController extends Controller
{
    /**
     * Halaman publik dari QR (tanpa login, read-only). Aset tidak ada (termasuk sudah dihapus) → 404.
     */
    public function show(string $token): JsonResponse|PublicAssetResource
    {
        $asset = Asset::query()
            ->where('public_token', $token)
            ->with(['assetType.category', 'sourceDepartment', 'room.building', 'activeLoan.destinationRoom.building'])
            ->first();

        if ($asset === null) {
            return response()->json(['message' => 'Aset tidak ditemukan.'], 404);
        }

        return PublicAssetResource::make($asset);
    }
}
