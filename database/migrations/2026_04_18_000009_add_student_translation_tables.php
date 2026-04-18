<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('alumni_translations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('alumni_id')->constrained('alumni')->cascadeOnDelete();
            $table->string('locale', 5)->index();
            $table->string('full_name');
            $table->timestamps();

            $table->unique(['alumni_id', 'locale']);
        });

        Schema::create('honor_student_translations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('honor_student_id')->constrained('honor_students')->cascadeOnDelete();
            $table->string('locale', 5)->index();
            $table->string('full_name');
            $table->timestamps();

            $table->unique(['honor_student_id', 'locale']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('honor_student_translations');
        Schema::dropIfExists('alumni_translations');
    }
};
