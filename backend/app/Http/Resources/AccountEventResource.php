<?php

namespace App\Http\Resources;

use App\Models\AccountEvent;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin AccountEvent
 */
class AccountEventResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'event' => $this->event,
            'actor_name' => $this->actor?->name,
            'created_at' => $this->created_at->toIso8601String(),
        ];
    }
}
