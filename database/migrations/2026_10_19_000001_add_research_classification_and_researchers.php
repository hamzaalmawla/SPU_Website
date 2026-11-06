<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Schema only. The university's research office keeps its register in its own
 * system ("gamma"), where a paper has a register number, a publication type, a
 * study type and an ISSN, and lists its researchers with their roles. These
 * columns and tables give the website the same fields, so new papers are
 * entered here the way the office records them.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('research_publications', function (Blueprint $table): void {
            $table->string('research_number', 30)->nullable()->unique()->after('slug');
            $table->string('publication_type', 40)->nullable()->index()->after('category_key');
            $table->string('study_type', 40)->nullable()->after('publication_type');
            $table->string('issn', 30)->nullable()->after('doi');
            $table->text('notes')->nullable()->after('external_url');
        });

        // Paper titles run past 255 characters; the office's register allows 500.
        Schema::table('research_publication_translations', function (Blueprint $table): void {
            $table->string('title', 500)->change();
        });

        Schema::create('researchers', function (Blueprint $table): void {
            $table->id();
            $table->string('full_name', 200)->index();
            $table->string('email', 150)->nullable();
            $table->string('phone', 30)->nullable();
            $table->foreignId('faculty_id')->nullable()->constrained('faculties')->nullOnDelete();
            $table->foreignId('faculty_member_id')->nullable()->constrained('faculty_members')->nullOnDelete();
            $table->string('affiliation')->nullable();
            $table->string('registration_status', 30)->default('registered');
            $table->text('notes')->nullable();
            $table->string('legacy_source', 40)->nullable();
            $table->unsignedBigInteger('legacy_source_id')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['legacy_source', 'legacy_source_id'], 'researchers_legacy_unique');
        });

        Schema::create('research_publication_researcher', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('research_publication_id')->constrained('research_publications', indexName: 'rpr_publication_fk')->cascadeOnDelete();
            $table->foreignId('researcher_id')->constrained('researchers', indexName: 'rpr_researcher_fk')->restrictOnDelete();
            $table->string('role', 40)->default('co_author');
            $table->unsignedInteger('sort_order')->default(1);
            $table->string('affiliation_snapshot')->nullable();
            $table->timestamps();

            $table->unique(['research_publication_id', 'researcher_id'], 'rpr_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('research_publication_researcher');
        Schema::dropIfExists('researchers');

        Schema::table('research_publications', function (Blueprint $table): void {
            $table->dropUnique(['research_number']);
            $table->dropIndex(['publication_type']);
            $table->dropColumn(['research_number', 'publication_type', 'study_type', 'issn', 'notes']);
        });
    }
};
