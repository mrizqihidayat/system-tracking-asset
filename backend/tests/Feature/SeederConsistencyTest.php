<?php

namespace Tests\Feature;

use App\Enums\AssetStatus;
use App\Enums\CountMethod;
use App\Enums\DamageConfirmation;
use App\Enums\EventType;
use App\Enums\LoanStatus;
use App\Models\Asset;
use App\Models\Consumable;
use App\Models\Department;
use App\Models\Loan;
use App\Models\RoomStandard;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
        Asset::withTrashed()->with('events', 'assetType')->get()->each(function (Asset $asset) {
            $this->assertTrue($asset->events->contains('event_type', EventType::Registrasi), $asset->code);
            $this->assertMatchesRegularExpression('/^SHD-'.$asset->assetType->code.'-\d{3}$/', $asset->code);
        });
    }

    public function test_every_loan_has_matching_events(): void
    {
        Loan::with('events')->get()->each(function (Loan $loan) {
            $this->assertTrue($loan->events->contains('event_type', EventType::Dipinjam));

            $closing = match ($loan->status) {
                LoanStatus::Dikembalikan => EventType::Dikembalikan,
                LoanStatus::Dibatalkan => EventType::Dibatalkan,
                LoanStatus::Dipinjam => null,
            };

            if ($closing !== null) {
                $this->assertTrue($loan->events->contains('event_type', $closing));
            }
        });
    }

    public function test_deleted_asset_has_reason_and_event(): void
    {
        $asset = Asset::onlyTrashed()->with('events')->firstOrFail();

        $this->assertNotNull($asset->deletion_reason);
        $this->assertNotNull($asset->deleted_by);
        $this->assertTrue($asset->events->contains('event_type', EventType::Dihapus));
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
