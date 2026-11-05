<?php

namespace App\Models;

use App\Enums\StockTransactionType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Satu baris kartu stok; hanya ditulis lewat ConsumableService::record().
 * quantity bertanda (+/−), balance_after = jumlah stok setelah transaksi ini.
 */
#[Fillable(['consumable_id', 'type', 'quantity', 'balance_after', 'transaction_date', 'reason', 'note', 'is_opening', 'room_id', 'supply_request_id', 'created_by'])]
class StockTransaction extends Model
{
    /**
     * Alasan Penghapusan.
     */
    public const REASONS = ['rusak', 'kedaluwarsa', 'lainnya'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => StockTransactionType::class,
            'quantity' => 'integer',
            'balance_after' => 'integer',
            'transaction_date' => 'date',
            'is_opening' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<Consumable, $this>
     */
    public function consumable(): BelongsTo
    {
        return $this->belongsTo(Consumable::class);
    }

    /**
     * @return BelongsTo<Room, $this>
     */
    public function room(): BelongsTo
    {
        return $this->belongsTo(Room::class);
    }

    /**
     * @return BelongsTo<SupplyRequest, $this>
     */
    public function supplyRequest(): BelongsTo
    {
        return $this->belongsTo(SupplyRequest::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
