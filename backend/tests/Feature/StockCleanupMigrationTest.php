<?php

namespace Tests\Feature;

use App\Models\Building;
use App\Models\Consumable;
use App\Models\Department;
use App\Models\RequestCatalog;
use App\Models\Room;
use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Migrasi data stok & standar ruangan: T26m (lokasi simpan teks, barang "tidak disimpan" menjadi baris standar),
 * lalu T26u (barang departemen lain pindah ke Stok, baris tanpa barang dihapus).
 */
class StockCleanupMigrationTest extends TestCase
{
    /**
     * Tanpa RefreshDatabase: PRAGMA foreign_keys SQLite tidak berlaku di dalam transaksi.
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->artisan('migrate');
    }

    private function migration(string $file): Migration
    {
        return require database_path("migrations/{$file}.php");
    }

    /**
     * Migration setelah T26m, dibatalkan dari yang terbaru.
     *
     * @return list<Migration>
     */
    private function laterMigrations(): array
    {
        return array_map(fn (string $file) => $this->migration($file), [
            '2026_11_05_000007_replace_storage_location_with_room',
            '2026_11_05_000006_add_partial_receipt_to_supply_requests',
            '2026_11_05_000005_create_stock_transactions_table',
            '2026_11_05_000004_simplify_room_standards',
            '2026_11_05_000002_create_consumable_categories_table',
            '2026_11_05_000001_drop_catalog_id_from_consumables',
        ]);
    }

    private function standardRow(array $data): int
    {
        return DB::table('room_standards')->insertGetId([
            'standard_quantity' => 5, 'count_method' => 'Manual', 'created_at' => now(), 'updated_at' => now(), ...$data,
        ]);
    }

    private function requestRow(array $data): int
    {
        return DB::table('supply_requests')->insertGetId([
            'request_date' => '2026-11-01', 'quantity' => 2, 'status' => 'Diajukan', 'created_by' => User::factory()->create()->id,
            'created_at' => now(), 'updated_at' => now(), ...$data,
        ]);
    }

    public function test_up_moves_location_and_unstored_items_without_losing_rows(): void
    {
        $location = $this->migration('2026_11_03_065401_replace_consumable_room_with_storage_location');
        $unstored = $this->migration('2026_11_03_065402_move_unstored_consumables_to_room_standards');
        $later = $this->laterMigrations();

        // SQLite membangun ulang tabel saat kolom dihapus; foreign key aktif akan memicu ON DELETE SET NULL.
        Schema::disableForeignKeyConstraints();
        foreach ($later as $migration) {
            $migration->down();
        }
        $unstored->down();
        $location->down();
        Schema::enableForeignKeyConstraints();

        $room = Room::factory()->for(Building::factory()->create(['name' => 'Gedung IHS']))->create(['name' => 'Gudang']);
        $provider = Department::factory()->create(['name' => 'SGA']);
        $internal = Department::factory()->internal()->create();
        $catalog = RequestCatalog::factory()->create(['provider_department_id' => $provider->id, 'item_name' => 'Tisu Dus']);

        $insert = fn (array $data) => DB::table('consumables')->insertGetId([
            'unit' => 'pcs', 'quantity' => 0, 'min_quantity' => 0, 'provider_department_id' => $provider->id, 'created_at' => now(), 'updated_at' => now(), ...$data,
        ]);
        $stored = $insert(['name' => 'Pulpen', 'quantity' => 5, 'room_id' => $room->id]);
        $tisu = $insert(['name' => 'Tisu', 'is_stored' => false, 'catalog_id' => $catalog->id]);
        $unused = $insert(['name' => 'Tidak Dipakai', 'is_stored' => false]);

        $storedRow = $this->standardRow(['room_id' => $room->id, 'item_name' => 'Pulpen', 'source_department_id' => $provider->id, 'consumable_id' => $stored]);
        $tisuRow = $this->standardRow(['room_id' => $room->id, 'item_name' => 'Tisu', 'source_department_id' => $internal->id, 'actual_quantity' => 1, 'consumable_id' => $tisu]);
        $request = $this->requestRow(['item_name' => 'Tisu', 'consumable_id' => $tisu, 'room_id' => $room->id, 'provider_department_id' => $provider->id]);
        $storedRequest = $this->requestRow(['item_name' => 'Pulpen', 'consumable_id' => $stored, 'provider_department_id' => $provider->id]);

        Schema::disableForeignKeyConstraints();
        $location->up();
        $unstored->up();
        Schema::enableForeignKeyConstraints();

        // Keadaan setelah T26m.
        $this->assertFalse(Schema::hasColumn('consumables', 'room_id'));
        $this->assertFalse(Schema::hasColumn('consumables', 'is_stored'));
        $this->assertSame('Gudang, Gedung IHS', DB::table('consumables')->where('id', $stored)->value('storage_location'));
        $this->assertSame(5, (int) DB::table('consumables')->where('id', $stored)->value('quantity'));
        $this->assertFalse(DB::table('consumables')->where('id', $tisu)->exists());
        $this->assertFalse(DB::table('consumables')->where('id', $unused)->exists());

        $row = DB::table('room_standards')->where('id', $tisuRow)->first();
        $this->assertNull($row->consumable_id);
        $this->assertSame($provider->id, (int) $row->source_department_id);
        $this->assertSame($catalog->id, (int) $row->catalog_id);
        $this->assertSame($stored, (int) DB::table('room_standards')->where('id', $storedRow)->value('consumable_id'));
        $this->assertNull(DB::table('supply_requests')->where('id', $request)->value('consumable_id'));
        $this->assertSame($tisuRow, (int) DB::table('supply_requests')->where('id', $request)->value('room_standard_id'));
        $this->assertSame($stored, (int) DB::table('supply_requests')->where('id', $storedRequest)->value('consumable_id'));

        // Baris bersumber SHD tanpa barang ("Perlu dilengkapi") tidak ikut T26u.
        $orphan = $this->standardRow(['room_id' => $room->id, 'item_name' => 'Papan', 'source_department_id' => $internal->id]);

        Schema::disableForeignKeyConstraints();
        foreach (array_reverse($later) as $migration) {
            $migration->up();
        }
        Schema::enableForeignKeyConstraints();

        // Keadaan akhir: barang departemen lain menjadi barang Stok, Ada & tautan permintaan terbawa.
        $moved = Consumable::query()->where('name', 'Tisu')->sole();
        $this->assertSame($provider->id, $moved->provider_department_id);
        $this->assertSame(0, $moved->quantity);
        $row = DB::table('room_standards')->where('id', $tisuRow)->first();
        $this->assertSame($moved->id, (int) $row->consumable_id);
        $this->assertSame(1, (int) $row->actual_quantity);
        $this->assertSame($moved->id, (int) DB::table('supply_requests')->where('id', $request)->value('consumable_id'));
        $this->assertFalse(DB::table('room_standards')->where('id', $orphan)->exists());
        $this->assertFalse(Schema::hasColumn('room_standards', 'item_name'));
        $this->assertFalse(Schema::hasColumn('supply_requests', 'room_standard_id'));
        // Teks lokasi "{ruangan}, {gedung}" kembali menjadi tautan ruangan.
        $this->assertSame($room->id, Consumable::find($stored)->room_id);
        $this->assertFalse(Schema::hasColumn('consumables', 'storage_location'));

        // Index unik nama tetap berlaku setelah kolom dihapus.
        $this->expectException(QueryException::class);
        DB::table('consumables')->insert(['name' => 'PULPEN', 'unit' => 'pcs', 'provider_department_id' => $provider->id]);
    }

    public function test_duplicate_standards_are_merged(): void
    {
        $partial = $this->migration('2026_11_05_000006_add_partial_receipt_to_supply_requests');
        $migration = $this->migration('2026_11_05_000004_simplify_room_standards');

        Schema::disableForeignKeyConstraints();
        $partial->down();
        $migration->down();
        Schema::enableForeignKeyConstraints();

        $room = Room::factory()->create();
        $department = Department::factory()->create();
        $consumable = Consumable::factory()->create(['provider_department_id' => $department->id]);
        $first = $this->standardRow(['room_id' => $room->id, 'item_name' => 'A', 'source_department_id' => $department->id, 'consumable_id' => $consumable->id, 'standard_quantity' => 2, 'actual_quantity' => 1]);
        $second = $this->standardRow(['room_id' => $room->id, 'item_name' => 'B', 'source_department_id' => $department->id, 'consumable_id' => $consumable->id, 'standard_quantity' => 3, 'actual_quantity' => null]);

        Schema::disableForeignKeyConstraints();
        $migration->up();
        $partial->up();
        Schema::enableForeignKeyConstraints();

        $this->assertFalse(DB::table('room_standards')->where('id', $second)->exists());
        $row = DB::table('room_standards')->where('id', $first)->first();
        $this->assertSame(5, (int) $row->standard_quantity);
        $this->assertSame(1, (int) $row->actual_quantity);
    }
}
