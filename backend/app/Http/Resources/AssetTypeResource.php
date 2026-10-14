<?php

namespace App\Http\Resources;

use App\Models\AssetType;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin AssetType
 */
class AssetTypeResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'category_id' => $this->category_id,
            'name' => $this->name,
            'code' => $this->code,
            'category' => CategoryResource::make($this->whenLoaded('category')),
        ];
    }
}
