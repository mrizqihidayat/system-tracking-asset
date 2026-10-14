<?php

namespace Tests\Feature;

use App\Models\Asset;
use App\Models\AssetType;
use App\Services\CodeGenerator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use LogicException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class CodeGeneratorTest extends TestCase
{
    use RefreshDatabase;

    private CodeGenerator $codes;

    protected function setUp(): void
    {
        parent::setUp();

        $this->codes = app(CodeGenerator::class);
    }

    /**
     * @return array<string, array{0: string, 1: string}>
     */
    public static function typeNameProvider(): array
    {
        return [
            'satu kata' => ['Laptop', 'LPT'],
            'Y dihitung konsonan' => ['Proyektor', 'PRY'],
            'konsonan berikutnya' => ['Kursi', 'KRS'],
            'dua kata' => ['Meja Peserta', 'MPS'],
            'tiga kata' => ['Papan Tulis Putih', 'PTP'],
            'lebih dari tiga kata' => ['Speaker Aktif Portabel Besar', 'SAP'],
            'kurang konsonan' => ['Tas', 'TAS'],
            'huruf kecil & spasi ganda' => ['  layar   proyektor ', 'LPR'],
        ];
    }

    #[DataProvider('typeNameProvider')]
    public function test_asset_type_code_from_name(string $name, string $expected): void
    {
        $this->assertSame($expected, $this->codes->assetTypeCode($name));
    }

    public function test_asset_type_code_adds_number_on_collision(): void
    {
        AssetType::factory()->create(['code' => 'LPT']);
        AssetType::factory()->create(['code' => 'LPT2']);

        $this->assertSame('LPT3', $this->codes->assetTypeCode('Laptop'));
    }

    public function test_asset_type_code_ignores_own_record_when_editing(): void
    {
        $type = AssetType::factory()->create(['name' => 'Laptop', 'code' => 'LPT']);

        $this->assertSame('LPT', $this->codes->assetTypeCode('Laptop', $type->id));
    }

    public function test_asset_type_code_requires_letters(): void
    {
        $this->expectException(LogicException::class);

        $this->codes->assetTypeCode('123');
    }

    public function test_next_asset_code_starts_at_001(): void
    {
        $type = AssetType::factory()->create(['code' => 'PRJ']);

        $code = DB::transaction(fn () => $this->codes->nextAssetCode($type));

        $this->assertSame('SHD-PRJ-001', $code);
    }

    public function test_next_asset_code_continues_after_highest_number_including_deleted(): void
    {
        $type = AssetType::factory()->create(['code' => 'PRJ']);
        Asset::factory()->for($type)->create(['code' => 'SHD-PRJ-001']);
        Asset::factory()->for($type)->create(['code' => 'SHD-PRJ-007'])->delete();

        $code = DB::transaction(fn () => $this->codes->nextAssetCode($type));

        $this->assertSame('SHD-PRJ-008', $code);
    }

    public function test_next_asset_code_is_counted_per_type(): void
    {
        $projector = AssetType::factory()->create(['code' => 'PRJ']);
        $laptop = AssetType::factory()->create(['code' => 'LPT']);
        Asset::factory()->for($projector)->create(['code' => 'SHD-PRJ-003']);

        $code = DB::transaction(fn () => $this->codes->nextAssetCode($laptop));

        $this->assertSame('SHD-LPT-001', $code);
    }

    public function test_next_asset_code_uses_current_type_code(): void
    {
        $type = AssetType::factory()->create(['code' => 'PRJ']);
        Asset::factory()->for($type)->create(['code' => 'SHD-PRJ-002']);
        $type->update(['code' => 'PRO']);

        $code = DB::transaction(fn () => $this->codes->nextAssetCode($type));

        $this->assertSame('SHD-PRO-003', $code);
    }
}
