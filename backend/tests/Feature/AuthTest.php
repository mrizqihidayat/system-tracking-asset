<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\AuthService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use PHPUnit\Framework\Attributes\DataProvider;
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
        $this->login('staf.shd', 'salah')->assertUnauthorized()->assertJsonPath('message', 'NIK/Username atau password salah');
        $this->login('tidak.ada')->assertUnauthorized()->assertJsonPath('message', 'NIK/Username atau password salah');
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

    /**
     * @return array<string, array{0: mixed}>
     */
    public static function nonTextUsernameProvider(): array
    {
        return [
            'array' => [['x']],
            'objek' => [['a' => 1]],
            'angka' => [123],
        ];
    }

    #[DataProvider('nonTextUsernameProvider')]
    public function test_non_text_username_returns_422_instead_of_500(mixed $username): void
    {
        $this->postJson('/api/login', ['username' => $username, 'password' => 'password'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('username');
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function invalidUsernameProvider(): array
    {
        return [
            'karakter NUL' => ["staf\0shd"],
            'bukan UTF-8' => ["staf\xE9"],
            'lebih dari 50 karakter' => [str_repeat('a', 51)],
        ];
    }

    #[DataProvider('invalidUsernameProvider')]
    public function test_invalid_username_returns_422_before_reaching_database(string $username): void
    {
        $this->post('/api/login', ['username' => $username, 'password' => 'password'], ['Accept' => 'application/json'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('username');
    }

    public function test_unknown_username_still_checks_a_password_hash(): void
    {
        Hash::spy();

        $this->login('tidak.ada', 'rahasia')->assertUnauthorized();

        Hash::shouldHaveReceived('check')->once()->with('rahasia', AuthService::DUMMY_PASSWORD_HASH);
    }

    public function test_dummy_hash_cost_matches_production_bcrypt_rounds(): void
    {
        preg_match('/^BCRYPT_ROUNDS=(\d+)/m', file_get_contents(base_path('.env.example')), $rounds);

        $this->assertSame((int) $rounds[1], password_get_info(AuthService::DUMMY_PASSWORD_HASH)['options']['cost']);
    }

    public function test_login_is_limited_to_20_attempts_per_minute_per_ip_across_usernames(): void
    {
        foreach (range(1, 20) as $attempt) {
            $this->login("spray{$attempt}", 'salah')->assertUnauthorized();
        }

        $blocked = $this->login('spray21', 'salah')->assertStatus(429);
        $this->assertMatchesRegularExpression('/^Terlalu banyak percobaan login\. Coba lagi dalam \d+ detik\.$/', $blocked->json('message'));

        // Jatah per IP, bukan global.
        $this->withServerVariables(['REMOTE_ADDR' => '10.0.0.9'])
            ->postJson('/api/login', ['username' => 'spray22', 'password' => 'salah'])
            ->assertUnauthorized();
    }

    /** Username berbeda setiap percobaan supaya yang teruji batas per IP, bukan penguncian akun. */
    private function loginThroughProxy(string $proxy, string $forwardedFor)
    {
        static $attempt = 0;

        return $this->withServerVariables(['REMOTE_ADDR' => $proxy])
            ->withHeader('X-Forwarded-For', $forwardedFor)
            ->postJson('/api/login', ['username' => 'tamu'.++$attempt, 'password' => 'salah']);
    }

    public function test_login_limit_reads_visitor_ip_behind_trusted_proxy(): void
    {
        config(['trustedproxy.proxies' => ['10.0.0.1']]);

        foreach (range(1, 20) as $attempt) {
            $this->loginThroughProxy('10.0.0.1', '203.0.113.5')->assertUnauthorized();
        }

        $this->loginThroughProxy('10.0.0.1', '203.0.113.5')->assertStatus(429);
        $this->loginThroughProxy('10.0.0.1', '203.0.113.6')->assertUnauthorized();
    }

    public function test_visitor_cannot_fake_ip_by_prepending_to_forwarded_header(): void
    {
        config(['trustedproxy.proxies' => ['10.0.0.1']]);

        foreach (range(1, 20) as $attempt) {
            $this->loginThroughProxy('10.0.0.1', "198.51.100.{$attempt}, 203.0.113.5")->assertUnauthorized();
        }

        $this->loginThroughProxy('10.0.0.1', '198.51.100.99, 203.0.113.5')->assertStatus(429);
    }

    public function test_forwarded_header_is_ignored_when_sender_is_not_a_trusted_proxy(): void
    {
        config(['trustedproxy.proxies' => ['10.0.0.1']]);

        foreach (range(1, 20) as $attempt) {
            $this->loginThroughProxy('10.0.0.2', "203.0.113.{$attempt}")->assertUnauthorized();
        }

        $this->loginThroughProxy('10.0.0.2', '203.0.113.99')->assertStatus(429);
    }

    protected function tearDown(): void
    {
        RateLimiter::clear('login');

        parent::tearDown();
    }
}
