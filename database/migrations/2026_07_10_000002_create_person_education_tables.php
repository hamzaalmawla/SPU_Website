<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('person_educations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('person_id')->constrained('persons')->cascadeOnDelete();
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_enabled')->default(true)->index();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('person_education_translations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('person_education_id')->constrained('person_educations')->cascadeOnDelete();
            $table->string('locale', 5)->index();
            $table->string('degree');
            $table->string('institution')->nullable();
            $table->string('field_of_study')->nullable();
            $table->unsignedSmallInteger('year_start')->nullable();
            $table->unsignedSmallInteger('year_end')->nullable();
            $table->text('description')->nullable();
            $table->timestamps();

            $table->unique(['person_education_id', 'locale']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('person_education_translations');
        Schema::dropIfExists('person_educations');
    }
};
