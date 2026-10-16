<?php

namespace App\Http\Resources;

use App\Models\AssetFile;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin AssetFile
 */
class AssetFileResource extends JsonResource
{
    /**
     * download_url butuh Bearer token (frontend mengambil sebagai blob lewat axios).
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'asset_id' => $this->asset_id,
            'file_type' => $this->file_type->value,
            'original_name' => $this->original_name,
            'mime_type' => $this->mime_type,
            'size' => $this->size,
            'download_url' => route('asset-files.download', $this->id),
            'uploaded_by' => $this->whenLoaded('uploader', fn () => $this->uploader ? ['id' => $this->uploader->id, 'name' => $this->uploader->name] : null),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
