<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The graduate grades (التقدير) offered on every faculty's Alumni page, one list
 * shared by all of them and managed by super admins (GraduationGradeServiceInterface).
 *
 * `alumni.grade` already holds a grade key (excellent, very_good, good, pass, as
 * decision imports write it), so the list starts with those four keys and no
 * graduate's grade changes. A deleted grade is kept (soft deleted) with the key
 * its graduates were moved to, so a page saved before the delete still reads.
 */
return new class extends Migration
{
    /** @var array<string, array{ar: string, en: string}> */
    private const STARTING_GRADES = [
        'excellent' => ['ar' => 'امتياز', 'en' => 'Excellent'],
        'very_good' => ['ar' => 'جيد جداً', 'en' => 'Very Good'],
        'good' => ['ar' => 'جيد', 'en' => 'Good'],
        'pass' => ['ar' => 'مقبول', 'en' => 'Pass'],
    ];

    public function up(): void
    {
        Schema::create('graduation_grades', function (Blueprint $table): void {
            $table->id();
            // Same length as alumni.grade, which stores it.
            $table->string('key', 20)->unique();
            $table->unsignedInteger('sort_order')->default(100);
            $table->string('replaced_by_key', 20)->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('graduation_grade_translations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('graduation_grade_id')->constrained('graduation_grades')->cascadeOnDelete();
            $table->string('locale', 5);
            $table->string('name', 120);
            $table->timestamps();
            $table->unique(['graduation_grade_id', 'locale']);
        });

        $now = now();
        $order = 1;

        foreach (self::STARTING_GRADES as $key => $names) {
            $id = DB::table('graduation_grades')->insertGetId([
                'key' => $key,
                'sort_order' => $order++,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            foreach ($names as $locale => $name) {
                DB::table('graduation_grade_translations')->insert([
                    'graduation_grade_id' => $id,
                    'locale' => $locale,
                    'name' => $name,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        }

        if (Schema::hasColumn('alumni', 'grade')) {
            // Counting and moving a grade's graduates reads by grade.
            Schema::table('alumni', function (Blueprint $table): void {
                $table->index('grade', 'alumni_grade_index');
            });

            // A grade typed as its name instead of its key (none are expected) is read as that grade.
            foreach (self::STARTING_GRADES as $key => $names) {
                DB::table('alumni')->whereIn('grade', [$names['ar'], $names['en'], strtolower($names['en'])])->update(['grade' => $key]);
            }
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('alumni', 'grade')) {
            Schema::table('alumni', function (Blueprint $table): void {
                $table->dropIndex('alumni_grade_index');
            });
        }

        Schema::dropIfExists('graduation_grade_translations');
        Schema::dropIfExists('graduation_grades');
    }
};
