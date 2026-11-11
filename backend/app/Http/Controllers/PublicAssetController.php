<?php

namespace App\Http\Controllers;

use App\Http\Resources\PublicAssetResource;
use App\Models\Asset;
use Illuminate\Http\JsonResponse;

class PublicAssetController extends Controller
{
    /**
     * Halaman publik dari QR (tanpa login, read-only). Aset tidak ada (termasuk sudah dihapus) → 404.
     * Token yang bentuknya pasti salah (mis. berisi karakter NUL) tidak diteruskan ke database.
     */
    public function show(string $token): JsonResponse|PublicAssetResource
    {
        $asset = preg_match('/\A[A-Za-z0-9]{'.Asset::PUBLIC_TOKEN_LENGTH.'}\z/', $token) === 1
            ? Asset::query()
                ->where('public_token', $token)
                ->with(['assetType.category', 'sourceDepartment', 'room.building', 'activeLoan.destinationRoom.building', 'upcomingBooking'])
                ->first()
            : null;

        if ($asset === null) {
            return response()->json(['message' => 'Aset tidak ditemukan.'], 404);
        }

        return PublicAssetResource::make($asset);
    }
}
