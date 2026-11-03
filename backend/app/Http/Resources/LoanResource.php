<?php

namespace App\Http\Resources;

use App\Models\Loan;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Loan
 */
class LoanResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'asset' => $this->whenLoaded('asset', fn () => [
                'id' => $this->asset->id,
                'code' => $this->asset->code,
                'name' => $this->asset->name,
                'status' => $this->asset->status,
                'condition' => $this->asset->condition,
                'room' => $this->asset->relationLoaded('room') ? RoomResource::make($this->asset->room) : null,
            ]),
            'borrower_name' => $this->borrower_name,
            'borrower_unit' => $this->borrower_unit,
            'purpose' => $this->purpose,
            'destination_room' => RoomResource::make($this->whenLoaded('destinationRoom')),
            'destination_note' => $this->destination_note,
            'borrow_date' => $this->borrow_date->toDateString(),
            'due_date' => $this->due_date->toDateString(),
            'is_overdue' => $this->isOverdue(),
            'is_due_today' => $this->isDueToday(),
            'condition_out' => $this->condition_out,
            'approval_number' => $this->approval_number,
            'approval_date' => $this->approval_date?->toDateString(),
            'status' => $this->status,
            'return_date' => $this->return_date?->toIso8601String(),
            'return_condition' => $this->return_condition,
            'return_room' => RoomResource::make($this->whenLoaded('returnRoom')),
            'cancel_reason' => $this->cancel_reason,
            'note' => $this->note,
            'borrowed_by' => $this->userSummary('borrowedBy'),
            'received_by' => $this->userSummary('receivedBy'),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }

    private function userSummary(string $relation): mixed
    {
        return $this->whenLoaded($relation, fn () => $this->{$relation} ? [
            'id' => $this->{$relation}->id,
            'name' => $this->{$relation}->name,
        ] : null);
    }
}
