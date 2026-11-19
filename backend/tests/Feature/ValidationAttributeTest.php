<?php

namespace Tests\Feature;

use App\Enums\CountMethod;
use App\Models\Room;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * T45 (temuan UAT U4): pesan validasi yang dilihat pengguna memakai nama kolom berbahasa Indonesia,
 * bukan nama kolom API (mis. "Standard quantity minimal 1." → "Jumlah standar minimal 1.").
 */
class ValidationAttributeTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Semua kolom di rules() setiap FormRequest wajib punya nama di lang/id/validation.php (attributes) atau di
     * attributes() request itu sendiri. Dibaca dari sumber agar request yang butuh rute/model tetap ikut diperiksa.
     */
    public function test_every_validated_field_has_an_indonesian_name(): void
    {
        $attributes = trans('validation.attributes', [], 'id');
        $missing = [];

        foreach (glob(app_path('Http/Requests/*.php')) as $file) {
            $source = (string) file_get_contents($file);
            if (! preg_match('/function rules\(\)[^{]*\{(.*?)\n    \}/s', $source, $rules)) {
                continue;
            }
            preg_match_all("/'([a-z_][a-z0-9_.*]*)'\s*=>/", $rules[1], $keys);

            $own = [];
            if (preg_match('/function attributes\(\)[^{]*\{(.*?)\n    \}/s', $source, $block)) {
                preg_match_all("/'([a-z_][a-z0-9_.*]*)'\s*=>/", $block[1], $ownKeys);
                $own = $ownKeys[1];
            }

            foreach (array_unique($keys[1]) as $key) {
                if (! isset($attributes[$key]) && ! in_array($key, $own, true)) {
                    $missing[] = basename($file, '.php').": {$key}";
                }
            }
        }

        $this->assertSame([], $missing, 'Kolom tanpa nama Indonesia di lang/id/validation.php');
    }

    public function test_messages_seen_by_users_use_indonesian_field_names(): void
    {
        $this->actingAs(User::factory()->create());
        $room = Room::factory()->create();

        $this->postJson("/api/rooms/{$room->id}/standards", ['standard_quantity' => 0, 'count_method' => CountMethod::Manual->value])
            ->assertUnprocessable()
            ->assertJsonPath('errors.standard_quantity.0', 'Jumlah standar minimal 1.');

        // Ringkasan banyak kesalahan juga berbahasa Indonesia (bukan "(and 1 more error)").
        $this->app['auth']->forgetGuards();
        $this->postJson('/api/login', [])
            ->assertUnprocessable()
            ->assertJsonPath('message', 'Username wajib diisi. (dan 1 kesalahan lain)');
    }
}
