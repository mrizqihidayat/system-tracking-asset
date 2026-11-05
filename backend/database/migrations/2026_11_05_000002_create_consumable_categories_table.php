<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Kategori stok menjadi data master (terpisah dari kategori aset): teks lama dipindah menjadi baris kategori,
     * nama yang sama tanpa beda huruf besar/kecil digabung, teks kosong menjadi tanpa kategori.
     */
    public function up(): void
    {
        Schema::create('consumable_categories', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100);
            $table->timestamps();
        });

        DB::statement('CREATE UNIQUE INDEX consumable_categories_name_lower_unique ON consumable_categories (lower(name))');

        $this->withoutNameIndexOnSqlite(function () {
            Schema::table('consumables', function (Blueprint $table) {
                $table->unsignedBigInteger('consumable_category_id')->nullable();
                $table->index('consumable_category_id');
                $table->foreign('consumable_category_id')->references('id')->on('consumable_categories')->restrictOnDelete();
            });
        });

        DB::transaction(function () {
            $ids = [];

            foreach (DB::table('consumables')->whereNotNull('category')->orderBy('id')->get(['id', 'category']) as $consumable) {
                $name = trim($consumable->category);

                if ($name === '') {
                    continue;
                }

                $ids[mb_strtolower($name)] ??= DB::table('consumable_categories')->insertGetId([
                    'name' => $name,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                DB::table('consumables')->where('id', $consumable->id)->update(['consumable_category_id' => $ids[mb_strtolower($name)]]);
            }
        });

        $this->withoutNameIndexOnSqlite(function () {
            Schema::table('consumables', function (Blueprint $table) {
                $table->dropColumn('category');
            });
        });
    }

    public function down(): void
    {
        $this->withoutNameIndexOnSqlite(function () {
            Schema::table('consumables', function (Blueprint $table) {
                $table->string('category', 100)->nullable();
            });
        });

        DB::transaction(function () {
            foreach (DB::table('consumable_categories')->get(['id', 'name']) as $category) {
                DB::table('consumables')->where('consumable_category_id', $category->id)->update(['category' => $category->name]);
            }
        });

        $this->withoutNameIndexOnSqlite(function () {
            Schema::table('consumables', function (Blueprint $table) {
                $table->dropForeign(['consumable_category_id']);
                $table->dropIndex(['consumable_category_id']);
                $table->dropColumn('consumable_category_id');
            });
        });

        Schema::dropIfExists('consumable_categories');
    }

    /**
     * SQLite (test) membangun ulang tabel saat FK/kolom berubah dan tidak bisa membawa unique index lower(name).
     */
    private function withoutNameIndexOnSqlite(callable $change): void
    {
        $sqlite = DB::getDriverName() === 'sqlite';

        if ($sqlite) {
            DB::statement('DROP INDEX IF EXISTS consumables_name_lower_unique');
        }

        $change();

        if ($sqlite) {
            DB::statement('CREATE UNIQUE INDEX consumables_name_lower_unique ON consumables (lower(name))');
        }
    }
};
