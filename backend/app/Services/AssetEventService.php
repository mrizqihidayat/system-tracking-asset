<?php

namespace App\Services;

use App\Enums\EventType;
use App\Models\Asset;
use App\Models\AssetEvent;
use App\Models\User;

/**
 * SATU-SATUNYA jalur menulis asset_events (CLAUDE.md). Dipanggil oleh service aset, lapor/servis,
 * dan peminjaman di dalam transaksi yang sama dengan perubahan asetnya.
 */
class AssetEventService
{
    /**
     * @param  array<string, mixed>  $attributes  party, condition, from_room_id, to_room_id, loan_id, note, event_date
     */
    public function record(Asset $asset, EventType $type, User $actor, array $attributes = []): AssetEvent
    {
        return $asset->events()->create([
            'event_date' => now(),
            ...$attributes,
            'event_type' => $type,
            'recorded_by' => $actor->id,
        ]);
    }
}
