<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Unconfirmed two-factor accounts lack verification timestamps,
        // which trapped administrative users in a redirect loop on login.
        // Resetting them to false allows users to cleanly complete TOTP enrollment.
        DB::table('users')
            ->where('two_factor_enabled', true)
            ->whereNull('two_factor_confirmed_at')
            ->update([
                'two_factor_enabled' => false,
                'totp_secret_encrypted' => null,
                'recovery_codes_encrypted' => null,
            ]);
    }

    public function down(): void
    {
        // One-time data correction migration; irreversible.
    }
};
