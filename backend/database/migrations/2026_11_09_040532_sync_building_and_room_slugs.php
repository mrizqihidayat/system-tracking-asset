<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * Samakan slug gedung & ruangan dengan nama sekarang (data yang diganti namanya sebelum slug ikut nama).
     * Aturan sama dengan HasSlug: gedung unik, ruangan unik per gedung, bentrok diberi angka (-2).
     * Slug yang sudah sesuai nama (termasuk bentuk "-N") tidak diubah.
     */
    public function up(): void
    {
        DB::transaction(function () {
            $this->sync('buildings', 'building', null);
            $this->sync('rooms', 'room', 'building_id');
        });
    }

    /**
     * Slug lama tidak dikembalikan: tautan lama memang tidak berlaku lagi.
     */
    public function down(): void {}

    private function sync(string $table, string $fallback, ?string $scope): void
    {
        $stale = DB::table($table)->orderBy('id')->get(['id', 'name', 'slug', ...($scope ? [$scope] : [])])
            ->filter(function ($row) use ($fallback) {
                $base = Str::slug((string) $row->name) ?: $fallback;

                return $row->slug !== $base && ! preg_match('/^'.preg_quote($base, '/').'-\d+$/', $row->slug);
            });

        // Kosongkan dulu semua slug usang agar slug yang dipegang baris usang lain bisa dipakai.
        foreach ($stale as $row) {
            DB::table($table)->where('id', $row->id)->update(['slug' => "__sync-{$row->id}"]);
        }

        foreach ($stale as $row) {
            $base = Str::slug((string) $row->name) ?: $fallback;
            $slug = $base;

            for ($suffix = 2; $this->taken($table, $scope, $row, $slug); $suffix++) {
                $slug = "{$base}-{$suffix}";
            }

            DB::table($table)->where('id', $row->id)->update(['slug' => $slug]);
        }
    }

    private function taken(string $table, ?string $scope, object $row, string $slug): bool
    {
        return DB::table($table)
            ->when($scope, fn ($query) => $query->where($scope, $row->{$scope}))
            ->where('id', '!=', $row->id)
            ->where('slug', $slug)
            ->exists();
    }
};
