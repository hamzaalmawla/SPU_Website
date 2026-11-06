<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Each semester a faculty council issues its graduates and top-students
 * decisions as Word files, and editors import them from the faculty's Alumni
 * and Honor List admin pages. An import is recorded here so it can be undone,
 * and the rows it writes carry their grade, semester and study year.
 *
 * Schema only: no rows are written.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('student_list_imports', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('faculty_id')->constrained('faculties')->cascadeOnDelete();
            // alumni | honor_students
            $table->string('kind', 20);
            $table->string('original_filename');
            $table->string('decision_number', 40)->nullable();
            $table->string('academic_year', 20);
            // first | second | summer
            $table->string('semester', 10);
            $table->unsignedInteger('created_count')->default(0);
            $table->unsignedInteger('updated_count')->default(0);
            $table->unsignedInteger('skipped_count')->default(0);
            // Specialization text => department id (or null for none) as the editor
            // confirmed it, so the next decision from this faculty matches the same way.
            $table->json('department_map')->nullable();
            // Values of the rows this import updated, as they were before it, for undo.
            $table->json('previous_values')->nullable();
            $table->foreignId('imported_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('undone_at')->nullable();
            $table->foreignId('undone_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['faculty_id', 'kind', 'created_at']);
        });

        Schema::table('alumni', function (Blueprint $table): void {
            $table->decimal('gpa_points', 3, 2)->nullable()->after('graduation_year');
            $table->decimal('gpa_percent', 5, 2)->nullable()->after('gpa_points');
            // excellent | very_good | good | pass
            $table->string('grade', 20)->nullable()->after('gpa_percent');
            $table->string('academic_year', 20)->nullable()->after('grade');
            $table->string('semester', 10)->nullable()->after('academic_year');
            $table->foreignId('student_list_import_id')->nullable()->after('semester')->constrained('student_list_imports')->nullOnDelete();

        });

        Schema::table('honor_students', function (Blueprint $table): void {
            $table->unsignedTinyInteger('study_year')->nullable()->after('academic_year');
            $table->string('semester', 10)->nullable()->after('study_year');
            $table->foreignId('student_list_import_id')->nullable()->after('semester')->constrained('student_list_imports')->nullOnDelete();

        });
    }

    public function down(): void
    {
        Schema::table('honor_students', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('student_list_import_id');
            $table->dropColumn(['study_year', 'semester']);
        });

        Schema::table('alumni', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('student_list_import_id');
            $table->dropColumn(['gpa_points', 'gpa_percent', 'grade', 'academic_year', 'semester']);
        });

        Schema::dropIfExists('student_list_imports');
    }
};
