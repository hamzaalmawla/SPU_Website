<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The positions a faculty's council page offers in its dropdown, shared by every
 * faculty (dean, vice dean, head of department and those editors add). The three
 * built-in positions live in code (FacultyPositionServiceInterface); a row here
 * with a built-in slug only renames it, so no data is inserted.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('faculty_positions', function (Blueprint $table): void {
            $table->id();
            $table->string('slug', 64)->unique();
            $table->string('placement', 16)->default('council');
            $table->unsignedInteger('sort_order')->default(100);
            $table->timestamps();
        });

        Schema::create('faculty_position_translations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('faculty_position_id')->constrained('faculty_positions')->cascadeOnDelete();
            $table->string('locale', 5);
            $table->string('name', 180);
            $table->timestamps();
            $table->unique(['faculty_position_id', 'locale']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('faculty_position_translations');
        Schema::dropIfExists('faculty_positions');
    }
};
