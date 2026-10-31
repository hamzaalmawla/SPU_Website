<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Schema only: existing rows keep a null slug and image, and the public site
 * keeps deriving both as before. The admin editor fills them on publish, so
 * an edited title no longer changes a publication's URL.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('research_publications', function (Blueprint $table): void {
            $table->string('slug', 191)->nullable()->unique()->after('id');
            $table->foreignId('image_media_id')->nullable()->after('file_media_id')->constrained('media_assets')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('research_publications', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('image_media_id');
            $table->dropUnique(['slug']);
            $table->dropColumn('slug');
        });
    }
};
