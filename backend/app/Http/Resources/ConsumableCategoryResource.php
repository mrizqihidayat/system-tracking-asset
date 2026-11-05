<?php

namespace App\Http\Resources;

use App\Models\ConsumableCategory;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin ConsumableCategory
 */
class ConsumableCategoryResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'consumables_count' => $this->whenCounted('consumables'),
        ];
    }
}
