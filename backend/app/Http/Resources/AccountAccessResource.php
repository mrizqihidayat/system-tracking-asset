<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Satu baris daftar akses (T41), dari AccountService::rows(). Tidak pernah memuat password atau hash;
 * `password_default` hanya penanda true/false (null untuk akun eksternal).
 *
 * @property array<string, mixed> $resource
 */
class AccountAccessResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            ...$this->resource,
            'last_login_at' => $this->resource['last_login_at']?->toIso8601String(),
        ];
    }
}
