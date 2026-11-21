<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('news_categories', function (Blueprint $table): void {
            $table->id();
            $table->string('slug')->unique();
            $table->string('type')->default('news')->index();
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_enabled')->default(true)->index();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('news_category_translations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('news_category_id')->constrained('news_categories')->cascadeOnDelete();
            $table->string('locale', 5)->index();
            $table->string('name');
            $table->text('description')->nullable();
            $table->timestamps();

            $table->unique(['news_category_id', 'locale'], 'nct_category_locale_unique');
        });

        Schema::create('news_articles', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('news_category_id')->nullable()->constrained('news_categories')->nullOnDelete();
            $table->foreignId('cover_media_id')->nullable()->constrained('media_assets')->nullOnDelete();
            $table->string('slug')->unique();
            $table->string('status')->default('draft')->index();
            $table->timestamp('published_at')->nullable()->index();
            $table->timestamp('scheduled_at')->nullable()->index();
            $table->boolean('is_enabled')->default(true)->index();
            $table->boolean('is_featured')->default(false)->index();
            $table->unsignedInteger('sort_order')->default(0);
            $table->string('faculty_scope_slug')->nullable()->index();
            $table->string('legacy_source_table')->nullable()->index();
            $table->unsignedBigInteger('legacy_source_id')->nullable()->index();
            $table->unsignedInteger('legacy_service_type')->nullable()->index();
            $table->string('legacy_url')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['legacy_source_table', 'legacy_source_id'], 'news_article_legacy_unique');
        });

        Schema::create('news_article_translations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('news_article_id')->constrained('news_articles')->cascadeOnDelete();
            $table->string('locale', 5)->index();
            $table->string('title');
            $table->text('excerpt')->nullable();
            $table->longText('body')->nullable();
            $table->timestamps();

            $table->unique(['news_article_id', 'locale'], 'nat_article_locale_unique');
        });

        Schema::create('news_article_seo_meta', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('news_article_id')->constrained('news_articles')->cascadeOnDelete();
            $table->string('locale', 5)->index();
            $table->string('meta_title')->nullable();
            $table->text('meta_description')->nullable();
            $table->string('og_title')->nullable();
            $table->text('og_description')->nullable();
            $table->foreignId('og_image_media_id')->nullable()->constrained('media_assets')->nullOnDelete();
            $table->string('og_image_url')->nullable();
            $table->string('robots')->default('index,follow');
            $table->timestamps();

            $table->unique(['news_article_id', 'locale'], 'nasm_article_locale_unique');
        });

        Schema::create('news_article_attachments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('news_article_id')->constrained('news_articles')->cascadeOnDelete();
            $table->foreignId('media_asset_id')->nullable()->constrained('media_assets')->nullOnDelete();
            $table->string('kind')->default('file')->index();
            $table->string('label_ar')->nullable();
            $table->string('label_en')->nullable();
            $table->string('legacy_source_table')->nullable();
            $table->unsignedBigInteger('legacy_source_id')->nullable();
            $table->string('legacy_path')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['legacy_source_table', 'legacy_source_id', 'legacy_path'], 'naa_legacy_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('news_article_attachments');
        Schema::dropIfExists('news_article_seo_meta');
        Schema::dropIfExists('news_article_translations');
        Schema::dropIfExists('news_articles');
        Schema::dropIfExists('news_category_translations');
        Schema::dropIfExists('news_categories');
    }
};
