<?php

namespace Tests\Feature;

use App\Models\Building;
use App\Models\Room;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Migration penyelarasan slug: data yang diganti namanya sebelum slug ikut nama.
 */
class SlugSyncMigrationTest extends TestCase
{
    use RefreshDatabase;

    private function runMigration(): void
    {
        (require database_path('migrations/2026_11_09_040532_sync_building_and_room_slugs.php'))->up();
    }

    /**
     * Simulasikan slug lama (ganti nama sebelum aturan baru) tanpa lewat model.
     */
    private function staleSlug(Building|Room $model, string $slug): void
    {
        DB::table($model->getTable())->where('id', $model->id)->update(['slug' => $slug]);
    }

    public function test_stale_slugs_follow_current_names(): void
    {
        $office = Building::factory()->create(['name' => 'Main Office']);
        $akasia = Room::factory()->for($office)->create(['name' => 'Ruang Akasia']);
        $this->staleSlug($office, 'gedung-ihs');
        $this->staleSlug($akasia, 'ruang-kelas-2');

        $this->runMigration();

        $this->assertSame('main-office', $office->fresh()->slug);
        $this->assertSame('ruang-akasia', $akasia->fresh()->slug);
    }

    public function test_matching_slugs_including_numbered_ones_are_kept(): void
    {
        $building = Building::factory()->create(['name' => 'Gedung IHS']);
        $first = Room::factory()->for($building)->create(['name' => 'Gudang']);
        $second = Room::factory()->for($building)->create(['name' => 'Gudang!']);

        $this->runMigration();

        $this->assertSame('gedung-ihs', $building->fresh()->slug);
        $this->assertSame('gudang', $first->fresh()->slug);
        $this->assertSame('gudang-2', $second->fresh()->slug);
    }

    public function test_swapped_names_get_their_slugs_without_numbers(): void
    {
        $building = Building::factory()->create();
        $a = Room::factory()->for($building)->create(['name' => 'Ruang A']);
        $b = Room::factory()->for($building)->create(['name' => 'Ruang B']);
        // Nama ditukar sebelum aturan baru: slug masing-masing dipegang ruangan lain.
        $this->staleSlug($a, 'tukar');
        $this->staleSlug($b, 'ruang-a');
        $this->staleSlug($a, 'ruang-b');

        $this->runMigration();

        $this->assertSame('ruang-a', $a->fresh()->slug);
        $this->assertSame('ruang-b', $b->fresh()->slug);
    }

    public function test_collision_gets_number_and_room_slug_is_unique_per_building(): void
    {
        $ihs = Building::factory()->create(['name' => 'Gedung IHS']);
        $tg = Building::factory()->create(['name' => 'Office TG']);
        Room::factory()->for($ihs)->create(['name' => 'Ruang Rapat']);
        $renamed = Room::factory()->for($ihs)->create(['name' => 'Ruang Rapat!']);
        $other = Room::factory()->for($tg)->create(['name' => 'Ruang Rapat']);
        $this->staleSlug($renamed, 'ruang-lama');
        $this->staleSlug($other, 'ruang-lama');

        $this->runMigration();

        $this->assertSame('ruang-rapat-2', $renamed->fresh()->slug);
        $this->assertSame('ruang-rapat', $other->fresh()->slug);
    }
}
