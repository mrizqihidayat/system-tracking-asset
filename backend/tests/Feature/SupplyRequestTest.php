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
use Illuminate\Testing\TestResponse;
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

        $this->getJson("/api/request-catalog/{$catalog->id}/usage")
            ->assertOk()
            ->assertExactJson(['data' => ['supply_requests' => 2, 'total' => 2]]);
        $this->getJson('/api/request-catalog/999/usage')->assertNotFound();
    }

    public function test_used_catalog_is_deleted_and_links_are_detached(): void
    {
        $catalog = $this->catalog(['must_return' => true, 'lead_time_days' => 2]);
        $request = SupplyRequest::query()->findOrFail(
            $this->postJson('/api/supply-requests', ['catalog_id' => $catalog->id, 'quantity' => 3, 'request_method' => 'Memo'])->assertCreated()->json('data.id'),
        );

        $this->deleteJson("/api/request-catalog/{$catalog->id}")->assertOk();

        $this->assertModelMissing($catalog);

        // Riwayat tetap utuh dari snapshot: item, departemen, cara, lead time, perlu dikembalikan.
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

    public function test_suggestions_come_only_from_low_stock(): void
    {
        $room = Room::factory()->create(['name' => 'Ruang 1']);

        Consumable::factory()->create(['name' => 'Spidol', 'quantity' => 0, 'min_quantity' => 10, 'provider_department_id' => $this->deptA->id]);
        Consumable::factory()->create(['name' => 'Kertas', 'quantity' => 4, 'min_quantity' => 10, 'provider_department_id' => $this->deptA->id]);
        $aman = Consumable::factory()->create(['name' => 'Map', 'quantity' => 50, 'min_quantity' => 10, 'provider_department_id' => $this->deptA->id]);

        // Kekurangan ruangan tidak menjadi saran selama stoknya aman.
        RoomStandard::factory()->create(['room_id' => $room->id, 'consumable_id' => $aman->id, 'standard_quantity' => 5, 'actual_quantity' => 0]);

        $response = $this->getJson('/api/supply-requests/suggestions')->assertOk();

        $response->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.provider_department.name', 'Dept A')
            ->assertJsonCount(2, 'data.0.items');

        $items = collect($response->json('data.0.items'))->keyBy('item_name');

        $this->assertSame(10, $items['Spidol']['quantity']);
        $this->assertSame('Habis', $items['Spidol']['stock_status']);
        $this->assertSame(6, $items['Kertas']['quantity']);
        $this->assertSame('Menipis', $items['Kertas']['stock_status']);
        $this->assertArrayNotHasKey('room', $items['Kertas']);
        $this->assertArrayNotHasKey('catalog_id', $items['Kertas']);
    }

    public function test_room_shortage_with_empty_stock_appears_once(): void
    {
        $empty = Consumable::factory()->create(['name' => 'Spidol', 'quantity' => 0, 'min_quantity' => 10, 'provider_department_id' => $this->deptA->id]);
        RoomStandard::factory()->create(['consumable_id' => $empty->id, 'standard_quantity' => 4, 'actual_quantity' => 1]);

        $items = $this->getJson('/api/supply-requests/suggestions')->assertOk()->json('data.0.items');

        $this->assertCount(1, $items);
        $this->assertSame($empty->id, $items[0]['consumable_id']);
        $this->assertSame(10, $items[0]['quantity']);
    }

    public function test_suggestions_flag_open_requests(): void
    {
        Consumable::factory()->create(['name' => 'Tisu', 'quantity' => 0, 'min_quantity' => 5, 'provider_department_id' => $this->deptA->id]);
        Consumable::factory()->create(['name' => 'Spidol', 'quantity' => 0, 'min_quantity' => 5, 'provider_department_id' => $this->deptA->id]);

        // Permintaan tanpa tautan dicocokkan lewat nama; yang sudah Diterima tidak dihitung.
        $this->supplyRequest(['item_name' => 'tisu']);
        $this->supplyRequest(['item_name' => 'spidol', 'status' => RequestStatus::Diterima]);

        $items = collect($this->getJson('/api/supply-requests/suggestions')->assertOk()->json('data.0.items'))->keyBy('item_name');

        $this->assertTrue($items['Tisu']['has_open_request']);
        $this->assertFalse($items['Spidol']['has_open_request']);
    }

    public function test_open_request_flag_is_per_item_when_linked_to_stock(): void
    {
        $pensil = Consumable::factory()->create(['name' => 'Pensil', 'quantity' => 1, 'min_quantity' => 5, 'provider_department_id' => $this->deptA->id]);
        Consumable::factory()->create(['name' => 'Spidol', 'quantity' => 1, 'min_quantity' => 5, 'provider_department_id' => $this->deptA->id]);

        $this->supplyRequest(['consumable_id' => $pensil->id, 'item_name' => 'Pensil']);

        $items = collect($this->getJson('/api/supply-requests/suggestions')->assertOk()->json('data.0.items'))->keyBy('item_name');

        $this->assertTrue($items['Pensil']['has_open_request']);
        $this->assertFalse($items['Spidol']['has_open_request']);
    }

    // ---------- status mengubah stok (diterima sebagian & kartu stok: StockTransactionTest) ----------

    private function setStatus(SupplyRequest $request, string $status): TestResponse
    {
        return $this->patchJson("/api/supply-requests/{$request->id}/status", ['status' => $status]);
    }

    public function test_must_return_choice_from_form_defaults_to_catalog(): void
    {
        $returned = $this->catalog(['must_return' => true]);
        $kept = $this->catalog(['item_name' => 'Kursi', 'must_return' => false]);
        $base = ['request_method' => 'Memo', 'quantity' => 1];

        // Tidak dikirim: mengikuti katalog.
        $this->postJson('/api/supply-requests', [...$base, 'catalog_id' => $returned->id])->assertCreated()->assertJsonPath('data.must_return', true);
        $this->postJson('/api/supply-requests', [...$base, 'catalog_id' => $kept->id])->assertCreated()->assertJsonPath('data.must_return', false);

        // Pilihan di form yang disimpan, walau berbeda dari katalog.
        $this->postJson('/api/supply-requests', [...$base, 'catalog_id' => $returned->id, 'must_return' => false])->assertCreated()->assertJsonPath('data.must_return', false);
        $this->postJson('/api/supply-requests', [...$base, 'catalog_id' => $kept->id, 'must_return' => true])->assertCreated()->assertJsonPath('data.must_return', true);

        // Di luar katalog: default tidak dikembalikan, boleh pilih dikembalikan; pilihan menentukan aksi Dikembalikan.
        $manual = [...$base, 'item_name' => 'Tenda', 'provider_department_id' => $this->deptA->id];
        $this->postJson('/api/supply-requests', $manual)->assertCreated()->assertJsonPath('data.must_return', false);
        $id = $this->postJson('/api/supply-requests', [...$manual, 'must_return' => true])
            ->assertCreated()->assertJsonPath('data.must_return', true)->json('data.id');

        $this->patchJson("/api/supply-requests/{$id}/status", ['status' => 'Diterima'])->assertOk();
        $this->patchJson("/api/supply-requests/{$id}/status", ['status' => 'Dikembalikan'])->assertOk();

        $this->postJson('/api/supply-requests', [...$manual, 'must_return' => 'mungkin'])->assertJsonValidationErrors('must_return');
    }

    /**
     * @return array<string, mixed>
     */
    private function stockRequestPayload(Consumable $consumable, array $overrides = []): array
    {
        return [
            'consumable_id' => $consumable->id,
            'item_name' => $consumable->name,
            'provider_department_id' => $this->deptA->id,
            'quantity' => 5,
            'request_method' => 'Memo',
            ...$overrides,
        ];
    }

    public function test_stock_request_has_no_catalog_lead_time_or_unresponded_status(): void
    {
        $pensil = Consumable::factory()->create(['name' => 'Pensil 2B', 'provider_department_id' => $this->deptA->id]);

        $id = $this->postJson('/api/supply-requests', $this->stockRequestPayload($pensil, [
            'item_name' => 'Nama lain',
            'request_date' => today()->subYear()->toDateString(),
        ]))
            ->assertCreated()
            ->assertJsonPath('data.item_name', 'Pensil 2B')
            ->assertJsonPath('data.consumable_id', $pensil->id)
            ->assertJsonPath('data.catalog', null)
            ->assertJsonPath('data.lead_time_days', null)
            ->assertJsonPath('data.must_return', false)
            ->assertJsonPath('data.is_unresponded', false)
            ->assertJsonPath('data.display_status', 'Diajukan')
            ->json('data.id');

        $this->getJson('/api/supply-requests?unresponded=1')->assertOk()->assertJsonMissing(['id' => $id]);
    }

    public function test_stock_request_rejects_catalog(): void
    {
        $pensil = Consumable::factory()->create(['name' => 'Pensil 2B', 'provider_department_id' => $this->deptA->id]);

        $this->postJson('/api/supply-requests', $this->stockRequestPayload($pensil, ['catalog_id' => $this->catalog()->id]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['consumable_id' => 'Barang stok tidak memakai katalog.']);

        $this->assertDatabaseCount('supply_requests', 0);
    }

    public function test_stock_request_updates_stock_when_status_changes_and_stops_at_zero(): void
    {
        $pensil = Consumable::factory()->create(['name' => 'Pensil 2B', 'quantity' => 2, 'provider_department_id' => $this->deptA->id]);
        $request = fn (array $overrides = []) => SupplyRequest::query()->findOrFail(
            $this->postJson('/api/supply-requests', $this->stockRequestPayload($pensil, $overrides))->assertCreated()->json('data.id'),
        );

        $canceled = $request();
        $this->setStatus($canceled, 'Diterima')->assertOk();
        $this->assertSame(7, $pensil->fresh()->quantity);
        $this->setStatus($canceled, 'Dibatalkan')->assertOk();
        $this->assertSame(2, $pensil->fresh()->quantity);

        // Dikembalikan boleh dipilih di form; stok tidak turun di bawah 0.
        $returned = $request(['must_return' => true]);
        $this->setStatus($returned, 'Diterima')->assertOk();
        $this->postJson("/api/consumables/{$pensil->id}/transactions", ['type' => 'Koreksi', 'quantity' => -4, 'note' => 'Terpakai'])->assertCreated();
        $this->setStatus($returned, 'Dikembalikan')->assertOk();
        $this->assertSame(0, $pensil->fresh()->quantity);
    }

    public function test_received_stock_request_clears_suggestion(): void
    {
        $tisu = Consumable::factory()->create(['name' => 'Tisu', 'quantity' => 0, 'min_quantity' => 2, 'provider_department_id' => $this->deptA->id]);

        $this->assertNotEmpty($this->getJson('/api/supply-requests/suggestions')->json('data'));

        $request = $this->supplyRequest(['consumable_id' => $tisu->id, 'item_name' => 'Tisu', 'quantity' => 5]);
        $this->assertTrue($this->getJson('/api/supply-requests/suggestions')->json('data.0.items.0.has_open_request'));

        $this->setStatus($request, 'Diterima')->assertOk()->assertJsonPath('meta.impact.message', 'Stok Tisu akan bertambah 5.');

        $this->assertSame([], $this->getJson('/api/supply-requests/suggestions')->json('data'));
    }

    public function test_history_can_be_filtered_by_id(): void
    {
        $first = $this->supplyRequest();
        $this->supplyRequest();

        $this->getJson("/api/supply-requests?id={$first->id}")->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $first->id);
        $this->getJson('/api/supply-requests?id=abc')->assertJsonValidationErrors('id');
    }

    public function test_request_quantity_above_integer_column_limit_is_rejected(): void
    {
        $this->postJson('/api/supply-requests', [
            'item_name' => 'Tisu', 'provider_department_id' => $this->deptA->id, 'request_method' => 'Email', 'quantity' => 2147483648,
        ])->assertUnprocessable()->assertJsonValidationErrors('quantity');

        $this->assertDatabaseCount('supply_requests', 0);
    }

    public function test_received_request_cannot_push_stock_over_integer_column_limit(): void
    {
        $pulpen = Consumable::factory()->create(['quantity' => 2147483647, 'provider_department_id' => $this->deptA->id]);
        $request = $this->supplyRequest(['consumable_id' => $pulpen->id, 'quantity' => 1]);

        $this->setStatus($request, 'Diterima')->assertUnprocessable()->assertJsonPath('errors.quantity.0', 'Jumlah stok melebihi batas.');

        $this->assertSame(2147483647, $pulpen->fresh()->quantity);
        $this->assertSame('Diajukan', $request->fresh()->status->value);
    }

    public function test_received_stored_item_adds_stock_only(): void
    {
        $room = Room::factory()->create();
        $pulpen = Consumable::factory()->create(['name' => 'Pulpen', 'quantity' => 1, 'min_quantity' => 0, 'provider_department_id' => $this->deptA->id]);
        $standard = RoomStandard::factory()->create([
            'room_id' => $room->id, 'standard_quantity' => 10,
            'count_method' => CountMethod::Manual, 'actual_quantity' => 1, 'consumable_id' => $pulpen->id,
        ]);
        $request = $this->supplyRequest(['consumable_id' => $pulpen->id, 'room_id' => $room->id, 'quantity' => 5]);

        $this->getJson("/api/supply-requests/{$request->id}/status-impact?status=Diterima")
            ->assertJsonPath('data.message', 'Stok Pulpen akan bertambah 5.');
        $this->setStatus($request, 'Diterima')->assertOk();

        $this->assertSame(6, $pulpen->fresh()->quantity);
        $this->assertSame(1, $standard->fresh()->actual_quantity);
    }

    public function test_returned_or_cancelled_after_received_reverses_and_stops_at_zero(): void
    {
        $catalogItem = Consumable::factory()->create(['name' => 'Tisu', 'quantity' => 0, 'min_quantity' => 0, 'provider_department_id' => $this->deptA->id]);
        $returned = $this->supplyRequest(['consumable_id' => $catalogItem->id, 'quantity' => 3, 'must_return' => true]);

        $this->setStatus($returned, 'Diterima')->assertOk();
        $this->assertSame(3, $catalogItem->fresh()->quantity);
        $this->setStatus($returned, 'Dikembalikan')->assertOk()->assertJsonPath('meta.impact.clamped', false);
        $this->assertSame(0, $catalogItem->fresh()->quantity);

        // Stok sudah terpakai: berhenti di 0 dan minta dicek manual.
        $pulpen = Consumable::factory()->create(['name' => 'Pulpen', 'quantity' => 0, 'min_quantity' => 0, 'provider_department_id' => $this->deptA->id]);
        $request = $this->supplyRequest(['consumable_id' => $pulpen->id, 'quantity' => 5]);
        $this->setStatus($request, 'Diterima')->assertOk();
        $pulpen->update(['quantity' => 3]);

        $this->getJson("/api/supply-requests/{$request->id}/status-impact?status=Dibatalkan")
            ->assertExactJson(['data' => ['message' => 'Stok Pulpen hanya tersisa 3, jadi berkurang 3 sampai 0. Cek manual selisihnya.', 'clamped' => true]]);
        $this->setStatus($request, 'Dibatalkan')->assertOk()->assertJsonPath('meta.impact.clamped', true);
        $this->assertSame(0, $pulpen->fresh()->quantity);
    }

    public function test_cancel_before_received_and_unlinked_requests_change_nothing(): void
    {
        $tisu = Consumable::factory()->create(['name' => 'Tisu', 'quantity' => 1, 'provider_department_id' => $this->deptA->id]);
        $room = Room::factory()->create();

        $cancelled = $this->supplyRequest(['consumable_id' => $tisu->id, 'room_id' => $room->id, 'quantity' => 2]);
        $this->setStatus($cancelled, 'Dibatalkan')->assertOk()->assertJsonPath('meta.impact.message', 'Tidak mengubah stok.');

        $unlinked = $this->supplyRequest(['room_id' => $room->id, 'item_name' => 'Tisu', 'quantity' => 2]);
        $this->setStatus($unlinked, 'Diterima')->assertOk();
        $this->setStatus($unlinked, 'Dibatalkan')->assertOk();

        $this->assertSame(1, $tisu->fresh()->quantity);
    }

    public function test_status_impact_rejects_invalid_transition(): void
    {
        $request = $this->supplyRequest(['status' => RequestStatus::Dibatalkan]);

        $this->getJson("/api/supply-requests/{$request->id}/status-impact?status=Diterima")->assertJsonValidationErrors('status');
        $this->getJson("/api/supply-requests/{$request->id}/status-impact")->assertJsonValidationErrors('status');
    }

    public function test_suggestions_empty_when_nothing_needed(): void
    {
        $this->getJson('/api/supply-requests/suggestions')->assertOk()->assertExactJson(['data' => []]);
    }

    public function test_status_counts_are_disjoint_and_follow_other_filters(): void
    {
        $catalog = $this->catalog(['lead_time_days' => 3]);
        $deptB = Department::factory()->create(['name' => 'Dept B']);
        $this->supplyRequest(['catalog_id' => $catalog->id, 'request_date' => today()->subDays(4)]);
        $this->supplyRequest(['catalog_id' => $catalog->id, 'request_date' => today()]);
        $this->supplyRequest(['status' => RequestStatus::DiterimaSebagian]);
        $this->supplyRequest(['status' => RequestStatus::Diterima]);
        $this->supplyRequest(['status' => RequestStatus::Diterima, 'provider_department_id' => $deptB->id]);

        $this->getJson('/api/supply-requests?display_status=Diterima')->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('meta.status_counts', [
                'Semua' => 5, 'Diajukan' => 1, 'Belum direspons' => 1, 'Diterima sebagian' => 1,
                'Diterima' => 2, 'Dikembalikan' => 0, 'Dibatalkan' => 0,
            ])
            ->assertJsonPath('meta.total', 2);

        // Filter penyedia ikut membatasi angka chip.
        $this->getJson("/api/supply-requests?provider_department_id={$deptB->id}")->assertOk()
            ->assertJsonPath('meta.status_counts.Semua', 1)
            ->assertJsonPath('meta.status_counts.Diterima', 1);
    }

    public function test_display_status_diajukan_excludes_unresponded(): void
    {
        $catalog = $this->catalog(['lead_time_days' => 3]);
        $late = $this->supplyRequest(['catalog_id' => $catalog->id, 'request_date' => today()->subDays(4)]);
        $onTime = $this->supplyRequest(['catalog_id' => $catalog->id, 'request_date' => today()]);
        $noCatalog = $this->supplyRequest(['request_date' => today()->subDays(30)]);

        $ids = fn (string $display) => collect($this->getJson('/api/supply-requests?display_status='.urlencode($display))->assertOk()->json('data'))
            ->pluck('id')->sort()->values()->all();

        $this->assertSame(collect([$onTime->id, $noCatalog->id])->sort()->values()->all(), $ids('Diajukan'));
        $this->assertSame([$late->id], $ids('Belum direspons'));
        $this->getJson('/api/supply-requests?display_status=Lain')->assertUnprocessable()->assertJsonValidationErrors('display_status');
    }

    /**
     * @param  list<array{consumable_id: int, quantity: int}>  $items
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function batchPayload(array $items, array $overrides = []): array
    {
        return [
            'provider_department_id' => $this->deptA->id,
            'request_method' => 'WhatsApp',
            'purpose' => 'Pengisian stok',
            'items' => $items,
            ...$overrides,
        ];
    }

    public function test_batch_creates_one_request_per_stock_item(): void
    {
        $baterai = Consumable::factory()->create(['name' => 'Baterai AA', 'quantity' => 0, 'min_quantity' => 8, 'provider_department_id' => $this->deptA->id]);
        $tinta = Consumable::factory()->create(['name' => 'Tinta Printer', 'quantity' => 1, 'min_quantity' => 2, 'provider_department_id' => $this->deptA->id]);

        $this->postJson('/api/supply-requests/batch', $this->batchPayload([
            ['consumable_id' => $baterai->id, 'quantity' => 8],
            ['consumable_id' => $tinta->id, 'quantity' => 1],
        ]))->assertCreated()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.item_name', 'Baterai AA')
            ->assertJsonPath('data.0.quantity', 8)
            ->assertJsonPath('data.0.consumable_id', $baterai->id)
            ->assertJsonPath('data.0.request_method', 'WhatsApp')
            ->assertJsonPath('data.0.purpose', 'Pengisian stok')
            ->assertJsonPath('data.0.must_return', false)
            ->assertJsonPath('data.1.item_name', 'Tinta Printer');

        $this->assertSame(2, SupplyRequest::where('status', RequestStatus::Diajukan)->where('provider_department_id', $this->deptA->id)->count());
        // Saran menandai kedua barang sudah diminta.
        $this->getJson('/api/supply-requests/suggestions')->assertOk()
            ->assertJsonPath('data.0.items.0.has_open_request', true)
            ->assertJsonPath('data.0.items.1.has_open_request', true);
    }

    public function test_batch_is_all_or_nothing(): void
    {
        $deptB = Department::factory()->create(['name' => 'Dept B']);
        $baterai = Consumable::factory()->create(['name' => 'Baterai AA', 'provider_department_id' => $this->deptA->id]);
        $lain = Consumable::factory()->create(['name' => 'Kabel', 'provider_department_id' => $deptB->id]);

        $this->postJson('/api/supply-requests/batch', $this->batchPayload([
            ['consumable_id' => $baterai->id, 'quantity' => 2],
            ['consumable_id' => $lain->id, 'quantity' => 1],
        ]))->assertUnprocessable()->assertJsonValidationErrors('items.1.consumable_id');

        $this->assertDatabaseCount('supply_requests', 0);
    }

    public function test_batch_validation(): void
    {
        $baterai = Consumable::factory()->create(['provider_department_id' => $this->deptA->id]);
        $one = [['consumable_id' => $baterai->id, 'quantity' => 1]];

        $this->postJson('/api/supply-requests/batch', $this->batchPayload([]))->assertUnprocessable()->assertJsonPath('errors.items.0', 'Pilih minimal satu barang.');
        $this->postJson('/api/supply-requests/batch', $this->batchPayload([['consumable_id' => $baterai->id, 'quantity' => 0]]))
            ->assertUnprocessable()->assertJsonValidationErrors('items.0.quantity');
        $this->postJson('/api/supply-requests/batch', $this->batchPayload([...$one, ['consumable_id' => $baterai->id, 'quantity' => 2]]))
            ->assertUnprocessable()->assertJsonValidationErrors('items.1.consumable_id');
        $this->postJson('/api/supply-requests/batch', $this->batchPayload($one, ['request_method' => '']))
            ->assertUnprocessable()->assertJsonValidationErrors('request_method');

        $this->assertDatabaseCount('supply_requests', 0);
    }
}
