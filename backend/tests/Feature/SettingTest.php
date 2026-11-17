<?php

namespace Tests\Feature;

use App\Models\Asset;
use App\Models\Department;
use App\Models\Setting;
use App\Models\SettingChange;
use App\Models\User;
use App\Services\AssetLabelService;
use App\Services\SettingService;
use Database\Seeders\SettingSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use OpenSpout\Reader\XLSX\Reader;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Pengaturan aplikasi (T40 tahap 1, SPEC §15): hak akses, validasi, riwayat perubahan, cache, logo/favicon,
 * kop laporan Excel, dan teks label QR.
 */
class SettingTest extends TestCase
{
    use RefreshDatabase;

    private User $employee;

    private User $shared;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
        config(['filesystems.asset_disk' => 'local']);

        $this->employee = User::factory()->employee('90100003')->create(['name' => 'Staf Pegawai SHD']);
        $this->shared = User::factory()->create(['username' => 'bersama.hrm']);
    }

    public function test_public_settings_need_no_login_and_expose_only_display_values(): void
    {
        $this->getJson('/api/settings/public')->assertOk()
            ->assertExactJson(['data' => [
                'theme_primary' => '#017cc2',
                'theme_sidebar' => '#0e4f7e',
                'site_title' => 'Aset SHD',
                'site_description' => 'Masuk untuk melanjutkan ke Sistem Informasi Aset',
                'footer_text' => 'Departemen SHD · PT INALUM',
                'password_min_length' => 8,
                'logo_url' => null,
                'favicon_url' => null,
            ]]);
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function protectedEndpoints(): array
    {
        return [
            'baca' => ['GET', '/api/settings'],
            'simpan' => ['PUT', '/api/settings'],
            'unggah logo' => ['POST', '/api/settings/files/logo'],
            'hapus favicon' => ['DELETE', '/api/settings/files/favicon'],
        ];
    }

    #[DataProvider('protectedEndpoints')]
    public function test_settings_require_login(string $method, string $uri): void
    {
        $this->json($method, $uri)->assertUnauthorized();
    }

    #[DataProvider('protectedEndpoints')]
    public function test_shared_account_cannot_open_or_change_settings(string $method, string $uri): void
    {
        $this->actingAs($this->shared)->json($method, $uri, ['site_title' => 'Diubah'])
            ->assertForbidden()
            ->assertJsonPath('code', 'employee_account_required');

        $this->assertSame(0, Setting::count());
    }

    public function test_employee_account_reads_settings_with_defaults(): void
    {
        $this->actingAs($this->employee)->getJson('/api/settings')->assertOk()
            ->assertJsonPath('data.max_login_attempts', 5)
            ->assertJsonPath('data.lockout_minutes', 15)
            ->assertJsonPath('data.otp_ttl_minutes', 10)
            ->assertJsonPath('data.label_text', '')
            ->assertJsonPath('defaults.theme_primary', '#017cc2')
            ->assertJsonPath('last_change', null)
            ->assertJsonMissingPath('data.logo_path');
    }

    public function test_update_saves_only_changed_values_and_records_who_what_when(): void
    {
        $this->actingAs($this->employee)->putJson('/api/settings', [
            'theme_primary' => ' #0A7F5C ',
            'site_title' => 'Aset SHD',
            'lockout_minutes' => '30',
        ])->assertOk()
            ->assertJsonPath('data.theme_primary', '#0a7f5c')
            ->assertJsonPath('data.lockout_minutes', 30)
            ->assertJsonPath('data.theme_sidebar', '#0e4f7e')
            ->assertJsonPath('last_change.by', 'Staf Pegawai SHD');

        // Judul sama dengan bawaan: tidak dicatat sebagai perubahan.
        $this->assertSame(['lockout_minutes', 'theme_primary'], SettingChange::orderBy('key')->pluck('key')->all());

        $change = SettingChange::where('key', 'lockout_minutes')->sole();
        $this->assertSame(15, $change->old_value);
        $this->assertSame(30, $change->new_value);
        $this->assertSame($this->employee->id, $change->user_id);
        $this->assertNotNull($change->created_at);
        $this->assertSame($this->employee->id, Setting::where('key', 'theme_primary')->sole()->updated_by);

        $this->getJson('/api/settings/public')->assertJsonPath('data.theme_primary', '#0a7f5c');
    }

    public function test_cache_is_cleared_on_every_save(): void
    {
        $settings = app(SettingService::class);
        $this->assertSame('Aset SHD', $settings->get('site_title'));
        $this->assertTrue(Cache::has('app:settings'));

        $this->actingAs($this->employee)->putJson('/api/settings', ['site_title' => 'Aset Pelatihan'])->assertOk();

        $this->assertSame('Aset Pelatihan', $settings->get('site_title'));
    }

    /**
     * @return array<string, array{string, mixed}>
     */
    public static function invalidValues(): array
    {
        return [
            'warna bukan kode hex' => ['theme_primary', 'biru'],
            'warna hex pendek' => ['theme_sidebar', '#fff'],
            'judul kosong' => ['site_title', '  '],
            'judul terlalu panjang' => ['site_title', str_repeat('a', 61)],
            'deskripsi terlalu panjang' => ['site_description', str_repeat('a', 201)],
            'teks label lebih dari 40' => ['label_text', str_repeat('a', 41)],
            'salah password terlalu sedikit' => ['max_login_attempts', 2],
            'salah password terlalu banyak' => ['max_login_attempts', 11],
            'lama terkunci nol' => ['lockout_minutes', 0],
            'password minimal di bawah 8' => ['password_min_length', 7],
            'masa OTP terlalu lama' => ['otp_ttl_minutes', 61],
            'angka berupa teks' => ['otp_ttl_minutes', 'lama'],
        ];
    }

    #[DataProvider('invalidValues')]
    public function test_invalid_values_are_rejected(string $key, mixed $value): void
    {
        $this->actingAs($this->employee)->putJson('/api/settings', [$key => $value])
            ->assertUnprocessable()
            ->assertJsonValidationErrors($key);

        $this->assertSame(0, Setting::count());
    }

    public function test_unknown_keys_and_file_paths_cannot_be_set_through_update(): void
    {
        $this->actingAs($this->employee)->putJson('/api/settings', ['logo_path' => '../.env', 'role' => 'admin'])->assertOk();

        $this->assertSame(0, Setting::count());
    }

    public function test_optional_texts_can_be_emptied(): void
    {
        $this->actingAs($this->employee)->putJson('/api/settings', ['footer_text' => null, 'label_text' => ''])->assertOk()
            ->assertJsonPath('data.footer_text', '');
    }

    public function test_logo_upload_is_stored_on_asset_disk_served_publicly_and_replaced(): void
    {
        $this->actingAs($this->employee);

        $url = $this->post('/api/settings/files/logo', ['file' => UploadedFile::fake()->image('logo.png', 200, 60)], ['Accept' => 'application/json'])
            ->assertOk()->json('data.logo_url');

        $first = app(SettingService::class)->get('logo_path');
        Storage::disk('local')->assertExists($first);
        $this->assertStringStartsWith('settings/logo-', $first);
        $this->assertStringContainsString('/api/settings/files/logo?v=', $url);

        $this->app['auth']->forgetGuards();
        $this->get('/api/settings/files/logo')->assertOk()
            ->assertHeader('X-Content-Type-Options', 'nosniff');
        $this->getJson('/api/settings/public')->assertJsonPath('data.logo_url', $url);

        $this->actingAs($this->employee)
            ->post('/api/settings/files/logo', ['file' => UploadedFile::fake()->image('baru.jpg')], ['Accept' => 'application/json'])
            ->assertOk();

        Storage::disk('local')->assertMissing($first);
        $this->assertNotSame($first, app(SettingService::class)->get('logo_path'));
        $this->assertSame(2, SettingChange::where('key', 'logo_path')->count());
    }

    public function test_deleting_favicon_returns_to_default(): void
    {
        $this->actingAs($this->employee);
        $this->post('/api/settings/files/favicon', ['file' => UploadedFile::fake()->image('ikon.png', 64, 64)], ['Accept' => 'application/json'])->assertOk();
        $path = app(SettingService::class)->get('favicon_path');

        $this->deleteJson('/api/settings/files/favicon')->assertOk()->assertJsonPath('data.favicon_url', null);

        Storage::disk('local')->assertMissing($path);
        $this->get('/api/settings/files/favicon', ['Accept' => 'application/json'])->assertNotFound();
    }

    public function test_upload_rejects_non_images_svg_and_large_files(): void
    {
        $this->actingAs($this->employee);

        foreach ([
            UploadedFile::fake()->create('logo.pdf', 10, 'application/pdf'),
            UploadedFile::fake()->createWithContent('logo.svg', '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>'),
            UploadedFile::fake()->image('besar.png')->size(1025),
        ] as $file) {
            $this->post('/api/settings/files/logo', ['file' => $file], ['Accept' => 'application/json'])
                ->assertUnprocessable()->assertJsonValidationErrors('file');
        }

        $this->postJson('/api/settings/files/banner')->assertNotFound();
        $this->assertNull(app(SettingService::class)->get('logo_path'));
    }

    public function test_seeder_fills_defaults_without_overwriting_saved_values(): void
    {
        Setting::create(['key' => 'site_title', 'value' => 'Aset Pelatihan']);

        $this->seed(SettingSeeder::class);

        $this->assertSame(count(SettingService::DEFAULTS), Setting::count());
        $this->assertSame('Aset Pelatihan', Setting::where('key', 'site_title')->sole()->value);
        $this->assertSame(5, Setting::where('key', 'max_login_attempts')->sole()->value);
    }

    public function test_report_letterhead_is_first_row_only_when_filled(): void
    {
        $this->actingAs($this->employee);
        Department::factory()->internal()->create(['name' => 'SHD']);

        $this->assertStringStartsWith('Laporan', (string) $this->firstReportCell());

        $this->putJson('/api/settings', ['report_header' => 'PT INALUM - Departemen SHD'])->assertOk();

        $this->assertSame('PT INALUM - Departemen SHD', $this->firstReportCell());
    }

    private function firstReportCell(): mixed
    {
        $path = tempnam(sys_get_temp_dir(), 'xlsx');
        file_put_contents($path, $this->get('/api/reports/assets')->assertOk()->streamedContent());

        $reader = new Reader;
        $reader->open($path);
        $cell = null;

        foreach ($reader->getSheetIterator() as $sheet) {
            foreach ($sheet->getRowIterator() as $row) {
                $cell = $row->toArray()[0];
                break 2;
            }
        }

        $reader->close();
        @unlink($path);

        return $cell;
    }

    public function test_label_text_is_printed_under_the_code_only_when_filled(): void
    {
        $this->actingAs($this->employee);
        $asset = Asset::factory()->create();
        $render = fn (): string => view('pdf.asset-labels', app(AssetLabelService::class)->viewData(collect([$asset])))->render();

        $this->assertStringNotContainsString('class="text"', $render());

        $this->putJson('/api/settings', ['label_text' => 'Milik Departemen SHD <INALUM>'])->assertOk();

        $this->assertStringContainsString('<div class="text">Milik Departemen SHD &lt;INALUM&gt;</div>', $render());
    }
}
