<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The university's councils (About › Councils). A row is one council and its
 * address (/about/councils/{slug}); everything shown on its page (title,
 * introduction, members, functions, in both languages) is the CMS content of
 * target entity.council.{id}, saved as drafts and published like every page.
 *
 * Not the `councils` table: that one holds the old site's imported council
 * lists (LegacyCentralCouncilImportService) and nothing public reads it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('university_councils', function (Blueprint $table): void {
            $table->id();
            $table->string('slug', 120)->unique();
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_enabled')->default(true);
            // An older address of this council's page that now redirects here
            // (the research council was /research/office).
            $table->string('former_path', 190)->nullable()->unique();
            $table->timestamps();

            $table->index(['is_enabled', 'sort_order'], 'idx_university_councils_listing');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('university_councils');
    }
};
