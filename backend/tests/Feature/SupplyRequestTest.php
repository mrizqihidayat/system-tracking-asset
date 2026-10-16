<?php

namespace Tests\Feature;

use App\Enums\CountMethod;
use App\Enums\RequestStatus;
use App\Models\Consumable;
use App\Models\Department;
use App\Models\RequestCatalog;
use App\Models\Room;
use App\Models\RoomStandard;
use App\Models\SupplyRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SupplyRequestTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Department $shd;

    private Department $deptA;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->actingAs($this->user);
        $this->shd = Department::factory()->internal()->create(['name' => 'SHD']);
        $this->deptA = Department::factory()->create(['name' => 'Dept A']);
    }

    private function catalog(array $overrides = []): RequestCatalog
    {
        return RequestCatalog::factory()->create([
            'item_name' => 'Tisu',
            'provider_department_id' => $this->deptA->id,
            'lead_time_days' => 3,
            ...$overrides,
        ]);
    }

    private function supplyRequest(array $overrides = []): SupplyRequest
    {
        return SupplyRequest::factory()->create([
            'provider_department_id' => $this->deptA->id,
            'created_by' => $this->user->id,
            ...$overrides,
        ]);
    }

    public function test_endpoints_require_token(): void
    {
        $this->app['auth']->forgetGuards();

        $this->getJson('/api/request-catalog')->assertUnauthorized();
        $this->getJson('/api/supply-requests')->assertUnauthorized();
        $this->getJson('/api/supply-requests/suggestions')->assertUnauthorized();
    }

    public function test_catalog_crud_and_filters(): void
    {
        $response = $this->postJson('/api/request-catalog', [
            'item_name' => 'Proyektor',
            'provider_department_id' => $this->deptA->id,
            'request_method' => 'Telepon Ext. 12',
            'must_return' => true,
            'lead_time_days' => 2,
        ])->assertCreated()
            ->assertJsonPath('data.request_method', 'Telepon Ext. 12')
            ->assertJsonPath('data.must_return', true)
            ->assertJsonPath('data.provider_department.name', 'Dept A');

        $id = $response->json('data.id');
        $this->catalog();

        $this->getJson('/api/request-catalog?search=proy')->assertOk()->assertJsonCount(1, 'data');
        $this->getJson("/api/request-catalog?provider_department_id={$this->deptA->id}")->assertOk()->assertJsonCount(2, 'data');

        $this->putJson("/api/request-catalog/{$id}", [
            'item_name' => 'Proyektor LCD',
            'provider_department_id' => $this->deptA->id,
            'request_method' => 'Memo',
            'must_return' => false,
            'lead_time_days' => 5,
        ])->assertOk()->assertJsonPath('data.item_name', 'Proyektor LCD')->assertJsonPath('data.lead_time_days', 5);

        $this->deleteJson("/api/request-catalog/{$id}")->assertOk();
        $this->assertDatabaseMissing('request_catalog', ['id' => $id]);
    }

    public function test_catalog_validation(): void
    {
        $this->postJson('/api/request-catalog', ['request_method' => str_repeat('a', 51), 'lead_time_days' => -1])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['item_name', 'provider_department_id', 'request_method', 'must_return', 'lead_time_days']);
    }

    public function test_used_catalog_cannot_be_deleted(): void
    {
        $catalog = $this->catalog();
        $this->supplyRequest(['catalog_id' => $catalog->id]);

        $this->deleteJson("/api/request-catalog/{$catalog->id}")->assertUnprocessable()->assertJsonValidationErrors('id');
        $this->assertModelExists($catalog);

        $viaStandard = $this->catalog(['item_name' => 'Kursi']);
        RoomStandard::factory()->create(['source_department_id' => $this->deptA->id, 'catalog_id' => $viaStandard->id]);
        $this->deleteJson("/api/request-catalog/{$viaStandard->id}")->assertUnprocessable();
    }

    public function test_store_fills_item_and_provider_from_catalog(): void
    {
        $catalog = $this->catalog();

        $this->postJson('/api/supply-requests', ['catalog_id' => $catalog->id, 'quantity' => 4, 'purpose' => 'Rapat'])
            ->assertCreated()
            ->assertJsonPath('data.item_name', 'Tisu')
            ->assertJsonPath('data.status', 'Diajukan')
            ->assertJsonPath('data.request_date', today()->toDateString())
            ->assertJsonPath('data.provider_department.id', $this->deptA->id)
            ->assertJsonPath('data.created_by.id', $this->user->id);
    }

    public function test_store_without_catalog_requires_item_and_provider(): void
    {
        $this->postJson('/api/supply-requests', ['quantity' => 0])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['item_name', 'provider_department_id', 'quantity']);

        $this->postJson('/api/supply-requests', [
            'item_name' => 'Kabel HDMI',
            'quantity' => 2,
            'provider_department_id' => $this->deptA->id,
        ])->assertCreated()->assertJsonPath('data.catalog', null);
    }

    public function test_catalog_must_match_provider(): void
    {
        $catalog = $this->catalog();

        $this->postJson('/api/supply-requests', ['catalog_id' => $catalog->id, 'quantity' => 1, 'provider_department_id' => $this->shd->id])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('catalog_id');
    }

    public function test_update_only_while_diajukan(): void
    {
        $request = $this->supplyRequest(['item_name' => 'Tisu', 'quantity' => 2]);
        $payload = ['item_name' => 'Tisu besar', 'quantity' => 6, 'provider_department_id' => $this->deptA->id];

        $this->putJson("/api/supply-requests/{$request->id}", $payload)
            ->assertOk()
            ->assertJsonPath('data.item_name', 'Tisu besar')
            ->assertJsonPath('data.quantity', 6);

        $this->patchJson("/api/supply-requests/{$request->id}/status", ['status' => 'Diterima'])->assertOk();
        $this->putJson("/api/supply-requests/{$request->id}", $payload)->assertUnprocessable()->assertJsonValidationErrors('status');
    }

    public function test_status_transitions(): void
    {
        $catalog = $this->catalog(['must_return' => true]);
        $request = $this->supplyRequest(['catalog_id' => $catalog->id]);

        $this->patchJson("/api/supply-requests/{$request->id}/status", ['status' => 'Dikembalikan'])->assertUnprocessable();
        $this->patchJson("/api/supply-requests/{$request->id}/status", ['status' => 'Diajukan'])->assertUnprocessable();

        $this->patchJson("/api/supply-requests/{$request->id}/status", ['status' => 'Diterima'])
            ->assertOk()
            ->assertJsonPath('data.status', 'Diterima');
        $this->assertNotNull($request->fresh()->received_at);

        $this->patchJson("/api/supply-requests/{$request->id}/status", ['status' => 'Dikembalikan', 'note' => 'Sudah kembali'])
            ->assertOk()
            ->assertJsonPath('data.status', 'Dikembalikan')
            ->assertJsonPath('data.note', 'Sudah kembali');
        $this->assertNotNull($request->fresh()->returned_at);

        $this->patchJson("/api/supply-requests/{$request->id}/status", ['status' => 'Dibatalkan'])->assertUnprocessable();
    }

    public function test_return_requires_must_return_catalog(): void
    {
        $noReturn = $this->supplyRequest(['catalog_id' => $this->catalog()->id, 'status' => RequestStatus::Diterima]);
        $noCatalog = $this->supplyRequest(['status' => RequestStatus::Diterima]);

        $this->patchJson("/api/supply-requests/{$noReturn->id}/status", ['status' => 'Dikembalikan'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('status');
        $this->patchJson("/api/supply-requests/{$noCatalog->id}/status", ['status' => 'Dikembalikan'])->assertUnprocessable();
    }

    public function test_cancel_from_diajukan_and_diterima(): void
    {
        $first = $this->supplyRequest();
        $second = $this->supplyRequest(['status' => RequestStatus::Diterima]);

        $this->patchJson("/api/supply-requests/{$first->id}/status", ['status' => 'Dibatalkan'])->assertOk();
        $this->patchJson("/api/supply-requests/{$second->id}/status", ['status' => 'Dibatalkan'])->assertOk();
        $this->patchJson("/api/supply-requests/{$first->id}/status", ['status' => 'Diterima'])->assertUnprocessable();
    }

    public function test_unresponded_is_computed_from_lead_time(): void
    {
        $catalog = $this->catalog(['lead_time_days' => 3]);

        $late = $this->supplyRequest(['catalog_id' => $catalog->id, 'request_date' => today()->subDays(4)]);
        $onTime = $this->supplyRequest(['catalog_id' => $catalog->id, 'request_date' => today()->subDays(3)]);
        $received = $this->supplyRequest(['catalog_id' => $catalog->id, 'request_date' => today()->subDays(10), 'status' => RequestStatus::Diterima]);
        $noCatalog = $this->supplyRequest(['request_date' => today()->subDays(30)]);

        $this->getJson('/api/supply-requests?unresponded=1')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $late->id)
            ->assertJsonPath('data.0.is_unresponded', true)
            ->assertJsonPath('data.0.display_status', 'Belum direspons')
            ->assertJsonPath('data.0.status', 'Diajukan');

        $list = $this->getJson('/api/supply-requests')->assertOk()->json('data');
        $byId = collect($list)->keyBy('id');

        $this->assertFalse($byId[$onTime->id]['is_unresponded']);
        $this->assertSame('Diajukan', $byId[$onTime->id]['display_status']);
        $this->assertSame('Diterima', $byId[$received->id]['display_status']);
        $this->assertFalse($byId[$noCatalog->id]['is_unresponded']);
        $this->assertNull($byId[$noCatalog->id]['response_due_date']);
    }

    public function test_list_filters_and_order(): void
    {
        $room = Room::factory()->create();
        $old = $this->supplyRequest(['item_name' => 'Spidol', 'request_date' => '2026-09-01', 'status' => RequestStatus::Diterima]);
        $new = $this->supplyRequest(['item_name' => 'Tisu', 'request_date' => '2026-10-01', 'room_id' => $room->id, 'purpose' => 'Seminar']);

        $this->getJson('/api/supply-requests')->assertOk()->assertJsonPath('data.0.id', $new->id);
        $this->getJson('/api/supply-requests?status=Diterima')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $old->id);
        $this->getJson("/api/supply-requests?room_id={$room->id}")->assertOk()->assertJsonCount(1, 'data');
        $this->getJson('/api/supply-requests?search=semin')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $new->id);
        $this->getJson('/api/supply-requests?from=2026-09-15&to=2026-10-31')->assertOk()->assertJsonCount(1, 'data');
        $this->getJson('/api/supply-requests?status=Salah')->assertUnprocessable();
    }

    public function test_suggestions_from_room_shortage_and_stock(): void
    {
        $room = Room::factory()->create(['name' => 'Ruang 1']);
        $catalog = $this->catalog();

        // Kurang 3 → masuk saran.
        RoomStandard::factory()->create([
            'room_id' => $room->id,
            'source_department_id' => $this->deptA->id,
            'standard_quantity' => 5,
            'count_method' => CountMethod::Manual,
            'actual_quantity' => 2,
            'catalog_id' => $catalog->id,
        ]);
        // Belum dicek → tidak masuk.
        RoomStandard::factory()->create([
            'room_id' => $room->id,
            'source_department_id' => $this->deptA->id,
            'standard_quantity' => 5,
            'count_method' => CountMethod::Manual,
            'actual_quantity' => null,
            'catalog_id' => $this->catalog(['item_name' => 'Kursi'])->id,
        ]);
        // Sesuai → tidak masuk.
        RoomStandard::factory()->create([
            'room_id' => $room->id,
            'source_department_id' => $this->deptA->id,
            'standard_quantity' => 5,
            'count_method' => CountMethod::Manual,
            'actual_quantity' => 5,
            'catalog_id' => $this->catalog(['item_name' => 'Meja'])->id,
        ]);

        Consumable::factory()->create(['name' => 'Spidol', 'quantity' => 0, 'min_quantity' => 10, 'provider_department_id' => $this->deptA->id]);
        Consumable::factory()->create(['name' => 'Kertas', 'quantity' => 4, 'min_quantity' => 10, 'provider_department_id' => $this->deptA->id, 'catalog_id' => $catalog->id]);
        Consumable::factory()->create(['name' => 'Map', 'quantity' => 50, 'min_quantity' => 10, 'provider_department_id' => $this->deptA->id]);

        $response = $this->getJson('/api/supply-requests/suggestions')->assertOk();

        $response->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.provider_department.name', 'Dept A')
            ->assertJsonCount(3, 'data.0.items');

        $items = collect($response->json('data.0.items'))->keyBy(fn ($i) => $i['source'].':'.$i['item_name']);

        $this->assertSame(3, $items['kekurangan_ruangan:Tisu']['quantity']);
        $this->assertSame('Ruang 1', $items['kekurangan_ruangan:Tisu']['room']['name']);
        $this->assertSame($catalog->id, $items['kekurangan_ruangan:Tisu']['catalog_id']);
        $this->assertSame(10, $items['stok:Spidol']['quantity']);
        $this->assertSame('Habis', $items['stok:Spidol']['stock_status']);
        $this->assertSame(6, $items['stok:Tisu']['quantity']);
        $this->assertSame('Menipis', $items['stok:Tisu']['stock_status']);
    }

    public function test_suggestions_flag_open_requests(): void
    {
        $room = Room::factory()->create();
        $catalog = $this->catalog();
        RoomStandard::factory()->create([
            'room_id' => $room->id,
            'source_department_id' => $this->deptA->id,
            'standard_quantity' => 5,
            'count_method' => CountMethod::Manual,
            'actual_quantity' => 0,
            'catalog_id' => $catalog->id,
        ]);
        Consumable::factory()->create(['name' => 'Spidol', 'quantity' => 0, 'min_quantity' => 5, 'provider_department_id' => $this->deptA->id]);

        $this->supplyRequest(['catalog_id' => $catalog->id, 'room_id' => $room->id, 'item_name' => 'Tisu']);
        $this->supplyRequest(['item_name' => 'spidol', 'status' => RequestStatus::Diterima]);

        $items = collect($this->getJson('/api/supply-requests/suggestions')->assertOk()->json('data.0.items'))->keyBy('item_name');

        $this->assertTrue($items['Tisu']['has_open_request']);
        $this->assertFalse($items['Spidol']['has_open_request']);
    }

    public function test_suggestions_empty_when_nothing_needed(): void
    {
        $this->getJson('/api/supply-requests/suggestions')->assertOk()->assertExactJson(['data' => []]);
    }
}
