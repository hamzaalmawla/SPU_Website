<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->text('totp_secret_encrypted')->nullable()->after('password');
            $table->text('recovery_codes_encrypted')->nullable()->after('totp_secret_encrypted');
            $table->boolean('two_factor_enabled')->default(false)->after('recovery_codes_encrypted');
            $table->timestamp('two_factor_confirmed_at')->nullable()->after('two_factor_enabled');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn([
                'totp_secret_encrypted',
                'recovery_codes_encrypted',
                'two_factor_enabled',
                'two_factor_confirmed_at',
            ]);
        });
    }
};
