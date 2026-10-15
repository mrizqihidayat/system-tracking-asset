<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * Token acak untuk link QR halaman publik ({QR_BASE_URL}/aset/{public_token}); tidak pernah berubah.
     * Aset yang sudah ada (termasuk dihapuskan) diisi di sini.
     */
    public function up(): void
    {
        Schema::table('assets', function (Blueprint $table) {
            $table->string('public_token', 32)->nullable()->after('code');
        });

        $used = [];

        foreach (DB::table('assets')->orderBy('id')->pluck('id') as $assetId) {
            do {
                $token = Str::random(12);
            } while (isset($used[$token]));

            $used[$token] = true;
            DB::table('assets')->where('id', $assetId)->update(['public_token' => $token]);
        }

        Schema::table('assets', function (Blueprint $table) {
            $table->string('public_token', 32)->nullable(false)->change();
            $table->unique('public_token');
        });
    }

    public function down(): void
    {
        Schema::table('assets', function (Blueprint $table) {
            $table->dropUnique(['public_token']);
            $table->dropColumn('public_token');
        });
    }
};
