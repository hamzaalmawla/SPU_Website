<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Contextual retrieval: each passage of an uploaded document carries one or
 * two sentences, written at index time, saying where it sits in its document
 * (which decree, article and subject). Schema only: passages indexed before
 * keep an empty context until their document is re-indexed.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('chatbot_chunks', function (Blueprint $table): void {
            $table->text('context')->nullable()->after('heading');
        });
    }

    public function down(): void
    {
        Schema::table('chatbot_chunks', function (Blueprint $table): void {
            $table->dropColumn('context');
        });
    }
};
