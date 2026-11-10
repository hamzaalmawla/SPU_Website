<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * An automatic lock (too many failed sign-ins) now expires, so one attacker
 * cannot keep every staff account out by guessing wrong five times each.
 * A lock with no locked_until (set by an administrator, or any lock that
 * predates this column) stays in place until someone lifts it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->timestamp('locked_until')->nullable()->after('locked_at');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn('locked_until');
        });
    }
};
