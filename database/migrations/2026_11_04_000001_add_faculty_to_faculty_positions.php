<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Each faculty keeps its own positions list. A position row now belongs to one
 * faculty; the slug is unique within that faculty. Rows saved before this
 * (no faculty) stay as they are and keep reading for the pages that use them,
 * so no data is changed (FacultyPositionServiceInterface).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('faculty_positions', function (Blueprint $table): void {
            $table->foreignId('faculty_id')->nullable()->after('id')->constrained('faculties')->cascadeOnDelete();
        });

        Schema::table('faculty_positions', function (Blueprint $table): void {
            $table->dropUnique('faculty_positions_slug_unique');
            $table->unique(['faculty_id', 'slug']);
        });
    }

    public function down(): void
    {
        Schema::table('faculty_positions', function (Blueprint $table): void {
            $table->dropUnique(['faculty_id', 'slug']);
            $table->dropConstrainedForeignId('faculty_id');
        });

        Schema::table('faculty_positions', function (Blueprint $table): void {
            $table->unique('slug');
        });
    }
};
