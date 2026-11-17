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
 * source "manual" = akun eksternal buatan staf (pengguna yang tidak terdaftar di SIMHC), login dengan username. Nonaktif = soft delete.
 */
#[Fillable(['nik', 'name', 'username', 'department', 'note', 'email', 'email_exempt', 'source', 'password', 'password_changed_at'])]
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
            'email_exempt' => 'boolean',
            'must_change_password' => 'boolean',
            'last_login_at' => 'datetime',
            'password_changed_at' => 'datetime',
        ];
    }

    public function isApi(): bool
    {
        return $this->source === self::SOURCE_API;
    }

    /** Akun tanpa email wajib mengisinya setelah login (T40), kecuali yang dikecualikan (akun eksternal staf1). */
    public function needsEmail(): bool
    {
        return $this->email === null && ! $this->email_exempt;
    }

    /** NIK untuk akun pegawai, username untuk akun manual. */
    public function loginId(): string
    {
        return (string) ($this->isApi() ? $this->nik : $this->username);
    }
}
