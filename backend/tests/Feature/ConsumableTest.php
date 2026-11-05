<?php

namespace Tests\Feature;

use App\Enums\ConsumableStatus;
use App\Enums\CountMethod;
use App\Enums\RequestStatus;
use App\Models\Building;
use App\Models\Consumable;
use App\Models\Department;
use App\Models\RequestCatalog;
use App\Models\Room;
use App\Models\RoomStandard;
use App\Models\SupplyRequest;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ConsumableTest extends TestCase
{
    use RefreshDatabase;

    private Department $sga;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAs(User::factory()->create());
        $this->sga = Department::factory()->create(['name' => 'SGA']);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function payload(array $overrides = []): array
    {
        return [
            'name' => 'Pulpen',
            'category' => 'ATK',
            'unit' => 'pcs',
            'quantity' => 50,
            'min_quantity' => 10,
            'provider_department_id' => $this->sga->id,
            ...$overrides,
        ];
    }

    private function consumable(array $overrides = []): Consumable
    {
        return Consumable::factory()->create(['provider_department_id' => $this->sga->id, ...$overrides]);
    }

    /**
     * @return array<string, array{0: string, 1: string}>
     */
    public static function endpointProvider(): array
    {
        return [
            'index' => ['GET', '/api/consumables'],
            'store' => ['POST', '/api/consumables'],
            'show' => ['GET', '/api/consumables/1'],
            'usage' => ['GET', '/api/consumables/1/usage'],
            'update' => ['PUT', '/api/consumables/1'],
            'quantity' => ['PATCH', '/api/consumables/1/quantity'],
            'destroy' => ['DELETE', '/api/consumables/1'],
        ];
    }

    #[DataProvider('endpointProvider')]
    public function test_endpoints_require_token(string $method, string $uri): void
    {
        $this->app['auth']->forgetGuards();

        $this->json($method, $uri)->assertUnauthorized();
    }

    /**
     * @return array<string, array{0: int, 1: int, 2: ConsumableStatus}>
     */
    public static function statusProvider(): array
    {
        return [
            'nol → habis' => [0, 10, ConsumableStatus::Habis],
            'nol dengan minimum nol → habis' => [0, 0, ConsumableStatus::Habis],
            'sama minimum → menipis' => [10, 10, ConsumableStatus::Menipis],
            'di bawah minimum → menipis' => [1, 10, ConsumableStatus::Menipis],
            'di atas minimum → aman' => [11, 10, ConsumableStatus::Aman],
            'minimum nol, ada stok → aman' => [1, 0, ConsumableStatus::Aman],
        ];
    }

    #[DataProvider('statusProvider')]
    public function test_status_and_scope_use_same_rule(int $quantity, int $minimum, ConsumableStatus $expected): void
    {
        $item = $this->consumable(['quantity' => $quantity, 'min_quantity' => $minimum]);

        $this->assertSame($expected, $item->status());
        $this->assertTrue(Consumable::withStatus($expected)->whereKey($item->id)->exists());

        foreach (ConsumableStatus::cases() as $other) {
            if ($other !== $expected) {
                $this->assertFalse(Consumable::withStatus($other)->whereKey($item->id)->exists(), $other->value);
            }
        }
    }

    public function test_store_and_show(): void
    {
        $catalog = RequestCatalog::factory()->create(['provider_department_id' => $this->sga->id, 'item_name' => 'ATK Isi Ulang']);

        $id = $this->postJson('/api/consumables', $this->payload(['catalog_id' => $catalog->id, 'storage_location' => 'Gudang IHS']))
            ->assertCreated()
            ->assertJsonPath('data.status', 'Aman')
            ->assertJsonPath('data.provider_department.name', 'SGA')
            ->assertJsonPath('data.catalog.item_name', 'ATK Isi Ulang')
            ->assertJsonPath('data.storage_location', 'Gudang IHS')
            ->assertJsonPath('data.open_request', null)
            ->json('data.id');

        $this->getJson("/api/consumables/{$id}")->assertOk()->assertJsonPath('meta.used_in_rooms', []);
    }

    public function test_quantity_and_adjustment_stop_at_integer_column_limit(): void
    {
        $item = $this->consumable(['quantity' => 10]);
        $url = "/api/consumables/{$item->id}/quantity";

        $this->patchJson($url, ['quantity' => 2147483648])->assertJsonValidationErrors('quantity');
        $this->patchJson($url, ['adjustment' => 2147483648])->assertJsonValidationErrors('adjustment');
        $this->patchJson($url, ['adjustment' => -2147483648])->assertJsonValidationErrors('adjustment');
        $this->assertSame(10, $item->fresh()->quantity);

        $this->patchJson($url, ['quantity' => 2147483647])->assertOk();
        $this->patchJson($url, ['adjustment' => 1])
            ->assertUnprocessable()
            ->assertJsonPath('errors.adjustment.0', 'Jumlah stok melebihi batas.');
        $this->assertSame(2147483647, $item->fresh()->quantity);
    }

    public function test_store_rejects_quantities_above_integer_column_limit(): void
    {
        $this->postJson('/api/consumables', $this->payload(['quantity' => 2147483648, 'min_quantity' => 2147483648]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['quantity', 'min_quantity']);

        $this->assertDatabaseCount('consumables', 0);
    }

    public function test_store_validation(): void
    {
        $this->consumable(['name' => 'Pulpen']);
        $otherUnit = Department::factory()->create();
        $catalog = RequestCatalog::factory()->create(['provider_department_id' => $otherUnit->id]);

        $this->postJson('/api/consumables', $this->payload(['name' => ' PULPEN ', 'catalog_id' => $catalog->id, 'quantity' => -1]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'name' => 'Nama sudah digunakan.',
                'catalog_id' => "Katalog harus dari departemen yang sama dengan 'minta ke'.",
                'quantity' => 'Jumlah minimal 0.',
            ]);

        $this->postJson('/api/consumables', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'name' => 'Nama wajib diisi.',
                'unit' => 'Satuan wajib diisi.',
                'provider_department_id' => 'Minta ke wajib diisi.',
            ]);
    }

    public function test_name_is_unique_case_insensitive_in_database(): void
    {
        $this->consumable(['name' => 'Pulpen']);

        $this->expectException(UniqueConstraintViolationException::class);

        Consumable::factory()->create(['name' => 'pulpen']);
    }

    public function test_index_filters(): void
    {
        $this->consumable(['name' => 'Pulpen', 'category' => 'ATK', 'quantity' => 50, 'min_quantity' => 10]);
        $this->consumable(['name' => 'Pensil', 'category' => 'ATK', 'quantity' => 5, 'min_quantity' => 10]);
        $this->consumable(['name' => 'Baterai AA', 'category' => 'Elektronik', 'quantity' => 0, 'min_quantity' => 5,
            'provider_department_id' => Department::factory()->create(['name' => 'SIT'])->id]);

        $this->getJson('/api/consumables')->assertOk()->assertJsonCount(3, 'data')->assertJsonPath('data.0.name', 'Baterai AA');
        $this->getJson('/api/consumables?status=Habis')->assertJsonCount(1, 'data')->assertJsonPath('data.0.name', 'Baterai AA');
        $this->getJson('/api/consumables?status=Menipis')->assertJsonCount(1, 'data')->assertJsonPath('data.0.name', 'Pensil');
        $this->getJson('/api/consumables?status=Aman')->assertJsonCount(1, 'data')->assertJsonPath('data.0.name', 'Pulpen');
        $this->getJson('/api/consumables?category=atk')->assertJsonCount(2, 'data');
        $this->getJson("/api/consumables?provider_department_id={$this->sga->id}")->assertJsonCount(2, 'data');
        $this->getJson('/api/consumables?search=elektro')->assertJsonCount(1, 'data');
        $this->getJson('/api/consumables?status=Kosong')->assertUnprocessable();
    }

    public function test_update_rejects_quantity_and_checks_catalog_against_current_provider(): void
    {
        $item = $this->consumable(['name' => 'Pulpen', 'quantity' => 20]);
        $otherCatalog = RequestCatalog::factory()->create();
        $sgaCatalog = RequestCatalog::factory()->create(['provider_department_id' => $this->sga->id]);

        $this->putJson("/api/consumables/{$item->id}", ['quantity' => 99])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['quantity' => 'Jumlah diubah lewat ubah jumlah.']);

        $this->putJson("/api/consumables/{$item->id}", ['catalog_id' => $otherCatalog->id])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['catalog_id' => "Katalog harus dari departemen yang sama dengan 'minta ke'."]);

        $this->putJson("/api/consumables/{$item->id}", ['name' => 'pulpen', 'catalog_id' => $sgaCatalog->id, 'min_quantity' => 30])
            ->assertOk()
            ->assertJsonPath('data.name', 'pulpen')
            ->assertJsonPath('data.quantity', 20)
            ->assertJsonPath('data.status', 'Menipis');
    }

    public function test_update_quantity_set_or_adjust(): void
    {
        $item = $this->consumable(['quantity' => 10, 'min_quantity' => 5]);
        $uri = "/api/consumables/{$item->id}/quantity";

        $this->patchJson($uri, ['quantity' => 3])->assertOk()->assertJsonPath('data.quantity', 3)->assertJsonPath('data.status', 'Menipis');
        $this->patchJson($uri, ['adjustment' => 7])->assertOk()->assertJsonPath('data.quantity', 10);
        $this->patchJson($uri, ['adjustment' => -10])->assertOk()->assertJsonPath('data.quantity', 0)->assertJsonPath('data.status', 'Habis');

        $this->patchJson($uri, ['adjustment' => -1])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['adjustment' => 'Stok tidak cukup (tersisa 0).']);

        $this->patchJson($uri, [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['quantity' => 'Isi jumlah atau penyesuaian.']);

        $this->patchJson($uri, ['quantity' => 5, 'adjustment' => 1])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['quantity' => 'Isi salah satu saja: jumlah atau penyesuaian.']);

        $this->patchJson($uri, ['quantity' => -1])->assertUnprocessable()->assertJsonValidationErrors('quantity');
        $this->patchJson($uri, ['adjustment' => 0])->assertUnprocessable()->assertJsonValidationErrors(['adjustment' => 'Penyesuaian tidak boleh 0.']);

        $this->assertSame(0, $item->fresh()->quantity);
    }

    public function test_list_shows_latest_open_request_with_one_extra_query(): void
    {
        $item = $this->consumable(['name' => 'Pulpen']);
        $other = $this->consumable(['name' => 'Pensil']);
        $creator = User::factory()->create();
        $request = fn (Consumable $consumable, RequestStatus $status) => SupplyRequest::factory()->create([
            'consumable_id' => $consumable->id, 'status' => $status, 'provider_department_id' => $this->sga->id, 'created_by' => $creator->id,
        ]);

        $request($item, RequestStatus::Diajukan);
        $latest = $request($item, RequestStatus::Diajukan);
        $request($item, RequestStatus::Diterima);
        $request($other, RequestStatus::Dibatalkan);

        $rows = collect($this->getJson('/api/consumables')->assertOk()->json('data'))->keyBy('name');

        $this->assertSame($latest->id, $rows['Pulpen']['open_request']['id']);
        $this->assertNotNull($rows['Pulpen']['open_request']['created_at']);
        $this->assertNull($rows['Pensil']['open_request']);

        DB::enableQueryLog();
        $this->getJson('/api/consumables')->assertOk();
        $requestQueries = collect(DB::getQueryLog())->filter(fn (array $query) => str_contains($query['query'], '"supply_requests"'));
        $this->assertCount(1, $requestQueries);
    }

    public function test_storage_location_lookup_suggests_used_values(): void
    {
        $this->consumable(['name' => 'Pulpen', 'storage_location' => 'Main Office SHD']);
        $this->consumable(['name' => 'Pensil', 'storage_location' => 'Main Office SHD']);
        $this->consumable(['name' => 'Spidol', 'storage_location' => 'Gudang IHS']);
        $this->consumable(['name' => 'Map']);

        $this->getJson('/api/lookups/storage-locations')->assertOk()->assertExactJson(['data' => ['Gudang IHS', 'Main Office SHD']]);
        $this->getJson('/api/lookups/storage-locations?search=shd')->assertOk()->assertExactJson(['data' => ['Main Office SHD']]);

        $this->postJson('/api/consumables', $this->payload(['name' => 'Tinta', 'storage_location' => str_repeat('x', 151)]))
            ->assertJsonValidationErrors('storage_location');
    }

    public function test_received_request_adds_stock_and_manual_quantity_still_works(): void
    {
        $item = $this->consumable(['name' => 'Pulpen', 'quantity' => 2, 'min_quantity' => 10]);
        $request = SupplyRequest::factory()->create(['consumable_id' => $item->id, 'quantity' => 8, 'provider_department_id' => $this->sga->id]);

        $this->patchJson("/api/supply-requests/{$request->id}/status", ['status' => 'Diterima'])
            ->assertOk()
            ->assertJsonPath('meta.impact.message', 'Stok Pulpen akan bertambah 8.');
        $this->assertSame(10, $item->fresh()->quantity);

        $this->patchJson("/api/consumables/{$item->id}/quantity", ['adjustment' => -1])->assertOk()->assertJsonPath('data.quantity', 9);
        $this->patchJson("/api/consumables/{$item->id}/quantity", ['quantity' => 3])->assertOk()->assertJsonPath('data.quantity', 3);

        $this->patchJson("/api/supply-requests/{$request->id}/status", ['status' => 'Dibatalkan'])
            ->assertOk()
            ->assertJsonPath('meta.impact.clamped', true);
        $this->assertSame(0, $item->fresh()->quantity);
    }

    public function test_changing_provider_updates_source_of_room_standards(): void
    {
        $item = $this->consumable();
        $other = Department::factory()->create(['name' => 'SIT']);
        $standard = RoomStandard::factory()->create([
            'source_department_id' => $this->sga->id, 'count_method' => CountMethod::Manual, 'consumable_id' => $item->id,
        ]);
        $unrelated = RoomStandard::factory()->create(['source_department_id' => $this->sga->id]);

        $this->putJson("/api/consumables/{$item->id}", ['provider_department_id' => $other->id])->assertOk();

        $this->assertSame($other->id, $standard->fresh()->source_department_id);
        $this->assertSame($this->sga->id, $unrelated->fresh()->source_department_id);
    }

    public function test_usage_and_hard_delete_detach_room_standards(): void
    {
        $item = $this->consumable(['name' => 'Pulpen']);
        $class = Room::factory()->for(Building::factory()->create(['name' => 'Gedung IHS']))->create(['name' => 'Ruang Kelas 1']);
        $standard = RoomStandard::factory()->for($class)->create([
            'item_name' => 'ATK peserta', 'count_method' => CountMethod::Manual,
            'standard_quantity' => 20, 'actual_quantity' => 15, 'consumable_id' => $item->id,
        ]);
        $expected = [['room_id' => $class->id, 'room' => 'Ruang Kelas 1', 'building' => 'Gedung IHS', 'item_name' => 'ATK peserta']];

        $this->getJson("/api/consumables/{$item->id}/usage")->assertOk()->assertExactJson(['data' => ['rooms' => $expected]]);
        $this->getJson("/api/consumables/{$item->id}")->assertJsonPath('meta.used_in_rooms', $expected);

        $this->deleteJson("/api/consumables/{$item->id}")
            ->assertOk()
            ->assertExactJson(['message' => 'Barang Pulpen dihapus.', 'detached_rooms' => $expected]);

        $this->assertModelMissing($item);
        $standard->refresh();
        $this->assertNull($standard->consumable_id);
        $this->assertSame(20, $standard->standard_quantity);
        $this->assertSame(15, $standard->actual_quantity);

        $this->getJson("/api/consumables/{$item->id}")->assertNotFound();
        $this->postJson('/api/consumables', $this->payload(['name' => 'Pulpen']))->assertCreated();
    }

    public function test_database_sets_null_when_consumable_row_is_deleted_directly(): void
    {
        $item = $this->consumable();
        $standard = RoomStandard::factory()->create(['consumable_id' => $item->id]);

        Consumable::query()->whereKey($item->id)->delete();

        $this->assertNull($standard->fresh()->consumable_id);
    }
}
