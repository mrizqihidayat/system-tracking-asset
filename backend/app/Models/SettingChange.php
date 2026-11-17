<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * Riwayat perubahan pengaturan (T40): key, nilai lama, nilai baru, pelaku, waktu. Hanya ditambah, tidak diubah.
 */
#[Fillable(['key', 'old_value', 'new_value', 'user_id', 'created_at'])]
class SettingChange extends Model
{
    public $timestamps = false;

    protected function casts(): array
    {
        return [
            'old_value' => 'json',
            'new_value' => 'json',
            'created_at' => 'datetime',
        ];
    }
}
