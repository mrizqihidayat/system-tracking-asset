<?php

namespace App\Models;

use App\Enums\ChecklistResult;
use App\Enums\CountMethod;
use Database\Factories\RoomStandardFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Standar kelengkapan ruangan: per jenis aset (QR, opsional) atau per barang stok (Manual, Ada diisi saat cek).
 */
#[Fillable(['room_id', 'standard_quantity', 'count_method', 'asset_type_id', 'actual_quantity', 'consumable_id'])]
class RoomStandard extends Model
{
    /** @use HasFactory<RoomStandardFactory> */
    use HasFactory;

    /**
     * Jumlah "Ada" baris QR, diisi RoomReadinessService::fillQrCounts() (bukan kolom tabel).
     */
    public ?int $qrPresentQuantity = null;

    /**
     * Jumlah "Ada": QR dari hitungan aset, Manual dari actual_quantity (null = Belum dicek).
     */
    public function presentQuantity(): ?int
    {
        return $this->count_method === CountMethod::Qr ? (int) $this->qrPresentQuantity : $this->actual_quantity;
    }

    public function checklistResult(): ChecklistResult
    {
        return ChecklistResult::fromQuantities($this->standard_quantity, $this->presentQuantity());
    }

    /**
     * Kekurangan (≥ 0); null jika Belum dicek.
     */
    public function shortage(): ?int
    {
        $present = $this->presentQuantity();

        return $present === null ? null : max(0, $this->standard_quantity - $present);
    }

    /**
     * Kurang dan stok masih ada → Isi dari stok.
     */
    public function canFillFromStock(): bool
    {
        return $this->hasStockShortage() && $this->consumable->quantity > 0;
    }

    /**
     * Kurang dan stok habis → Ajukan permintaan ke penyedia.
     */
    public function canRequest(): bool
    {
        return $this->hasStockShortage() && ! $this->canFillFromStock();
    }

    private function hasStockShortage(): bool
    {
        return $this->count_method === CountMethod::Manual && ($this->shortage() ?? 0) > 0;
    }

    /**
     * Nama mengikuti jenis aset atau barang stoknya.
     *
     * @return Attribute<string, never>
     */
    protected function itemName(): Attribute
    {
        return Attribute::get(fn (): string => $this->count_method === CountMethod::Qr ? $this->assetType->name : $this->consumable->name);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'standard_quantity' => 'integer',
            'actual_quantity' => 'integer',
            'count_method' => CountMethod::class,
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
     * @return BelongsTo<AssetType, $this>
     */
    public function assetType(): BelongsTo
    {
        return $this->belongsTo(AssetType::class);
    }

    /**
     * @return BelongsTo<Consumable, $this>
     */
    public function consumable(): BelongsTo
    {
        return $this->belongsTo(Consumable::class);
    }
}
