<?php

namespace Tests\Feature;

use App\Enums\ConsumableStatus;
use App\Enums\CountMethod;
use App\Enums\RequestStatus;
use App\Models\Building;
use App\Models\Consumable;
use App\Models\ConsumableCategory;
use App\Models\Department;
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
            'transactions' => ['GET', '/api/consumables/1/transactions'],
            'store transaction' => ['POST', '/api/consumables/1/transactions'],
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
        // Stok tidak tertaut katalog: catalog_id yang masih dikirim diabaikan.
        $category = ConsumableCategory::factory()->create(['name' => 'ATK']);
        $gudang = Room::factory()->for(Building::factory()->create(['name' => 'Gedung IHS']))->create(['name' => 'Gudang']);

        $id = $this->postJson('/api/consumables', $this->payload(['catalog_id' => 999, 'consumable_category_id' => $category->id, 'room_id' => $gudang->id]))
            ->assertCreated()
            ->assertJsonPath('data.status', 'Aman')
            ->assertJsonPath('data.provider_department.name', 'SGA')
            ->assertJsonMissingPath('data.catalog')
            ->assertJsonPath('data.consumable_category_id', $category->id)
            ->assertJsonPath('data.category', 'ATK')
            ->assertJsonPath('data.room.name', 'Gudang')
            ->assertJsonPath('data.room.building.name', 'Gedung IHS')
            ->assertJsonPath('data.open_request', null)
            ->json('data.id');

        $this->getJson("/api/consumables/{$id}")->assertOk()->assertJsonPath('meta.used_in_rooms', []);
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

        $this->postJson('/api/consumables', $this->payload(['name' => ' PULPEN ', 'consumable_category_id' => 999, 'quantity' => -1]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'name' => 'Nama sudah digunakan.',
                'consumable_category_id',
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
        $atk = ConsumableCategory::factory()->create(['name' => 'ATK'])->id;
        $elektronik = ConsumableCategory::factory()->create(['name' => 'Elektronik'])->id;

        $this->consumable(['name' => 'Pulpen', 'consumable_category_id' => $atk, 'quantity' => 50, 'min_quantity' => 10]);
        $this->consumable(['name' => 'Pensil', 'consumable_category_id' => $atk, 'quantity' => 5, 'min_quantity' => 10]);
        $this->consumable(['name' => 'Baterai AA', 'consumable_category_id' => $elektronik, 'quantity' => 0, 'min_quantity' => 5,
            'provider_department_id' => Department::factory()->create(['name' => 'SIT'])->id]);

        $this->getJson('/api/consumables')->assertOk()->assertJsonCount(3, 'data')->assertJsonPath('data.0.name', 'Baterai AA');
        $this->getJson('/api/consumables?status=Habis')->assertJsonCount(1, 'data')->assertJsonPath('data.0.name', 'Baterai AA');
        $this->getJson('/api/consumables?status=Menipis')->assertJsonCount(1, 'data')->assertJsonPath('data.0.name', 'Pensil');
        $this->getJson('/api/consumables?status=Aman')->assertJsonCount(1, 'data')->assertJsonPath('data.0.name', 'Pulpen');
        $this->getJson('/api/consumables?category=atk')->assertJsonCount(2, 'data');
        $this->getJson("/api/consumables?provider_department_id={$this->sga->id}")->assertJsonCount(2, 'data');
        $this->getJson('/api/consumables?search=elektro')->assertJsonCount(1, 'data');
        $this->getJson('/api/consumables?sort=-category')->assertOk()->assertJsonPath('data.0.name', 'Baterai AA')->assertJsonPath('meta.total', 3);
        $this->getJson('/api/consumables?status=Kosong')->assertUnprocessable();
    }

    public function test_update_rejects_quantity(): void
    {
        $item = $this->consumable(['name' => 'Pulpen', 'quantity' => 20]);

        $this->putJson("/api/consumables/{$item->id}", ['quantity' => 99])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['quantity' => 'Jumlah diubah lewat transaksi stok.']);

        $this->putJson("/api/consumables/{$item->id}", ['name' => 'pulpen', 'min_quantity' => 30])
            ->assertOk()
            ->assertJsonPath('data.name', 'pulpen')
            ->assertJsonPath('data.quantity', 20)
            ->assertJsonPath('data.status', 'Menipis');
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

    public function test_location_is_a_room_and_list_filters_by_building_and_room(): void
    {
        $ihs = Building::factory()->create(['name' => 'Gedung IHS']);
        $gudang = Room::factory()->for($ihs)->create(['name' => 'Gudang']);
        $mh = Room::factory()->create(['name' => 'Gudang MH']);
        $this->consumable(['name' => 'Pulpen', 'room_id' => $gudang->id]);
        $this->consumable(['name' => 'Pensil', 'room_id' => $mh->id]);
        $this->consumable(['name' => 'Spidol']);

        $this->getJson("/api/consumables?building_id={$ihs->id}")->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.name', 'Pulpen');
        $this->getJson("/api/consumables?room_id={$mh->id}")->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.name', 'Pensil');

        $this->postJson('/api/consumables', $this->payload(['name' => 'Tinta', 'room_id' => 999]))->assertJsonValidationErrors('room_id');
        $this->postJson('/api/consumables', $this->payload(['name' => 'Tinta', 'storage_location' => 'Gudang']))->assertCreated()->assertJsonPath('data.room', null);
        $this->getJson('/api/lookups/storage-locations')->assertNotFound();
    }

    public function test_room_used_as_storage_location_cannot_be_deleted(): void
    {
        $gudang = Room::factory()->create(['name' => 'Gudang']);
        $item = $this->consumable(['name' => 'Pulpen', 'room_id' => $gudang->id]);
        $this->consumable(['name' => 'Pensil', 'room_id' => $gudang->id]);

        $this->getJson("/api/rooms/{$gudang->id}/usage")
            ->assertJsonPath('data.items', [['label' => 'barang stok (lokasi simpan)', 'count' => 2]])
            ->assertJsonPath('data.can_delete', false);
        $this->deleteJson("/api/rooms/{$gudang->id}")
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['id' => 'Ruangan masih dipakai: 2 barang stok (lokasi simpan).']);
        $this->assertModelExists($gudang);

        $item->update(['room_id' => null]);
        $this->getJson("/api/rooms/{$gudang->id}/usage")->assertJsonPath('data.items.0.count', 1);
    }

    public function test_received_request_adds_stock_and_transactions_still_work(): void
    {
        $item = $this->consumable(['name' => 'Pulpen', 'quantity' => 2, 'min_quantity' => 10]);
        $request = SupplyRequest::factory()->create(['consumable_id' => $item->id, 'quantity' => 8, 'provider_department_id' => $this->sga->id]);

        $this->patchJson("/api/supply-requests/{$request->id}/status", ['status' => 'Diterima'])
            ->assertOk()
            ->assertJsonPath('meta.impact.message', 'Stok Pulpen akan bertambah 8.');
        $this->assertSame(10, $item->fresh()->quantity);

        $this->postJson("/api/consumables/{$item->id}/transactions", ['type' => 'Pemakaian', 'quantity' => 1])->assertCreated()->assertJsonPath('meta.consumable.quantity', 9);
        $this->postJson("/api/consumables/{$item->id}/transactions", ['type' => 'Koreksi', 'quantity' => -6, 'note' => 'Hitung ulang'])->assertCreated()->assertJsonPath('meta.consumable.quantity', 3);

        $this->patchJson("/api/supply-requests/{$request->id}/status", ['status' => 'Dibatalkan'])
            ->assertOk()
            ->assertJsonPath('meta.impact.clamped', true);
        $this->assertSame(0, $item->fresh()->quantity);
    }

    public function test_usage_and_hard_delete_remove_room_standards(): void
    {
        $item = $this->consumable(['name' => 'Pulpen']);
        $class = Room::factory()->for(Building::factory()->create(['name' => 'Gedung IHS']))->create(['name' => 'Ruang Kelas 1']);
        $standard = RoomStandard::factory()->for($class)->create(['count_method' => CountMethod::Manual,
            'standard_quantity' => 20, 'actual_quantity' => 15, 'consumable_id' => $item->id,
        ]);
        $expected = [['room_id' => $class->id, 'room' => 'Ruang Kelas 1', 'building' => 'Gedung IHS', 'item_name' => 'Pulpen']];

        $this->getJson("/api/consumables/{$item->id}/usage")->assertOk()->assertExactJson(['data' => ['rooms' => $expected, 'delete_block_reason' => null]]);
        $this->getJson("/api/consumables/{$item->id}")->assertJsonPath('meta.used_in_rooms', $expected);

        $this->deleteJson("/api/consumables/{$item->id}")
            ->assertOk()
            ->assertExactJson(['message' => 'Barang Pulpen dihapus.', 'detached_rooms' => $expected]);

        $this->assertModelMissing($item);
        $this->assertModelMissing($standard);

        $this->getJson("/api/consumables/{$item->id}")->assertNotFound();
        $this->postJson('/api/consumables', $this->payload(['name' => 'Pulpen']))->assertCreated();
    }

    public function test_database_removes_standard_when_consumable_row_is_deleted_directly(): void
    {
        $item = $this->consumable();
        $standard = RoomStandard::factory()->create(['consumable_id' => $item->id]);

        Consumable::query()->whereKey($item->id)->delete();

        $this->assertModelMissing($standard);
    }
}
