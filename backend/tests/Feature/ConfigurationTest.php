<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Env;
use PHPUnit\Framework\Attributes\DataProvider;
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

    /**
     * @return array<string, array{0: string, 1: string, 2: string}>
     */
    public static function qrBaseUrlProvider(): array
    {
        return [
            'kosong → frontend_url' => ['', 'http://localhost:5173', 'http://localhost:5173'],
            'kosong → origin pertama' => ['', 'https://aset.example.com/, http://localhost:5173', 'https://aset.example.com'],
            'diisi' => ['https://qr.example.com', 'http://localhost:5173', 'https://qr.example.com'],
            'trailing slash dibuang' => ['https://qr.example.com//', 'http://localhost:5173', 'https://qr.example.com'],
        ];
    }

    #[DataProvider('qrBaseUrlProvider')]
    public function test_qr_base_url_resolution(string $qrBaseUrl, string $frontendUrl, string $expected): void
    {
        $config = $this->loadAppConfigWith(['QR_BASE_URL' => $qrBaseUrl, 'FRONTEND_URL' => $frontendUrl]);

        $this->assertSame($expected, $config['qr_base_url']);
        $this->assertStringNotContainsString('//scan', $config['qr_base_url'].'/scan/SHD-PRJ-001');
    }

    /**
     * @param  array<string, string>  $env
     * @return array<string, mixed>
     */
    private function loadAppConfigWith(array $env): array
    {
        $original = [];

        foreach ($env as $key => $value) {
            $original[$key] = Env::get($key);
            Env::getRepository()->set($key, $value);
        }

        try {
            return require config_path('app.php');
        } finally {
            foreach ($original as $key => $value) {
                $value === null ? Env::getRepository()->clear($key) : Env::getRepository()->set($key, (string) $value);
            }
        }
    }
}
