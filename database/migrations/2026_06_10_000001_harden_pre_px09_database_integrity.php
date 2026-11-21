<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('homepage_drafts', function (Blueprint $table): void {
            $table->index(['target_type', 'status', 'scheduled_at'], 'idx_homepage_scheduled_publish');
        });

        Schema::table('pages', function (Blueprint $table): void {
            $table->index(['status', 'publish_at'], 'idx_pages_scheduled_publish');
        });

        Schema::table('page_drafts', function (Blueprint $table): void {
            $table->index(['page_id', 'status', 'scheduled_at'], 'idx_page_drafts_scheduled_publish');
        });

        DB::table('roles')
            ->select(['id', 'slug'])
            ->orderBy('id')
            ->get()
            ->each(function (object $role): void {
                DB::table('users')
                    ->where('role_slug', (string) $role->slug)
                    ->whereNull('role_id')
                    ->update(['role_id' => (int) $role->id]);
            });

        if (DB::getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE preview_tokens MODIFY token_hash VARCHAR(64) NOT NULL');
        }
    }

    public function down(): void
    {
        Schema::table('homepage_drafts', function (Blueprint $table): void {
            $table->dropIndex('idx_homepage_scheduled_publish');
        });

        Schema::table('pages', function (Blueprint $table): void {
            $table->dropIndex('idx_pages_scheduled_publish');
        });

        Schema::table('page_drafts', function (Blueprint $table): void {
            $table->dropIndex('idx_page_drafts_scheduled_publish');
        });

        if (DB::getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE preview_tokens MODIFY token_hash VARCHAR(64) NULL');
        }
    }
};
