<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('faculties', function (Blueprint $table): void {
            $table->id();
            $table->string('slug')->unique();
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_enabled')->default(true)->index();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('faculty_translations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('faculty_id')->constrained('faculties')->cascadeOnDelete();
            $table->string('locale', 5)->index();
            $table->string('name');
            $table->text('short_description')->nullable();
            $table->text('description')->nullable();
            $table->timestamps();

            $table->unique(['faculty_id', 'locale']);
        });

        Schema::create('departments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('faculty_id')->constrained('faculties')->cascadeOnDelete();
            $table->string('slug')->unique();
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_enabled')->default(true)->index();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('department_translations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('department_id')->constrained('departments')->cascadeOnDelete();
            $table->string('locale', 5)->index();
            $table->string('name');
            $table->text('description')->nullable();
            $table->timestamps();

            $table->unique(['department_id', 'locale']);
        });

        Schema::create('faculty_members', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('faculty_id')->nullable()->constrained('faculties')->nullOnDelete();
            $table->foreignId('department_id')->nullable()->constrained('departments')->nullOnDelete();
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->foreignId('photo_media_id')->nullable()->constrained('media_assets')->nullOnDelete();
            $table->foreignId('cv_media_id')->nullable()->constrained('media_assets')->nullOnDelete();
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_enabled')->default(true)->index();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('faculty_member_translations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('faculty_member_id')->constrained('faculty_members')->cascadeOnDelete();
            $table->string('locale', 5)->index();
            $table->string('full_name');
            $table->string('title')->nullable();
            $table->string('position')->nullable();
            $table->text('bio')->nullable();
            $table->json('specializations')->nullable();
            $table->timestamps();

            $table->unique(['faculty_member_id', 'locale']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('faculty_member_translations');
        Schema::dropIfExists('faculty_members');
        Schema::dropIfExists('department_translations');
        Schema::dropIfExists('departments');
        Schema::dropIfExists('faculty_translations');
        Schema::dropIfExists('faculties');
    }
};
