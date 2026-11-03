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
            'must_return' => true,
            'lead_time_days' => 2,
        ])->assertCreated()
            ->assertJsonMissingPath('data.request_method')
            ->assertJsonPath('data.must_return', true)
            ->assertJsonPath('data.provider_department.name', 'Dept A');

        $id = $response->json('data.id');
        $this->catalog();

        $this->getJson('/api/request-catalog?search=proy')->assertOk()->assertJsonCount(1, 'data');
        $this->getJson("/api/request-catalog?provider_department_id={$this->deptA->id}")->assertOk()->assertJsonCount(2, 'data');

        $this->putJson("/api/request-catalog/{$id}", [
            'item_name' => 'Proyektor LCD',
            'provider_department_id' => $this->deptA->id,
            'must_return' => false,
            'lead_time_days' => 5,
        ])->assertOk()->assertJsonPath('data.item_name', 'Proyektor LCD')->assertJsonPath('data.lead_time_days', 5);

        $this->deleteJson("/api/request-catalog/{$id}")->assertOk();
        $this->assertDatabaseMissing('request_catalog', ['id' => $id]);
    }

    public function test_catalog_validation(): void
    {
        $this->postJson('/api/request-catalog', ['lead_time_days' => -1])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['item_name', 'provider_department_id', 'must_return', 'lead_time_days'])
            ->assertJsonMissingValidationErrors('request_method');
    }

    public function test_catalog_usage_counts_links(): void
    {
        $catalog = $this->catalog();
        $this->supplyRequest(['catalog_id' => $catalog->id]);
        $this->supplyRequest(['catalog_id' => $catalog->id]);
        RoomStandard::factory()->create(['source_department_id' => $this->deptA->id, 'catalog_id' => $catalog->id]);
        Consumable::factory()->create(['provider_department_id' => $this->deptA->id, 'catalog_id' => $catalog->id]);

        $this->getJson("/api/request-catalog/{$catalog->id}/usage")
            ->assertOk()
            ->assertExactJson(['data' => ['supply_requests' => 2, 'room_standards' => 1, 'consumables' => 1, 'total' => 4]]);
        $this->getJson('/api/request-catalog/999/usage')->assertNotFound();
    }

    public function test_used_catalog_is_deleted_and_links_are_detached(): void
    {
        $catalog = $this->catalog(['must_return' => true, 'lead_time_days' => 2]);
        $request = SupplyRequest::query()->findOrFail(
            $this->postJson('/api/supply-requests', ['catalog_id' => $catalog->id, 'quantity' => 3, 'request_method' => 'Memo'])->assertCreated()->json('data.id'),
        );
        $standard = RoomStandard::factory()->create(['source_department_id' => $this->deptA->id, 'catalog_id' => $catalog->id]);
        $stock = Consumable::factory()->create(['provider_department_id' => $this->deptA->id, 'catalog_id' => $catalog->id]);

        $this->deleteJson("/api/request-catalog/{$catalog->id}")->assertOk();

        $this->assertModelMissing($catalog);
        $this->assertNull($standard->fresh()->catalog_id);
        $this->assertNull($stock->fresh()->catalog_id);

        // Riwayat tetap utuh dari snapshot: item, unit, cara, lead time, perlu dikembalikan.
        $request->refresh();
        $this->assertNull($request->catalog_id);
        $this->assertSame([$catalog->item_name, $this->deptA->id, 'Memo', 2, true], [
            $request->item_name, $request->provider_department_id, $request->request_method, $request->lead_time_days, $request->must_return,
        ]);
        $this->getJson('/api/supply-requests')->assertOk()
            ->assertJsonPath('data.0.catalog', null)
            ->assertJsonPath('data.0.lead_time_days', 2)
            ->assertJsonPath('data.0.response_due_date', today()->addDays(2)->toDateString());

        // Masih bisa Diterima → Dikembalikan walau katalognya sudah dihapus.
        $this->patchJson("/api/supply-requests/{$request->id}/status", ['status' => 'Diterima'])->assertOk();
        $this->patchJson("/api/supply-requests/{$request->id}/status", ['status' => 'Dikembalikan'])->assertOk();
    }

    public function test_snapshot_is_kept_when_catalog_is_edited(): void
    {
        $catalog = $this->catalog(['lead_time_days' => 1]);
        $id = $this->postJson('/api/supply-requests', ['catalog_id' => $catalog->id, 'quantity' => 1, 'request_method' => 'Email', 'request_date' => today()->subDays(3)->toDateString()])
            ->assertCreated()->json('data.id');

        $catalog->update(['lead_time_days' => 30, 'must_return' => true]);

        $this->getJson('/api/supply-requests')->assertOk()
            ->assertJsonPath('data.0.lead_time_days', 1)
            ->assertJsonPath('data.0.must_return', false)
            ->assertJsonPath('data.0.display_status', 'Belum direspons');
        $this->getJson('/api/supply-requests?unresponded=1')->assertOk()->assertJsonCount(1, 'data');

        // Diubah selama Diajukan → snapshot diambil ulang dari katalog terbaru.
        $this->putJson("/api/supply-requests/{$id}", ['catalog_id' => $catalog->id, 'quantity' => 2, 'request_method' => 'Email', 'request_date' => today()->subDays(3)->toDateString()])
            ->assertOk()
            ->assertJsonPath('data.lead_time_days', 30)
            ->assertJsonPath('data.must_return', true)
            ->assertJsonPath('data.display_status', 'Diajukan');
    }

    public function test_store_fills_item_and_provider_from_catalog(): void
    {
        $catalog = $this->catalog();

        $this->postJson('/api/supply-requests', ['catalog_id' => $catalog->id, 'quantity' => 4, 'purpose' => 'Rapat', 'request_method' => 'WhatsApp'])
            ->assertCreated()
            ->assertJsonPath('data.item_name', 'Tisu')
            ->assertJsonPath('data.request_method', 'WhatsApp')
            ->assertJsonPath('data.status', 'Diajukan')
            ->assertJsonPath('data.request_date', today()->toDateString())
            ->assertJsonPath('data.provider_department.id', $this->deptA->id)
            ->assertJsonPath('data.created_by.id', $this->user->id);
    }

    public function test_store_without_catalog_requires_item_and_provider(): void
    {
        $this->postJson('/api/supply-requests', ['quantity' => 0])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['item_name', 'provider_department_id', 'quantity', 'request_method']);

        $this->postJson('/api/supply-requests', [
            'item_name' => 'Kabel HDMI',
            'quantity' => 2,
            'provider_department_id' => $this->deptA->id,
            'request_method' => 'Telepon Ext. 12',
        ])->assertCreated()->assertJsonPath('data.catalog', null)->assertJsonPath('data.request_method', 'Telepon Ext. 12');
    }

    public function test_request_method_is_required_and_limited(): void
    {
        $catalog = $this->catalog();

        $this->postJson('/api/supply-requests', ['catalog_id' => $catalog->id, 'quantity' => 1])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('request_method');

        $this->postJson('/api/supply-requests', ['catalog_id' => $catalog->id, 'quantity' => 1, 'request_method' => str_repeat('a', 51)])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('request_method');
    }

    public function test_catalog_must_match_provider(): void
    {
        $catalog = $this->catalog();

        $this->postJson('/api/supply-requests', ['catalog_id' => $catalog->id, 'quantity' => 1, 'provider_department_id' => $this->shd->id, 'request_method' => 'Memo'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('catalog_id');
    }

    public function test_update_only_while_diajukan(): void
    {
        $request = $this->supplyRequest(['item_name' => 'Tisu', 'quantity' => 2]);
        $payload = ['item_name' => 'Tisu besar', 'quantity' => 6, 'provider_department_id' => $this->deptA->id, 'request_method' => 'Email'];

        $this->putJson("/api/supply-requests/{$request->id}", $payload)
            ->assertOk()
            ->assertJsonPath('data.item_name', 'Tisu besar')
            ->assertJsonPath('data.request_method', 'Email')
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
        $notStored = fn (string $name, ?int $catalogId = null) => Consumable::factory()->create([
            'name' => $name, 'quantity' => 0, 'min_quantity' => 0, 'is_stored' => false,
            'provider_department_id' => $this->deptA->id, 'catalog_id' => $catalogId,
        ]);
        $standard = fn (Consumable $consumable, ?int $actual) => RoomStandard::factory()->create([
            'room_id' => $room->id,
            'source_department_id' => $this->deptA->id,
            'standard_quantity' => 5,
            'count_method' => CountMethod::Manual,
            'actual_quantity' => $actual,
            'consumable_id' => $consumable->id,
        ]);

        // Kurang 3 → masuk saran.
        $standard($notStored('Tisu', $catalog->id), 2);
        // Belum dicek → tidak masuk.
        $standard($notStored('Kursi'), null);
        // Sesuai → tidak masuk.
        $standard($notStored('Meja'), 5);
        // Baris lama tanpa barang stok → tidak masuk sampai dilengkapi.
        RoomStandard::factory()->create([
            'room_id' => $room->id, 'source_department_id' => $this->deptA->id, 'standard_quantity' => 5,
            'count_method' => CountMethod::Manual, 'actual_quantity' => 0, 'catalog_id' => $this->catalog(['item_name' => 'Lama'])->id,
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

    public function test_room_suggestion_follows_stock_and_is_not_duplicated(): void
    {
        $room = Room::factory()->create(['name' => 'Ruang 1']);
        $inStock = Consumable::factory()->create(['name' => 'Pulpen', 'quantity' => 5, 'min_quantity' => 1, 'provider_department_id' => $this->deptA->id]);
        $empty = Consumable::factory()->create(['name' => 'Spidol', 'quantity' => 0, 'min_quantity' => 10, 'provider_department_id' => $this->deptA->id]);

        foreach ([$inStock, $empty] as $consumable) {
            RoomStandard::factory()->create([
                'room_id' => $room->id, 'source_department_id' => $this->deptA->id, 'standard_quantity' => 4,
                'count_method' => CountMethod::Manual, 'actual_quantity' => 1, 'consumable_id' => $consumable->id,
            ]);
        }

        $items = $this->getJson('/api/supply-requests/suggestions')->assertOk()->json('data.0.items');

        // Stok masih ada → Isi dari stok, bukan saran. Stok habis → satu saran dari ruangan, tanpa saran stok ganda.
        $this->assertCount(1, $items);
        $this->assertSame('kekurangan_ruangan', $items[0]['source']);
        $this->assertSame('Spidol', $items[0]['item_name']);
        $this->assertSame(3, $items[0]['quantity']);
        $this->assertSame($empty->id, $items[0]['consumable_id']);
    }

    public function test_suggestions_flag_open_requests(): void
    {
        $room = Room::factory()->create();
        $catalog = $this->catalog();
        $tisu = Consumable::factory()->create([
            'name' => 'Tisu', 'quantity' => 0, 'min_quantity' => 0, 'is_stored' => false,
            'provider_department_id' => $this->deptA->id, 'catalog_id' => $catalog->id,
        ]);
        RoomStandard::factory()->create([
            'room_id' => $room->id,
            'source_department_id' => $this->deptA->id,
            'standard_quantity' => 5,
            'count_method' => CountMethod::Manual,
            'actual_quantity' => 0,
            'consumable_id' => $tisu->id,
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
