<?php

namespace App\Http\Resources;

use App\Models\AssetEvent;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Baris riwayat aset: tanggal, kegiatan, pihak, kondisi, ruangan dari/ke, dicatat oleh, catatan.
 *
 * @mixin AssetEvent
 */
class AssetEventResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'event_type' => $this->event_type,
            'event_date' => $this->event_date->toIso8601String(),
            'party' => $this->party,
            'condition' => $this->condition,
            'from_room' => RoomResource::make($this->whenLoaded('fromRoom')),
            'to_room' => RoomResource::make($this->whenLoaded('toRoom')),
            'loan_id' => $this->loan_id,
            'recorded_by' => $this->whenLoaded('recorder', fn () => ['id' => $this->recorder->id, 'name' => $this->recorder->name]),
            'note' => $this->note,
        ];
    }
}
