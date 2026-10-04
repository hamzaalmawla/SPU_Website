<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Hides the demo faculty projects that predate the legacy import.
 *
 * Every faculty carried about a dozen of them - slugs running
 * medicine-project-1 through -12 - and they sorted above the real records, so
 * the first thing a visitor saw on any project listing was invented content:
 * titles like "تحسين تدفق المواعيد السريرية" and a team reading
 * "فريق طلابي واسم الطالب", which is not a name but a note describing what
 * should go there.
 *
 * Nothing in the repository creates them. No seeder and no migration references
 * FacultyStudentProject, and resources/data/frontend-faculty-projects.json - the
 * fixture their titles come from - has no reader. They are leftovers of a seeder
 * that has since been deleted, so disabling them is permanent without being
 * fragile: no deploy will put them back.
 *
 * legacy_source_id is the discriminator. The importer sets it on every record it
 * writes, so a project without one came from somewhere else. That is narrower
 * than matching slugs, which would also catch a real project numbered 1.
 *
 * Disabled rather than deleted. The rows stay for anyone who wants to look at
 * what was there, the public queries filter on is_enabled, and down() puts them
 * back exactly.
 */
return new class extends Migration
{
    public function up(): void
    {
        $affected = DB::table('faculty_student_projects')
            ->whereNull('legacy_source_id')
            ->where('is_enabled', true)
            ->update(['is_enabled' => false, 'updated_at' => now()]);

        // Printed because the number is the point: if this ever reports
        // something far from the expected ~84, the discriminator has stopped
        // meaning what it meant and somebody should look before trusting it.
        if (app()->runningInConsole()) {
            echo "  Disabled {$affected} placeholder faculty project(s) with no legacy_source_id.".PHP_EOL;
        }
    }

    public function down(): void
    {
        DB::table('faculty_student_projects')
            ->whereNull('legacy_source_id')
            ->where('is_enabled', false)
            ->update(['is_enabled' => true, 'updated_at' => now()]);
    }
};
