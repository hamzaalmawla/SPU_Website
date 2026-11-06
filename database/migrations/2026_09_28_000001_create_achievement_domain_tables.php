<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('achievement_categories', function (Blueprint $table): void {
            $table->id();
            $table->string('slug')->unique();
            $table->boolean('is_active')->default(true)->index();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('achievement_category_translations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('achievement_category_id');
            $table->string('locale', 5)->index();
            $table->string('name');
            $table->timestamps();
            $table->unique(['achievement_category_id', 'locale'], 'act_category_locale_unique');
            $table->foreign('achievement_category_id', 'act_category_fk')->references('id')->on('achievement_categories')->cascadeOnDelete();
        });

        Schema::create('achievements', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('image_media_id')->nullable()->constrained('media_assets')->nullOnDelete();
            $table->string('legacy_image_path')->nullable();
            $table->string('status')->default('draft')->index();
            $table->timestamp('published_at')->nullable()->index();
            $table->boolean('is_public')->default(false)->index();
            $table->boolean('pin_to_homepage')->default(false)->index();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
            $table->softDeletes();
            $table->index(['status', 'is_public', 'published_at'], 'achievements_public_index');
        });

        Schema::create('achievement_translations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('achievement_id');
            $table->string('locale', 5)->index();
            $table->string('title');
            $table->text('summary')->nullable();
            $table->string('meta')->nullable();
            $table->string('action_label')->nullable();
            $table->string('action_url', 2048)->nullable();
            $table->timestamps();
            $table->unique(['achievement_id', 'locale'], 'at_achievement_locale_unique');
            $table->foreign('achievement_id', 'at_achievement_fk')->references('id')->on('achievements')->cascadeOnDelete();
        });

        Schema::create('achievement_category', function (Blueprint $table): void {
            $table->foreignId('achievement_id');
            $table->foreignId('achievement_category_id');
            $table->primary(['achievement_id', 'achievement_category_id'], 'achievement_category_primary');
            $table->foreign('achievement_id', 'ac_achievement_fk')->references('id')->on('achievements')->cascadeOnDelete();
            $table->foreign('achievement_category_id', 'ac_category_fk')->references('id')->on('achievement_categories')->cascadeOnDelete();
        });

    }

    public function down(): void
    {
        throw new RuntimeException('Achievement tables contain managed content and cannot be removed automatically. Restore the pre-deployment database backup for a full rollback.');
    }
};
