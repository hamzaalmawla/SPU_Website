<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Add composite indexes for real query access patterns.
 *
 * Covers: menu tree loading, draft lookups, public page queries,
 * audit log filtering/sorting, and media library operations.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Menu tree loading: public and admin tree queries filter by group, locale,
        // parent, enabled state, and sort by sort_order.
        Schema::table('menu_items', function (Blueprint $table): void {
            $table->index(
                ['group_key', 'locale', 'parent_id', 'is_enabled', 'sort_order'],
                'idx_menu_tree_lookup'
            );
            $table->index(
                ['target_kind', 'target_id'],
                'idx_menu_target_lookup'
            );
        });

        // Homepage draft lookup: find latest editable draft by target type and status.
        Schema::table('homepage_drafts', function (Blueprint $table): void {
            $table->index(
                ['target_type', 'status', 'updated_at'],
                'idx_homepage_draft_lookup'
            );
        });

        // Page draft lookup: find latest editable draft for a specific page.
        Schema::table('page_drafts', function (Blueprint $table): void {
            $table->index(
                ['page_id', 'status', 'updated_at'],
                'idx_page_draft_lookup'
            );
        });

        // Public page queries: sitemap, listings, and public page resolution.
        Schema::table('pages', function (Blueprint $table): void {
            $table->index(
                ['status', 'is_enabled', 'published_at', 'id'],
                'idx_public_page_query'
            );
        });

        // Audit log: default sort, action filter, entity filter, user filter.
        Schema::table('audit_logs', function (Blueprint $table): void {
            $table->index(['created_at'], 'idx_audit_created');
            $table->index(['action', 'created_at'], 'idx_audit_action_created');
            $table->index(['entity_type', 'created_at'], 'idx_audit_entity_created');
            $table->index(['actor_user_id', 'created_at'], 'idx_audit_actor_created');
        });

        // Media library: default sort, filename search, title search.
        Schema::table('media_assets', function (Blueprint $table): void {
            $table->index(['created_at'], 'idx_media_created');
            $table->index(['filename'], 'idx_media_filename');
        });
    }

    public function down(): void
    {
        Schema::table('menu_items', function (Blueprint $table): void {
            $table->dropIndex('idx_menu_tree_lookup');
            $table->dropIndex('idx_menu_target_lookup');
        });

        Schema::table('homepage_drafts', function (Blueprint $table): void {
            $table->dropIndex('idx_homepage_draft_lookup');
        });

        Schema::table('page_drafts', function (Blueprint $table): void {
            $table->dropIndex('idx_page_draft_lookup');
        });

        Schema::table('pages', function (Blueprint $table): void {
            $table->dropIndex('idx_public_page_query');
        });

        Schema::table('audit_logs', function (Blueprint $table): void {
            $table->dropIndex('idx_audit_created');
            $table->dropIndex('idx_audit_action_created');
            $table->dropIndex('idx_audit_entity_created');
            $table->dropIndex('idx_audit_actor_created');
        });

        Schema::table('media_assets', function (Blueprint $table): void {
            $table->dropIndex('idx_media_created');
            $table->dropIndex('idx_media_filename');
        });
    }
};
