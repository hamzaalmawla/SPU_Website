<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Flat, rebuildable index behind public site search.
 *
 * One row per (source record, locale). Everything the results page needs is
 * pre-rendered here — the public URL, a plain-text summary, the display title —
 * so a search is a single scan of this table with no model hydration and no
 * N+1 across the five content domains.
 *
 * `title_normalized` and `body_normalized` hold text already folded by
 * App\Support\SearchTextNormalizer, so matching never depends on the database
 * collation. That keeps sqlite (tests) and MariaDB 10.11 (production) in
 * agreement, and it is why no MySQL-8-only feature (FULLTEXT, JSON_TABLE,
 * functional indexes) appears anywhere in this feature.
 *
 * The table is derived data: it is always safe to truncate and rebuild with
 * `php artisan search:index --fresh`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('search_documents', function (Blueprint $table): void {
            $table->id();

            // Source record identity. Kept as a plain string + id rather than a
            // polymorphic relation: nothing ever eager-loads back to the model.
            $table->string('searchable_type', 191);
            $table->unsignedBigInteger('searchable_id');

            // Filter bucket shown to visitors: news | research | people | pages.
            $table->string('type', 32);
            $table->string('locale', 5);

            $table->string('title', 512);
            $table->string('title_normalized', 512);
            $table->text('summary')->nullable();
            $table->mediumText('body_normalized');

            $table->string('url', 512);
            $table->string('meta', 255)->nullable();
            $table->dateTime('published_at')->nullable();

            // Static per-type importance, used only to break scoring ties.
            $table->smallInteger('weight')->default(0);

            $table->timestamps();

            $table->unique(
                ['searchable_type', 'searchable_id', 'locale'],
                'uniq_search_documents_source'
            );
            // The only index a search can actually use. Matching is
            // LIKE '%term%', which no b-tree can serve, so the query narrows on
            // locale first and scans from there; indexing the text columns would
            // cost writes and buy nothing.
            $table->index(['locale', 'type'], 'idx_search_documents_locale_type');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('search_documents');
    }
};
