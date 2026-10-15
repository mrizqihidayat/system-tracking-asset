<?php

namespace Tests\Feature;

use App\Enums\AssetCondition;
use App\Enums\AssetStatus;
use App\Enums\EventType;
use App\Models\Asset;
use App\Models\Loan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class AssetReportServiceTest extends TestCase
{
    use RefreshDatabase;

    private User $staff;

    protected function setUp(): void
    {
        parent::setUp();

        $this->staff = User::factory()->create();
        $this->actingAs($this->staff);
    }

    private function asset(AssetStatus $status = AssetStatus::Tersedia, AssetCondition $condition = AssetCondition::Baik): Asset
    {
        $asset = Asset::factory()->create(['status' => $status, 'condition' => $condition]);

        if ($status === AssetStatus::Dipinjam) {
            Loan::factory()->for($asset)->create();
        }

        return $asset;
    }

    /**
     * @return array<string, array{0: string, 1: string}>
     */
    public static function endpointProvider(): array
    {
        return [
            'report' => ['POST', '/api/assets/1/report'],
            'found' => ['POST', '/api/assets/1/found'],
            'services' => ['POST', '/api/assets/1/services'],
        ];
    }

    #[DataProvider('endpointProvider')]
    public function test_endpoints_require_token(string $method, string $uri): void
    {
        $this->app['auth']->forgetGuards();

        $this->json($method, $uri)->assertUnauthorized();
    }

    // ---------- catat kerusakan ----------

    /**
     * @return array<string, array{0: string, 1: ?string, 2: string}>
     */
    public static function damageProvider(): array
    {
        return [
            'rusak berat → perbaikan' => ['Rusak Berat', null, 'Perbaikan'],
            'rusak ringan default tersedia' => ['Rusak Ringan', null, 'Tersedia'],
            'rusak ringan pilih perbaikan' => ['Rusak Ringan', 'Perbaikan', 'Perbaikan'],
        ];
    }

    #[DataProvider('damageProvider')]
    public function test_report_damage_updates_condition_and_status(string $condition, ?string $status, string $expected): void
    {
        $asset = $this->asset();

        $this->postJson("/api/assets/{$asset->id}/report", ['type' => 'rusak', 'condition' => $condition, 'status' => $status, 'note' => 'Lampu redup'])
            ->assertOk()
            ->assertJsonPath('data.condition', $condition)
            ->assertJsonPath('data.status', $expected);

        $event = $asset->events()->sole();
        $this->assertSame(EventType::LaporRusak, $event->event_type);
        $this->assertSame($condition, $event->condition->value);
        $this->assertSame('Lampu redup', $event->note);
        $this->assertSame('SHD', $event->party);
        $this->assertSame($this->staff->id, $event->recorded_by);
    }

    public function test_report_damage_while_borrowed_only_changes_condition(): void
    {
        $asset = $this->asset(AssetStatus::Dipinjam);

        $this->postJson("/api/assets/{$asset->id}/report", ['type' => 'rusak', 'condition' => 'Rusak Berat', 'note' => 'Dilaporkan peminjam', 'party' => 'Andi'])
            ->assertOk()
            ->assertJsonPath('data.condition', 'Rusak Berat')
            ->assertJsonPath('data.status', 'Dipinjam');

        $this->assertSame('Andi', $asset->events()->value('party'));
    }

    public function test_report_damage_validation(): void
    {
        $asset = $this->asset();

        $this->postJson("/api/assets/{$asset->id}/report", [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['type' => 'Jenis laporan wajib diisi.', 'note' => 'Catatan wajib diisi.']);

        $this->postJson("/api/assets/{$asset->id}/report", ['type' => 'rusak', 'note' => 'x'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['condition' => 'Kondisi wajib diisi.']);

        $this->postJson("/api/assets/{$asset->id}/report", ['type' => 'rusak', 'condition' => 'Baik', 'note' => 'x'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['condition' => 'Kondisi harus Rusak Ringan atau Rusak Berat.']);

        $this->postJson("/api/assets/{$asset->id}/report", ['type' => 'rusak', 'condition' => 'Rusak Berat', 'status' => 'Tersedia', 'note' => 'x'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['status' => 'Status hanya bisa dipilih jika kondisi Rusak Ringan.']);

        $this->postJson("/api/assets/{$asset->id}/report", ['type' => 'hancur', 'note' => 'x'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['type' => 'Jenis laporan harus rusak, hilang, atau tidak layak pakai.']);

        $this->assertSame(0, $asset->events()->count());
    }

    public function test_report_damage_on_lost_asset_is_rejected(): void
    {
        $asset = $this->asset(AssetStatus::Hilang);

        $this->postJson("/api/assets/{$asset->id}/report", ['type' => 'rusak', 'condition' => 'Rusak Ringan', 'note' => 'x'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['asset' => 'Aset berstatus Hilang. Tandai ditemukan dulu.']);
    }

    // ---------- catat kehilangan ----------

    public function test_report_lost_sets_status_hilang_and_keeps_condition(): void
    {
        $asset = $this->asset(AssetStatus::Perbaikan, AssetCondition::RusakRingan);

        $this->postJson("/api/assets/{$asset->id}/report", ['type' => 'hilang', 'note' => 'Tidak ada di gudang'])
            ->assertOk()
            ->assertJsonPath('data.status', 'Hilang')
            ->assertJsonPath('data.condition', 'Rusak Ringan');

        $this->assertSame(EventType::LaporHilang, $asset->events()->sole()->event_type);
    }

    public function test_report_lost_rejects_condition_borrowed_and_already_lost(): void
    {
        $asset = $this->asset();
        $this->postJson("/api/assets/{$asset->id}/report", ['type' => 'hilang', 'condition' => 'Rusak Berat', 'note' => 'x'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['condition' => 'Kondisi hanya diisi untuk catat kerusakan.']);

        $borrowed = $this->asset(AssetStatus::Dipinjam);
        $this->postJson("/api/assets/{$borrowed->id}/report", ['type' => 'hilang', 'note' => 'x'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['asset' => 'Aset sedang dipinjam. Selesaikan lewat peminjaman.']);

        $lost = $this->asset(AssetStatus::Hilang);
        $this->postJson("/api/assets/{$lost->id}/report", ['type' => 'hilang', 'note' => 'x'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['asset' => 'Aset sudah berstatus Hilang.']);
    }

    // ---------- tandai ditemukan ----------

    /**
     * @return array<string, array{0: string, 1: ?string, 2: string}>
     */
    public static function foundProvider(): array
    {
        return [
            'baik → tersedia' => ['Baik', null, 'Tersedia'],
            'rusak berat → perbaikan' => ['Rusak Berat', null, 'Perbaikan'],
            'rusak ringan pilih perbaikan' => ['Rusak Ringan', 'Perbaikan', 'Perbaikan'],
        ];
    }

    #[DataProvider('foundProvider')]
    public function test_mark_found_restores_status_from_condition(string $condition, ?string $status, string $expected): void
    {
        $asset = $this->asset(AssetStatus::Hilang);

        $this->postJson("/api/assets/{$asset->id}/found", ['condition' => $condition, 'status' => $status, 'note' => 'Ada di Auditorium'])
            ->assertOk()
            ->assertJsonPath('data.status', $expected)
            ->assertJsonPath('data.condition', $condition);

        $event = $asset->events()->sole();
        $this->assertSame(EventType::Ditemukan, $event->event_type);
        $this->assertSame('Ada di Auditorium', $event->note);
    }

    public function test_mark_found_only_for_lost_assets_and_never_borrowed(): void
    {
        $asset = $this->asset();

        $this->postJson("/api/assets/{$asset->id}/found", ['condition' => 'Baik'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['asset' => 'Hanya aset berstatus Hilang yang bisa ditandai ditemukan.']);

        $lost = $this->asset(AssetStatus::Hilang);
        $this->postJson("/api/assets/{$lost->id}/found", ['condition' => 'Rusak Ringan', 'status' => 'Dipinjam'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['status' => 'Status harus Tersedia atau Perbaikan.']);

        $this->postJson("/api/assets/{$lost->id}/found", [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['condition' => 'Kondisi wajib diisi.']);
    }

    // ---------- catat servis ----------

    public function test_service_updates_condition_status_next_date_and_event(): void
    {
        $asset = $this->asset(AssetStatus::Perbaikan, AssetCondition::RusakBerat);
        $asset->update(['next_service_date' => '2026-01-01']);

        $this->postJson("/api/assets/{$asset->id}/services", [
            'service_date' => today()->subDay()->toDateString(),
            'vendor' => 'CV Mitra Proyeksi',
            'condition' => 'Baik',
            'next_service_date' => today()->addMonths(6)->toDateString(),
            'note' => 'Ganti lampu',
        ])
            ->assertOk()
            ->assertJsonPath('data.condition', 'Baik')
            ->assertJsonPath('data.status', 'Tersedia')
            ->assertJsonPath('data.next_service_date', today()->addMonths(6)->toDateString());

        $event = $asset->events()->sole();
        $this->assertSame(EventType::Servis, $event->event_type);
        $this->assertSame('CV Mitra Proyeksi', $event->party);
        $this->assertSame(today()->subDay()->toDateString(), $event->event_date->toDateString());
        $this->assertSame('Ganti lampu', $event->note);
    }

    public function test_service_status_follows_a6_and_keeps_next_date_when_empty(): void
    {
        $asset = $this->asset(AssetStatus::Perbaikan, AssetCondition::RusakBerat);
        $asset->update(['next_service_date' => '2027-03-01']);
        $payload = ['service_date' => today()->toDateString(), 'vendor' => 'Vendor A'];

        $this->postJson("/api/assets/{$asset->id}/services", [...$payload, 'condition' => 'Rusak Ringan'])
            ->assertOk()
            ->assertJsonPath('data.status', 'Tersedia')
            ->assertJsonPath('data.next_service_date', '2027-03-01');

        $this->postJson("/api/assets/{$asset->id}/services", [...$payload, 'condition' => 'Rusak Ringan', 'status' => 'Perbaikan'])
            ->assertOk()
            ->assertJsonPath('data.status', 'Perbaikan');

        $this->postJson("/api/assets/{$asset->id}/services", [...$payload, 'condition' => 'Rusak Berat'])
            ->assertOk()
            ->assertJsonPath('data.status', 'Perbaikan');
    }

    public function test_service_validation_and_status_guards(): void
    {
        $asset = $this->asset();

        $this->postJson("/api/assets/{$asset->id}/services", [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'service_date' => 'Tanggal servis wajib diisi.',
                'vendor' => 'Vendor wajib diisi.',
                'condition' => 'Kondisi wajib diisi.',
            ]);

        $this->postJson("/api/assets/{$asset->id}/services", [
            'service_date' => today()->addDay()->toDateString(),
            'vendor' => 'V',
            'condition' => 'Baik',
            'next_service_date' => today()->toDateString(),
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'service_date' => 'Tanggal servis tidak boleh setelah hari ini.',
                'next_service_date' => 'Servis berikutnya harus setelah tanggal servis.',
            ]);

        $payload = ['service_date' => today()->toDateString(), 'vendor' => 'V', 'condition' => 'Baik'];

        $borrowed = $this->asset(AssetStatus::Dipinjam);
        $this->postJson("/api/assets/{$borrowed->id}/services", $payload)
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['asset' => 'Aset sedang dipinjam.']);

        $lost = $this->asset(AssetStatus::Hilang);
        $this->postJson("/api/assets/{$lost->id}/services", $payload)
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['asset' => 'Aset berstatus Hilang. Tandai ditemukan dulu.']);
    }

    // ---------- tidak layak pakai ----------

    /**
     * @return array<string, array{0: AssetStatus}>
     */
    public static function unfitAllowedProvider(): array
    {
        return ['tersedia' => [AssetStatus::Tersedia], 'perbaikan' => [AssetStatus::Perbaikan]];
    }

    #[DataProvider('unfitAllowedProvider')]
    public function test_report_unfit_from_available_or_repair(AssetStatus $from): void
    {
        $asset = $this->asset($from, AssetCondition::RusakBerat);

        $this->postJson("/api/assets/{$asset->id}/report", ['type' => 'tidak_layak_pakai', 'note' => 'Rangka patah'])
            ->assertOk()
            ->assertJsonPath('data.status', 'Tidak Layak Pakai')
            ->assertJsonPath('data.condition', 'Rusak Berat');

        $event = $asset->events()->sole();
        $this->assertSame(EventType::TidakLayakPakai, $event->event_type);
        $this->assertSame('Rangka patah', $event->note);
    }

    public function test_report_unfit_rejected_for_other_statuses_and_requires_note(): void
    {
        $this->postJson("/api/assets/{$this->asset()->id}/report", ['type' => 'tidak_layak_pakai'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['note' => 'Catatan wajib diisi.']);

        $borrowed = $this->asset(AssetStatus::Dipinjam);
        $this->postJson("/api/assets/{$borrowed->id}/report", ['type' => 'tidak_layak_pakai', 'note' => 'x'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['asset' => 'Aset sedang dipinjam. Selesaikan lewat peminjaman.']);

        $lost = $this->asset(AssetStatus::Hilang);
        $this->postJson("/api/assets/{$lost->id}/report", ['type' => 'tidak_layak_pakai', 'note' => 'x'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['asset' => 'Aset berstatus Hilang, tidak bisa dicatat tidak layak pakai.']);
    }

    public function test_unfit_asset_rejects_every_status_action(): void
    {
        $asset = $this->asset(AssetStatus::TidakLayakPakai, AssetCondition::RusakBerat);
        $unfit = ['asset' => 'Aset berstatus Tidak Layak Pakai.'];

        $this->postJson("/api/assets/{$asset->id}/report", ['type' => 'rusak', 'condition' => 'Rusak Berat', 'note' => 'x'])
            ->assertUnprocessable()->assertJsonValidationErrors($unfit);
        $this->postJson("/api/assets/{$asset->id}/report", ['type' => 'hilang', 'note' => 'x'])
            ->assertUnprocessable()->assertJsonValidationErrors($unfit);
        $this->postJson("/api/assets/{$asset->id}/report", ['type' => 'tidak_layak_pakai', 'note' => 'x'])
            ->assertUnprocessable()->assertJsonValidationErrors(['asset' => 'Aset berstatus Tidak Layak Pakai, tidak bisa dicatat tidak layak pakai.']);
        $this->postJson("/api/assets/{$asset->id}/services", ['service_date' => today()->toDateString(), 'vendor' => 'V', 'condition' => 'Baik'])
            ->assertUnprocessable()->assertJsonValidationErrors($unfit);
        $this->postJson("/api/assets/{$asset->id}/found", ['condition' => 'Baik'])
            ->assertUnprocessable()->assertJsonValidationErrors(['asset' => 'Hanya aset berstatus Hilang yang bisa ditandai ditemukan.']);
        $this->postJson('/api/loans', [
            'asset_id' => $asset->id, 'borrower_name' => 'A', 'borrower_unit' => 'SGA', 'purpose' => 'x', 'due_date' => today()->toDateString(),
        ])->assertUnprocessable()->assertJsonValidationErrors(['asset_id' => 'Aset berstatus Tidak Layak Pakai, tidak bisa dipinjam.']);

        $this->assertSame(['pindah_lokasi'], AssetStatus::TidakLayakPakai->allowedActions());
        $this->assertSame(0, $asset->events()->count());
    }

    public function test_deleted_asset_returns_404(): void
    {
        $asset = $this->asset();
        $asset->delete();

        $this->postJson("/api/assets/{$asset->id}/report", ['type' => 'hilang', 'note' => 'x'])->assertNotFound();
        $this->postJson("/api/assets/{$asset->id}/services", ['service_date' => today()->toDateString(), 'vendor' => 'V', 'condition' => 'Baik'])->assertNotFound();
    }
}
