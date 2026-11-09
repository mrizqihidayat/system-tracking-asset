<?php

namespace App\Models;

use App\Enums\RoomStockTransactionType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Satu baris kartu ruangan: barang habis pakai di satu ruangan; hanya ditulis lewat RoomStockService::record().
 * Dikunci per ruangan + barang (bukan baris standar), sehingga riwayat tetap ada saat barang dilepas dari ruangan.
 * quantity bertanda (+/−), balance_after = jumlah di ruangan setelah transaksi ini.
 */
#[Fillable(['room_id', 'consumable_id', 'type', 'quantity', 'balance_after', 'transaction_date', 'note', 'stock_transaction_id', 'created_by'])]
class RoomStockTransaction extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => RoomStockTransactionType::class,
            'quantity' => 'integer',
            'balance_after' => 'integer',
            'transaction_date' => 'date',
        ];
    }

    /**
     * @return BelongsTo<Room, $this>
     */
    public function room(): BelongsTo
    {
        return $this->belongsTo(Room::class);
    }

    /**
     * @return BelongsTo<Consumable, $this>
     */
    public function consumable(): BelongsTo
    {
        return $this->belongsTo(Consumable::class);
    }

    /**
     * @return BelongsTo<StockTransaction, $this>
     */
    public function stockTransaction(): BelongsTo
    {
        return $this->belongsTo(StockTransaction::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
