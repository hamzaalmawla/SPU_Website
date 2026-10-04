<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Faculty\Faculty;
use App\Models\Faculty\FacultyStudentProject;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

final class DisablePlaceholderFacultyProjectsTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_disables_only_projects_without_a_legacy_source_id(): void
    {
        $faculty = Faculty::query()->create([
            'slug' => 'medicine',
            'public_slug' => 'medicine',
            'faculty_scope_slug' => 'medicine',
            'sort_order' => 1,
            'is_enabled' => true,
        ]);

        $demo = FacultyStudentProject::query()->create([
            'faculty_id' => $faculty->getKey(),
            'slug' => 'medicine-project-1',
            'legacy_source_id' => null,
            'is_enabled' => true,
            'sort_order' => 1,
        ]);

        $imported = FacultyStudentProject::query()->create([
            'faculty_id' => $faculty->getKey(),
            'slug' => 'medicine-project-6158',
            'legacy_source_id' => 6158,
            'is_enabled' => true,
            'sort_order' => 2,
        ]);

        // An imported project the editors deliberately left disabled - one of the
        // 54 source-hidden ones - must not be switched on by down().
        $hiddenImport = FacultyStudentProject::query()->create([
            'faculty_id' => $faculty->getKey(),
            'slug' => 'medicine-project-6159',
            'legacy_source_id' => 6159,
            'is_enabled' => false,
            'sort_order' => 3,
        ]);

        $migration = require database_path('migrations/2026_10_04_000001_disable_placeholder_faculty_projects.php');
        $migration->up();

        self::assertFalse($demo->fresh()->is_enabled, 'A project with no legacy_source_id is placeholder data and must be hidden');
        self::assertTrue($imported->fresh()->is_enabled, 'An imported project must be left alone');
        self::assertFalse($hiddenImport->fresh()->is_enabled, 'A deliberately hidden import must stay hidden');

        $migration->down();

        self::assertTrue($demo->fresh()->is_enabled, 'down() must restore exactly what up() disabled');
        self::assertFalse($hiddenImport->fresh()->is_enabled, 'down() must not enable an import that was never ours to touch');
    }

    public function test_it_is_safe_to_run_twice(): void
    {
        $faculty = Faculty::query()->create([
            'slug' => 'pharmacy',
            'public_slug' => 'pharmacy',
            'faculty_scope_slug' => 'pharmacy',
            'sort_order' => 1,
            'is_enabled' => true,
        ]);

        FacultyStudentProject::query()->create([
            'faculty_id' => $faculty->getKey(),
            'slug' => 'pharmacy-project-1',
            'legacy_source_id' => null,
            'is_enabled' => true,
            'sort_order' => 1,
        ]);

        $migration = require database_path('migrations/2026_10_04_000001_disable_placeholder_faculty_projects.php');
        $migration->up();
        $migration->up();

        self::assertSame(
            0,
            DB::table('faculty_student_projects')->whereNull('legacy_source_id')->where('is_enabled', true)->count(),
            'Re-running must be a no-op rather than an error',
        );
    }
}
