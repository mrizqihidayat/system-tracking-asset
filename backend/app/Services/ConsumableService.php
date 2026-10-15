<?php

namespace App\Services;

use App\Models\Consumable;
use App\Models\RoomStandard;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Stok habis pakai: perubahan jumlah & hapus di DB::transaction() + lockForUpdate().
 * T13 (isi dari stok) memakai adjust() agar stok tidak pernah negatif.
 */
class ConsumableService
{
    public function setQuantity(Consumable $consumable, int $quantity): Consumable
    {
        return DB::transaction(function () use ($consumable, $quantity) {
            $consumable = $this->lock($consumable);
            $consumable->update(['quantity' => $quantity]);

            return $consumable;
        });
    }

    /**
     * Tambah/kurang jumlah; hasil akhir tidak boleh negatif.
     */
    public function adjust(Consumable $consumable, int $adjustment): Consumable
    {
        return DB::transaction(function () use ($consumable, $adjustment) {
            $consumable = $this->lock($consumable);
            $result = $consumable->quantity + $adjustment;

            if ($result < 0) {
                throw ValidationException::withMessages(['adjustment' => "Stok tidak cukup (tersisa {$consumable->quantity})."]);
            }

            $consumable->update(['quantity' => $result]);

            return $consumable;
        });
    }

    /**
     * Ruangan yang standarnya memakai barang ini (pemenuhan "Dari stok SHD").
     *
     * @return list<array{room_id: int, room: string, building: string, item_name: string}>
     */
    public function usage(Consumable $consumable): array
    {
        return RoomStandard::query()
            ->with('room.building')
            ->where('consumable_id', $consumable->id)
            ->orderBy('room_id')
            ->get()
            ->map(fn (RoomStandard $standard) => [
                'room_id' => $standard->room_id,
                'room' => $standard->room->name,
                'building' => $standard->room->building->name,
                'item_name' => $standard->item_name,
            ])
            ->values()
            ->all();
    }

    /**
     * Hapus PERMANEN. Tautan di standar ruangan dilepas (consumable_id = null) dalam transaksi yang sama;
     * baris standar, jumlah standar, dan "Ada" tetap utuh.
     *
     * @return list<array{room_id: int, room: string, building: string, item_name: string}> ruangan terdampak
     */
    public function delete(Consumable $consumable): array
    {
        return DB::transaction(function () use ($consumable) {
            $consumable = $this->lock($consumable);
            $detached = $this->usage($consumable);

            RoomStandard::query()->where('consumable_id', $consumable->id)->update(['consumable_id' => null]);
            $consumable->delete();

            return $detached;
        });
    }

    private function lock(Consumable $consumable): Consumable
    {
        return Consumable::query()->whereKey($consumable->getKey())->lockForUpdate()->firstOrFail();
    }
}
