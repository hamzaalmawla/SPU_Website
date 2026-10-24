<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('news_articles', function (Blueprint $table): void {
            $table->index(['status', 'is_enabled', 'published_at', 'id'], 'na_public_idx');
            $table->index(['news_category_id', 'status', 'is_enabled', 'published_at', 'id'], 'na_category_public_idx');
            $table->index(['is_featured', 'status', 'is_enabled', 'published_at'], 'na_featured_public_idx');
        });

        Schema::table('news_categories', function (Blueprint $table): void {
            $table->index(['type', 'is_enabled', 'sort_order'], 'nc_type_enabled_sort_idx');
        });
    }

    public function down(): void
    {
        Schema::table('news_categories', function (Blueprint $table): void {
            $table->dropIndex('nc_type_enabled_sort_idx');
        });

        Schema::table('news_articles', function (Blueprint $table): void {
            $table->dropIndex('na_featured_public_idx');
            $table->dropIndex('na_category_public_idx');
            $table->dropIndex('na_public_idx');
        });
    }
};
