<?php

namespace App\Http\Resources;

use App\Models\SupplyRequest;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Muat RELATIONS agar is_unresponded & response_due_date (butuh catalog) terisi.
 *
 * @mixin SupplyRequest
 */
class SupplyRequestResource extends JsonResource
{
    public const RELATIONS = ['catalog', 'providerDepartment', 'room.building', 'creator'];

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $unresponded = $this->isUnresponded();

        return [
            'id' => $this->id,
            'request_date' => $this->request_date->toDateString(),
            'item_name' => $this->item_name,
            'quantity' => $this->quantity,
            'purpose' => $this->purpose,
            'status' => $this->status->value,
            'is_unresponded' => $unresponded,
            'display_status' => $unresponded ? 'Belum direspons' : $this->status->value,
            'response_due_date' => $this->responseDueDate()?->toDateString(),
            'catalog' => $this->whenLoaded('catalog', fn () => $this->catalog ? [
                'id' => $this->catalog->id,
                'item_name' => $this->catalog->item_name,
                'request_method' => $this->catalog->request_method,
                'must_return' => $this->catalog->must_return,
                'lead_time_days' => $this->catalog->lead_time_days,
            ] : null),
            'provider_department' => DepartmentResource::make($this->whenLoaded('providerDepartment')),
            'room' => RoomResource::make($this->whenLoaded('room')),
            'received_at' => $this->received_at?->toIso8601String(),
            'returned_at' => $this->returned_at?->toIso8601String(),
            'note' => $this->note,
            'created_by' => $this->whenLoaded('creator', fn () => ['id' => $this->creator->id, 'name' => $this->creator->name]),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
