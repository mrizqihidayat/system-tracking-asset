<?php

namespace Tests\Feature;

use App\Models\Asset;
use App\Models\Loan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

class SecurityLogTest extends TestCase
{
    use RefreshDatabase;

    private const PASSWORD = 'Rahasia-Uji-123';

    private string $dir;

    protected function setUp(): void
    {
        parent::setUp();

        $this->dir = sys_get_temp_dir().'/security-log-test-'.uniqid();
        File::ensureDirectoryExists($this->dir);

        // Konfigurasi channel yang sebenarnya, hanya folder tujuannya yang dipindah.
        $channel = (require config_path('logging.php'))['channels']['security'];
        config(['logging.channels.security' => ['path' => "{$this->dir}/security.log"] + $channel]);
    }

    protected function tearDown(): void
    {
        Log::forgetChannel('security');
        File::deleteDirectory($this->dir);

        parent::tearDown();
    }

    private function logged(): string
    {
        return implode('', array_map(file_get_contents(...), glob("{$this->dir}/*.log") ?: []));
    }

    private function staf(): User
    {
        return User::factory()->create(['username' => 'staf.shd', 'password' => self::PASSWORD]);
    }

    public function test_successful_login_records_who_and_ip_without_password(): void
    {
        $user = $this->staf();

        $this->postJson('/api/login', ['username' => 'staf.shd', 'password' => self::PASSWORD])->assertOk();

        $log = $this->logged();
        $this->assertMatchesRegularExpression('/^\[\d{4}-\d{2}-\d{2}[^\]]*\] \w+\.INFO: login\.success /m', $log);
        $this->assertStringContainsString('"username":"staf.shd"', $log);
        $this->assertStringContainsString("\"user_id\":{$user->id}", $log);
        $this->assertStringContainsString('"ip":"127.0.0.1"', $log);
        $this->assertStringNotContainsString(self::PASSWORD, $log);
    }

    public function test_failed_login_records_attempted_username_without_password(): void
    {
        $this->staf();

        $this->postJson('/api/login', ['username' => 'staf.shd', 'password' => 'Salah-Uji-456'])->assertUnauthorized();
        $this->postJson('/api/login', ['username' => 'tidak.ada', 'password' => 'Salah-Uji-789'])->assertUnauthorized();

        $log = $this->logged();
        $this->assertSame(2, substr_count($log, 'login.failed'));
        $this->assertStringContainsString('"username":"staf.shd"', $log);
        $this->assertStringContainsString('"username":"tidak.ada"', $log);
        $this->assertStringNotContainsString('Salah-Uji', $log);
        $this->assertStringNotContainsString('login.success', $log);
    }

    public function test_deleting_asset_records_code_and_who_deleted_it(): void
    {
        $user = $this->staf();
        $asset = Asset::factory()->create(['code' => 'SHD-PRJ-077']);

        $this->actingAs($user)->deleteJson("/api/assets/{$asset->id}")->assertOk();

        $log = $this->logged();
        $this->assertMatchesRegularExpression('/^\[\d{4}-\d{2}-\d{2}[^\]]*\] \w+\.INFO: asset\.deleted /m', $log);
        $this->assertStringContainsString('"asset_code":"SHD-PRJ-077"', $log);
        $this->assertStringContainsString('"username":"staf.shd"', $log);
        $this->assertStringContainsString("\"user_id\":{$user->id}", $log);
    }

    public function test_rejected_delete_is_not_recorded(): void
    {
        $asset = Asset::factory()->create();
        Loan::factory()->for($asset)->create();

        $this->actingAs($this->staf())->deleteJson("/api/assets/{$asset->id}", ['confirm_code' => 'SALAH'])->assertUnprocessable();

        $this->assertModelExists($asset);
        $this->assertStringNotContainsString('asset.deleted', $this->logged());
    }

    public function test_channel_level_is_fixed_so_log_level_cannot_silence_it(): void
    {
        $this->assertSame('info', config('logging.channels.security.level'));
    }
}
