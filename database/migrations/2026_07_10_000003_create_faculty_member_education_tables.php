<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('faculty_member_educations', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('faculty_member_id');
            $table->foreign('faculty_member_id', 'fme_member_fk')
                ->references('id')
                ->on('faculty_members')
                ->cascadeOnDelete();
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_enabled')->default(true)->index();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('faculty_member_education_translations', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('fme_id');
            $table->foreign('fme_id', 'fmet_fme_fk')
                ->references('id')
                ->on('faculty_member_educations')
                ->cascadeOnDelete();
            $table->string('locale', 5)->index();
            $table->string('degree');
            $table->string('institution')->nullable();
            $table->string('field_of_study')->nullable();
            $table->unsignedSmallInteger('year_start')->nullable();
            $table->unsignedSmallInteger('year_end')->nullable();
            $table->text('description')->nullable();
            $table->timestamps();

            $table->unique(['fme_id', 'locale']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('faculty_member_education_translations');
        Schema::dropIfExists('faculty_member_educations');
    }
};
