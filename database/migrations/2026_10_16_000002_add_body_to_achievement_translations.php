<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Each achievement now has its own page, like a news article. The body is the
 * full text shown there; existing achievements keep it empty and show their
 * summary until an editor writes one.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('achievement_translations', function (Blueprint $table): void {
            $table->longText('body')->nullable()->after('summary');
        });
    }

    public function down(): void
    {
        Schema::table('achievement_translations', function (Blueprint $table): void {
            $table->dropColumn('body');
        });
    }
};
