<?php

namespace Tests\Feature;

use App\Console\Commands\PackageSyncAgent;
use Illuminate\Support\Facades\File;
use Tests\TestCase;
use ZipArchive;

/**
 * `php artisan agent:package` (T44): paket zip agen sinkron pegawai untuk tim IT perusahaan.
 */
class PackageSyncAgentTest extends TestCase
{
    private string $output;

    protected function setUp(): void
    {
        parent::setUp();

        $this->output = sys_get_temp_dir().DIRECTORY_SEPARATOR.'agent-package-'.uniqid();
        File::ensureDirectoryExists($this->output);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->output);

        parent::tearDown();
    }

    private function agentPath(string $file = ''): string
    {
        return dirname(base_path()).DIRECTORY_SEPARATOR.'employee-sync-agent'.($file === '' ? '' : DIRECTORY_SEPARATOR.$file);
    }

    public function test_zip_is_versioned_and_contains_only_the_agent_files_in_one_folder(): void
    {
        preg_match("/^const AGENT_VERSION = '(\d+\.\d+)';/m", (string) file_get_contents($this->agentPath('sync.php')), $match);

        $this->artisan('agent:package', ['--output' => $this->output])->assertSuccessful();

        $zipPath = $this->output.DIRECTORY_SEPARATOR."employee-sync-agent-v{$match[1]}.zip";
        $this->assertFileExists($zipPath);

        $zip = new ZipArchive;
        $this->assertTrue($zip->open($zipPath));
        $names = [];
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $names[] = $zip->getNameIndex($i);
        }
        $zip->close();

        // Tanpa .env, log, atau file lain walau ada di folder agen saat pengembangan.
        $this->assertEqualsCanonicalizing(
            array_map(fn (string $file) => "employee-sync-agent/{$file}", PackageSyncAgent::FILES),
            $names,
        );
    }

    public function test_agent_folder_is_self_contained_and_ships_no_secrets(): void
    {
        $script = (string) file_get_contents($this->agentPath('sync.php'));

        // Tidak ada require/include ke luar folder dan tidak butuh composer.
        $this->assertDoesNotMatchRegularExpression('/\b(require|include)(_once)?\b/', $script);
        $this->assertStringNotContainsString('vendor/autoload', $script);

        // .env.example hanya contoh: token dan API key kosong.
        $example = (string) file_get_contents($this->agentPath('.env.example'));
        $this->assertMatchesRegularExpression('/^EMPLOYEE_PUSH_TOKEN=$/m', $example);
        $this->assertMatchesRegularExpression('/^EMPLOYEE_API_KEY=$/m', $example);
    }
}
