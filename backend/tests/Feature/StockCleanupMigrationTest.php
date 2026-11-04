<?php

namespace Tests\Feature;

use App\Enums\CountMethod;
use App\Models\Building;
use App\Models\Consumable;
use App\Models\Department;
use App\Models\RequestCatalog;
use App\Models\Room;
use App\Models\RoomStandard;
use App\Models\SupplyRequest;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Migrasi data T26m: lokasi simpan menjadi teks, barang "tidak disimpan" menjadi baris standar Barang unit lain.
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

    public function test_up_moves_location_and_unstored_items_without_losing_rows(): void
    {
        $location = $this->migration('2026_11_03_065401_replace_consumable_room_with_storage_location');
        $unstored = $this->migration('2026_11_03_065402_move_unstored_consumables_to_room_standards');

        // SQLite membangun ulang tabel saat kolom dihapus; foreign key aktif akan memicu ON DELETE SET NULL.
        Schema::disableForeignKeyConstraints();
        $unstored->down();
        $location->down();
        Schema::enableForeignKeyConstraints();

        $room = Room::factory()->for(Building::factory()->create(['name' => 'Gedung IHS']))->create(['name' => 'Gudang']);
        $provider = Department::factory()->create(['name' => 'SGA']);
        $catalog = RequestCatalog::factory()->create(['provider_department_id' => $provider->id, 'item_name' => 'Tisu Dus']);

        $insert = fn (array $data) => DB::table('consumables')->insertGetId([
            'unit' => 'pcs', 'quantity' => 0, 'min_quantity' => 0, 'provider_department_id' => $provider->id, 'created_at' => now(), 'updated_at' => now(), ...$data,
        ]);
        $stored = $insert(['name' => 'Pulpen', 'quantity' => 5, 'room_id' => $room->id]);
        $tisu = $insert(['name' => 'Tisu', 'is_stored' => false, 'catalog_id' => $catalog->id]);
        $unused = $insert(['name' => 'Tidak Dipakai', 'is_stored' => false]);

        $storedRow = RoomStandard::factory()->create([
            'room_id' => $room->id, 'source_department_id' => $provider->id, 'count_method' => CountMethod::Manual, 'consumable_id' => $stored,
        ]);
        $tisuRow = RoomStandard::factory()->create([
            'room_id' => $room->id, 'item_name' => 'Tisu', 'source_department_id' => Department::factory()->internal()->create()->id,
            'count_method' => CountMethod::Manual, 'actual_quantity' => 1, 'consumable_id' => $tisu,
        ]);
        $request = SupplyRequest::factory()->create(['consumable_id' => $tisu, 'room_id' => $room->id, 'provider_department_id' => $provider->id]);
        $storedRequest = SupplyRequest::factory()->create(['consumable_id' => $stored, 'provider_department_id' => $provider->id]);

        Schema::disableForeignKeyConstraints();
        $location->up();
        $unstored->up();
        Schema::enableForeignKeyConstraints();

        $this->assertFalse(Schema::hasColumn('consumables', 'room_id'));
        $this->assertFalse(Schema::hasColumn('consumables', 'is_stored'));
        $this->assertSame('Gudang, Gedung IHS', Consumable::find($stored)->storage_location);
        $this->assertSame(5, Consumable::find($stored)->quantity);
        $this->assertNull(Consumable::find($tisu));
        $this->assertNull(Consumable::find($unused));

        $tisuRow->refresh();
        $this->assertNull($tisuRow->consumable_id);
        $this->assertSame($provider->id, $tisuRow->source_department_id);
        $this->assertSame($catalog->id, $tisuRow->catalog_id);
        $this->assertSame(1, $tisuRow->actual_quantity);
        $this->assertTrue($tisuRow->isOtherUnit());
        $this->assertFalse($tisuRow->needsCompletion());

        $this->assertSame($stored, $storedRow->fresh()->consumable_id);

        $request->refresh();
        $this->assertNull($request->consumable_id);
        $this->assertSame($tisuRow->id, $request->room_standard_id);
        $this->assertSame($stored, $storedRequest->fresh()->consumable_id);

        // Index unik nama tetap berlaku setelah kolom dihapus.
        $this->expectException(QueryException::class);
        DB::table('consumables')->insert(['name' => 'PULPEN', 'unit' => 'pcs', 'provider_department_id' => $provider->id]);
    }
}
