<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * An optional button on an announcement: to an event article, a research
 * conference, or any address. The button text can be set per language.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('news_articles', function (Blueprint $table): void {
            $table->string('related_kind', 20)->nullable();
            $table->foreignId('related_article_id')->nullable()->constrained('news_articles')->nullOnDelete();
            $table->string('related_conference_id')->nullable();
            $table->string('related_url')->nullable();
        });

        Schema::table('news_article_translations', function (Blueprint $table): void {
            $table->string('cta_label')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('news_article_translations', function (Blueprint $table): void {
            $table->dropColumn('cta_label');
        });

        Schema::table('news_articles', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('related_article_id');
            $table->dropColumn(['related_kind', 'related_conference_id', 'related_url']);
        });
    }
};
