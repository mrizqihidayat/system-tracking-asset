<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Migration T41: akun yang sudah ada diberi penanda "password bawaan" sekali, lewat Hash::check(nik, password).
 */
class AccountTrackingMigrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_backfill_marks_default_password_and_shared_accounts(): void
    {
        $migration = require database_path('migrations/2026_11_18_000001_add_login_tracking_and_account_events.php');

        $default = User::factory()->employee('90100001')->create(['password' => '90100001']);
        $changed = User::factory()->employee('90100002')->create(['password' => 'Sudah-Diganti-1']);
        $shared = User::factory()->create(['username' => 'bersama.hrm']);

        // Keadaan sebelum migration: kolom belum ada.
        $migration->down();
        $migration->up();

        $this->assertNull($default->fresh()->password_changed_at);
        $this->assertNotNull($changed->fresh()->password_changed_at);
        $this->assertEquals($shared->created_at, $shared->fresh()->password_changed_at);
        $this->assertNull($default->fresh()->last_login_at);
    }
}
