<?php

namespace App\Http\Resources;

use App\Enums\CountMethod;
use App\Models\RoomStandard;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Baris standar: Standar, Ada, Selisih, Hasil, aksi.
 * Baris QR wajib melewati RoomReadinessService::fillQrCounts(), baris Manual fillLastUsage() lebih dulu.
 *
 * @mixin RoomStandard
 */
class RoomStandardResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $present = $this->presentQuantity();

        return [
            'id' => $this->id,
            'room_id' => $this->room_id,
            'item_name' => $this->item_name,
            'standard_quantity' => $this->standard_quantity,
            'count_method' => $this->count_method->value,
            'asset_type' => $this->count_method === CountMethod::Qr ? [
                'id' => $this->assetType->id,
                'name' => $this->assetType->name,
                'code' => $this->assetType->code,
            ] : null,
            'consumable' => $this->count_method === CountMethod::Manual ? [
                'id' => $this->consumable->id,
                'name' => $this->consumable->name,
                'unit' => $this->consumable->unit,
                'quantity' => $this->consumable->quantity,
                'min_quantity' => $this->consumable->min_quantity,
                'provider_department' => DepartmentResource::make($this->consumable->providerDepartment),
            ] : null,
            // Ada: null selama Belum dicek. recorded_quantity = saldo catatan transaksi ruangan (selalu terisi untuk barang stok).
            'present_quantity' => $present,
            'recorded_quantity' => $this->count_method === CountMethod::Manual ? (int) $this->actual_quantity : null,
            'checked_at' => $this->checked_at?->toIso8601String(),
            'last_usage' => $this->lastUsage,
            'difference' => $present === null ? null : $present - $this->standard_quantity,
            'shortage' => $this->shortage(),
            'result' => $this->checklistResult()->value,
            'can_fill_from_stock' => $this->canFillFromStock(),
            'can_request' => $this->canRequest(),
        ];
    }
}
