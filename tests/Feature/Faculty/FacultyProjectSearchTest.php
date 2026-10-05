<?php

declare(strict_types=1);

namespace Tests\Feature\Faculty;

use App\Models\Faculty\Faculty;
use App\Models\Faculty\FacultyTranslation;
use App\Models\Faculty\FacultyStudentProject;
use App\Models\Faculty\FacultyStudentProjectTranslation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class FacultyProjectSearchTest extends TestCase
{
    use RefreshDatabase;

    private Faculty $faculty;

    protected function setUp(): void
    {
        parent::setUp();

        $this->faculty = Faculty::create([
            'slug' => 'pharmacy',
            'public_slug' => 'pharmacy',
            'faculty_scope_slug' => 'pharmacy',
            'is_enabled' => true,
            'sort_order' => 1,
        ]);

        FacultyTranslation::create([
            'faculty_id' => $this->faculty->id,
            'locale' => 'ar',
            'name' => 'كلية الصيدلة',
        ]);

        $project1 = FacultyStudentProject::create([
            'faculty_id' => $this->faculty->id,
            'slug' => 'project-alpha',
            'is_enabled' => true,
            'sort_order' => 1,
        ]);

        FacultyStudentProjectTranslation::create([
            'faculty_student_project_id' => $project1->id,
            'locale' => 'ar',
            'title' => 'مشروع الأدوية الذكية',
            'team' => 'أحمد علي، سارة محمود',
            'supervisor' => 'د. خالد العمري',
        ]);

        $project2 = FacultyStudentProject::create([
            'faculty_id' => $this->faculty->id,
            'slug' => 'project-beta',
            'is_enabled' => true,
            'sort_order' => 2,
        ]);

        FacultyStudentProjectTranslation::create([
            'faculty_student_project_id' => $project2->id,
            'locale' => 'ar',
            'title' => 'تحليل المركبات العضوية',
            'team' => 'محمد سمير، ياسمين حسن',
            'supervisor' => 'د. رانيا سعيد',
        ]);
    }

    public function test_can_search_faculty_projects_by_team_member_name(): void
    {
        $response = $this->call('GET', '/ar/faculties/pharmacy/projects', ['q' => 'سارة']);

        $response->assertOk();
        $response->assertSee('مشروع الأدوية الذكية');
        $response->assertDontSee('تحليل المركبات العضوية');
    }

    public function test_can_search_faculty_projects_by_project_title(): void
    {
        $response = $this->call('GET', '/ar/faculties/pharmacy/projects', ['q' => 'المركبات']);

        $response->assertOk();
        $response->assertSee('تحليل المركبات العضوية');
        $response->assertDontSee('مشروع الأدوية الذكية');
    }
}
