<?php

namespace App\Http\Resources;

use App\Models\RoomStandard;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Baris checklist: Standar, Ada, Selisih, Hasil, Pemenuhan.
 * Baris QR wajib melewati RoomReadinessService::fillQrCounts() lebih dulu.
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
            'source_department' => DepartmentResource::make($this->whenLoaded('sourceDepartment')),
            'standard_quantity' => $this->standard_quantity,
            'count_method' => $this->count_method->value,
            'asset_type' => $this->whenLoaded('assetType', fn () => $this->assetType ? [
                'id' => $this->assetType->id,
                'name' => $this->assetType->name,
                'code' => $this->assetType->code,
            ] : null),
            'assets' => $this->qrAssets,
            'not_counted' => (object) $this->qrNotCounted,
            'present_quantity' => $present,
            'difference' => $present === null ? null : $present - $this->standard_quantity,
            'shortage' => $this->shortage(),
            'result' => $this->checklistResult()->value,
            'fulfillment' => $this->fulfillment(),
            'needs_completion' => $this->needsCompletion(),
            'can_fill_from_stock' => $this->canFillFromStock(),
            'can_request' => $this->canRequest(),
        ];
    }

    /**
     * Kolom "Pemenuhan": barang stok beserta penyedianya, atau barang departemen lain beserta katalognya.
     *
     * @return array<string, mixed>|null
     */
    private function fulfillment(): ?array
    {
        if ($this->isOtherUnit()) {
            return [
                'type' => 'unit_lain',
                'catalog' => $this->catalog ? ['id' => $this->catalog->id, 'item_name' => $this->catalog->item_name] : null,
                'provider_department' => DepartmentResource::make($this->sourceDepartment),
            ];
        }

        if (! $this->consumable_id) {
            return null;
        }

        return [
            'type' => 'stok',
            'consumable' => [
                'id' => $this->consumable->id,
                'name' => $this->consumable->name,
                'unit' => $this->consumable->unit,
                'quantity' => $this->consumable->quantity,
                'provider_department' => DepartmentResource::make($this->consumable->providerDepartment),
            ],
        ];
    }
}
