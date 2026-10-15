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

    public function test_auto_type_code_skips_code_used_by_other_type(): void
    {
        $oldType = AssetType::factory()->create(['code' => 'PRO']);
        Asset::factory()->for($oldType)->create(['code' => 'SHD-LPT-001']);

        $this->assertSame('LPT2', $this->codes->assetTypeCode('Laptop'));
    }

    public function test_next_asset_code_starts_at_001_and_persists_counter(): void
    {
        $type = AssetType::factory()->create(['code' => 'PRJ']);

        $first = DB::transaction(fn () => $this->codes->nextAssetCode($type));
        $second = DB::transaction(fn () => $this->codes->nextAssetCode($type));

        $this->assertSame('SHD-PRJ-001', $first);
        $this->assertSame('SHD-PRJ-002', $second);
        $this->assertSame(2, $type->fresh()->last_number);
        $this->assertSame(2, $type->last_number);
    }

    public function test_number_is_never_reused_after_asset_is_deleted(): void
    {
        $type = AssetType::factory()->create(['code' => 'PRJ']);

        $code = DB::transaction(fn () => $this->codes->nextAssetCode($type));
        $asset = Asset::factory()->for($type)->create(['code' => $code]);
        $asset->delete();

        $this->assertSame('SHD-PRJ-002', DB::transaction(fn () => $this->codes->nextAssetCode($type)));
    }

    public function test_number_goes_past_999_without_truncation(): void
    {
        $type = AssetType::factory()->create(['code' => 'PRJ']);
        $type->forceFill(['last_number' => 999])->save();

        $this->assertSame('SHD-PRJ-1000', DB::transaction(fn () => $this->codes->nextAssetCode($type)));
    }

    public function test_next_asset_code_is_counted_per_type(): void
    {
        $projector = AssetType::factory()->create(['code' => 'PRJ']);
        $laptop = AssetType::factory()->create(['code' => 'LPT']);
        DB::transaction(fn () => $this->codes->nextAssetCode($projector));

        $this->assertSame('SHD-LPT-001', DB::transaction(fn () => $this->codes->nextAssetCode($laptop)));
    }

    public function test_next_asset_code_uses_current_type_code_and_keeps_numbering(): void
    {
        $type = AssetType::factory()->create(['code' => 'PRJ']);
        DB::transaction(fn () => $this->codes->nextAssetCode($type));
        DB::transaction(fn () => $this->codes->nextAssetCode($type));
        $type->update(['code' => 'PRO']);

        $this->assertSame('SHD-PRO-003', DB::transaction(fn () => $this->codes->nextAssetCode($type)));
    }

    public function test_preview_does_not_consume_number(): void
    {
        $type = AssetType::factory()->create(['code' => 'PRJ']);

        $this->assertSame('SHD-PRJ-001', $this->codes->previewAssetCode($type));
        $this->assertSame('SHD-PRJ-001', $this->codes->previewAssetCode($type));
        $this->assertSame('SHD-PRJ-001', DB::transaction(fn () => $this->codes->nextAssetCode($type)));
        $this->assertSame('SHD-PRJ-002', $this->codes->previewAssetCode($type));
    }
}
