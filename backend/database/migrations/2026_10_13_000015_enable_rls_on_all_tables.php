<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Aktifkan RLS di semua tabel schema public tanpa policy, sehingga akses anon/PostgREST
     * Supabase tertutup; Laravel memakai role pemilik/bypass RLS sehingga tidak terpengaruh.
     */
    public function up(): void
    {
        $this->setRowLevelSecurity('ENABLE');
    }

    public function down(): void
    {
        $this->setRowLevelSecurity('DISABLE');
    }

    private function setRowLevelSecurity(string $action): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        $tables = DB::select("SELECT tablename FROM pg_tables WHERE schemaname = 'public'");

        foreach ($tables as $table) {
            DB::statement("ALTER TABLE public.\"{$table->tablename}\" {$action} ROW LEVEL SECURITY");
        }
    }
};
