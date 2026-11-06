<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Schema only. A researcher entered twice is merged into one record; the
 * other is soft-deleted and points here, so a later import of the research
 * office's register attaches papers to the kept record instead.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('researchers', function (Blueprint $table): void {
            $table->foreignId('merged_into_id')->nullable()->after('legacy_source_id')->constrained('researchers')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('researchers', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('merged_into_id');
        });
    }
};
