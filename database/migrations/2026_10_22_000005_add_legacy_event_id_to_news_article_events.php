<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The Events page list ID an event article was moved from, so the old entry
 * is hidden and its old addresses redirect to the article.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('news_article_events', function (Blueprint $table): void {
            $table->string('legacy_event_id')->nullable()->unique();
        });
    }

    public function down(): void
    {
        Schema::table('news_article_events', function (Blueprint $table): void {
            $table->dropUnique(['legacy_event_id']);
            $table->dropColumn('legacy_event_id');
        });
    }
};
