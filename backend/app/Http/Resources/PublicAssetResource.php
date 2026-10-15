<?php

namespace App\Http\Resources;

use App\Enums\CountMethod;
use App\Models\Asset;
use App\Models\Room;
use App\Models\RoomStandard;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Data aset untuk halaman publik QR (tanpa login). SATU-SATUNYA tempat field publik diatur:
 * hapus baris di sini untuk menyembunyikan field (menunggu konfirmasi mentor, SPEC §6).
 * Jangan sertakan id internal atau data peminjam.
 *
 * @mixin Asset
 */
class PublicAssetResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $location = $this->currentLocation();
        $standard = $this->roomStandard();

        return [
            'code' => $this->code,
            'name' => $this->name,
            'category' => $this->assetType->category->name,
            'asset_type' => $this->assetType->name,
            'source' => [
                'name' => $this->sourceDepartment->name,
                'is_internal' => $this->sourceDepartment->is_internal,
            ],
            'building' => $this->room->building->name,
            'room' => $this->room->name,
            'current_location' => [
                'on_loan' => $location['on_loan'],
                'building' => $location['room']?->building->name,
                'room' => $location['room']?->name,
                'note' => $location['note'],
            ],
            'pic_name' => $this->pic_name,
            'condition' => $this->condition,
            'status' => $this->status,
            'acquisition_date' => $this->acquisition_date?->toDateString(),
            'next_service_date' => $this->next_service_date?->toDateString(),
            'finance_code' => $this->finance_code,
            'room_standard' => [
                'is_standard' => $standard !== null,
                'item_name' => $standard?->item_name,
            ],
            'actions' => $this->status->allowedActions(),
        ];
    }

    /**
     * Baris standar QR di ruangan asal yang memakai jenis aset ini.
     */
    private function roomStandard(): ?RoomStandard
    {
        /** @var Room $room */
        $room = $this->room;

        return $room->standards()
            ->where('count_method', CountMethod::Qr->value)
            ->where('asset_type_id', $this->asset_type_id)
            ->first();
    }
}
