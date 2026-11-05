<?php

namespace App\Services;

use App\Models\Consumable;
use App\Models\RoomStandard;
use App\Support\DbInteger;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Stok habis pakai: perubahan jumlah & hapus di DB::transaction() + lockForUpdate().
 * Ambil dari stok memakai adjust() agar stok tidak pernah negatif.
 */
class ConsumableService
{
    /**
     * Ubah data barang.
     *
     * @param  array<string, mixed>  $data
     */
    public function update(Consumable $consumable, array $data): Consumable
    {
        return DB::transaction(function () use ($consumable, $data) {
            $consumable = $this->lock($consumable);
            $consumable->update($data);

            return $consumable;
        });
    }

    public function setQuantity(Consumable $consumable, int $quantity): Consumable
    {
        return DB::transaction(function () use ($consumable, $quantity) {
            $consumable = $this->lock($consumable);
            $consumable->update(['quantity' => $quantity]);

            return $consumable;
        });
    }

    /**
     * Tambah/kurang jumlah; hasil akhir tidak boleh negatif atau melewati batas kolom.
     */
    public function adjust(Consumable $consumable, int $adjustment): Consumable
    {
        return DB::transaction(function () use ($consumable, $adjustment) {
            $consumable = $this->lock($consumable);
            $result = $consumable->quantity + $adjustment;

            if ($result < 0) {
                throw ValidationException::withMessages(['adjustment' => "Stok tidak cukup (tersisa {$consumable->quantity})."]);
            }

            if ($result > DbInteger::MAX) {
                throw ValidationException::withMessages(['adjustment' => 'Jumlah stok melebihi batas.']);
            }

            $consumable->update(['quantity' => $result]);

            return $consumable;
        });
    }

    /**
     * Ruangan yang standarnya memakai barang ini.
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
                'item_name' => $consumable->name,
            ])
            ->values()
            ->all();
    }

    /**
     * Hapus PERMANEN beserta baris standar ruangan yang memakainya, dalam satu transaksi.
     *
     * @return list<array{room_id: int, room: string, building: string, item_name: string}> ruangan terdampak
     */
    public function delete(Consumable $consumable): array
    {
        return DB::transaction(function () use ($consumable) {
            $consumable = $this->lock($consumable);
            $detached = $this->usage($consumable);

            RoomStandard::query()->where('consumable_id', $consumable->id)->delete();
            $consumable->delete();

            return $detached;
        });
    }

    private function lock(Consumable $consumable): Consumable
    {
        return Consumable::query()->whereKey($consumable->getKey())->lockForUpdate()->firstOrFail();
    }
}
