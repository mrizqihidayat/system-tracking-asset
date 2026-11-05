<?php

namespace Tests\Feature;

use App\Models\Consumable;
use App\Models\ConsumableCategory;
use App\Models\Department;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Migrasi T26s: teks kategori stok menjadi data master tanpa kehilangan isi.
 */
class ConsumableCategoryMigrationTest extends TestCase
{
    /**
     * Tanpa RefreshDatabase: PRAGMA foreign_keys SQLite tidak berlaku di dalam transaksi.
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->artisan('migrate');
    }

    private function migration(): Migration
    {
        return require database_path('migrations/2026_11_05_000002_create_consumable_categories_table.php');
    }

    public function test_up_moves_text_to_master_and_merges_case_variants(): void
    {
        $migration = $this->migration();

        Schema::disableForeignKeyConstraints();
        $migration->down();
        Schema::enableForeignKeyConstraints();

        $provider = Department::factory()->create();
        $insert = fn (string $name, ?string $category) => DB::table('consumables')->insertGetId([
            'name' => $name, 'category' => $category, 'unit' => 'pcs', 'quantity' => 1, 'min_quantity' => 0,
            'provider_department_id' => $provider->id, 'created_at' => now(), 'updated_at' => now(),
        ]);
        $pulpen = $insert('Pulpen', 'ATK');
        $pensil = $insert('Pensil', ' atk ');
        $tinta = $insert('Tinta', 'Printer');
        $kosong = $insert('Kosong', '  ');
        $tanpa = $insert('Tanpa', null);

        Schema::disableForeignKeyConstraints();
        $migration->up();
        Schema::enableForeignKeyConstraints();

        $this->assertFalse(Schema::hasColumn('consumables', 'category'));
        $this->assertSame(['ATK', 'Printer'], ConsumableCategory::query()->orderBy('name')->pluck('name')->all());

        $category = fn (int $id) => Consumable::with('consumableCategory')->findOrFail($id)->consumableCategory?->name;
        $this->assertSame('ATK', $category($pulpen));
        $this->assertSame('ATK', $category($pensil));
        $this->assertSame('Printer', $category($tinta));
        $this->assertNull($category($kosong));
        $this->assertNull($category($tanpa));

        // Index unik nama kategori berlaku tanpa membedakan huruf besar/kecil.
        $this->expectException(QueryException::class);
        DB::table('consumable_categories')->insert(['name' => 'atk']);
    }

    public function test_down_restores_text(): void
    {
        $category = ConsumableCategory::factory()->create(['name' => 'ATK']);
        $item = Consumable::factory()->create(['consumable_category_id' => $category->id]);

        Schema::disableForeignKeyConstraints();
        $this->migration()->down();
        Schema::enableForeignKeyConstraints();

        $this->assertFalse(Schema::hasTable('consumable_categories'));
        $this->assertFalse(Schema::hasColumn('consumables', 'consumable_category_id'));
        $this->assertSame('ATK', DB::table('consumables')->where('id', $item->id)->value('category'));
    }
}
