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
            'consumable_category_id' => $this->consumable_category_id,
            'category' => $this->consumableCategory?->name,
            'unit' => $this->unit,
            'quantity' => $this->quantity,
            'min_quantity' => $this->min_quantity,
            'status' => $this->status(),
            'provider_department' => DepartmentResource::make($this->whenLoaded('providerDepartment')),
            'room' => RoomResource::make($this->whenLoaded('room')),
            'open_request' => $this->whenLoaded('openRequest', fn () => $this->openRequest ? [
                'id' => $this->openRequest->id,
                'created_at' => $this->openRequest->created_at?->toIso8601String(),
            ] : null),
            'note' => $this->note,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
