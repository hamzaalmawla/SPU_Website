<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('faculties', function (Blueprint $table): void {
            if (! Schema::hasColumn('faculties', 'public_slug')) {
                $table->string('public_slug')->nullable()->unique()->after('slug');
            }

            if (! Schema::hasColumn('faculties', 'faculty_scope_slug')) {
                $table->string('faculty_scope_slug')->nullable()->index()->after('public_slug');
            }

            if (! Schema::hasColumn('faculties', 'accent_color')) {
                $table->string('accent_color', 20)->nullable()->after('faculty_scope_slug');
            }

            if (! Schema::hasColumn('faculties', 'hero_image')) {
                $table->string('hero_image')->nullable()->after('accent_color');
            }

            if (! Schema::hasColumn('faculties', 'logo_image')) {
                $table->string('logo_image')->nullable()->after('hero_image');
            }

            if (! Schema::hasColumn('faculties', 'gallery_json')) {
                $table->json('gallery_json')->nullable()->after('logo_image');
            }
        });

        Schema::table('faculty_translations', function (Blueprint $table): void {
            if (! Schema::hasColumn('faculty_translations', 'catalog_title')) {
                $table->string('catalog_title')->nullable()->after('name');
            }

            if (! Schema::hasColumn('faculty_translations', 'years_label')) {
                $table->string('years_label')->nullable()->after('description');
            }
        });

        Schema::create('faculty_pages', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('faculty_id')->nullable()->constrained('faculties')->cascadeOnDelete();
            $table->string('slug');
            $table->string('kind')->index();
            $table->string('hero_image')->nullable();
            $table->json('payload_json')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_enabled')->default(true)->index();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['faculty_id', 'slug']);
            $table->index(['kind', 'is_enabled', 'sort_order']);
        });

        Schema::create('faculty_page_translations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('faculty_page_id')->constrained('faculty_pages')->cascadeOnDelete();
            $table->string('locale', 5)->index();
            $table->string('title');
            $table->text('summary')->nullable();
            $table->longText('body')->nullable();
            $table->json('sections_json')->nullable();
            $table->timestamps();

            $table->unique(['faculty_page_id', 'locale']);
        });

        Schema::create('faculty_highlights', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('faculty_id')->constrained('faculties')->cascadeOnDelete();
            $table->string('key');
            $table->string('value')->nullable();
            $table->string('icon')->nullable();
            $table->string('url')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_enabled')->default(true)->index();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['faculty_id', 'key']);
            $table->index(['faculty_id', 'is_enabled', 'sort_order']);
        });

        Schema::create('faculty_highlight_translations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('faculty_highlight_id')->constrained('faculty_highlights')->cascadeOnDelete();
            $table->string('locale', 5)->index();
            $table->string('title');
            $table->text('summary')->nullable();
            $table->timestamps();

            $table->unique(['faculty_highlight_id', 'locale'], 'faculty_highlight_translation_unique');
        });

        Schema::create('faculty_labs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('faculty_id')->constrained('faculties')->cascadeOnDelete();
            $table->string('slug');
            $table->string('image')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_enabled')->default(true)->index();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['faculty_id', 'slug']);
            $table->index(['faculty_id', 'is_enabled', 'sort_order']);
        });

        Schema::create('faculty_lab_translations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('faculty_lab_id')->constrained('faculty_labs')->cascadeOnDelete();
            $table->string('locale', 5)->index();
            $table->string('title');
            $table->string('department')->nullable();
            $table->string('instructor')->nullable();
            $table->text('description')->nullable();
            $table->timestamps();

            $table->unique(['faculty_lab_id', 'locale']);
        });

        Schema::create('faculty_student_projects', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('faculty_id')->constrained('faculties')->cascadeOnDelete();
            $table->string('slug');
            $table->string('image')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_enabled')->default(true)->index();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['faculty_id', 'slug']);
            $table->index(['faculty_id', 'is_enabled', 'sort_order']);
        });

        Schema::create('faculty_student_project_translations', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('faculty_student_project_id');
            $table->string('locale', 5)->index();
            $table->string('title');
            $table->text('summary')->nullable();
            $table->string('tag')->nullable();
            $table->string('team')->nullable();
            $table->string('supervisor')->nullable();
            $table->timestamps();

            $table->foreign('faculty_student_project_id', 'faculty_project_translation_project_fk')
                ->references('id')
                ->on('faculty_student_projects')
                ->cascadeOnDelete();
            $table->unique(['faculty_student_project_id', 'locale'], 'faculty_project_translation_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('faculty_student_project_translations');
        Schema::dropIfExists('faculty_student_projects');
        Schema::dropIfExists('faculty_lab_translations');
        Schema::dropIfExists('faculty_labs');
        Schema::dropIfExists('faculty_highlight_translations');
        Schema::dropIfExists('faculty_highlights');
        Schema::dropIfExists('faculty_page_translations');
        Schema::dropIfExists('faculty_pages');

        Schema::table('faculty_translations', function (Blueprint $table): void {
            foreach (['years_label', 'catalog_title'] as $column) {
                if (Schema::hasColumn('faculty_translations', $column)) {
                    $table->dropColumn($column);
                }
            }
        });

        Schema::table('faculties', function (Blueprint $table): void {
            foreach (['gallery_json', 'logo_image', 'hero_image', 'accent_color', 'faculty_scope_slug', 'public_slug'] as $column) {
                if (Schema::hasColumn('faculties', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
