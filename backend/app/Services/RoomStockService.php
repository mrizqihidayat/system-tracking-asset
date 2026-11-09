<?php

namespace App\Services;

use App\Enums\CountMethod;
use App\Enums\RoomStockTransactionType;
use App\Models\RoomStandard;
use App\Models\RoomStockTransaction;
use App\Models\User;
use App\Support\DbInteger;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

/**
 * Barang habis pakai di ruangan (SPEC §8, T30). Jumlah Ada (room_standards.actual_quantity) hanya berubah lewat
 * record(), sehingga selalu sama dengan saldo transaksi ruangan + barang. Gudang tidak memakai ledger ini.
 */
class RoomStockService
{
    /**
     * Saldo terakhir ruangan + barang (0 jika belum pernah ada transaksi); dipakai saat barang ditambahkan ke ruangan.
     */
    public function balance(int $roomId, int $consumableId): int
    {
        return (int) RoomStockTransaction::query()
            ->where('room_id', $roomId)
            ->where('consumable_id', $consumableId)
            ->orderByDesc('id')
            ->value('balance_after');
    }

    /**
     * Ruangan + barang sudah punya riwayat transaksi.
     */
    public function hasHistory(int $roomId, int $consumableId): bool
    {
        return RoomStockTransaction::query()->where('room_id', $roomId)->where('consumable_id', $consumableId)->exists();
    }

    /**
     * Satu-satunya jalur perubahan jumlah Ada barang habis pakai ruangan. Nilai 0 tidak pernah disimpan.
     *
     * @param  array<string, mixed>  $attributes  note, stock_transaction_id
     */
    public function record(RoomStandard $standard, RoomStockTransactionType $type, int $delta, array $attributes, ?User $user): RoomStockTransaction
    {
        if ($delta === 0) {
            throw new InvalidArgumentException('Transaksi ruangan bernilai 0 tidak disimpan.');
        }

        return DB::transaction(function () use ($standard, $type, $delta, $attributes, $user) {
            $standard = RoomStandard::query()->with('room')->whereKey($standard->getKey())->lockForUpdate()->firstOrFail();

            if ($standard->count_method !== CountMethod::Manual || $standard->consumable_id === null) {
                throw ValidationException::withMessages(['standard' => 'Baris ini bukan barang stok.']);
            }

            if ($standard->room->is_storage) {
                throw ValidationException::withMessages(['standard' => 'Gudang tidak dicek terhadap standar.']);
            }

            $current = (int) $standard->actual_quantity;
            $result = $current + $delta;

            if ($result < 0) {
                throw ValidationException::withMessages(['quantity' => "Jumlah di ruangan tidak cukup (tersisa {$current})."]);
            }

            if ($result > DbInteger::MAX) {
                throw ValidationException::withMessages(['quantity' => 'Jumlah di ruangan melebihi batas.']);
            }

            $standard->update(['actual_quantity' => $result]);

            return RoomStockTransaction::create([
                ...$attributes,
                'room_id' => $standard->room_id,
                'consumable_id' => $standard->consumable_id,
                'type' => $type,
                'quantity' => $delta,
                'balance_after' => $result,
                'transaction_date' => today(),
                'created_by' => $user?->id,
            ]);
        });
    }
}
