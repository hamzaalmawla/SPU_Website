<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('research_publications', function (Blueprint $table): void {
            $table->unsignedSmallInteger('publication_year')->nullable()->index()->after('published_at');
            $table->string('doi')->nullable()->index()->after('publication_year');
            $table->string('journal_rank', 32)->nullable()->after('doi');
            $table->string('legacy_source_table')->nullable()->after('journal_rank');
            $table->unsignedBigInteger('legacy_source_id')->nullable()->after('legacy_source_table');
            $table->unsignedBigInteger('legacy_owner_id')->nullable()->after('legacy_source_id');
            $table->string('legacy_owner_source')->nullable()->after('legacy_owner_id');
            $table->string('extraction_status')->default('pending_review')->index()->after('legacy_owner_source');
            $table->unique(['legacy_source_table', 'legacy_source_id'], 'research_publications_legacy_unique');
        });

        Schema::table('research_publication_translations', function (Blueprint $table): void {
            $table->text('authors')->nullable()->after('title');
            $table->text('citation')->nullable()->after('publisher');
            $table->json('keywords')->nullable()->after('citation');
        });

        Schema::create('legacy_research_file_references', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('research_publication_id')->constrained('research_publications')->cascadeOnDelete();
            $table->string('legacy_source_table')->default('jx_member_items');
            $table->unsignedBigInteger('legacy_source_id');
            $table->string('legacy_path');
            $table->string('label_ar')->nullable();
            $table->string('label_en')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->string('status')->default('deferred')->index();
            $table->timestamps();

            $table->unique(
                ['research_publication_id', 'legacy_source_table', 'legacy_source_id', 'legacy_path'],
                'legacy_research_file_reference_unique',
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('legacy_research_file_references');

        Schema::table('research_publication_translations', function (Blueprint $table): void {
            $table->dropColumn(['authors', 'citation', 'keywords']);
        });

        Schema::table('research_publications', function (Blueprint $table): void {
            $table->dropUnique('research_publications_legacy_unique');
            $table->dropColumn([
                'publication_year',
                'doi',
                'journal_rank',
                'legacy_source_table',
                'legacy_source_id',
                'legacy_owner_id',
                'legacy_owner_source',
                'extraction_status',
            ]);
        });
    }
};
