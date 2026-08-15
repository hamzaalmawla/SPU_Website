<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Add search-performance indexes on translation tables and media assets.
 *
 * Composite (locale + text) indexes allow the query planner to narrow scans
 * to a single locale before evaluating LIKE predicates.
 */
return new class extends Migration
{
    public function up(): void
    {
        // ── Translation tables ──
        Schema::table('page_translations', function (Blueprint $table): void {
            $table->index(['locale', 'title'], 'idx_page_translations_locale_title');
        });

        Schema::table('news_article_translations', function (Blueprint $table): void {
            $table->index(['locale', 'title'], 'idx_news_article_translations_locale_title');
            $table->index(['locale', DB::raw('excerpt(255)')], 'idx_news_article_translations_locale_excerpt');
        });

        Schema::table('news_category_translations', function (Blueprint $table): void {
            $table->index(['locale', 'name'], 'idx_news_category_translations_locale_name');
        });

        Schema::table('about_page_translations', function (Blueprint $table): void {
            $table->index(['locale', 'title'], 'idx_about_page_translations_locale_title');
        });

        Schema::table('person_translations', function (Blueprint $table): void {
            $table->index(['locale', 'name'], 'idx_person_translations_locale_name');
        });

        Schema::table('directorate_translations', function (Blueprint $table): void {
            $table->index(['locale', 'title'], 'idx_directorate_translations_locale_title');
        });

        Schema::table('partnership_translations', function (Blueprint $table): void {
            $table->index(['locale', 'name'], 'idx_partnership_translations_locale_name');
        });

        Schema::table('faculty_member_translations', function (Blueprint $table): void {
            $table->index(['locale', 'full_name'], 'idx_faculty_member_translations_locale_full_name');
        });

        // ── Media assets ──
        Schema::table('media_assets', function (Blueprint $table): void {
            $table->index(['title_ar'], 'idx_media_title_ar');
            $table->index(['title_en'], 'idx_media_title_en');
            $table->index(['alt_text_ar'], 'idx_media_alt_text_ar');
            $table->index(['alt_text_en'], 'idx_media_alt_text_en');
        });

        // ── Dynamic form submissions ──
        // Functional indexes on JSON paths for context-title search (MySQL 8.0.13+)
        if ($this->supportsFunctionalIndexes()) {
            Schema::table('dynamic_form_submissions', function (Blueprint $table): void {
                $table->index([
                    DB::raw("(CAST(JSON_UNQUOTE(JSON_EXTRACT(payload_json, '$._context.event_title')) AS CHAR(255)))"),
                ], 'idx_dfs_event_title');
                $table->index([
                    DB::raw("(CAST(JSON_UNQUOTE(JSON_EXTRACT(payload_json, '$._context.job_title')) AS CHAR(255)))"),
                ], 'idx_dfs_job_title');
            });
        }
    }

    public function down(): void
    {
        Schema::table('page_translations', function (Blueprint $table): void {
            $table->dropIndex('idx_page_translations_locale_title');
        });

        Schema::table('news_article_translations', function (Blueprint $table): void {
            $table->dropIndex('idx_news_article_translations_locale_title');
            $table->dropIndex('idx_news_article_translations_locale_excerpt');
        });

        Schema::table('news_category_translations', function (Blueprint $table): void {
            $table->dropIndex('idx_news_category_translations_locale_name');
        });

        Schema::table('about_page_translations', function (Blueprint $table): void {
            $table->dropIndex('idx_about_page_translations_locale_title');
        });

        Schema::table('person_translations', function (Blueprint $table): void {
            $table->dropIndex('idx_person_translations_locale_name');
        });

        Schema::table('directorate_translations', function (Blueprint $table): void {
            $table->dropIndex('idx_directorate_translations_locale_title');
        });

        Schema::table('partnership_translations', function (Blueprint $table): void {
            $table->dropIndex('idx_partnership_translations_locale_name');
        });

        Schema::table('faculty_member_translations', function (Blueprint $table): void {
            $table->dropIndex('idx_faculty_member_translations_locale_full_name');
        });

        Schema::table('media_assets', function (Blueprint $table): void {
            $table->dropIndex('idx_media_title_ar');
            $table->dropIndex('idx_media_title_en');
            $table->dropIndex('idx_media_alt_text_ar');
            $table->dropIndex('idx_media_alt_text_en');
        });

        if ($this->supportsFunctionalIndexes()) {
            Schema::table('dynamic_form_submissions', function (Blueprint $table): void {
                $table->dropIndex('idx_dfs_event_title');
                $table->dropIndex('idx_dfs_job_title');
            });
        }
    }

    private function supportsFunctionalIndexes(): bool
    {
        try {
            $version = DB::selectOne("SELECT VERSION() as v")?->v ?? '';

            return str_starts_with($version, '8.');
        } catch (\Throwable) {
            return false;
        }
    }
};
