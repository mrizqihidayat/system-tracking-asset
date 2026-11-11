<?php

namespace Tests\Feature;

use App\Enums\AssetCondition;
use App\Enums\AssetStatus;
use App\Enums\EventType;
use App\Enums\LoanStatus;
use App\Models\Asset;
use App\Models\Loan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Pemesanan peminjaman (T31, SPEC §7): status Direncanakan, Serahkan, batal, cek bentrok, tampilan.
 */
class LoanBookingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(now()->setTime(10, 0));
        $this->actingAs(User::factory()->create(['name' => 'Staf SHD']));
    }

    private function asset(AssetStatus $status = AssetStatus::Tersedia, AssetCondition $condition = AssetCondition::Baik): Asset
    {
        $asset = Asset::factory()->create(['status' => $status, 'condition' => $condition]);
        $asset->forceFill(['created_at' => now()->subYear()])->save();

        return $asset;
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function payload(Asset $asset, int $fromDays, int $toDays, array $overrides = []): array
    {
        return [
            'asset_id' => $asset->id,
            'borrower_name' => 'Budi Santoso',
            'borrower_unit' => 'SIT',
            'purpose' => 'Workshop',
            'borrow_date' => today()->addDays($fromDays)->toDateString(),
            'due_date' => today()->addDays($toDays)->toDateString(),
            ...$overrides,
        ];
    }

    private function book(Asset $asset, int $fromDays, int $toDays, array $overrides = []): Loan
    {
        $id = $this->postJson('/api/loans', $this->payload($asset, $fromDays, $toDays, $overrides))->assertCreated()->json('data.id');

        return Loan::findOrFail($id);
    }

    // ---------- membuat pemesanan ----------

    public function test_future_borrow_date_creates_planned_loan_without_touching_asset(): void
    {
        $asset = $this->asset();

        $this->postJson('/api/loans', $this->payload($asset, 3, 5))
            ->assertCreated()
            ->assertJsonPath('data.status', 'Direncanakan')
            ->assertJsonPath('data.handover_date', null)
            ->assertJsonPath('data.is_unhanded', false)
            ->assertJsonPath('data.is_overdue', false);

        $this->assertSame(AssetStatus::Tersedia, $asset->fresh()->status);
        $this->assertSame(0, $asset->events()->count());
    }

    public function test_today_borrow_date_still_borrows_immediately(): void
    {
        $asset = $this->asset();

        $this->postJson('/api/loans', $this->payload($asset, 0, 2))->assertCreated()->assertJsonPath('data.status', 'Dipinjam');

        $this->assertSame(AssetStatus::Dipinjam, $asset->fresh()->status);
        $this->assertSame(EventType::Dipinjam, $asset->events()->sole()->event_type);
    }

    /**
     * @return array<string, array{0: AssetStatus, 1: bool}>
     */
    public static function plannableStatusProvider(): array
    {
        return [
            'tersedia' => [AssetStatus::Tersedia, true],
            'perbaikan' => [AssetStatus::Perbaikan, true],
            'hilang' => [AssetStatus::Hilang, false],
            'tidak layak pakai' => [AssetStatus::TidakLayakPakai, false],
        ];
    }

    #[DataProvider('plannableStatusProvider')]
    public function test_booking_allowed_asset_statuses(AssetStatus $status, bool $allowed): void
    {
        $asset = $this->asset($status);
        $response = $this->postJson('/api/loans', $this->payload($asset, 3, 4));

        if ($allowed) {
            $response->assertCreated()->assertJsonPath('data.status', 'Direncanakan');
            $this->assertSame($status, $asset->fresh()->status);

            return;
        }

        $response->assertUnprocessable()->assertJsonValidationErrors(['asset_id' => "Aset berstatus {$status->value}, tidak bisa dipesan."]);
    }

    public function test_asset_currently_borrowed_can_be_booked_after_its_due_date(): void
    {
        $asset = $this->asset();
        $this->book($asset, 0, 2);

        $this->postJson('/api/loans', $this->payload($asset, 3, 4))->assertCreated()->assertJsonPath('data.status', 'Direncanakan');

        $this->postJson('/api/loans', $this->payload($asset, 2, 6))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['borrow_date']);
        $this->assertStringContainsString('peminjaman aktif Budi Santoso', $this->postJson('/api/loans', $this->payload($asset, 2, 6))->json('errors.borrow_date.0'));
    }

    // ---------- bentrok ----------

    public function test_overlapping_booking_is_rejected_with_conflicting_period(): void
    {
        $asset = $this->asset();
        $this->book($asset, 10, 12);
        $period = today()->addDays(10)->format('d/m/Y').'–'.today()->addDays(12)->format('d/m/Y');

        foreach ([[8, 10], [12, 14], [11, 11], [5, 20]] as [$from, $to]) {
            $this->postJson('/api/loans', $this->payload($asset, $from, $to, ['borrower_name' => 'Rina']))
                ->assertUnprocessable()
                ->assertJsonValidationErrors(['borrow_date' => "Bentrok dengan pemesanan Budi Santoso ({$period})."]);
        }

        $this->book($asset, 13, 14);
        $this->book($asset, 5, 9);
        $this->assertSame(3, Loan::where('status', LoanStatus::Direncanakan)->count());
    }

    public function test_booking_on_other_asset_does_not_conflict(): void
    {
        $this->book($this->asset(), 10, 12);
        $this->book($this->asset(), 10, 12);

        $this->assertSame(2, Loan::count());
    }

    public function test_borrow_now_allowed_only_if_returned_before_booking(): void
    {
        $asset = $this->asset();
        $this->book($asset, 5, 7);

        $this->postJson('/api/loans', $this->payload($asset, 0, 5))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['borrow_date']);
        $this->assertSame(AssetStatus::Tersedia, $asset->fresh()->status);

        $this->postJson('/api/loans', $this->payload($asset, 0, 4))->assertCreated()->assertJsonPath('data.status', 'Dipinjam');
    }

    public function test_cancelled_and_returned_loans_do_not_block(): void
    {
        $asset = $this->asset();
        $booking = $this->book($asset, 5, 7);
        $this->patchJson("/api/loans/{$booking->id}/cancel", ['cancel_reason' => 'Acara batal'])->assertOk();

        $this->book($asset, 5, 7);
    }

    public function test_overdue_active_loan_occupies_asset_until_today(): void
    {
        $asset = $this->asset(AssetStatus::Dipinjam);
        $loan = Loan::factory()->for($asset)->create([
            'borrow_date' => today()->subDays(5),
            'due_date' => today()->subDays(2),
            'borrower_name' => 'Andi',
        ]);

        $this->assertSame(today()->toDateString(), $loan->occupiedPeriod()[1]->toDateString());
        $this->book($asset, 1, 2);
    }

    // ---------- serahkan ----------

    /**
     * @return array<string, array{0: int}>
     */
    public static function handoverOffsetProvider(): array
    {
        return ['sebelum rencana' => [-2], 'tepat rencana' => [0], 'setelah rencana' => [1]];
    }

    #[DataProvider('handoverOffsetProvider')]
    public function test_hand_over_marks_loan_and_asset_borrowed(int $offset): void
    {
        $asset = $this->asset();
        $booking = $this->book($asset, 3, 6);
        $asset->update(['condition' => AssetCondition::RusakRingan]);
        $this->travel(3 + $offset)->days();

        $this->patchJson("/api/loans/{$booking->id}/hand-over", [])
            ->assertOk()
            ->assertJsonPath('data.status', 'Dipinjam')
            ->assertJsonPath('data.handover_date', today()->toDateString())
            ->assertJsonPath('data.borrow_date', $booking->borrow_date->toDateString())
            ->assertJsonPath('data.condition_out', 'Rusak Ringan');

        $this->assertSame(AssetStatus::Dipinjam, $asset->fresh()->status);
        $event = $asset->events()->sole();
        $this->assertSame(EventType::Dipinjam, $event->event_type);
        $this->assertSame('Budi Santoso', $event->party);
        $this->assertSame($booking->id, $event->loan_id);
    }

    public function test_hand_over_with_past_date_records_event_on_that_date(): void
    {
        $asset = $this->asset();
        $booking = $this->book($asset, 1, 6);
        $this->travel(3)->days();

        $this->patchJson("/api/loans/{$booking->id}/hand-over", ['handover_date' => today()->subDay()->toDateString()])
            ->assertOk()
            ->assertJsonPath('data.handover_date', today()->subDay()->toDateString());

        $this->assertSame(today()->subDay()->toDateString(), $asset->events()->sole()->event_date->toDateString());
    }

    public function test_hand_over_requires_available_asset(): void
    {
        $asset = $this->asset();
        $booking = $this->book($asset, 3, 4);
        $asset->update(['status' => AssetStatus::Perbaikan]);

        $this->patchJson("/api/loans/{$booking->id}/hand-over", [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['asset_id' => 'Aset berstatus Perbaikan, tidak bisa dipinjam.']);

        $this->assertSame(LoanStatus::Direncanakan, $booking->fresh()->status);
    }

    public function test_hand_over_date_rules(): void
    {
        $booking = $this->book($this->asset(), 1, 2);

        $this->patchJson("/api/loans/{$booking->id}/hand-over", ['handover_date' => today()->addDay()->toDateString()])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['handover_date' => 'Tanggal penyerahan tidak boleh di masa depan.']);

        $this->travel(4)->days();
        $this->patchJson("/api/loans/{$booking->id}/hand-over", [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['handover_date' => 'Tanggal penyerahan tidak boleh setelah rencana kembali.']);
    }

    public function test_early_hand_over_cannot_overlap_another_booking(): void
    {
        $asset = $this->asset();
        $this->book($asset, 2, 3);
        $later = $this->book($asset, 5, 7, ['borrower_name' => 'Rina']);

        $this->patchJson("/api/loans/{$later->id}/hand-over", [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['handover_date']);

        $this->assertSame(LoanStatus::Direncanakan, $later->fresh()->status);
    }

    public function test_hand_over_only_for_planned_loans(): void
    {
        $loan = $this->book($this->asset(), 0, 2);

        $this->patchJson("/api/loans/{$loan->id}/hand-over", [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['loan' => 'Transaksi tidak berstatus Direncanakan.']);
    }

    public function test_planned_loan_cannot_be_returned(): void
    {
        $booking = $this->book($this->asset(), 2, 3);

        $this->patchJson("/api/loans/{$booking->id}/return", ['return_condition' => 'Baik'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['loan' => 'Transaksi tidak berstatus Dipinjam.']);
    }

    // ---------- batal & belum diserahkan ----------

    public function test_cancel_booking_requires_reason_and_keeps_asset(): void
    {
        $asset = $this->asset(AssetStatus::Perbaikan, AssetCondition::RusakBerat);
        $booking = $this->book($asset, 2, 3);

        $this->patchJson("/api/loans/{$booking->id}/cancel", [])->assertUnprocessable()->assertJsonValidationErrors(['cancel_reason']);

        $this->patchJson("/api/loans/{$booking->id}/cancel", ['cancel_reason' => 'Acara ditunda'])
            ->assertOk()
            ->assertJsonPath('data.status', 'Dibatalkan')
            ->assertJsonPath('data.cancel_reason', 'Acara ditunda');

        $this->assertSame(AssetStatus::Perbaikan, $asset->fresh()->status);
        $this->assertSame(0, $asset->events()->count());
    }

    public function test_past_planned_loan_stays_planned_and_is_flagged_unhanded(): void
    {
        $asset = $this->asset();
        $booking = $this->book($asset, 1, 3);
        $this->travel(2)->days();

        $this->getJson("/api/loans/{$booking->id}")
            ->assertOk()
            ->assertJsonPath('data.status', 'Direncanakan')
            ->assertJsonPath('data.is_unhanded', true)
            ->assertJsonPath('data.is_overdue', false);

        $this->assertSame(AssetStatus::Tersedia, $asset->fresh()->status);
    }

    // ---------- daftar & tampilan ----------

    public function test_list_filters_planned_and_orders_them_first(): void
    {
        $asset = $this->asset();
        $active = $this->book($asset, 0, 1);
        $later = $this->book($asset, 8, 9);
        $sooner = $this->book($asset, 4, 5);

        $this->getJson('/api/loans?status=Direncanakan')->assertOk()->assertJsonPath('meta.total', 2)
            ->assertJsonPath('data.0.id', $sooner->id)
            ->assertJsonPath('data.1.id', $later->id);

        $this->assertSame([$sooner->id, $later->id, $active->id], array_column($this->getJson('/api/loans')->json('data'), 'id'));
    }

    public function test_dashboard_lists_handovers_due_today_and_unhanded(): void
    {
        $asset = $this->asset();
        $unhanded = $this->book($asset, 1, 1);
        $today = $this->book($asset, 3, 3);
        $this->book($asset, 4, 5);
        $this->travel(3)->days();

        $panel = $this->getJson('/api/dashboard/panels')->assertOk()->json('data.handovers_today');

        $this->assertSame([$unhanded->id, $today->id], array_column($panel, 'id'));
        $this->assertTrue($panel[0]['is_unhanded']);
        $this->assertFalse($panel[1]['is_unhanded']);
    }

    public function test_asset_detail_and_scan_show_nearest_booking_with_borrower(): void
    {
        $asset = $this->asset();
        $this->book($asset, 9, 10, ['borrower_name' => 'Rina']);
        $this->book($asset, 4, 5);

        foreach (["/api/assets/{$asset->id}", "/api/assets/scan/{$asset->code}"] as $url) {
            $this->getJson($url)
                ->assertOk()
                ->assertJsonPath('data.status', 'Tersedia')
                ->assertJsonPath('data.upcoming_booking.borrow_date', today()->addDays(4)->toDateString())
                ->assertJsonPath('data.upcoming_booking.borrower_name', 'Budi Santoso');
        }
    }

    public function test_public_page_shows_booking_date_without_borrower(): void
    {
        $asset = $this->asset();
        $this->book($asset, 4, 5);
        $this->app['auth']->forgetGuards();

        $booking = $this->getJson("/api/public/assets/{$asset->public_token}")->assertOk()->json('data.upcoming_booking');

        $this->assertSame(['borrow_date' => today()->addDays(4)->toDateString()], $booking);
    }
}
