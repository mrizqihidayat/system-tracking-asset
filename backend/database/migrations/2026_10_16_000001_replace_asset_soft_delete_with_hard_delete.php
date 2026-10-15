<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

return new class extends Migration
{
    private const CONDITIONS = ['Baik', 'Rusak Ringan', 'Rusak Berat'];

    private const OLD_STATUSES = ['Tersedia', 'Dipinjam', 'Perbaikan', 'Hilang'];

    private const NEW_STATUSES = [...self::OLD_STATUSES, 'Tidak Layak Pakai'];

    private const COMMON_EVENTS = [
        'Registrasi', 'Dipinjam', 'Dikembalikan', 'Dibatalkan', 'Pindah Lokasi',
        'Lapor Rusak', 'Lapor Hilang', 'Servis', 'Ubah Data', 'Ditemukan',
    ];

    /**
     * Aset tidak lagi soft delete (hard delete lewat AssetService). Aset yang sudah ter-soft-delete
     * dihapus permanen beserta data turunannya; tambah status & event "Tidak Layak Pakai".
     */
    public function up(): void
    {
        $this->purgeSoftDeletedAssets();

        DB::table('asset_events')->where('event_type', 'Dihapus')->delete();

        Schema::table('assets', function (Blueprint $table) {
            $table->dropConstrainedForeignId('deleted_by');
        });

        Schema::table('assets', function (Blueprint $table) {
            $table->dropColumn(['deleted_at', 'deletion_reason', 'deletion_note']);
        });

        $this->applyChecks(self::NEW_STATUSES, [...self::COMMON_EVENTS, 'Tidak Layak Pakai']);
    }

    /**
     * Kolom & nilai lama dikembalikan; data yang sudah dihapus permanen tidak bisa dipulihkan.
     */
    public function down(): void
    {
        if (DB::table('assets')->where('status', 'Tidak Layak Pakai')->exists()
            || DB::table('asset_events')->where('event_type', 'Tidak Layak Pakai')->exists()) {
            throw new RuntimeException('Tidak bisa rollback: sudah ada data "Tidak Layak Pakai".');
        }

        Schema::table('assets', function (Blueprint $table) {
            $table->softDeletes();
            $table->string('deletion_reason', 50)->nullable();
            $table->text('deletion_note')->nullable();
            $table->foreignId('deleted_by')->nullable()->constrained('users')->restrictOnDelete();
        });

        $this->applyChecks(self::OLD_STATUSES, [...self::COMMON_EVENTS, 'Dihapus']);
    }

    private function purgeSoftDeletedAssets(): void
    {
        $assetIds = DB::table('assets')->whereNotNull('deleted_at')->pluck('id');

        if ($assetIds->isEmpty()) {
            return;
        }

        $paths = DB::table('asset_files')->whereIn('asset_id', $assetIds)->pluck('path')->all();

        DB::table('asset_code_histories')->whereIn('asset_id', $assetIds)->delete();
        DB::table('asset_files')->whereIn('asset_id', $assetIds)->delete();
        DB::table('asset_events')->whereIn('asset_id', $assetIds)->delete();
        DB::table('loans')->whereIn('asset_id', $assetIds)->delete();
        DB::table('assets')->whereIn('id', $assetIds)->delete();

        if ($paths !== []) {
            Storage::disk(config('filesystems.asset_disk'))->delete($paths);
        }
    }

    /**
     * PostgreSQL: ganti CHECK assets.status & asset_events.event_type saja.
     *
     * SQLite (test): ->change() membangun ulang tabel dan menghapus CHECK enum kolom lain, sehingga
     * semua kolom enum per tabel dipasang ulang sekaligus (memulihkan CHECK yang hilang sejak
     * migration 2026_10_14_000004 / 2026_10_15_00000x).
     *
     * @param  list<string>  $statuses
     * @param  list<string>  $eventTypes
     */
    private function applyChecks(array $statuses, array $eventTypes): void
    {
        if (DB::getDriverName() === 'pgsql') {
            $this->pgsqlCheck('assets', 'status', $statuses);
            $this->pgsqlCheck('asset_events', 'event_type', $eventTypes);

            return;
        }

        Schema::table('assets', function (Blueprint $table) use ($statuses) {
            $table->enum('condition', self::CONDITIONS)->default('Baik')->change();
            $table->enum('status', $statuses)->default('Tersedia')->change();
        });

        Schema::table('asset_events', function (Blueprint $table) use ($eventTypes) {
            $table->enum('event_type', $eventTypes)->change();
            $table->enum('condition', self::CONDITIONS)->nullable()->change();
        });

        Schema::table('loans', function (Blueprint $table) {
            $table->enum('status', ['Dipinjam', 'Dikembalikan', 'Dibatalkan', 'Hilang'])->default('Dipinjam')->change();
            $table->enum('condition_out', self::CONDITIONS)->change();
            $table->enum('return_condition', self::CONDITIONS)->nullable()->change();
            $table->enum('damage_confirmation', ['Menunggu Konfirmasi', 'Dikonfirmasi'])->nullable()->change();
        });
    }

    /**
     * @param  list<string>  $values
     */
    private function pgsqlCheck(string $table, string $column, array $values): void
    {
        $list = implode(', ', array_map(fn (string $value) => "'{$value}'", $values));

        DB::statement("ALTER TABLE {$table} DROP CONSTRAINT IF EXISTS {$table}_{$column}_check");
        DB::statement("ALTER TABLE {$table} ADD CONSTRAINT {$table}_{$column}_check CHECK ({$column} IN ({$list}))");
    }
};
