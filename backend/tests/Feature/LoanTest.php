<?php

namespace Tests\Feature;

use App\Enums\AssetCondition;
use App\Enums\AssetStatus;
use App\Enums\DamageConfirmation;
use App\Enums\EventType;
use App\Enums\LoanStatus;
use App\Models\Asset;
use App\Models\Loan;
use App\Models\Room;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class LoanTest extends TestCase
{
    use RefreshDatabase;

    private User $staff;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(now()->setTime(10, 0));
        $this->staff = User::factory()->create(['name' => 'Staf SHD']);
        $this->actingAs($this->staff);
    }

    private function asset(AssetCondition $condition = AssetCondition::Baik, AssetStatus $status = AssetStatus::Tersedia): Asset
    {
        $asset = Asset::factory()->create(['condition' => $condition, 'status' => $status]);
        $asset->forceFill(['created_at' => now()->subYear()])->save();

        return $asset;
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function borrowPayload(Asset $asset, array $overrides = []): array
    {
        return [
            'asset_id' => $asset->id,
            'borrower_name' => 'Andi Pratama',
            'borrower_unit' => 'SGA',
            'purpose' => 'Pelatihan operator',
            'due_date' => today()->addDays(3)->toDateString(),
            ...$overrides,
        ];
    }

    private function borrow(Asset $asset, array $overrides = []): Loan
    {
        $id = $this->postJson('/api/loans', $this->borrowPayload($asset, $overrides))->assertCreated()->json('data.id');

        return Loan::findOrFail($id);
    }

    // ---------- auth ----------

    /**
     * @return array<string, array{0: string, 1: string}>
     */
    public static function endpointProvider(): array
    {
        return [
            'index' => ['GET', '/api/loans'],
            'store' => ['POST', '/api/loans'],
            'show' => ['GET', '/api/loans/1'],
            'return' => ['PATCH', '/api/loans/1/return'],
            'cancel' => ['PATCH', '/api/loans/1/cancel'],
            'confirm' => ['PATCH', '/api/loans/1/confirm-damage'],
        ];
    }

    #[DataProvider('endpointProvider')]
    public function test_endpoints_require_token(string $method, string $uri): void
    {
        $this->app['auth']->forgetGuards();

        $this->json($method, $uri)->assertUnauthorized();
    }

    // ---------- pinjam ----------

    public function test_borrow_sets_asset_dipinjam_and_records_event(): void
    {
        $asset = $this->asset(AssetCondition::RusakRingan);
        $room = Room::factory()->create(['name' => 'Auditorium']);

        $this->postJson('/api/loans', $this->borrowPayload($asset, ['destination_room_id' => $room->id, 'approval_number' => 'ND-01']))
            ->assertCreated()
            ->assertJsonPath('data.status', 'Dipinjam')
            ->assertJsonPath('data.condition_out', 'Rusak Ringan')
            ->assertJsonPath('data.borrow_date', today()->toDateString())
            ->assertJsonPath('data.destination_room.name', 'Auditorium')
            ->assertJsonPath('data.borrowed_by.name', 'Staf SHD')
            ->assertJsonPath('data.is_overdue', false);

        $this->assertSame(AssetStatus::Dipinjam, $asset->fresh()->status);

        $event = $asset->events()->sole();
        $this->assertSame(EventType::Dipinjam, $event->event_type);
        $this->assertSame('Andi Pratama', $event->party);
        $this->assertSame($room->id, $event->to_room_id);
        $this->assertNotNull($event->loan_id);
    }

    public function test_borrow_requires_available_asset_and_blocks_second_active_loan(): void
    {
        $asset = $this->asset();
        $this->borrow($asset);

        $this->postJson('/api/loans', $this->borrowPayload($asset))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['asset_id' => 'Aset sedang dipinjam.']);

        $repair = $this->asset(AssetCondition::RusakBerat, AssetStatus::Perbaikan);
        $this->postJson('/api/loans', $this->borrowPayload($repair))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['asset_id' => 'Aset berstatus Perbaikan, tidak bisa dipinjam.']);

        $unfit = $this->asset(AssetCondition::RusakBerat, AssetStatus::TidakLayakPakai);
        $this->postJson('/api/loans', $this->borrowPayload($unfit))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['asset_id' => 'Aset berstatus Tidak Layak Pakai, tidak bisa dipinjam.']);

        $this->assertSame(1, Loan::where('status', LoanStatus::Dipinjam)->count());
    }

    public function test_borrow_date_rules(): void
    {
        $asset = $this->asset();

        $this->postJson('/api/loans', $this->borrowPayload($asset, ['borrow_date' => today()->addDay()->toDateString(), 'due_date' => today()->addDays(2)->toDateString()]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['borrow_date' => 'Tanggal pinjam tidak boleh di masa depan.']);

        $this->postJson('/api/loans', $this->borrowPayload($asset, ['borrow_date' => today()->subDays(2)->toDateString(), 'due_date' => today()->subDays(3)->toDateString()]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['due_date' => 'Rencana kembali tidak boleh sebelum tanggal pinjam.']);

        $this->postJson('/api/loans', $this->borrowPayload($asset, ['borrow_date' => today()->subYears(2)->toDateString(), 'due_date' => today()->toDateString()]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['borrow_date' => 'Tanggal pinjam tidak boleh sebelum aset terdaftar.']);

        $this->postJson('/api/loans', $this->borrowPayload($asset, ['condition_out' => 'Baik']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['condition_out' => 'Kondisi saat keluar diisi otomatis dari kondisi aset.']);

        $this->postJson('/api/loans', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'asset_id' => 'Aset wajib diisi.',
                'borrower_name' => 'Nama peminjam wajib diisi.',
                'borrower_unit' => 'Unit kerja wajib diisi.',
                'purpose' => 'Keperluan wajib diisi.',
                'due_date' => 'Rencana kembali wajib diisi.',
            ]);
    }

    public function test_late_recording_with_past_dates_shows_overdue_and_keeps_created_at(): void
    {
        $asset = $this->asset();

        $loan = $this->borrow($asset, ['borrow_date' => today()->subDays(5)->toDateString(), 'due_date' => today()->subDays(2)->toDateString()]);

        $this->assertSame(today()->subDays(5)->toDateString(), $loan->borrow_date->toDateString());
        $this->assertTrue($loan->created_at->isToday());
        $this->assertTrue($loan->isOverdue());
        $this->assertSame(today()->subDays(5)->toDateString(), $asset->events()->sole()->event_date->toDateString());
    }

    // ---------- daftar ----------

    public function test_index_filters(): void
    {
        $overdue = $this->borrow($this->asset(), ['borrower_name' => 'Budi', 'borrow_date' => today()->subDays(4)->toDateString(), 'due_date' => today()->subDay()->toDateString()]);
        $dueToday = $this->borrow($this->asset(), ['borrower_name' => 'Citra', 'due_date' => today()->toDateString()]);
        $returned = $this->borrow($this->asset(), ['borrower_name' => 'Dedi', 'borrow_date' => today()->subDays(30)->toDateString(), 'due_date' => today()->subDays(28)->toDateString()]);
        $this->patchJson("/api/loans/{$returned->id}/return", ['return_condition' => 'Rusak Berat', 'note' => 'Retak'])->assertOk();

        $this->getJson('/api/loans')->assertOk()->assertJsonCount(3, 'data')
            ->assertJsonPath('data.0.id', $overdue->id)
            ->assertJsonPath('data.1.id', $dueToday->id)
            ->assertJsonPath('data.2.id', $returned->id);

        $this->getJson('/api/loans?overdue=1')->assertJsonCount(1, 'data')->assertJsonPath('data.0.is_overdue', true);
        $this->getJson('/api/loans?due_today=1')->assertJsonCount(1, 'data')->assertJsonPath('data.0.is_due_today', true);
        $this->getJson('/api/loans?status=Dikembalikan')->assertJsonCount(1, 'data');
        $this->getJson('/api/loans?pending_confirmation=1')->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $returned->id);
        $this->getJson('/api/loans?from='.today()->subDays(5)->toDateString())->assertJsonCount(2, 'data');
        $this->getJson('/api/loans?search=citra')->assertJsonCount(1, 'data');
        $this->getJson('/api/loans?search='.strtolower($overdue->asset->code))->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $overdue->id);
        $this->getJson("/api/loans?asset_id={$dueToday->asset_id}")->assertJsonCount(1, 'data');
        $this->getJson('/api/loans?status=Ngawur')->assertUnprocessable();
    }

    // ---------- terima kembali ----------

    /**
     * @return array<string, array{0: string, 1: string, 2: ?string, 3: string, 4: ?string}>
     */
    public static function returnProvider(): array
    {
        return [
            'baik → baik' => ['Baik', 'Baik', null, 'Tersedia', null],
            'baik → rusak ringan (default tersedia)' => ['Baik', 'Rusak Ringan', null, 'Tersedia', 'Menunggu Konfirmasi'],
            'baik → rusak ringan pilih perbaikan' => ['Baik', 'Rusak Ringan', 'Perbaikan', 'Perbaikan', 'Menunggu Konfirmasi'],
            'baik → rusak berat' => ['Baik', 'Rusak Berat', null, 'Perbaikan', 'Menunggu Konfirmasi'],
            'rusak ringan → rusak ringan (sama, tanpa konfirmasi)' => ['Rusak Ringan', 'Rusak Ringan', null, 'Tersedia', null],
            'rusak ringan → baik (lebih baik)' => ['Rusak Ringan', 'Baik', null, 'Tersedia', null],
            'rusak ringan → rusak berat' => ['Rusak Ringan', 'Rusak Berat', null, 'Perbaikan', 'Menunggu Konfirmasi'],
        ];
    }

    #[DataProvider('returnProvider')]
    public function test_return_condition_status_and_confirmation(string $out, string $back, ?string $status, string $expectedStatus, ?string $confirmation): void
    {
        $asset = $this->asset(AssetCondition::from($out));
        $loan = $this->borrow($asset);

        $this->patchJson("/api/loans/{$loan->id}/return", ['return_condition' => $back, 'status' => $status, 'note' => $back === 'Baik' ? null : 'Catatan'])
            ->assertOk()
            ->assertJsonPath('data.status', 'Dikembalikan')
            ->assertJsonPath('data.return_condition', $back)
            ->assertJsonPath('data.damage_confirmation', $confirmation)
            ->assertJsonPath('data.received_by.name', 'Staf SHD');

        $asset->refresh();
        $this->assertSame($back, $asset->condition->value);
        $this->assertSame($expectedStatus, $asset->status->value);
        $this->assertSame(EventType::Dikembalikan, $asset->events()->latest('id')->first()->event_type);
    }

    public function test_return_to_other_room_moves_origin_and_records_move(): void
    {
        $asset = $this->asset();
        $origin = $asset->room_id;
        $storage = Room::factory()->create(['name' => 'Gudang']);
        $loan = $this->borrow($asset);

        $this->patchJson("/api/loans/{$loan->id}/return", ['return_condition' => 'Baik', 'return_room_id' => $storage->id])
            ->assertOk()
            ->assertJsonPath('data.return_room.name', 'Gudang');

        $this->assertSame($storage->id, $asset->fresh()->room_id);
        $move = $asset->events()->where('event_type', EventType::PindahLokasi)->sole();
        $this->assertSame($origin, $move->from_room_id);
        $this->assertSame($storage->id, $move->to_room_id);
        $this->assertSame($loan->id, $move->loan_id);
    }

    public function test_return_default_room_is_origin_without_move_event(): void
    {
        $asset = $this->asset();
        $loan = $this->borrow($asset);

        $this->patchJson("/api/loans/{$loan->id}/return", ['return_condition' => 'Baik'])->assertOk();

        $this->assertSame($asset->room_id, $loan->fresh()->return_room_id);
        $this->assertSame(0, $asset->events()->where('event_type', EventType::PindahLokasi)->count());
    }

    public function test_return_validation(): void
    {
        $loan = $this->borrow($this->asset(), ['borrow_date' => today()->subDays(3)->toDateString()]);

        $this->patchJson("/api/loans/{$loan->id}/return", [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['return_condition' => 'Kondisi saat kembali wajib diisi.']);

        $this->patchJson("/api/loans/{$loan->id}/return", ['return_condition' => 'Rusak Berat'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['note' => 'Catatan wajib diisi jika kondisi tidak Baik.']);

        $this->patchJson("/api/loans/{$loan->id}/return", ['return_condition' => 'Baik', 'status' => 'Perbaikan'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['status' => 'Status hanya bisa dipilih jika kondisi Rusak Ringan.']);

        $this->patchJson("/api/loans/{$loan->id}/return", ['return_condition' => 'Baik', 'return_date' => today()->subDays(4)->toDateString()])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['return_date' => 'Tanggal kembali tidak boleh sebelum tanggal pinjam.']);

        $this->patchJson("/api/loans/{$loan->id}/return", ['return_condition' => 'Baik', 'return_date' => now()->addHour()->toDateTimeString()])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['return_date' => 'Tanggal kembali tidak boleh di masa depan.']);

        $this->patchJson("/api/loans/{$loan->id}/return", ['return_condition' => 'Baik', 'return_date' => today()->subDay()->toDateString()])
            ->assertOk()
            ->assertJsonPath('data.return_date', today()->subDay()->startOfDay()->toIso8601String());

        $this->patchJson("/api/loans/{$loan->id}/return", ['return_condition' => 'Baik'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['loan' => 'Transaksi tidak berstatus Dipinjam.']);
    }

    // ---------- hilang saat dipinjam ----------

    public function test_return_as_lost_closes_loan_and_marks_asset_lost(): void
    {
        $asset = $this->asset();
        $loan = $this->borrow($asset);

        $this->patchJson("/api/loans/{$loan->id}/return", ['result' => 'hilang', 'note' => 'Hilang di lokasi acara'])
            ->assertOk()
            ->assertJsonPath('data.status', 'Hilang')
            ->assertJsonPath('data.borrower_name', 'Andi Pratama')
            ->assertJsonPath('data.damage_confirmation', null)
            ->assertJsonPath('data.return_condition', null);

        $this->assertSame(AssetStatus::Hilang, $asset->fresh()->status);
        $event = $asset->events()->where('event_type', EventType::LaporHilang)->sole();
        $this->assertSame('Andi Pratama', $event->party);
        $this->assertSame($loan->id, $event->loan_id);

        $this->getJson("/api/assets/{$asset->id}")
            ->assertOk()
            ->assertJsonPath('data.lost_during_loan', [
                'borrower_name' => 'Andi Pratama',
                'borrower_unit' => 'SGA',
                'date' => today()->toDateString(),
                'loan_id' => $loan->id,
            ]);

        $public = $this->getJson("/api/public/assets/{$asset->public_token}")->assertOk();
        $this->assertStringNotContainsString('Andi Pratama', $public->getContent());

        // Tetap bisa ditandai ditemukan (T9), lalu info hilang tidak tampil lagi.
        $this->postJson("/api/assets/{$asset->id}/found", ['condition' => 'Baik'])->assertOk()->assertJsonPath('data.status', 'Tersedia');
        $this->getJson("/api/assets/{$asset->id}")->assertJsonPath('data.lost_during_loan', null);
    }

    public function test_return_as_lost_validation(): void
    {
        $loan = $this->borrow($this->asset());

        $this->patchJson("/api/loans/{$loan->id}/return", ['result' => 'hilang'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['note' => 'Catatan kehilangan wajib diisi.']);

        $this->patchJson("/api/loans/{$loan->id}/return", ['result' => 'hilang', 'note' => 'x', 'return_condition' => 'Baik', 'return_room_id' => 1])
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'return_condition' => 'Kondisi tidak diisi jika aset hilang.',
                'return_room_id' => 'Ruangan tidak diisi jika aset hilang.',
            ]);

        $this->patchJson("/api/loans/{$loan->id}/return", ['result' => 'rusak'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['result' => 'Hasil harus kembali atau hilang.']);
    }

    // ---------- batalkan ----------

    public function test_cancel_restores_status_from_current_condition(): void
    {
        $asset = $this->asset();
        $loan = $this->borrow($asset);

        // Kerusakan dicatat selama dipinjam → status tetap Dipinjam (T9).
        $this->postJson("/api/assets/{$asset->id}/report", ['type' => 'rusak', 'condition' => 'Rusak Berat', 'note' => 'Jatuh'])
            ->assertJsonPath('data.status', 'Dipinjam');

        $this->patchJson("/api/loans/{$loan->id}/cancel", ['cancel_reason' => 'Kegiatan batal'])
            ->assertOk()
            ->assertJsonPath('data.status', 'Dibatalkan')
            ->assertJsonPath('data.cancel_reason', 'Kegiatan batal');

        $this->assertSame(AssetStatus::Perbaikan, $asset->fresh()->status);
        $this->assertSame('Kegiatan batal', $asset->events()->where('event_type', EventType::Dibatalkan)->value('note'));
    }

    public function test_cancel_requires_reason_and_active_loan(): void
    {
        $loan = $this->borrow($this->asset());

        $this->patchJson("/api/loans/{$loan->id}/cancel", [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['cancel_reason' => 'Alasan pembatalan wajib diisi.']);

        $this->patchJson("/api/loans/{$loan->id}/cancel", ['cancel_reason' => 'Batal'])->assertOk();
        $this->assertSame(AssetStatus::Tersedia, $loan->asset->fresh()->status);

        $this->patchJson("/api/loans/{$loan->id}/cancel", ['cancel_reason' => 'Batal lagi'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['loan' => 'Transaksi tidak berstatus Dipinjam.']);
    }

    // ---------- konfirmasi kerusakan ----------

    public function test_confirm_damage(): void
    {
        $loan = $this->borrow($this->asset());
        $this->patchJson("/api/loans/{$loan->id}/return", ['return_condition' => 'Rusak Berat', 'note' => 'Layar retak'])->assertOk();

        $this->patchJson("/api/loans/{$loan->id}/confirm-damage", [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['damage_confirmation_note' => 'Catatan konfirmasi wajib diisi.']);

        $this->patchJson("/api/loans/{$loan->id}/confirm-damage", ['damage_confirmation_note' => 'Peminjam setuju ganti biaya servis', 'damage_confirmation_document' => 'BA-012/2026'])
            ->assertOk()
            ->assertJsonPath('data.damage_confirmation', 'Dikonfirmasi')
            ->assertJsonPath('data.damage_confirmation_note', 'Peminjam setuju ganti biaya servis')
            ->assertJsonPath('data.damage_confirmation_document', 'BA-012/2026')
            ->assertJsonPath('data.damage_confirmed_by.name', 'Staf SHD');

        $this->assertNotNull($loan->fresh()->damage_confirmed_at);

        $this->patchJson("/api/loans/{$loan->id}/confirm-damage", ['damage_confirmation_note' => 'x'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['loan' => 'Transaksi tidak menunggu konfirmasi kerusakan.']);
    }

    public function test_confirm_damage_rejected_without_pending_confirmation(): void
    {
        $loan = $this->borrow($this->asset());

        $this->patchJson("/api/loans/{$loan->id}/confirm-damage", ['damage_confirmation_note' => 'x'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['loan' => 'Transaksi tidak menunggu konfirmasi kerusakan.']);
    }

    public function test_show_and_missing_loan(): void
    {
        $loan = $this->borrow($this->asset());

        $this->getJson("/api/loans/{$loan->id}")->assertOk()->assertJsonPath('data.asset.code', $loan->asset->code);
        $this->getJson('/api/loans/999')->assertNotFound()->assertExactJson(['message' => 'Data tidak ditemukan.']);
    }

    public function test_condition_severity_order(): void
    {
        $this->assertTrue(AssetCondition::RusakRingan->isWorseThan(AssetCondition::Baik));
        $this->assertTrue(AssetCondition::RusakBerat->isWorseThan(AssetCondition::RusakRingan));
        $this->assertFalse(AssetCondition::RusakRingan->isWorseThan(AssetCondition::RusakRingan));
        $this->assertFalse(AssetCondition::Baik->isWorseThan(AssetCondition::RusakBerat));
        $this->assertSame(DamageConfirmation::MenungguKonfirmasi->value, 'Menunggu Konfirmasi');
    }
}
