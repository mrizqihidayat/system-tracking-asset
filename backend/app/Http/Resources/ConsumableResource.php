<?php

namespace App\Http\Resources;

use App\Models\Consumable;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Consumable
 */
class ConsumableResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'category' => $this->category,
            'unit' => $this->unit,
            'quantity' => $this->quantity,
            'min_quantity' => $this->min_quantity,
            'is_stored' => $this->is_stored,
            'status' => $this->status(),
            'provider_department' => DepartmentResource::make($this->whenLoaded('providerDepartment')),
            'catalog' => $this->whenLoaded('catalog', fn () => $this->catalog ? [
                'id' => $this->catalog->id,
                'item_name' => $this->catalog->item_name,
            ] : null),
            'room' => RoomResource::make($this->whenLoaded('room')),
            'note' => $this->note,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
