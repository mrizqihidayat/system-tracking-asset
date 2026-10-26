<?php

namespace Tests\Feature;

use App\Models\Asset;
use App\Models\User;
use chillerlan\QRCode\QRCode;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AssetQrTest extends TestCase
{
    use RefreshDatabase;

    public function test_requires_token(): void
    {
        $asset = Asset::factory()->create();

        $this->getJson("/api/assets/{$asset->id}/qr")->assertUnauthorized();
    }

    public function test_returns_png_with_public_url(): void
    {
        config(['app.qr_base_url' => 'https://aset.example.com']);
        $this->actingAs(User::factory()->create());
        $asset = Asset::factory()->create();

        $response = $this->get("/api/assets/{$asset->id}/qr")->assertOk()->assertHeader('Content-Type', 'image/png');

        $png = $response->getContent();
        $this->assertStringStartsWith("\x89PNG\r\n\x1a\n", $png);
        $this->assertSame(
            "https://aset.example.com/aset/{$asset->public_token}",
            (string) (new QRCode)->readFromBlob($png),
        );
    }

    public function test_unknown_asset_is_404(): void
    {
        $this->actingAs(User::factory()->create());

        $this->getJson('/api/assets/999999/qr')->assertNotFound();
    }
}
