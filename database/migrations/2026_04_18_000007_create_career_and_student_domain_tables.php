<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('career_links', function (Blueprint $table): void {
            $table->id();
            $table->string('url');
            $table->boolean('is_external')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_enabled')->default(true)->index();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('career_link_translations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('career_link_id')->constrained('career_links')->cascadeOnDelete();
            $table->string('locale', 5)->index();
            $table->string('title');
            $table->text('description')->nullable();
            $table->timestamps();

            $table->unique(['career_link_id', 'locale']);
        });

        Schema::create('alumni', function (Blueprint $table): void {
            $table->id();
            $table->string('student_identifier')->nullable();
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->foreignId('faculty_id')->nullable()->constrained('faculties')->nullOnDelete();
            $table->foreignId('department_id')->nullable()->constrained('departments')->nullOnDelete();
            $table->string('degree')->nullable();
            $table->unsignedSmallInteger('graduation_year')->nullable();
            $table->foreignId('country_id')->nullable()->constrained('countries')->nullOnDelete();
            $table->foreignId('city_id')->nullable()->constrained('cities')->nullOnDelete();
            $table->foreignId('photo_media_id')->nullable()->constrained('media_assets')->nullOnDelete();
            $table->boolean('is_featured')->default(false)->index();
            $table->boolean('is_enabled')->default(true)->index();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('honor_students', function (Blueprint $table): void {
            $table->id();
            $table->string('student_identifier')->nullable();
            $table->foreignId('faculty_id')->nullable()->constrained('faculties')->nullOnDelete();
            $table->foreignId('department_id')->nullable()->constrained('departments')->nullOnDelete();
            $table->string('academic_year');
            $table->decimal('gpa', 4, 2)->nullable();
            $table->foreignId('photo_media_id')->nullable()->constrained('media_assets')->nullOnDelete();
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_enabled')->default(true)->index();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('honor_students');
        Schema::dropIfExists('alumni');
        Schema::dropIfExists('career_link_translations');
        Schema::dropIfExists('career_links');
    }
};
