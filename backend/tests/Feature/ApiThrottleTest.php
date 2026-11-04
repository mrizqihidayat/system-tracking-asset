<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ApiThrottleTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Rate limit dihitung sebelum validasi, jadi request yang akhirnya ditolak pun masuk hitungan.
     *
     * @return array<string, array{0: string, 1: string, 2: int}>
     */
    public static function limitedEndpointProvider(): array
    {
        return [
            'laporan' => ['GET', '/api/reports/assets?search[]=x', 10],
            'label satu aset' => ['GET', '/api/assets/999999/label', 20],
            'label banyak aset' => ['POST', '/api/assets/labels', 20],
            'unggah file' => ['POST', '/api/assets/999999/files', 30],
        ];
    }

    #[DataProvider('limitedEndpointProvider')]
    public function test_endpoint_returns_429_in_indonesian_after_limit_per_user(string $method, string $uri, int $limit): void
    {
        $this->actingAs(User::factory()->create());

        for ($i = 0; $i < $limit; $i++) {
            $this->assertNotSame(429, $this->json($method, $uri)->status(), "Permintaan ke-{$i} tidak boleh dibatasi.");
        }

        $blocked = $this->json($method, $uri)->assertTooManyRequests();

        $this->assertMatchesRegularExpression('/^Terlalu banyak permintaan\. Coba lagi dalam \d+ detik\.$/', $blocked->json('message'));
        $this->assertNotNull($blocked->headers->get('Retry-After'));

        // Pengguna lain punya jatah sendiri.
        $this->actingAs(User::factory()->create());
        $this->assertNotSame(429, $this->json($method, $uri)->status());
    }

    public function test_single_and_bulk_labels_share_one_limit(): void
    {
        $this->actingAs(User::factory()->create());

        for ($i = 0; $i < 20; $i++) {
            $this->getJson('/api/assets/999999/label');
        }

        $this->postJson('/api/assets/labels')->assertTooManyRequests();
    }

    public function test_limit_resets_after_a_minute(): void
    {
        $this->actingAs(User::factory()->create());

        for ($i = 0; $i < 10; $i++) {
            $this->getJson('/api/reports/assets?search[]=x');
        }
        $this->getJson('/api/reports/assets?search[]=x')->assertTooManyRequests();

        $this->travel(61)->seconds();

        $this->getJson('/api/reports/assets?search[]=x')->assertUnprocessable();
    }
}
