<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

final class ResetUnconfirmedTwoFactorStatusMigrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_resets_unconfirmed_two_factor_users_to_disabled(): void
    {
        $unconfirmedUser = User::factory()->create([
            'two_factor_enabled' => true,
            'two_factor_confirmed_at' => null,
            'totp_secret_encrypted' => 'secret-test',
        ]);

        $confirmedUser = User::factory()->create([
            'two_factor_enabled' => true,
            'two_factor_confirmed_at' => now(),
            'totp_secret_encrypted' => 'secret-confirmed',
        ]);

        $migration = require database_path('migrations/2026_09_05_000001_reset_unconfirmed_two_factor_status.php');
        $migration->up();

        $unconfirmedUser->refresh();
        $confirmedUser->refresh();

        $this->assertFalse((bool) $unconfirmedUser->two_factor_enabled);
        $this->assertNull($unconfirmedUser->two_factor_confirmed_at);
        $this->assertNull($unconfirmedUser->totp_secret_encrypted);

        $this->assertTrue((bool) $confirmedUser->two_factor_enabled);
        $this->assertNotNull($confirmedUser->two_factor_confirmed_at);
        $this->assertSame('secret-confirmed', $confirmedUser->totp_secret_encrypted);
    }
}
