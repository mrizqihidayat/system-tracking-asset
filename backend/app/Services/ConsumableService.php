<?php

namespace App\Services;

use App\Enums\StockTransactionType;
use App\Models\Consumable;
use App\Models\RoomStandard;
use App\Models\StockTransaction;
use App\Models\User;
use App\Support\DbInteger;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Stok habis pakai (SPEC §10). Jumlah stok hanya berubah lewat record(), sehingga selalu sama dengan hasil transaksi.
 */
class ConsumableService
{
    private const HAS_HISTORY = 'Barang sudah punya riwayat transaksi. Catat Penghapusan agar stok menjadi 0.';

    /**
     * Barang baru; jumlah awal dicatat sebagai saldo awal.
     *
     * @param  array<string, mixed>  $data
     */
    public function create(array $data, ?User $user): Consumable
    {
        return DB::transaction(function () use ($data, $user) {
            $quantity = (int) ($data['quantity'] ?? 0);
            $consumable = Consumable::create(['quantity' => 0] + collect($data)->except('quantity')->all());

            if ($quantity > 0) {
                $this->record($consumable, StockTransactionType::BarangMasuk, $quantity, ['note' => 'Saldo awal', 'is_opening' => true], $user);
            }

            return $consumable->refresh();
        });
    }

    /**
     * Ubah data barang; jumlah tidak berubah di sini.
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

    /**
     * Transaksi dari form: Pemakaian & Penghapusan mengurangi, Barang Masuk menambah, Koreksi mengikuti tanda jumlah.
     *
     * @param  array{type: string, quantity: int, reason?: ?string, note?: ?string, room_id?: ?int}  $data
     */
    public function transact(Consumable $consumable, array $data, User $user): StockTransaction
    {
        $type = StockTransactionType::from($data['type']);
        $quantity = (int) $data['quantity'];

        $delta = match ($type) {
            StockTransactionType::Pemakaian, StockTransactionType::Penghapusan => -$quantity,
            default => $quantity,
        };

        return $this->record($consumable, $type, $delta, [
            'reason' => $data['reason'] ?? null,
            'note' => $data['note'] ?? null,
            'room_id' => $data['room_id'] ?? null,
        ], $user);
    }

    /**
     * Satu-satunya jalur perubahan jumlah stok: hasil akhir tidak boleh negatif atau melewati batas kolom.
     *
     * @param  array<string, mixed>  $attributes  reason, note, is_opening, room_id, supply_request_id
     */
    public function record(Consumable $consumable, StockTransactionType $type, int $delta, array $attributes, ?User $user): StockTransaction
    {
        return DB::transaction(function () use ($consumable, $type, $delta, $attributes, $user) {
            $consumable = $this->lock($consumable);
            $result = $consumable->quantity + $delta;

            if ($result < 0) {
                throw ValidationException::withMessages(['quantity' => "Stok tidak cukup (tersisa {$consumable->quantity})."]);
            }

            if ($result > DbInteger::MAX) {
                throw ValidationException::withMessages(['quantity' => 'Jumlah stok melebihi batas.']);
            }

            $consumable->update(['quantity' => $result]);

            return StockTransaction::create([
                ...$attributes,
                'consumable_id' => $consumable->id,
                'type' => $type,
                'quantity' => $delta,
                'balance_after' => $result,
                'transaction_date' => today(),
                'created_by' => $user?->id,
            ])->setRelation('consumable', $consumable);
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
     * Alasan barang tidak bisa dihapus: hanya barang yang transaksinya saldo awal saja (salah input) yang boleh.
     */
    public function deleteBlockReason(Consumable $consumable): ?string
    {
        return $consumable->transactions()->where('is_opening', false)->exists() ? self::HAS_HISTORY : null;
    }

    /**
     * Hapus PERMANEN (kasus salah input) beserta saldo awal dan baris standar ruangan yang memakainya.
     *
     * @return list<array{room_id: int, room: string, building: string, item_name: string}> ruangan terdampak
     */
    public function delete(Consumable $consumable): array
    {
        return DB::transaction(function () use ($consumable) {
            $consumable = $this->lock($consumable);
            $reason = $this->deleteBlockReason($consumable);

            if ($reason !== null) {
                throw ValidationException::withMessages(['id' => $reason]);
            }

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
