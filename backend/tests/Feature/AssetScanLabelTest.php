<?php

namespace Tests\Feature;

use App\Enums\AssetStatus;
use App\Enums\EventType;
use App\Models\Asset;
use App\Models\AssetCodeHistory;
use App\Models\Loan;
use App\Models\Room;
use App\Models\User;
use App\Services\AssetLabelService;
use App\Services\QrCodeService;
use chillerlan\QRCode\QRCode;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class AssetScanLabelTest extends TestCase
{
    use RefreshDatabase;

    private Asset $asset;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAs(User::factory()->create());
        $this->asset = Asset::factory()->create(['code' => 'SHD-PRJ-001']);
    }

    /**
     * @return array<string, array{0: string, 1: string}>
     */
    public static function endpointProvider(): array
    {
        return [
            'scan' => ['GET', '/api/assets/scan/SHD-PRJ-001'],
            'move' => ['PATCH', '/api/assets/1/location'],
            'label' => ['GET', '/api/assets/1/label'],
            'labels' => ['POST', '/api/assets/labels'],
        ];
    }

    #[DataProvider('endpointProvider')]
    public function test_endpoints_require_token(string $method, string $uri): void
    {
        $this->app['auth']->forgetGuards();

        $this->json($method, $uri)->assertUnauthorized();
    }

    // ---------- scan ----------

    public function test_scan_by_code_is_case_insensitive(): void
    {
        $this->getJson('/api/assets/scan/shd-prj-001')
            ->assertOk()
            ->assertJsonPath('data.id', $this->asset->id)
            ->assertJsonPath('meta.matched_by', 'code')
            ->assertJsonPath('meta.actions', ['pinjam', 'pindah_lokasi', 'catat_kerusakan', 'catat_kehilangan', 'catat_tidak_layak_pakai']);
    }

    public function test_scan_by_previous_code_and_token(): void
    {
        AssetCodeHistory::factory()->for($this->asset)->create(['code' => 'SHD-LYR-007', 'asset_type_id' => $this->asset->asset_type_id]);

        $this->getJson('/api/assets/scan/SHD-LYR-007')
            ->assertOk()
            ->assertJsonPath('data.code', 'SHD-PRJ-001')
            ->assertJsonPath('meta.matched_by', 'previous_code');

        $this->getJson("/api/assets/scan/{$this->asset->public_token}")
            ->assertOk()
            ->assertJsonPath('data.id', $this->asset->id)
            ->assertJsonPath('meta.matched_by', 'token');

        $this->getJson('/api/assets/scan/'.strtolower($this->asset->public_token))->assertNotFound();
    }

    public function test_scan_of_borrowed_asset_includes_cancel_action_for_staff(): void
    {
        Loan::factory()->for($this->asset)->create();
        $this->asset->update(['status' => AssetStatus::Dipinjam]);

        $this->getJson('/api/assets/scan/SHD-PRJ-001')
            ->assertOk()
            ->assertJsonPath('meta.actions', ['terima_kembali', 'pindah_lokasi', 'catat_kerusakan', 'batalkan'])
            ->assertJsonPath('data.current_location.on_loan', true);
    }

    public function test_scan_unknown_or_deleted_asset_returns_404(): void
    {
        $this->getJson('/api/assets/scan/SHD-XXX-999')
            ->assertNotFound()
            ->assertExactJson(['message' => 'Aset tidak ditemukan.']);

        $this->asset->delete();

        $this->getJson('/api/assets/scan/SHD-PRJ-001')
            ->assertNotFound()
            ->assertExactJson(['message' => 'Aset tidak ditemukan.']);
    }

    // ---------- pindah lokasi ----------

    public function test_move_location_records_event_with_note(): void
    {
        $from = $this->asset->room_id;
        $to = Room::factory()->create();

        $this->patchJson("/api/assets/{$this->asset->id}/location", ['room_id' => $to->id, 'note' => 'Dipindah setelah renovasi'])
            ->assertOk()
            ->assertJsonPath('data.room.id', $to->id);

        $event = $this->asset->events()->sole();
        $this->assertSame(EventType::PindahLokasi, $event->event_type);
        $this->assertSame($from, $event->from_room_id);
        $this->assertSame($to->id, $event->to_room_id);
        $this->assertSame('Dipindah setelah renovasi', $event->note);
    }

    public function test_move_location_allowed_for_lost_asset_and_rejects_same_or_missing_room(): void
    {
        $this->asset->update(['status' => AssetStatus::Hilang]);

        $this->patchJson("/api/assets/{$this->asset->id}/location", ['room_id' => $this->asset->room_id])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['room_id' => 'Ruangan sama dengan lokasi saat ini.']);

        $this->patchJson("/api/assets/{$this->asset->id}/location", ['room_id' => 999])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['room_id' => 'Ruangan tidak ditemukan.']);

        $this->patchJson("/api/assets/{$this->asset->id}/location", ['room_id' => Room::factory()->create()->id])->assertOk();
    }

    // ---------- label ----------

    public function test_single_label_is_pdf_inline_or_download(): void
    {
        $response = $this->get("/api/assets/{$this->asset->id}/label")->assertOk();
        $this->assertSame('application/pdf', $response->headers->get('Content-Type'));
        $this->assertStringStartsWith('%PDF', $response->getContent());
        $this->assertStringContainsString('inline; filename=label-SHD-PRJ-001.pdf', $response->headers->get('Content-Disposition'));

        $download = $this->get("/api/assets/{$this->asset->id}/label?download=1")->assertOk();
        $this->assertStringContainsString('attachment; filename=label-SHD-PRJ-001.pdf', $download->headers->get('Content-Disposition'));
    }

    public function test_bulk_labels_pdf_and_validation(): void
    {
        $other = Asset::factory()->create(['code' => 'SHD-LPT-001']);

        $response = $this->post('/api/assets/labels', ['ids' => [$this->asset->id, $other->id]])->assertOk();
        $this->assertStringStartsWith('%PDF', $response->getContent());

        $this->postJson('/api/assets/labels', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['ids' => 'Pilih minimal satu aset.']);

        $this->postJson('/api/assets/labels', ['ids' => range(1, AssetLabelService::MAX_LABELS + 1)])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['ids' => 'Maksimal 120 label sekali cetak.']);

        $other->delete();
        $this->postJson('/api/assets/labels', ['ids' => [$this->asset->id, $other->id]])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['ids.1' => 'Aset tidak ditemukan.']);
    }

    public function test_deleted_asset_single_label_returns_404(): void
    {
        $this->asset->delete();

        $this->get("/api/assets/{$this->asset->id}/label")->assertNotFound();
    }

    public function test_label_pages_are_sorted_and_grouped_3_by_4(): void
    {
        $assets = Asset::factory()->count(13)->create()->push($this->asset)->sortBy('code')->values();

        $pages = app(AssetLabelService::class)->pages($assets);

        $this->assertCount(2, $pages);
        $this->assertCount(4, $pages[0]);
        $this->assertCount(3, $pages[0][0]);
        $this->assertCount(1, $pages[1]);
        $this->assertCount(2, $pages[1][0]);
        $this->assertSame($assets->first()->code, $pages[0][0][0]['code']);
    }

    public function test_label_html_contains_code_and_qr_with_public_url(): void
    {
        config(['app.qr_base_url' => 'https://aset.example.com']);

        $html = view('pdf.asset-labels', ['pages' => app(AssetLabelService::class)->pages(collect([$this->asset]))])->render();

        $this->assertStringContainsString('SHD-PRJ-001', $html);
        $this->assertStringContainsString('src="data:image/png;base64,', $html);

        preg_match('/src="data:image\/png;base64,([^"]+)"/', $html, $match);
        $decoded = (string) (new QRCode)->readFromBlob(base64_decode($match[1]));

        $this->assertSame("https://aset.example.com/aset/{$this->asset->public_token}", $decoded);
    }

    public function test_qr_service_png_round_trip(): void
    {
        $uri = app(QrCodeService::class)->pngDataUri('https://aset.example.com/aset/AbC123xyZ789');

        $this->assertStringStartsWith('data:image/png;base64,', $uri);
        $this->assertSame(
            'https://aset.example.com/aset/AbC123xyZ789',
            (string) (new QRCode)->readFromBlob(base64_decode(substr($uri, strlen('data:image/png;base64,')))),
        );
    }
}
