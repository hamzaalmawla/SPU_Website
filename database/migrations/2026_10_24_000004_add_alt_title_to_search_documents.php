<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The record's title in its other language, folded, so a search typed in
 * English ranks an Arabic page's title match like its own title. Filled by
 * `search:index`, which every deploy runs.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('search_documents', function (Blueprint $table): void {
            $table->string('alt_title_normalized', 512)->nullable()->after('title_normalized');
        });
    }

    public function down(): void
    {
        Schema::table('search_documents', function (Blueprint $table): void {
            $table->dropColumn('alt_title_normalized');
        });
    }
};
