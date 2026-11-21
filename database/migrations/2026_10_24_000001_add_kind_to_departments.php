<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * A faculty's academic departments are teaching units (each with a head), not
 * the specializations a student enrolls in; those come from the study plan.
 * `kind` marks the Basic Sciences department that teaches the common years,
 * so the page can tell it apart: basic_sciences, academic or clinical.
 *
 * Marks the Basic Sciences departments the old site listed, and adds the one
 * the AI faculty has (its council lists a head of Basic Sciences) but the
 * old site's sections page left out. An existing row is never changed beyond
 * its kind, and nothing is added to a faculty that is not there.
 */
return new class extends Migration
{
    private const BASIC_SCIENCES = ['petroleum-basic-sciences', 'construction-basic-sciences', 'life-sciences', 'ai-basic-sciences'];

    public function up(): void
    {
        Schema::table('departments', function (Blueprint $table): void {
            $table->string('kind', 32)->default('academic')->after('slug');
        });

        DB::table('departments')->whereIn('slug', self::BASIC_SCIENCES)->update(['kind' => 'basic_sciences']);

        $facultyId = DB::table('faculties')->where('public_slug', 'artificial-intelligence')->value('id');

        if ($facultyId === null || DB::table('departments')->where('slug', 'ai-basic-sciences')->exists()) {
            return;
        }

        $now = now();
        $departmentId = DB::table('departments')->insertGetId([
            'faculty_id' => $facultyId,
            'slug' => 'ai-basic-sciences',
            'kind' => 'basic_sciences',
            'sort_order' => 0,
            'is_enabled' => true,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        DB::table('department_translations')->insert([
            ['department_id' => $departmentId, 'locale' => 'ar', 'name' => 'قسم العلوم الأساسية', 'description' => null, 'created_at' => $now, 'updated_at' => $now],
            ['department_id' => $departmentId, 'locale' => 'en', 'name' => 'Department of Basic Sciences', 'description' => null, 'created_at' => $now, 'updated_at' => $now],
        ]);
    }

    public function down(): void
    {
        $departmentId = DB::table('departments')->where('slug', 'ai-basic-sciences')->value('id');

        if ($departmentId !== null) {
            DB::table('department_translations')->where('department_id', $departmentId)->delete();
            DB::table('departments')->where('id', $departmentId)->delete();
        }

        Schema::table('departments', function (Blueprint $table): void {
            $table->dropColumn('kind');
        });
    }
};
