<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Arr;

/**
 * Satu baris daftar akses (T41), dari AccountService::rows(). Tidak pernah memuat password atau hash;
 * `password_default` hanya penanda true/false (null untuk akun eksternal). NIK tidak dikirim (T45, temuan UAT K4):
 * NIK adalah password awal, jadi menampilkannya di samping "Masih bawaan" sama dengan membuka password rekan.
 * `key` = penanda baris yang stabil (`akun-{id}` / `pegawai-{id salinan}`) untuk baris tanpa akun.
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
            ...Arr::except($this->resource, ['nik']),
            'last_login_at' => $this->resource['last_login_at']?->toIso8601String(),
        ];
    }
}
