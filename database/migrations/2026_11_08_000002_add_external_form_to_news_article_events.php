<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * An event article can take registrations through a form on another site
 * (a Google or Microsoft form, say) instead of one built under Forms; its
 * Register button then opens that address.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('news_article_events', function (Blueprint $table): void {
            $table->string('form_source', 20)->default('internal')->after('form_key');
            $table->string('form_url', 2048)->nullable()->after('form_source');
        });
    }

    public function down(): void
    {
        Schema::table('news_article_events', function (Blueprint $table): void {
            $table->dropColumn(['form_source', 'form_url']);
        });
    }
};
