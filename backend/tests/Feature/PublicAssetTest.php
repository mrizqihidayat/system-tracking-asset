<?php

namespace Tests\Feature;

use App\Enums\AssetCondition;
use App\Enums\AssetStatus;
use App\Enums\CountMethod;
use App\Models\Asset;
use App\Models\AssetType;
use App\Models\Building;
use App\Models\Category;
use App\Models\Department;
use App\Models\Loan;
use App\Models\Room;
use App\Models\RoomStandard;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class PublicAssetTest extends TestCase
{
    use RefreshDatabase;

    private Asset $asset;

    protected function setUp(): void
    {
        parent::setUp();

        RateLimiter::clear('public-assets');

        $room = Room::factory()
            ->for(Building::factory()->create(['name' => 'Gedung IHS']))
            ->create(['name' => 'Ruang Kelas 1']);
        $type = AssetType::factory()
            ->for(Category::factory()->create(['name' => 'Elektronik']))
            ->create(['name' => 'Proyektor', 'code' => 'PRJ']);

        $this->asset = Asset::factory()->for($type)->for($room)->create([
            'code' => 'SHD-PRJ-001',
            'name' => 'Proyektor Epson EB-X51',
            'source_department_id' => Department::factory()->internal()->create(['name' => 'SHD'])->id,
            'pic_name' => 'Staf SHD',
            'finance_code' => 'FIN-001',
            'acquisition_date' => '2024-01-15',
            'next_service_date' => '2026-12-01',
        ]);
    }

    public function test_public_asset_is_readable_without_token(): void
    {
        $response = $this->getJson("/api/public/assets/{$this->asset->public_token}")->assertOk();

        $response->assertExactJson(['data' => [
            'code' => 'SHD-PRJ-001',
            'name' => 'Proyektor Epson EB-X51',
            'category' => 'Elektronik',
            'asset_type' => 'Proyektor',
            'source' => ['name' => 'SHD', 'is_internal' => true],
            'building' => 'Gedung IHS',
            'room' => 'Ruang Kelas 1',
            'current_location' => ['on_loan' => false, 'building' => 'Gedung IHS', 'room' => 'Ruang Kelas 1', 'note' => null],
            'pic_name' => 'Staf SHD',
            'condition' => 'Baik',
            'status' => 'Tersedia',
            'acquisition_date' => '2024-01-15',
            'next_service_date' => '2026-12-01',
            'finance_code' => 'FIN-001',
            'room_standard' => ['is_standard' => false, 'item_name' => null],
            'upcoming_booking' => null,
            'actions' => ['pinjam', 'pindah_lokasi', 'catat_kerusakan', 'catat_kehilangan', 'catat_tidak_layak_pakai'],
        ]]);

        $this->assertArrayNotHasKey('id', $response->json('data'));
        $this->assertArrayNotHasKey('public_token', $response->json('data'));
    }

    public function test_borrowed_asset_shows_loan_location_without_borrower_data(): void
    {
        $destination = Room::factory()->for(Building::factory()->create(['name' => 'Millennials Hub']))->create(['name' => 'Auditorium']);
        Loan::factory()->for($this->asset)->create(['destination_room_id' => $destination->id, 'borrower_name' => 'Andi Pratama']);
        $this->asset->update(['status' => AssetStatus::Dipinjam]);

        $response = $this->getJson("/api/public/assets/{$this->asset->public_token}")
            ->assertOk()
            ->assertJsonPath('data.current_location', ['on_loan' => true, 'building' => 'Millennials Hub', 'room' => 'Auditorium', 'note' => null])
            ->assertJsonPath('data.room', 'Ruang Kelas 1')
            ->assertJsonPath('data.actions', ['terima_kembali', 'pindah_lokasi', 'catat_kerusakan']);

        $this->assertStringNotContainsString('Andi Pratama', $response->getContent());
    }

    public function test_room_standard_flag_when_type_is_part_of_room_standard(): void
    {
        RoomStandard::factory()->for($this->asset->room)->create([
            'count_method' => CountMethod::Qr,
            'asset_type_id' => $this->asset->asset_type_id,
            'actual_quantity' => null,
        ]);

        $this->getJson("/api/public/assets/{$this->asset->public_token}")
            ->assertOk()
            ->assertJsonPath('data.room_standard', ['is_standard' => true, 'item_name' => 'Proyektor']);
    }

    /**
     * @return array<string, array{0: AssetStatus, 1: list<string>}>
     */
    public static function statusActionProvider(): array
    {
        return [
            'tersedia' => [AssetStatus::Tersedia, ['pinjam', 'pindah_lokasi', 'catat_kerusakan', 'catat_kehilangan', 'catat_tidak_layak_pakai']],
            'dipinjam' => [AssetStatus::Dipinjam, ['terima_kembali', 'pindah_lokasi', 'catat_kerusakan']],
            'perbaikan' => [AssetStatus::Perbaikan, ['pindah_lokasi', 'catat_kerusakan', 'catat_kehilangan', 'catat_tidak_layak_pakai']],
            'tidak layak pakai' => [AssetStatus::TidakLayakPakai, ['pindah_lokasi']],
            'hilang' => [AssetStatus::Hilang, ['tandai_ditemukan', 'pindah_lokasi']],
        ];
    }

    #[DataProvider('statusActionProvider')]
    public function test_actions_follow_status(AssetStatus $status, array $actions): void
    {
        $this->assertSame($actions, $status->allowedActions());
    }

    public function test_deleted_asset_and_unknown_token_return_404(): void
    {
        $token = $this->asset->public_token;
        $this->asset->delete();

        $this->getJson("/api/public/assets/{$token}")
            ->assertNotFound()
            ->assertExactJson(['message' => 'Aset tidak ditemukan.']);

        $this->getJson('/api/public/assets/tidakada12345')->assertNotFound();
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function malformedTokenProvider(): array
    {
        return [
            'karakter NUL' => ['abc%00defghij'],
            'terlalu pendek' => ['abc'],
            'terlalu panjang' => [str_repeat('a', 13)],
            'ada simbol' => ['abcdefghij-k'],
            'kode aset' => ['SHD-PRJ-001'],
            'wildcard' => ['%25'],
        ];
    }

    #[DataProvider('malformedTokenProvider')]
    public function test_malformed_token_returns_404_without_querying_database(string $token): void
    {
        DB::enableQueryLog();

        $this->getJson("/api/public/assets/{$token}")
            ->assertNotFound()
            ->assertExactJson(['message' => 'Aset tidak ditemukan.']);

        $this->assertSame([], DB::getQueryLog());
    }

    public function test_rate_limit_reads_visitor_ip_behind_trusted_proxy(): void
    {
        config(['trustedproxy.proxies' => ['10.0.0.1']]);

        $visit = fn (string $ip) => $this->withServerVariables(['REMOTE_ADDR' => '10.0.0.1'])
            ->withHeader('X-Forwarded-For', $ip)
            ->getJson("/api/public/assets/{$this->asset->public_token}");

        for ($i = 0; $i < 30; $i++) {
            $visit('203.0.113.5')->assertOk();
        }

        $visit('203.0.113.5')->assertTooManyRequests();
        $visit('203.0.113.6')->assertOk();
    }

    public function test_public_endpoint_is_rate_limited(): void
    {
        for ($i = 0; $i < 30; $i++) {
            $this->getJson("/api/public/assets/{$this->asset->public_token}")->assertOk();
        }

        $this->getJson("/api/public/assets/{$this->asset->public_token}")->assertTooManyRequests();
    }

    public function test_public_token_is_random_unique_and_immutable(): void
    {
        $other = Asset::factory()->create(['public_token' => 'dipaksa']);

        $this->assertSame(Asset::PUBLIC_TOKEN_LENGTH, strlen($this->asset->public_token));
        $this->assertMatchesRegularExpression('/^[A-Za-z0-9]{12}$/', $other->public_token);
        $this->assertNotSame($this->asset->public_token, $other->public_token);

        $original = $this->asset->public_token;
        $this->asset->update(['name' => 'Baru', 'condition' => AssetCondition::RusakRingan]);
        $this->asset->forceFill(['public_token' => 'ubahpaksa'])->save();

        $this->assertSame($original, $this->asset->fresh()->public_token);
    }

    public function test_public_url_uses_qr_base_url(): void
    {
        config(['app.qr_base_url' => 'https://aset.example.com']);

        $this->assertSame("https://aset.example.com/aset/{$this->asset->public_token}", $this->asset->publicUrl());
    }
}
