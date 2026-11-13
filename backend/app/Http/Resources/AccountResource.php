<?php

namespace App\Http\Resources;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Akun staf (T33): akun pegawai SHD (source api, login NIK) atau akun bersama (source manual, login username).
 *
 * @mixin User
 */
class AccountResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'nik' => $this->nik,
            'username' => $this->username,
            'department' => $this->department,
            'source' => $this->source,
            'must_change_password' => (bool) $this->must_change_password,
            'is_active' => ! $this->trashed(),
            'deactivated_at' => $this->deleted_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
