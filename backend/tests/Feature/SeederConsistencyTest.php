<?php

namespace Tests\Feature;

use App\Enums\AssetCondition;
use App\Enums\AssetStatus;
use App\Enums\CountMethod;
use App\Enums\DamageConfirmation;
use App\Enums\EventType;
use App\Enums\LoanStatus;
use App\Models\Asset;
use App\Models\AssetCodeHistory;
use App\Models\AssetType;
use App\Models\Consumable;
use App\Models\Department;
use App\Models\Loan;
use App\Models\RequestCatalog;
use App\Models\RoomStandard;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;

class SeederConsistencyTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed();
    }

    public function test_seeded_volumes_match_task(): void
    {
        $this->assertSame(1, Department::where('is_internal', true)->count());
        $this->assertSame(3, Department::where('is_internal', false)->count());
        $this->assertEqualsWithDelta(25, Asset::count(), 3);
        $this->assertEqualsWithDelta(10, Consumable::count(), 2);
    }

    public function test_asset_status_matches_active_loans(): void
    {
        $borrowedIds = Asset::where('status', AssetStatus::Dipinjam)->pluck('id')->sort()->values();
        $activeLoanAssetIds = Loan::where('status', LoanStatus::Dipinjam)->pluck('asset_id')->sort()->values();

        $this->assertNotEmpty($borrowedIds);
        $this->assertEquals($borrowedIds, $activeLoanAssetIds);
        $this->assertSame($activeLoanAssetIds->count(), $activeLoanAssetIds->unique()->count());
    }

    public function test_damaged_return_waits_for_confirmation_and_asset_is_in_repair(): void
    {
        $loan = Loan::where('damage_confirmation', DamageConfirmation::MenungguKonfirmasi)->firstOrFail();

        $this->assertSame(AssetStatus::Perbaikan, $loan->asset->status);
    }

    public function test_every_asset_has_registration_event_and_codes_follow_format(): void
    {
        Asset::with('events', 'assetType')->get()->each(function (Asset $asset) {
            $this->assertTrue($asset->events->contains('event_type', EventType::Registrasi), $asset->code);
            $this->assertMatchesRegularExpression('/^SHD-'.$asset->assetType->code.'-\d{3}$/', $asset->code);
        });
    }

    public function test_type_counter_matches_highest_asset_number_and_tokens_exist(): void
    {
        AssetType::with('assets')->get()->each(function (AssetType $type) {
            $codes = $type->assets->pluck('code')
                ->concat(AssetCodeHistory::where('asset_type_id', $type->id)->pluck('code'));
            $highest = $codes->max(fn (string $code) => (int) Str::afterLast($code, '-')) ?? 0;

            $this->assertSame($highest, $type->last_number, $type->code);
        });

        $this->assertSame(0, Asset::whereNull('public_token')->count());
        $this->assertTrue(RequestCatalog::where('request_method', 'WhatsApp')->exists());
    }

    public function test_asset_service_scenarios_are_seeded(): void
    {
        $minorDamage = Asset::where('condition', AssetCondition::RusakRingan)->where('status', AssetStatus::Tersedia)->firstOrFail();
        $this->assertTrue($minorDamage->events()->where('event_type', EventType::Registrasi)->exists());

        $history = AssetCodeHistory::with('asset')->sole();
        $this->assertStringStartsWith('SHD-PRJ-', $history->code);
        $this->assertStringStartsWith('SHD-LPR-', $history->asset->code);
        $this->assertTrue($history->asset->events()->where('event_type', EventType::UbahData)->where('note', 'like', "%{$history->code}%")->exists());
    }

    public function test_lost_during_loan_scenario_is_seeded(): void
    {
        $loan = Loan::where('status', LoanStatus::Hilang)->with('asset')->sole();

        $this->assertSame(AssetStatus::Hilang, $loan->asset->status);
        $this->assertNull($loan->damage_confirmation);
        $this->assertSame($loan->borrower_name, $loan->events()->where('event_type', EventType::LaporHilang)->value('party'));
    }

    public function test_every_loan_has_matching_events(): void
    {
        Loan::with('events')->get()->each(function (Loan $loan) {
            $this->assertTrue($loan->events->contains('event_type', EventType::Dipinjam));

            $closing = match ($loan->status) {
                LoanStatus::Dikembalikan => EventType::Dikembalikan,
                LoanStatus::Dibatalkan => EventType::Dibatalkan,
                LoanStatus::Hilang => EventType::LaporHilang,
                LoanStatus::Dipinjam => null,
            };

            if ($closing !== null) {
                $this->assertTrue($loan->events->contains('event_type', $closing));
            }
        });
    }

    public function test_unfit_asset_scenario_is_seeded(): void
    {
        $asset = Asset::where('status', AssetStatus::TidakLayakPakai)->with('events')->sole();

        $this->assertTrue($asset->events->contains('event_type', EventType::TidakLayakPakai));
        $this->assertFalse(Schema::hasColumn('assets', 'deleted_at'));
    }

    public function test_room_standards_follow_fulfillment_rules(): void
    {
        $shd = Department::where('is_internal', true)->value('id');

        RoomStandard::all()->each(function (RoomStandard $standard) use ($shd) {
            $fulfillments = collect([$standard->consumable_id, $standard->fulfillment_note, $standard->catalog_id])->filter();

            $this->assertLessThanOrEqual(1, $fulfillments->count());

            if ($standard->count_method === CountMethod::Qr) {
                $this->assertNotNull($standard->asset_type_id);
            }

            if ($standard->catalog_id !== null) {
                $this->assertNotSame($shd, $standard->source_department_id);
            }

            if ($standard->consumable_id !== null || $standard->fulfillment_note !== null) {
                $this->assertSame($shd, $standard->source_department_id);
            }
        });
    }
}
