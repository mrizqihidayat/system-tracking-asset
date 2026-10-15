<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * Penghitung nomor urut kode aset per jenis; nomor tidak pernah dipakai ulang.
     */
    public function up(): void
    {
        Schema::table('asset_types', function (Blueprint $table) {
            $table->unsignedInteger('last_number')->default(0)->after('code');
        });

        $highest = DB::table('assets')
            ->get(['asset_type_id', 'code'])
            ->groupBy('asset_type_id')
            ->map(fn ($assets) => $assets->max(fn ($asset) => (int) Str::afterLast($asset->code, '-')));

        foreach ($highest as $assetTypeId => $lastNumber) {
            DB::table('asset_types')->where('id', $assetTypeId)->update(['last_number' => $lastNumber]);
        }
    }

    public function down(): void
    {
        Schema::table('asset_types', function (Blueprint $table) {
            $table->dropColumn('last_number');
        });
    }
};
