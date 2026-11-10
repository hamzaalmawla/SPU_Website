<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The editor asks "newest draft of this target with this status" ordered by
 * updated_at then id. Naming id in the index lets MySQL walk it backwards and
 * skip the filesort entirely, which is what ran out of sort memory before.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cms_drafts', function (Blueprint $table): void {
            $table->index(['target_key', 'status', 'updated_at', 'id'], 'idx_cms_drafts_latest');
        });
    }

    public function down(): void
    {
        Schema::table('cms_drafts', function (Blueprint $table): void {
            $table->dropIndex('idx_cms_drafts_latest');
        });
    }
};
