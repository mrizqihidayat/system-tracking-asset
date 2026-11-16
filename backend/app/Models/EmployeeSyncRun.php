<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * Riwayat satu kali `employees:sync` (T34). status: berhasil | gagal | dibatalkan.
 */
#[Fillable(['status', 'started_at', 'finished_at', 'total', 'created', 'updated', 'deactivated', 'skipped', 'message'])]
class EmployeeSyncRun extends Model
{
    public const SUCCESS = 'berhasil';

    public const FAILED = 'gagal';

    public const CANCELLED = 'dibatalkan';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
        ];
    }
}
