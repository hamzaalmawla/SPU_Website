<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('about_pages', function (Blueprint $table): void {
            $table->id();
            $table->string('slug')->unique();
            $table->string('template')->default('content');
            $table->string('hero_image')->nullable();
            $table->json('payload_json')->nullable();
            $table->string('status')->default('published')->index();
            $table->timestamp('publish_at')->nullable()->index();
            $table->timestamp('published_at')->nullable();
            $table->boolean('is_enabled')->default(true)->index();
            $table->unsignedInteger('sort_order')->default(0)->index();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('about_page_translations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('about_page_id')->constrained('about_pages')->cascadeOnDelete();
            $table->string('locale', 5)->index();
            $table->string('title');
            $table->string('headline')->nullable();
            $table->text('summary')->nullable();
            $table->json('sections_json')->nullable();
            $table->timestamps();

            $table->unique(['about_page_id', 'locale']);
        });

        Schema::create('persons', function (Blueprint $table): void {
            $table->id();
            $table->string('slug')->unique();
            $table->string('category')->index();
            $table->string('faculty_scope_slug')->nullable()->index();
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->string('image')->nullable();
            $table->string('profile_url')->nullable();
            $table->unsignedInteger('sort_order')->default(0)->index();
            $table->boolean('is_enabled')->default(true)->index();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('person_translations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('person_id')->constrained('persons')->cascadeOnDelete();
            $table->string('locale', 5)->index();
            $table->string('name');
            $table->string('role');
            $table->text('bio')->nullable();
            $table->text('quote')->nullable();
            $table->timestamps();

            $table->unique(['person_id', 'locale']);
        });

        Schema::create('directorates', function (Blueprint $table): void {
            $table->id();
            $table->string('slug')->unique();
            $table->string('icon')->nullable();
            $table->string('email')->nullable();
            $table->string('location')->nullable();
            $table->unsignedInteger('sort_order')->default(0)->index();
            $table->boolean('is_enabled')->default(true)->index();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('directorate_translations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('directorate_id')->constrained('directorates')->cascadeOnDelete();
            $table->string('locale', 5)->index();
            $table->string('title');
            $table->text('summary')->nullable();
            $table->text('description')->nullable();
            $table->json('services_json')->nullable();
            $table->timestamps();

            $table->unique(['directorate_id', 'locale']);
        });

        Schema::create('partnerships', function (Blueprint $table): void {
            $table->id();
            $table->string('slug')->unique();
            $table->string('logo')->nullable();
            $table->string('website_url')->nullable();
            $table->date('signed_at')->nullable();
            $table->unsignedInteger('sort_order')->default(0)->index();
            $table->boolean('is_enabled')->default(true)->index();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('partnership_translations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('partnership_id')->constrained('partnerships')->cascadeOnDelete();
            $table->string('locale', 5)->index();
            $table->string('name');
            $table->string('category')->nullable();
            $table->string('status')->nullable();
            $table->string('established_label')->nullable();
            $table->text('description')->nullable();
            $table->string('scope')->nullable();
            $table->timestamps();

            $table->unique(['partnership_id', 'locale']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('partnership_translations');
        Schema::dropIfExists('partnerships');
        Schema::dropIfExists('directorate_translations');
        Schema::dropIfExists('directorates');
        Schema::dropIfExists('person_translations');
        Schema::dropIfExists('persons');
        Schema::dropIfExists('about_page_translations');
        Schema::dropIfExists('about_pages');
    }
};
