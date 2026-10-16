<?php

namespace App\Http\Resources;

use App\Models\RequestCatalog;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin RequestCatalog
 */
class RequestCatalogResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'item_name' => $this->item_name,
            'provider_department' => DepartmentResource::make($this->whenLoaded('providerDepartment')),
            'request_method' => $this->request_method,
            'must_return' => $this->must_return,
            'lead_time_days' => $this->lead_time_days,
            'note' => $this->note,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
