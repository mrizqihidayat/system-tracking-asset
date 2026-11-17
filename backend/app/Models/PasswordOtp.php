<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Model;

/**
 * OTP lupa password (T40): satu baris per akun, hanya berisi hash. Baca dan tulis lewat PasswordOtpService.
 */
#[Fillable(['user_id', 'otp_hash', 'attempts', 'expires_at', 'last_sent_at', 'used_at', 'reset_token_hash', 'reset_expires_at'])]
#[Hidden(['otp_hash', 'reset_token_hash'])]
class PasswordOtp extends Model
{
    protected function casts(): array
    {
        return [
            'attempts' => 'integer',
            'expires_at' => 'datetime',
            'last_sent_at' => 'datetime',
            'used_at' => 'datetime',
            'reset_expires_at' => 'datetime',
        ];
    }
}
