<?php

namespace App\Http\Resources;

use App\Models\Asset;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Baris Daftar Aset & saran aset: hanya kolom tabel (dari scope Asset::forList()). Data lengkap ada
 * di AssetResource lewat GET /assets/{id}.
 *
 * @mixin Asset
 */
class AssetListResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'code' => $this->code,
            'name' => $this->name,
            'category_name' => $this->category_name,
            'room_name' => $this->room_name,
            'building_name' => $this->building_name,
            'source_is_internal' => (bool) $this->source_is_internal,
            'condition' => $this->condition,
            'status' => $this->status,
        ];
    }
}
