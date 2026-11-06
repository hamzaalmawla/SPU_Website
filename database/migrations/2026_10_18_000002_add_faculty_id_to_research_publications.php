<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Schema only. A publication's faculty used to come solely from its linked faculty member, which
 * only 5 of the imported legacy publications have, so the research archive could not be filtered
 * by faculty. The old site published research per faculty; this column records that directly.
 * The public site falls back to the faculty member's faculty when it is null.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('research_publications', function (Blueprint $table): void {
            $table->foreignId('faculty_id')->nullable()->after('slug')->constrained('faculties')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('research_publications', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('faculty_id');
        });
    }
};
