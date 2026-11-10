<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Contracts\Faculty\FacultyStudyPlanImportServiceInterface;
use App\Models\User\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** The Faculty of Business 2026 study plan (study_plans/build_business_2026.py) imports cleanly. */
final class BusinessStudyPlan2026Test extends TestCase
{
    use RefreshDatabase;

    public function test_the_2026_business_plan_is_a_valid_import(): void
    {
        $this->seed(DatabaseSeeder::class);
        $source = json_decode((string) file_get_contents(database_path('seeders/Data/study_plans/business-administration.json')), true, flags: JSON_THROW_ON_ERROR);
        $user = User::query()->where('role_slug', 'super_admin')->firstOrFail();

        $result = app(FacultyStudyPlanImportServiceInterface::class)
            ->import('business-administration', $source, (string) $user->getKey(), false, replaceEdited: true);

        $this->assertTrue($result->valid, json_encode($result->errors, JSON_THROW_ON_ERROR));
        $this->assertFalse($result->written);
        $this->assertSame(['hr', 'accounting', 'marketing', 'finance', 'mis'], array_keys($result->coursesByDepartment));

        foreach ($source['departments'] as $department) {
            $this->assertCount(8, $department['terms']);
            $this->assertSame(134, $department['totalCredits']);
        }
    }
}
