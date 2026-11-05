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

    public function test_suggestions_from_room_shortage_and_stock(): void
    {
        $room = Room::factory()->create(['name' => 'Ruang 1']);
        $catalog = $this->catalog();
        $standard = fn (string $name, ?int $actual, ?int $catalogId = null) => RoomStandard::factory()->create([
            'room_id' => $room->id,
            'item_name' => $name,
            'source_department_id' => $this->deptA->id,
            'standard_quantity' => 5,
            'count_method' => CountMethod::Manual,
            'actual_quantity' => $actual,
            'catalog_id' => $catalogId,
        ]);

        // Barang departemen lain kurang 3 → masuk saran.
        $standard('Tisu', 2, $catalog->id);
        // Belum dicek → tidak masuk.
        $standard('Kursi', null);
        // Sesuai → tidak masuk.
        $standard('Meja', 5);
        // Baris SHD tanpa barang stok → tidak masuk sampai dilengkapi.
        RoomStandard::factory()->create([
            'room_id' => $room->id, 'source_department_id' => $this->shd->id, 'standard_quantity' => 5,
            'count_method' => CountMethod::Manual, 'actual_quantity' => 0,
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
        $this->assertSame('Tisu', $items['kekurangan_ruangan:Tisu']['catalog_name']);
        $this->assertNull($items['stok:Spidol']['catalog_name']);
        // Nama barang sebenarnya, katalognya sebagai keterangan.
        $this->assertSame(6, $items['stok:Kertas']['quantity']);
        $this->assertSame('Tisu', $items['stok:Kertas']['catalog_name']);
        $this->assertSame('Menipis', $items['stok:Kertas']['stock_status']);
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
        RoomStandard::factory()->create([
            'room_id' => $room->id,
            'item_name' => 'Tisu',
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

    public function test_open_request_flag_is_per_item_when_linked_to_stock(): void
    {
        $catalog = $this->catalog(['item_name' => 'ATK Isi Ulang']);
        $pensil = Consumable::factory()->create(['name' => 'Pensil', 'quantity' => 1, 'min_quantity' => 5, 'provider_department_id' => $this->deptA->id, 'catalog_id' => $catalog->id]);
        Consumable::factory()->create(['name' => 'Spidol', 'quantity' => 1, 'min_quantity' => 5, 'provider_department_id' => $this->deptA->id, 'catalog_id' => $catalog->id]);

        $this->supplyRequest(['catalog_id' => $catalog->id, 'consumable_id' => $pensil->id, 'item_name' => 'Pensil']);

        $items = collect($this->getJson('/api/supply-requests/suggestions')->assertOk()->json('data.0.items'))->keyBy('item_name');

        $this->assertTrue($items['Pensil']['has_open_request']);
        $this->assertFalse($items['Spidol']['has_open_request']);
    }

    // ---------- status mengubah stok / Ada ruangan ----------

    private function otherUnitStandard(int $actual = 0): RoomStandard
    {
        return RoomStandard::factory()->create([
            'room_id' => Room::factory()->create(['name' => 'Ruang Kelas 1'])->id,
            'item_name' => 'Tisu',
            'source_department_id' => $this->deptA->id,
            'standard_quantity' => 2,
            'count_method' => CountMethod::Manual,
            'actual_quantity' => $actual,
        ]);
    }

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

    public function test_store_with_stock_link_uses_item_name_of_consumable(): void
    {
        $catalog = $this->catalog(['item_name' => 'ATK Isi Ulang']);
        $pensil = Consumable::factory()->create(['name' => 'Pensil 2B', 'provider_department_id' => $this->deptA->id, 'catalog_id' => $catalog->id]);

        $this->postJson('/api/supply-requests', ['catalog_id' => $catalog->id, 'consumable_id' => $pensil->id, 'quantity' => 5, 'request_method' => 'Memo'])
            ->assertCreated()
            ->assertJsonPath('data.item_name', 'Pensil 2B')
            ->assertJsonPath('data.consumable_id', $pensil->id)
            ->assertJsonPath('data.catalog.item_name', 'ATK Isi Ulang');
    }

    public function test_received_other_unit_item_adds_room_quantity_and_clears_suggestion(): void
    {
        $standard = $this->otherUnitStandard();

        $this->assertNotEmpty($this->getJson('/api/supply-requests/suggestions')->json('data'));

        // Nama & ruangan mengikuti baris standar yang ditautkan.
        $id = $this->postJson('/api/supply-requests', [
            'room_standard_id' => $standard->id, 'item_name' => 'Lain', 'provider_department_id' => $this->deptA->id, 'quantity' => 2, 'request_method' => 'Memo',
        ])->assertCreated()
            ->assertJsonPath('data.item_name', 'Tisu')
            ->assertJsonPath('data.room.id', $standard->room_id)
            ->assertJsonPath('data.room_standard_id', $standard->id)
            ->json('data.id');
        $request = SupplyRequest::findOrFail($id);

        $items = $this->getJson('/api/supply-requests/suggestions')->json('data.0.items');
        $this->assertTrue($items[0]['has_open_request']);

        $this->getJson("/api/supply-requests/{$request->id}/status-impact?status=Diterima")
            ->assertOk()
            ->assertExactJson(['data' => ['message' => 'Ada Tisu di Ruang Kelas 1 akan bertambah 2.', 'clamped' => false]]);
        $this->assertSame(0, $standard->fresh()->actual_quantity);

        $this->setStatus($request, 'Diterima')
            ->assertOk()
            ->assertJsonPath('meta.impact.message', 'Ada Tisu di Ruang Kelas 1 akan bertambah 2.');

        $this->assertSame(2, $standard->fresh()->actual_quantity);
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

        $this->setStatus($request, 'Diterima')->assertUnprocessable()->assertJsonPath('errors.adjustment.0', 'Jumlah stok melebihi batas.');

        $this->assertSame(2147483647, $pulpen->fresh()->quantity);
        $this->assertSame('Diajukan', $request->fresh()->status->value);
    }

    public function test_received_request_cannot_push_room_quantity_over_integer_column_limit(): void
    {
        $standard = $this->otherUnitStandard(2147483647);
        $request = $this->supplyRequest(['room_standard_id' => $standard->id, 'quantity' => 1]);

        $this->setStatus($request, 'Diterima')->assertUnprocessable()->assertJsonPath('errors.status.0', 'Jumlah Ada melebihi batas.');

        $this->assertSame(2147483647, $standard->fresh()->actual_quantity);
        $this->assertSame('Diajukan', $request->fresh()->status->value);
    }

    public function test_received_stored_item_adds_stock_only(): void
    {
        $room = Room::factory()->create();
        $pulpen = Consumable::factory()->create(['name' => 'Pulpen', 'quantity' => 1, 'min_quantity' => 0, 'provider_department_id' => $this->deptA->id]);
        $standard = RoomStandard::factory()->create([
            'room_id' => $room->id, 'source_department_id' => $this->deptA->id, 'standard_quantity' => 10,
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
        $standard = $this->otherUnitStandard();
        $catalog = $this->catalog(['must_return' => true]);
        $returned = $this->supplyRequest(['room_standard_id' => $standard->id, 'room_id' => $standard->room_id, 'quantity' => 3, 'must_return' => true, 'catalog_id' => $catalog->id]);

        $this->setStatus($returned, 'Diterima')->assertOk();
        $this->assertSame(3, $standard->fresh()->actual_quantity);
        $this->setStatus($returned, 'Dikembalikan')->assertOk()->assertJsonPath('meta.impact.clamped', false);
        $this->assertSame(0, $standard->fresh()->actual_quantity);

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
        $standard = $this->otherUnitStandard(1);
        $room = $standard->room;

        $cancelled = $this->supplyRequest(['room_standard_id' => $standard->id, 'room_id' => $room->id, 'quantity' => 2]);
        $this->setStatus($cancelled, 'Dibatalkan')->assertOk()->assertJsonPath('meta.impact.message', 'Tidak mengubah stok atau kelengkapan ruangan.');

        $unlinked = $this->supplyRequest(['room_id' => $room->id, 'item_name' => 'Tisu', 'quantity' => 2]);
        $this->setStatus($unlinked, 'Diterima')->assertOk();
        $this->setStatus($unlinked, 'Dibatalkan')->assertOk();

        $this->assertSame(1, $standard->fresh()->actual_quantity);
    }

    public function test_reverse_is_skipped_when_target_was_deleted(): void
    {
        $standard = $this->otherUnitStandard();
        $request = $this->supplyRequest(['room_standard_id' => $standard->id, 'room_id' => $standard->room_id, 'quantity' => 2]);
        $this->setStatus($request, 'Diterima')->assertOk();

        $this->deleteJson("/api/room-standards/{$standard->id}")->assertOk();

        $this->setStatus($request, 'Dibatalkan')->assertOk()->assertJsonPath('meta.impact.message', 'Tidak mengubah stok atau kelengkapan ruangan.');
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
}
