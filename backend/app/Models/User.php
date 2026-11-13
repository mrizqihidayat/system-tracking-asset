<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Laravel\Sanctum\HasApiTokens;

/**
 * Akun staf (T33). source "api" = pegawai SHD dari API pegawai, login dengan NIK;
 * source "manual" = akun bersama buatan staf, login dengan username. Nonaktif = soft delete.
 */
#[Fillable(['nik', 'name', 'username', 'department', 'source', 'password', 'must_change_password'])]
#[Hidden(['password'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, SoftDeletes;

    public const SOURCE_API = 'api';

    public const SOURCE_MANUAL = 'manual';

    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'must_change_password' => 'boolean',
        ];
    }

    public function isApi(): bool
    {
        return $this->source === self::SOURCE_API;
    }

    /** NIK untuk akun pegawai, username untuk akun manual. */
    public function loginId(): string
    {
        return (string) ($this->isApi() ? $this->nik : $this->username);
    }
}
