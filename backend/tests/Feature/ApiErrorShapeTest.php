<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Route as LaravelRoute;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * Semua error API berupa JSON (SPEC §14/§15): 404 id tidak ada, 405, rute tidak dikenal, 422 Indonesia.
 */
class ApiErrorShapeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAs(User::factory()->create());
    }

    /**
     * Rute ber-id numerik (route model binding), selain GET yang punya pesan 404 sendiri.
     *
     * @return list<LaravelRoute>
     */
    private function idRoutes(): array
    {
        return collect(Route::getRoutes()->getRoutes())
            ->filter(fn (LaravelRoute $route) => str_starts_with($route->uri(), 'api/') && $route->wheres !== [] && str_contains($route->uri(), '{') && ! str_contains($route->uri(), '{type}'))
            ->values()
            ->all();
    }

    public function test_unknown_id_returns_json_404_on_every_numeric_id_route(): void
    {
        $routes = $this->idRoutes();
        $this->assertGreaterThanOrEqual(36, count($routes), 'Jumlah rute ber-id berkurang tanpa disengaja.');

        foreach ($routes as $route) {
            $method = $route->methods()[0];
            $uri = '/'.preg_replace('/\{\w+\}/', '999999', $route->uri());

            $this->json($method, $uri, [])
                ->assertNotFound()
                ->assertJsonPath('message', 'Data tidak ditemukan.');
        }
    }

    public function test_unknown_api_route_and_wrong_method_return_json(): void
    {
        $this->getJson('/api/tidak-ada')->assertNotFound()->assertJsonPath('message', 'Data tidak ditemukan.');
        $this->getJson('/api/reports/semua')->assertNotFound();
        $this->patchJson('/api/assets', [])->assertStatus(405)->assertJsonStructure(['message']);
        $this->deleteJson('/api/dashboard/cards')->assertStatus(405)->assertJsonStructure(['message']);
    }

    public function test_validation_errors_have_message_and_errors_in_indonesian(): void
    {
        $cases = [
            ['POST', '/api/consumables', ['name']],
            ['POST', '/api/loans', ['asset_id', 'borrower_name']],
            ['POST', '/api/supply-requests', ['quantity']],
            ['POST', '/api/assets', ['asset_type_id', 'name']],
            ['POST', '/api/request-catalog', ['item_name']],
        ];

        foreach ($cases as [$method, $uri, $fields]) {
            $response = $this->json($method, $uri, [])->assertUnprocessable()->assertJsonValidationErrors($fields);

            $this->assertIsString($response->json('message'));

            foreach ($fields as $field) {
                $text = $response->json("errors.{$field}.0");
                $this->assertIsString($text);
                $this->assertDoesNotMatchRegularExpression('/\b(is required|field is|must be|The )\b/', $text, "Pesan {$uri} {$field} bukan Bahasa Indonesia: {$text}");
                $this->assertStringContainsString('wajib', $text);
            }
        }
    }

    public function test_malformed_ids_in_query_return_422_not_500(): void
    {
        $this->getJson('/api/assets?room_id=abc')->assertUnprocessable();
        $this->getJson('/api/loans?status=Salah')->assertUnprocessable();
        $this->getJson('/api/consumables?per_page=1000')->assertUnprocessable();
        $this->getJson('/api/supply-requests?from=bukan-tanggal')->assertUnprocessable();
    }
}
