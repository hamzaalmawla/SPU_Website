<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Scanned pages of a knowledge file (a PDF of page images, or a photo) are
 * read by the vision model a batch at a time. These record how far that has
 * got, so the admin list can show it and the next request or the scheduler
 * can carry on. Schema only: no data changes.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('chatbot_documents', function (Blueprint $table): void {
            $table->unsignedInteger('scan_pages_total')->default(0)->after('embedding_dimensions');
            $table->unsignedInteger('scan_pages_read')->default(0)->after('scan_pages_total');
        });
    }

    public function down(): void
    {
        Schema::table('chatbot_documents', function (Blueprint $table): void {
            $table->dropColumn(['scan_pages_total', 'scan_pages_read']);
        });
    }
};
