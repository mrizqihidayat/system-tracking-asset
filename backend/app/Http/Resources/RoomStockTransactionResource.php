<?php

namespace App\Http\Resources;

use App\Models\RoomStockTransaction;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Baris kartu ruangan (barang habis pakai di satu ruangan).
 *
 * @mixin RoomStockTransaction
 */
class RoomStockTransactionResource extends JsonResource
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
            'note' => $this->note,
            'stock_transaction_id' => $this->stock_transaction_id,
            'created_by' => $this->creator?->name,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
