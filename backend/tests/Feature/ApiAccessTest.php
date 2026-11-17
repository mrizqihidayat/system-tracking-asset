<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Route as LaravelRoute;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * Kriteria 401 untuk SEMUA rute API: kecuali daftar putih, tanpa token harus 401 JSON.
 * Rute baru yang lupa dipasang auth:sanctum langsung ketahuan di sini.
 */
class ApiAccessTest extends TestCase
{
    use RefreshDatabase;

    /** Rute publik yang disengaja (method uri). */
    private const PUBLIC_ROUTES = [
        'POST api/login',
        'GET api/public/assets/{token}',
        // T38: dijaga CRON_SECRET / EMPLOYEE_MOCK_ENABLED + API key (lihat CronEmployeesSyncTest), 404 bila env kosong.
        'GET api/internal/cron/employees-sync',
        'GET api/mock/employees',
        // T40: tampilan & teks halaman login, logo/favicon (lihat SettingTest).
        'GET api/settings/public',
        'GET api/settings/files/{type}',
        // T40: lupa password via OTP (lihat PasswordOtpTest).
        'POST api/password/forgot',
        'POST api/password/verify-otp',
        'POST api/password/reset',
    ];

    /**
     * @return list<LaravelRoute>
     */
    private function apiRoutes(): array
    {
        return collect(Route::getRoutes()->getRoutes())
            ->filter(fn (LaravelRoute $route) => str_starts_with($route->uri(), 'api/'))
            ->values()
            ->all();
    }

    private function key(LaravelRoute $route): string
    {
        return $route->methods()[0].' '.$route->uri();
    }

    private function sampleUri(LaravelRoute $route): string
    {
        return preg_replace_callback('/\{(\w+)\}/', fn (array $match) => match ($match[1]) {
            'type' => str_contains($route->uri(), 'settings/files') ? 'logo' : 'assets',
            'value', 'token' => 'abc',
            default => '1',
        }, $route->uri());
    }

    /**
     * @return list<LaravelRoute>
     */
    private function protectedRoutes(): array
    {
        return array_values(array_filter($this->apiRoutes(), fn (LaravelRoute $route) => ! in_array($this->key($route), self::PUBLIC_ROUTES, true)));
    }

    public function test_every_non_public_api_route_is_behind_sanctum(): void
    {
        $routes = $this->protectedRoutes();

        $this->assertGreaterThanOrEqual(70, count($routes), 'Jumlah rute terlindungi berkurang tanpa disengaja.');

        foreach ($routes as $route) {
            $this->assertContains('auth:sanctum', $route->gatherMiddleware(), "Rute {$this->key($route)} tidak dilindungi auth:sanctum.");
        }
    }

    public function test_every_non_public_api_route_returns_401_json_without_token(): void
    {
        foreach ($this->protectedRoutes() as $route) {
            $this->app['auth']->forgetGuards();

            $this->json($route->methods()[0], '/'.$this->sampleUri($route))
                ->assertUnauthorized();
        }
    }

    public function test_invalid_token_is_rejected_on_every_non_public_api_route(): void
    {
        foreach ($this->protectedRoutes() as $route) {
            $this->app['auth']->forgetGuards();

            $this->withToken('999|token-palsu')->json($route->methods()[0], '/'.$this->sampleUri($route))
                ->assertUnauthorized();
        }
    }

    public function test_public_routes_do_not_require_a_token(): void
    {
        $this->postJson('/api/login', [])->assertUnprocessable();
        $this->getJson('/api/public/assets/tidak-ada')->assertNotFound()->assertJsonPath('message', 'Aset tidak ditemukan.');

        $public = array_map(fn (LaravelRoute $route) => $this->key($route), array_filter(
            $this->apiRoutes(),
            fn (LaravelRoute $route) => ! in_array('auth:sanctum', $route->gatherMiddleware(), true),
        ));

        $this->assertEqualsCanonicalizing(self::PUBLIC_ROUTES, $public);
    }
}
