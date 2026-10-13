<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ConfigurationTest extends TestCase
{
    use RefreshDatabase;

    public function test_cors_preflight_allows_frontend_origin(): void
    {
        $origin = config('app.frontend_url');

        $response = $this->options('/api/login', [], [
            'Origin' => $origin,
            'Access-Control-Request-Method' => 'POST',
        ]);

        $response->assertNoContent();
        $response->assertHeader('Access-Control-Allow-Origin', $origin);
    }

    public function test_cors_preflight_rejects_unknown_origin(): void
    {
        $response = $this->options('/api/login', [], [
            'Origin' => 'https://evil.example.com',
            'Access-Control-Request-Method' => 'POST',
        ]);

        $this->assertNotSame('https://evil.example.com', $response->headers->get('Access-Control-Allow-Origin'));
        $this->assertNotSame('*', $response->headers->get('Access-Control-Allow-Origin'));
    }

    public function test_token_is_valid_until_expiration(): void
    {
        $token = User::factory()->create()->createToken('web')->plainTextToken;

        $this->travel(479)->minutes();

        $this->withToken($token)->getJson('/api/user')->assertOk();
    }

    public function test_token_expires_after_480_minutes(): void
    {
        $token = User::factory()->create()->createToken('web')->plainTextToken;

        $this->travel(481)->minutes();

        $this->withToken($token)->getJson('/api/user')->assertUnauthorized();
    }

    public function test_qr_base_url_defaults_to_frontend_scan_path(): void
    {
        if (filled(env('QR_BASE_URL'))) {
            $this->markTestSkipped('QR_BASE_URL diisi di environment.');
        }

        $this->assertSame(config('app.frontend_url').'/scan', config('app.qr_base_url'));
    }
}
