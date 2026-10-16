<?php

namespace App\Http\Resources;

use App\Enums\CountMethod;
use App\Models\RoomStandard;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Baris checklist: Standar, Ada, Selisih, Hasil, Ambil dari / Minta ke.
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
            'present_quantity' => $present,
            'difference' => $present === null ? null : $present - $this->standard_quantity,
            'shortage' => $this->shortage(),
            'result' => $this->checklistResult()->value,
            'fulfillment' => $this->fulfillment(),
            'can_fill_from_stock' => $this->count_method === CountMethod::Manual
                && $this->consumable_id !== null
                && ($this->shortage() ?? 0) > 0,
        ];
    }

    /**
     * Kolom "Ambil dari / Minta ke".
     *
     * @return array<string, mixed>|null
     */
    private function fulfillment(): ?array
    {
        if ($this->consumable_id) {
            return [
                'type' => 'stok_shd',
                'consumable' => [
                    'id' => $this->consumable->id,
                    'name' => $this->consumable->name,
                    'unit' => $this->consumable->unit,
                    'quantity' => $this->consumable->quantity,
                ],
            ];
        }

        if ($this->fulfillment_note) {
            return ['type' => 'ambil_dari', 'note' => $this->fulfillment_note];
        }

        if ($this->catalog_id) {
            return [
                'type' => 'minta_ke',
                'catalog' => [
                    'id' => $this->catalog->id,
                    'item_name' => $this->catalog->item_name,
                    'provider_department' => DepartmentResource::make($this->catalog->providerDepartment),
                    'request_method' => $this->catalog->request_method,
                ],
            ];
        }

        return null;
    }
}
