<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Satu baris per key pengaturan (T40). Baca dan tulis hanya lewat SettingService.
 */
#[Fillable(['key', 'value', 'updated_by'])]
class Setting extends Model
{
    protected function casts(): array
    {
        return [
            'value' => 'json',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by')->withTrashed();
    }
}
