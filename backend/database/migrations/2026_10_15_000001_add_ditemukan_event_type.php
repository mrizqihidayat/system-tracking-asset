<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const BASE_TYPES = [
        'Registrasi', 'Dipinjam', 'Dikembalikan', 'Dibatalkan', 'Pindah Lokasi',
        'Lapor Rusak', 'Lapor Hilang', 'Servis', 'Ubah Data', 'Dihapus',
    ];

    /**
     * Event riwayat baru "Ditemukan" (aset Hilang ditandai ditemukan).
     */
    public function up(): void
    {
        $this->setEventTypes([...self::BASE_TYPES, 'Ditemukan']);
    }

    public function down(): void
    {
        if (DB::table('asset_events')->where('event_type', 'Ditemukan')->exists()) {
            throw new RuntimeException('Tidak bisa rollback: sudah ada riwayat "Ditemukan".');
        }

        $this->setEventTypes(self::BASE_TYPES);
    }

    /**
     * @param  list<string>  $types
     */
    private function setEventTypes(array $types): void
    {
        if (DB::getDriverName() === 'pgsql') {
            $list = implode(', ', array_map(fn (string $type) => "'{$type}'", $types));

            DB::statement('ALTER TABLE asset_events DROP CONSTRAINT IF EXISTS asset_events_event_type_check');
            DB::statement("ALTER TABLE asset_events ADD CONSTRAINT asset_events_event_type_check CHECK (event_type IN ({$list}))");

            return;
        }

        Schema::table('asset_events', function (Blueprint $table) use ($types) {
            $table->enum('event_type', $types)->change();
        });
    }
};
