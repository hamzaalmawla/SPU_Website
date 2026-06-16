<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Faculty categories
        Schema::create('faculty_categories', function (Blueprint $table) {
            $table->id();
            $table->string('slug')->unique();
            $table->integer('order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('faculty_category_translations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('faculty_category_id')->constrained()->cascadeOnDelete();
            $table->string('locale', 2);
            $table->string('name');
            $table->text('description')->nullable();
            $table->timestamps();

            $table->unique(['faculty_category_id', 'locale']);
        });

        // Faculty members
        Schema::create('faculty_members', function (Blueprint $table) {
            $table->id();
            $table->foreignId('faculty_category_id')->nullable()->constrained()->nullOnDelete();
            $table->string('email')->unique()->nullable();
            $table->string('phone')->nullable();
            $table->string('office_location')->nullable();
            $table->string('photo_path')->nullable();
            $table->string('cv_path')->nullable();
            $table->integer('order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->boolean('is_featured')->default(false);
            $table->timestamps();
            $table->softDeletes();

            $table->index('is_featured');
        });

        Schema::create('faculty_member_translations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('faculty_member_id')->constrained()->cascadeOnDelete();
            $table->string('locale', 2);
            $table->string('full_name');
            $table->string('title')->nullable(); // Dr., Prof., etc.
            $table->string('position')->nullable();
            $table->text('bio')->nullable();
            $table->text('specializations')->nullable();
            $table->text('education')->nullable();
            $table->timestamps();

            $table->unique(['faculty_member_id', 'locale']);
        });

        // Faculty publications/research
        Schema::create('faculty_publications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('faculty_member_id')->constrained()->cascadeOnDelete();
            $table->string('type'); // research, publication, award, etc.
            $table->date('published_date')->nullable();
            $table->string('url')->nullable();
            $table->string('file_path')->nullable();
            $table->integer('order')->default(0);
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('faculty_publication_translations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('faculty_publication_id')->constrained()->cascadeOnDelete();
            $table->string('locale', 2);
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('publisher')->nullable();
            $table->timestamps();

            $table->unique(['faculty_publication_id', 'locale'], 'faculty_pub_trans_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('faculty_publication_translations');
        Schema::dropIfExists('faculty_publications');
        Schema::dropIfExists('faculty_member_translations');
        Schema::dropIfExists('faculty_members');
        Schema::dropIfExists('faculty_category_translations');
        Schema::dropIfExists('faculty_categories');
    }
};
