<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create(['username' => 'staf.shd', 'name' => 'Staf SHD']);
    }

    private function login(string $username = 'staf.shd', string $password = 'password')
    {
        return $this->postJson('/api/login', ['username' => $username, 'password' => $password]);
    }

    public function test_login_returns_token_and_user_without_password(): void
    {
        $response = $this->login()->assertOk()
            ->assertJsonStructure(['token', 'user' => ['id', 'name', 'username']])
            ->assertJsonPath('user.username', 'staf.shd')
            ->assertJsonMissingPath('user.password');

        $this->assertNotEmpty($response->json('token'));
        $this->assertDatabaseCount('personal_access_tokens', 1);
    }

    public function test_wrong_credentials_return_401_without_leaking_which_field(): void
    {
        $this->login('staf.shd', 'salah')->assertUnauthorized()->assertJsonPath('message', 'Username atau password salah');
        $this->login('tidak.ada')->assertUnauthorized()->assertJsonPath('message', 'Username atau password salah');
        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_login_validation_is_in_indonesian(): void
    {
        $this->postJson('/api/login', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['username', 'password']);
    }

    public function test_token_authenticates_requests_and_user_endpoint(): void
    {
        $token = $this->login()->json('token');

        $this->withToken($token)->getJson('/api/user')
            ->assertOk()
            ->assertJsonPath('username', 'staf.shd')
            ->assertJsonMissingPath('password');
    }

    public function test_logout_invalidates_only_the_current_token(): void
    {
        $first = $this->login()->json('token');
        $second = $this->login()->json('token');
        $this->assertDatabaseCount('personal_access_tokens', 2);

        $this->withToken($first)->postJson('/api/logout')->assertOk()->assertJsonPath('message', 'Logout berhasil');
        $this->assertDatabaseCount('personal_access_tokens', 1);

        $this->app['auth']->forgetGuards();
        $this->withToken($first)->getJson('/api/user')->assertUnauthorized();

        $this->app['auth']->forgetGuards();
        $this->withToken($second)->getJson('/api/user')->assertOk();
    }

    public function test_invalid_token_returns_401_json(): void
    {
        $this->withToken('1|bukan-token-valid')->getJson('/api/user')->assertUnauthorized();
        $this->getJson('/api/user')->assertUnauthorized();
    }

    public function test_login_is_limited_to_5_attempts_per_minute_per_username_and_ip(): void
    {
        foreach (range(1, 5) as $attempt) {
            $this->login('staf.shd', 'salah')->assertUnauthorized();
        }

        $blocked = $this->login('staf.shd', 'salah')->assertStatus(429);

        $this->assertMatchesRegularExpression('/^Terlalu banyak percobaan login\. Coba lagi dalam \d+ detik\.$/', $blocked->json('message'));
        $this->assertNotNull($blocked->headers->get('Retry-After'));
        $this->assertStringContainsString($blocked->headers->get('Retry-After'), $blocked->json('message'));

        // Password benar pun ditolak selama diblokir; username berbeda tidak terpengaruh.
        $this->login('staf.shd', 'password')->assertStatus(429);
        User::factory()->create(['username' => 'lain']);
        $this->login('lain')->assertOk();
    }

    public function test_login_limit_ignores_username_case_and_is_per_ip(): void
    {
        foreach (['staf.shd', 'STAF.SHD', ' Staf.Shd ', 'staf.shd', 'STAF.shd'] as $username) {
            $this->login($username, 'salah')->assertUnauthorized();
        }

        $this->login('Staf.SHD', 'salah')->assertStatus(429);

        // IP yang sama tetap diblokir; IP lain dengan username yang sama tidak terpengaruh.
        $this->login('staf.shd', 'password')->assertStatus(429);
        $this->withServerVariables(['REMOTE_ADDR' => '10.0.0.9'])
            ->postJson('/api/login', ['username' => 'staf.shd', 'password' => 'password'])
            ->assertOk();
    }

    public function test_login_limit_resets_after_a_minute(): void
    {
        foreach (range(1, 5) as $attempt) {
            $this->login('staf.shd', 'salah')->assertUnauthorized();
        }
        $this->login('staf.shd', 'password')->assertStatus(429);

        $this->travel(61)->seconds();

        $this->login('staf.shd', 'password')->assertOk();
    }

    protected function tearDown(): void
    {
        RateLimiter::clear('login');

        parent::tearDown();
    }
}
