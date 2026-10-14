<?php

namespace App\Http\Resources;

use App\Models\Room;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Room
 */
class RoomResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'building_id' => $this->building_id,
            'name' => $this->name,
            'slug' => $this->slug,
            'building' => BuildingResource::make($this->whenLoaded('building')),
            'last_checked_at' => $this->last_checked_at?->toIso8601String(),
        ];
    }
}
