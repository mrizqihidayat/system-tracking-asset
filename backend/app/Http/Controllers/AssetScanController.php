<?php

namespace App\Http\Controllers;

use App\Http\Resources\AssetResource;
use App\Models\Asset;
use App\Models\AssetCodeHistory;
use Illuminate\Http\JsonResponse;

class AssetScanController extends Controller
{
    private const RELATIONS = [
        'assetType.category',
        'sourceDepartment',
        'room.building',
        'activeLoan.destinationRoom.building',
        'upcomingBooking',
        'latestLoan',
        'codeHistories',
    ];

    /**
     * Scan staf: value = kode aset, kode lama, atau public_token (segmen terakhir URL QR).
     * Urutan: kode → kode lama → token. meta.actions = aksi untuk status (+ batalkan jika Dipinjam).
     */
    public function show(string $value): JsonResponse|AssetResource
    {
        [$asset, $matchedBy] = $this->find(trim($value));

        if ($asset === null) {
            return response()->json(['message' => 'Aset tidak ditemukan.'], 404);
        }

        return AssetResource::make($asset->load(self::RELATIONS))->additional(['meta' => [
            'matched_by' => $matchedBy,
            'actions' => $asset->availableActions(),
        ]]);
    }

    /**
     * @return array{0: ?Asset, 1: ?string}
     */
    private function find(string $value): array
    {
        if ($value === '') {
            return [null, null];
        }

        $upper = mb_strtoupper($value);

        $asset = Asset::query()->whereRaw('upper(code) = ?', [$upper])->first();

        if ($asset !== null) {
            return [$asset, 'code'];
        }

        $history = AssetCodeHistory::query()->whereRaw('upper(code) = ?', [$upper])->first();

        if ($history !== null) {
            return [Asset::query()->find($history->asset_id), 'previous_code'];
        }

        $asset = Asset::query()->where('public_token', $value)->first();

        return [$asset, $asset ? 'token' : null];
    }
}
