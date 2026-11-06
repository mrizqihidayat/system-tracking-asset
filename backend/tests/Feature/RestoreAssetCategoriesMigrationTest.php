<?php

namespace Tests\Feature;

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * T26x: jenis aset yang sudah ada sebelum kategori kembali masuk kategori "Lainnya".
 */
class RestoreAssetCategoriesMigrationTest extends TestCase
{
    private Migration $migration;

    /**
     * Tanpa RefreshDatabase: PRAGMA foreign_keys SQLite tidak berlaku di dalam transaksi.
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->artisan('migrate');
        $this->migration = require database_path('migrations/2026_11_06_000001_restore_asset_categories.php');
        $this->migration->down();
    }

    public function test_existing_types_move_to_lainnya_and_column_becomes_required(): void
    {
        DB::table('asset_types')->insert([
            ['name' => 'Proyektor', 'code' => 'PRJ', 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Kursi', 'code' => 'KRS', 'created_at' => now(), 'updated_at' => now()],
        ]);

        $this->migration->up();

        $other = DB::table('categories')->where('name', 'Lainnya')->value('id');
        $this->assertNotNull($other);
        $this->assertSame(2, DB::table('asset_types')->where('category_id', $other)->count());
        $this->assertFalse(collect(Schema::getColumns('asset_types'))->firstWhere('name', 'category_id')['nullable']);
    }

    public function test_empty_database_gets_no_default_category(): void
    {
        $this->migration->up();

        $this->assertSame(0, DB::table('categories')->count());
    }
}
