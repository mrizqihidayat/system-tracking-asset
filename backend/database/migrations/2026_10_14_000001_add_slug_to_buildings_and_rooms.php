<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * Slug untuk URL halaman frontend (API tetap memakai id). Data lama diisi di sini.
     */
    public function up(): void
    {
        Schema::table('buildings', function (Blueprint $table) {
            $table->string('slug', 120)->nullable()->after('name');
        });

        Schema::table('rooms', function (Blueprint $table) {
            $table->string('slug', 120)->nullable()->after('name');
        });

        $this->backfill('buildings', 'gedung');

        foreach (DB::table('buildings')->pluck('id') as $buildingId) {
            $this->backfill('rooms', 'ruangan', ['building_id' => $buildingId]);
        }

        Schema::table('buildings', function (Blueprint $table) {
            $table->string('slug', 120)->nullable(false)->change();
            $table->unique('slug');
        });

        Schema::table('rooms', function (Blueprint $table) {
            $table->string('slug', 120)->nullable(false)->change();
            $table->unique(['building_id', 'slug']);
        });
    }

    public function down(): void
    {
        Schema::table('rooms', function (Blueprint $table) {
            $table->dropUnique(['building_id', 'slug']);
            $table->dropColumn('slug');
        });

        Schema::table('buildings', function (Blueprint $table) {
            $table->dropUnique(['slug']);
            $table->dropColumn('slug');
        });
    }

    /**
     * @param  array<string, mixed>  $scope
     */
    private function backfill(string $table, string $fallback, array $scope = []): void
    {
        $used = [];

        foreach (DB::table($table)->where($scope)->orderBy('id')->get(['id', 'name']) as $row) {
            $base = Str::slug($row->name) ?: $fallback;
            $slug = $base;

            for ($suffix = 2; in_array($slug, $used, true); $suffix++) {
                $slug = "{$base}-{$suffix}";
            }

            $used[] = $slug;
            DB::table($table)->where('id', $row->id)->update(['slug' => $slug]);
        }
    }
};
