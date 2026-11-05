<?php

namespace Tests\Feature;

use App\Enums\CountMethod;
use App\Models\Asset;
use App\Models\AssetCodeHistory;
use App\Models\AssetType;
use App\Models\Building;
use App\Models\Department;
use App\Models\Loan;
use App\Models\Room;
use App\Models\RoomStandard;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Satu skenario nyata lintas modul: aset didaftarkan, dipinjam, kembali rusak, dikonfirmasi, diservis,
 * dipindah, dilaporkan rusak, tidak layak pakai, lalu dihapus. Di tiap langkah status aset, pinjaman,
 * riwayat, kelengkapan ruangan, dan dashboard harus konsisten.
 */
class AssetLifecycleTest extends TestCase
{
    use RefreshDatabase;

    private User $staff;

    private Room $classroom;

    private Room $storage;

    private AssetType $projector;

    private Department $shd;

    protected function setUp(): void
    {
        parent::setUp();

        $this->staff = User::factory()->create(['name' => 'Staf SHD']);
        $this->actingAs($this->staff);

        $this->shd = Department::factory()->internal()->create(['name' => 'SHD']);
        $this->projector = AssetType::factory()->create(['name' => 'Proyektor', 'code' => 'PRJ']);
        $building = Building::factory()->create(['name' => 'Gedung IHS']);
        $this->classroom = Room::factory()->for($building)->create(['name' => 'Ruang Kelas 1']);
        $this->storage = Room::factory()->for($building)->create(['name' => 'Gudang']);

        RoomStandard::factory()->create([
            'room_id' => $this->classroom->id,
            'standard_quantity' => 1,
            'count_method' => CountMethod::Qr,
            'asset_type_id' => $this->projector->id,
            'actual_quantity' => null,
        ]);
    }

    /**
     * @return list<string>
     */
    private function eventTypes(Asset $asset): array
    {
        return $asset->events()->orderBy('id')->get()->map(fn ($event) => $event->event_type->value)->all();
    }

    private function checklistRow(): array
    {
        return $this->getJson("/api/rooms/{$this->classroom->id}/checklist")->assertOk()->json('data.assets.0');
    }

    private function dashboard(): array
    {
        return $this->getJson('/api/dashboard/cards')->assertOk()->json('data');
    }

    public function test_full_asset_lifecycle_keeps_every_module_consistent(): void
    {
        // 1. Registrasi
        $id = $this->postJson('/api/assets', [
            'asset_type_id' => $this->projector->id,
            'name' => 'Proyektor Epson',
            'source_department_id' => $this->shd->id,
            'room_id' => $this->classroom->id,
        ])->assertCreated()->assertJsonPath('data.code', 'SHD-PRJ-001')->assertJsonPath('data.status', 'Tersedia')->json('data.id');

        $asset = Asset::findOrFail($id);
        $this->assertSame(['Registrasi'], $this->eventTypes($asset));
        $this->assertSame(1, $this->checklistRow()['present_quantity']);
        $this->assertSame('Sesuai', $this->checklistRow()['result']);
        $this->assertSame(['total' => 1, 'owned_by_shd' => 1, 'from_other_units' => 0, 'not_feasible' => 0], $this->dashboard()['total_assets']);

        // 2. Dipinjam: status Dipinjam, tidak dihitung "Ada" di ruangan, tampil di kartu pinjaman
        $loanId = $this->postJson('/api/loans', [
            'asset_id' => $id,
            'borrower_name' => 'Andi',
            'borrower_unit' => 'SGA',
            'purpose' => 'Pelatihan',
            'due_date' => today()->addDay()->toDateString(),
        ])->assertCreated()->json('data.id');

        $this->assertSame('Dipinjam', $asset->fresh()->status->value);
        $this->assertSame(['Registrasi', 'Dipinjam'], $this->eventTypes($asset));
        $this->assertSame('Kurang', $this->checklistRow()['result']);
        $this->assertSame(1, $this->dashboard()['active_loans']['total']);
        $this->assertSame(1, $this->dashboard()['incomplete_rooms']['incomplete']);
        $this->getJson("/api/assets/{$id}")->assertJsonPath('data.active_loan.id', $loanId);
        $this->postJson('/api/loans', [
            'asset_id' => $id, 'borrower_name' => 'Budi', 'borrower_unit' => 'SIT', 'purpose' => 'x', 'due_date' => today()->addDay()->toDateString(),
        ])->assertUnprocessable();
        $this->assertSame(1, Loan::count());

        // 3. Kembali rusak berat → Perbaikan
        $this->patchJson("/api/loans/{$loanId}/return", ['return_condition' => 'Rusak Berat', 'note' => 'Lensa pecah'])
            ->assertOk()
            ->assertJsonPath('data.status', 'Dikembalikan')
            ->assertJsonPath('data.return_condition', 'Rusak Berat');

        $asset->refresh();
        $this->assertSame('Perbaikan', $asset->status->value);
        $this->assertSame('Rusak Berat', $asset->condition->value);
        $this->assertSame(['Registrasi', 'Dipinjam', 'Dikembalikan'], $this->eventTypes($asset));
        $this->assertSame('Kurang', $this->checklistRow()['result']);
        $this->assertSame(['assets_in_repair' => 1], $this->dashboard()['needs_repair']);
        $this->assertSame(0, $this->dashboard()['active_loans']['total']);

        // 4. Servis → Tersedia lagi, jadwal servis berikutnya dekat → muncul "perlu servis"
        $this->postJson("/api/assets/{$id}/services", [
            'service_date' => today()->toDateString(),
            'vendor' => 'CV Mitra',
            'condition' => 'Baik',
            'next_service_date' => today()->addDays(10)->toDateString(),
        ])->assertOk()->assertJsonPath('data.status', 'Tersedia');

        $this->assertSame(['Registrasi', 'Dipinjam', 'Dikembalikan', 'Servis'], $this->eventTypes($asset));
        $this->assertSame('Sesuai', $this->checklistRow()['result']);
        $this->assertSame(1, $this->dashboard()['needs_service']['total']);
        $this->assertSame(0, $this->dashboard()['needs_service']['overdue']);

        // 6. Pindah ke gudang: tidak lagi dihitung di ruang kelas
        $this->patchJson("/api/assets/{$id}/location", ['room_id' => $this->storage->id])->assertOk();

        $this->assertSame($this->storage->id, $asset->fresh()->room_id);
        $this->assertSame('Pindah Lokasi', last($this->eventTypes($asset)));
        $this->assertSame('Kurang', $this->checklistRow()['result']);

        // 7. Lapor rusak berat → Perbaikan
        $this->postJson("/api/assets/{$id}/report", ['type' => 'rusak', 'condition' => 'Rusak Berat', 'note' => 'Kipas mati'])->assertOk();

        $this->assertSame('Perbaikan', $asset->fresh()->status->value);
        $this->assertSame('Lapor Rusak', last($this->eventTypes($asset)));

        // 8. Tidak layak pakai: keluar dari total aset, aksi tinggal pindah lokasi, tidak bisa dipinjam
        $this->postJson("/api/assets/{$id}/report", ['type' => 'tidak_layak_pakai', 'note' => 'Tidak ekonomis diperbaiki'])
            ->assertOk()
            ->assertJsonPath('data.status', 'Tidak Layak Pakai');

        $this->assertSame('Tidak Layak Pakai', last($this->eventTypes($asset)));
        $this->assertSame(['total' => 0, 'owned_by_shd' => 0, 'from_other_units' => 0, 'not_feasible' => 1], $this->dashboard()['total_assets']);
        $this->getJson('/api/assets/scan/SHD-PRJ-001')->assertOk()->assertJsonPath('meta.actions', ['pindah_lokasi']);
        $this->postJson('/api/loans', [
            'asset_id' => $id, 'borrower_name' => 'Citra', 'borrower_unit' => 'SGA', 'purpose' => 'x', 'due_date' => today()->addDay()->toDateString(),
        ])->assertUnprocessable();
        $this->postJson("/api/assets/{$id}/services", ['service_date' => today()->toDateString(), 'vendor' => 'x', 'condition' => 'Baik'])->assertUnprocessable();

        // Setiap langkah yang mengubah aset menulis tepat satu event, oleh staf yang login.
        $this->assertSame(
            ['Registrasi', 'Dipinjam', 'Dikembalikan', 'Servis', 'Pindah Lokasi', 'Lapor Rusak', 'Tidak Layak Pakai'],
            $this->eventTypes($asset),
        );
        $this->assertSame([$this->staff->id], $asset->events()->pluck('recorded_by')->unique()->values()->all());

        // 9. Hapus aset (punya riwayat pinjam → wajib confirm_code); semua turunan ikut hilang
        $this->getJson("/api/assets/{$id}/delete-impact")
            ->assertOk()
            ->assertJsonPath('data.loans_count', 1)
            ->assertJsonPath('data.events_count', 7)
            ->assertJsonPath('data.requires_confirm_code', true);
        $this->deleteJson("/api/assets/{$id}")->assertUnprocessable()->assertJsonValidationErrors('confirm_code');
        $this->deleteJson("/api/assets/{$id}", ['confirm_code' => 'shd-prj-001'])->assertOk();

        $this->assertDatabaseMissing('assets', ['id' => $id]);
        $this->assertSame(0, Loan::count());
        $this->assertDatabaseCount('asset_events', 0);
        $this->assertSame(0, AssetCodeHistory::count());
        $this->getJson("/api/assets/{$id}")->assertNotFound();
        $this->getJson('/api/assets/scan/SHD-PRJ-001')->assertNotFound();
        $this->assertSame(0, $this->dashboard()['total_assets']['not_feasible']);

        // Nomor urut kode tidak dipakai ulang
        $this->postJson('/api/assets', [
            'asset_type_id' => $this->projector->id,
            'name' => 'Proyektor baru',
            'source_department_id' => $this->shd->id,
            'room_id' => $this->classroom->id,
        ])->assertCreated()->assertJsonPath('data.code', 'SHD-PRJ-002');
    }
}
