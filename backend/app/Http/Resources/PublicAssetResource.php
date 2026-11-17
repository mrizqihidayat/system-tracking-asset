<?php

namespace App\Http\Resources;

use App\Models\Asset;
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
        $standard = $this->resource->roomStandard();

        return [
            'code' => $this->code,
            'name' => $this->name,
            'category' => $this->assetType->category->name,
            'asset_type' => $this->assetType->name,
            'specification' => $this->specification,
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
            // "Dipesan untuk {tanggal}" tanpa data peminjam (T31).
            'upcoming_booking' => $this->upcomingBooking ? [
                'borrow_date' => $this->upcomingBooking->borrow_date->toDateString(),
            ] : null,
            'actions' => $this->status->allowedActions(),
        ];
    }
}
