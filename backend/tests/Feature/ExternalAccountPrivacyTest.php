<?php

namespace Tests\Feature;

use App\Enums\AssetStatus;
use App\Models\Asset;
use App\Models\Consumable;
use App\Models\Department;
use App\Models\Employee;
use App\Models\Loan;
use App\Models\Room;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * T45 (temuan UAT K1): akun eksternal tidak pernah menerima NIK (password awal akun pegawai SHD) dari endpoint
 * mana pun, dan tetap tidak bisa membuka Pengaturan > Akun.
 */
class ExternalAccountPrivacyTest extends TestCase
{
    use RefreshDatabase;

    private const NIK = '90100001';

    public function test_external_account_never_receives_any_nik(): void
    {
        $shd = Department::factory()->internal()->create(['name' => 'SHD']);
        Employee::factory()->inDepartment('SHD')->create(['nik' => self::NIK, 'name' => 'ANDI PRATAMA']);
        $employee = User::factory()->employee(self::NIK)->create(['name' => 'ANDI PRATAMA']);

        // Data yang dicatat akun pegawai (riwayat, pinjaman, transaksi stok) dibuka akun eksternal.
        $this->actingAs($employee);
        $room = Room::factory()->create();
        $storage = Room::factory()->create(['is_storage' => true]);
        $asset = Asset::factory()->create(['source_department_id' => $shd->id, 'room_id' => $room->id]);
        $this->postJson("/api/assets/{$asset->id}/report", ['type' => 'rusak', 'condition' => 'Rusak Ringan', 'status' => 'Tersedia', 'note' => 'Lecet'])->assertOk();
        $borrowed = Asset::factory()->create(['source_department_id' => $shd->id, 'room_id' => $room->id, 'status' => AssetStatus::Dipinjam]);
        $loan = Loan::factory()->create(['asset_id' => $borrowed->id, 'borrower_name' => 'ANDI PRATAMA', 'borrowed_by' => $employee->id]);
        $item = Consumable::factory()->create(['quantity' => 0, 'room_id' => $storage->id, 'provider_department_id' => $shd->id]);
        $this->postJson("/api/consumables/{$item->id}/transactions", ['type' => 'Barang Masuk', 'quantity' => 3])->assertCreated();

        $external = User::factory()->create(['name' => 'Akun SGA', 'username' => 'sga1']);
        $this->actingAs($external);

        $endpoints = [
            '/api/user', '/api/employees?search=andi', '/api/employees?search='.self::NIK, '/api/employees/sync-status',
            '/api/assets', "/api/assets/{$asset->id}", "/api/assets/{$asset->id}/events", "/api/assets/scan/{$asset->code}",
            '/api/loans', "/api/loans/{$loan->id}", '/api/dashboard/cards', '/api/dashboard/panels', '/api/dashboard/room-readiness',
            '/api/consumables', "/api/consumables/{$item->id}", "/api/consumables/{$item->id}/transactions",
            '/api/room-readiness', "/api/rooms/{$room->id}/checklist", '/api/supply-requests', '/api/departments',
        ];

        foreach ($endpoints as $endpoint) {
            $content = $this->getJson($endpoint)->assertOk()->getContent();
            $this->assertStringNotContainsString(self::NIK, $content, "NIK terkirim di {$endpoint}");
            // `"nik":null` di /user adalah akun eksternal itu sendiri (tanpa NIK); yang dilarang adalah NIK berisi.
            $this->assertDoesNotMatchRegularExpression('/"nik":"/', $content, "NIK berisi terkirim di {$endpoint}");
        }

        $this->getJson('/api/accounts')->assertForbidden()->assertJsonPath('code', 'employee_account_required');
        $this->getJson('/api/settings')->assertForbidden();
    }
}
