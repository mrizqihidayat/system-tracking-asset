<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * Riwayat satu kali sinkronisasi pegawai (T34). status: berhasil | gagal | dibatalkan.
 * source (T44): api = `employees:sync` menarik dari API pegawai; agen = kiriman agen sinkron dari jaringan perusahaan.
 */
#[Fillable(['status', 'source', 'started_at', 'finished_at', 'total', 'created', 'updated', 'deactivated', 'skipped', 'message'])]
class EmployeeSyncRun extends Model
{
    public const SUCCESS = 'berhasil';

    public const FAILED = 'gagal';

    public const CANCELLED = 'dibatalkan';

    public const SOURCE_API = 'api';

    public const SOURCE_AGENT = 'agen';

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
