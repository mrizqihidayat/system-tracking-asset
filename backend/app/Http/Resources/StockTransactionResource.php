<?php

namespace App\Http\Resources;

use App\Models\StockTransaction;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin StockTransaction
 */
class StockTransactionResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type->value,
            'quantity' => $this->quantity,
            'balance_after' => $this->balance_after,
            'transaction_date' => $this->transaction_date->toDateString(),
            'reason' => $this->reason,
            'note' => $this->note,
            'is_opening' => $this->is_opening,
            'room' => $this->room ? ['id' => $this->room->id, 'name' => $this->room->name, 'building' => $this->room->building->name] : null,
            'supply_request_id' => $this->supply_request_id,
            'created_by' => $this->creator?->name,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
