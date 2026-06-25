<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Contracts\Page\FacultyAdminWorkflowServiceInterface;
use App\Models\Career\Alumni;
use App\Models\Career\HonorStudent;
use App\Models\Faculty\Faculty;
use App\Models\Faculty\FacultyHighlight;
use App\Models\Faculty\FacultyLab;
use App\Models\Faculty\FacultyPage;
use App\Models\Faculty\FacultyStudentProject;
use App\Models\Shared\AuditLog;
use App\Models\User\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class FacultyAdminWorkflowServiceTest extends TestCase
{
    use RefreshDatabase;

    private FacultyAdminWorkflowServiceInterface $service;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = app(FacultyAdminWorkflowServiceInterface::class);
        $this->user = User::factory()->create(['role_slug' => 'editor']);
    }

    public function test_faculty_write_events_create_audit_entries(): void
    {
        $faculty = Faculty::query()->create([
            'slug' => 'medicine',
            'public_slug' => 'medicine',
            'faculty_scope_slug' => 'medicine',
            'sort_order' => 1,
            'is_enabled' => true,
        ]);

        $this->assertTrue($this->service->recordFacultyCreated((int) $faculty->getKey(), (int) $this->user->getKey()));

        $faculty->forceFill(['sort_order' => 2])->save();

        $this->assertTrue($this->service->recordFacultyUpdated((int) $faculty->getKey(), (int) $this->user->getKey(), [
            'slug' => 'medicine',
            'public_slug' => 'medicine',
            'faculty_scope_slug' => 'medicine',
            'sort_order' => 1,
            'is_enabled' => true,
        ]));
        $this->assertTrue($this->service->deleteFaculty((int) $faculty->getKey(), (int) $this->user->getKey()));
        $this->assertSoftDeleted('faculties', ['id' => $faculty->getKey()]);
        $this->assertDatabaseHas(AuditLog::class, ['action' => 'faculty.created']);
        $this->assertDatabaseHas(AuditLog::class, ['action' => 'faculty.updated']);
        $this->assertDatabaseHas(AuditLog::class, ['action' => 'faculty.deleted']);
    }

    public function test_faculty_page_write_events_create_audit_entries(): void
    {
        $faculty = $this->faculty();
        $page = FacultyPage::query()->create([
            'faculty_id' => $faculty->getKey(),
            'slug' => 'overview',
            'kind' => 'overview',
            'sort_order' => 1,
            'is_enabled' => true,
        ]);

        $this->assertTrue($this->service->recordFacultyPageCreated((int) $page->getKey(), (int) $this->user->getKey()));

        $page->forceFill(['sort_order' => 2])->save();

        $this->assertTrue($this->service->recordFacultyPageUpdated((int) $page->getKey(), (int) $this->user->getKey(), [
            'faculty_id' => $faculty->getKey(),
            'slug' => 'overview',
            'kind' => 'overview',
            'sort_order' => 1,
            'is_enabled' => true,
        ]));
        $this->assertTrue($this->service->deleteFacultyPage((int) $page->getKey(), (int) $this->user->getKey()));
        $this->assertSoftDeleted('faculty_pages', ['id' => $page->getKey()]);
        $this->assertDatabaseHas(AuditLog::class, ['action' => 'faculty.page.created']);
        $this->assertDatabaseHas(AuditLog::class, ['action' => 'faculty.page.updated']);
        $this->assertDatabaseHas(AuditLog::class, ['action' => 'faculty.page.deleted']);
    }

    public function test_faculty_highlight_write_events_create_audit_entries(): void
    {
        $faculty = $this->faculty();
        $highlight = FacultyHighlight::query()->create([
            'faculty_id' => $faculty->getKey(),
            'key' => 'labs',
            'value' => '12',
            'sort_order' => 1,
            'is_enabled' => true,
        ]);

        $this->assertTrue($this->service->recordFacultyHighlightCreated((int) $highlight->getKey(), (int) $this->user->getKey()));

        $highlight->forceFill(['value' => '14'])->save();

        $this->assertTrue($this->service->recordFacultyHighlightUpdated((int) $highlight->getKey(), (int) $this->user->getKey(), [
            'faculty_id' => $faculty->getKey(),
            'key' => 'labs',
            'value' => '12',
            'url' => null,
            'sort_order' => 1,
            'is_enabled' => true,
        ]));
        $this->assertTrue($this->service->deleteFacultyHighlight((int) $highlight->getKey(), (int) $this->user->getKey()));
        $this->assertSoftDeleted('faculty_highlights', ['id' => $highlight->getKey()]);
        $this->assertDatabaseHas(AuditLog::class, ['action' => 'faculty.highlight.created']);
        $this->assertDatabaseHas(AuditLog::class, ['action' => 'faculty.highlight.updated']);
        $this->assertDatabaseHas(AuditLog::class, ['action' => 'faculty.highlight.deleted']);
    }

    public function test_faculty_options_are_scoped_for_faculty_editors(): void
    {
        $medicine = $this->facultyWithSlug('medicine');
        $this->facultyWithSlug('pharmacy');
        $facultyEditor = User::factory()->create([
            'role_slug' => 'faculty_editor',
            'faculty_scope_slug' => 'medicine',
        ]);

        $this->assertSame([
            $medicine->getKey() => 'medicine',
        ], $this->service->facultyOptionsForCurrentUser((int) $facultyEditor->getKey()));
    }

    public function test_faculty_lab_write_events_create_audit_entries(): void
    {
        $faculty = $this->faculty();
        $lab = FacultyLab::query()->create(['faculty_id' => $faculty->getKey(), 'slug' => 'ai-lab', 'sort_order' => 1, 'is_enabled' => true]);

        $this->assertTrue($this->service->recordFacultyLabCreated((int) $lab->getKey(), (int) $this->user->getKey()));
        $lab->forceFill(['sort_order' => 2])->save();
        $this->assertTrue($this->service->recordFacultyLabUpdated((int) $lab->getKey(), (int) $this->user->getKey(), ['faculty_id' => $faculty->getKey(), 'slug' => 'ai-lab', 'image' => null, 'sort_order' => 1, 'is_enabled' => true]));
        $this->assertTrue($this->service->deleteFacultyLab((int) $lab->getKey(), (int) $this->user->getKey()));
        $this->assertSoftDeleted('faculty_labs', ['id' => $lab->getKey()]);
        $this->assertDatabaseHas(AuditLog::class, ['action' => 'faculty.lab.created']);
        $this->assertDatabaseHas(AuditLog::class, ['action' => 'faculty.lab.updated']);
        $this->assertDatabaseHas(AuditLog::class, ['action' => 'faculty.lab.deleted']);
    }

    public function test_faculty_project_write_events_create_audit_entries(): void
    {
        $faculty = $this->faculty();
        $project = FacultyStudentProject::query()->create(['faculty_id' => $faculty->getKey(), 'slug' => 'robotics', 'sort_order' => 1, 'is_enabled' => true]);

        $this->assertTrue($this->service->recordFacultyProjectCreated((int) $project->getKey(), (int) $this->user->getKey()));
        $project->forceFill(['sort_order' => 2])->save();
        $this->assertTrue($this->service->recordFacultyProjectUpdated((int) $project->getKey(), (int) $this->user->getKey(), ['faculty_id' => $faculty->getKey(), 'slug' => 'robotics', 'image' => null, 'sort_order' => 1, 'is_enabled' => true]));
        $this->assertTrue($this->service->deleteFacultyProject((int) $project->getKey(), (int) $this->user->getKey()));
        $this->assertSoftDeleted('faculty_student_projects', ['id' => $project->getKey()]);
        $this->assertDatabaseHas(AuditLog::class, ['action' => 'faculty.project.created']);
        $this->assertDatabaseHas(AuditLog::class, ['action' => 'faculty.project.updated']);
        $this->assertDatabaseHas(AuditLog::class, ['action' => 'faculty.project.deleted']);
    }

    public function test_alumni_write_events_create_audit_entries(): void
    {
        $faculty = $this->faculty();
        $alumni = Alumni::query()->create(['student_identifier' => 'A-1', 'faculty_id' => $faculty->getKey(), 'graduation_year' => 2025, 'is_enabled' => true]);

        $this->assertTrue($this->service->recordAlumniCreated((int) $alumni->getKey(), (int) $this->user->getKey()));
        $alumni->forceFill(['graduation_year' => 2026])->save();
        $this->assertTrue($this->service->recordAlumniUpdated((int) $alumni->getKey(), (int) $this->user->getKey(), ['student_identifier' => 'A-1', 'faculty_id' => $faculty->getKey(), 'department_id' => null, 'degree' => null, 'graduation_year' => 2025, 'is_featured' => false, 'is_enabled' => true]));
        $this->assertTrue($this->service->deleteAlumni((int) $alumni->getKey(), (int) $this->user->getKey()));
        $this->assertSoftDeleted('alumni', ['id' => $alumni->getKey()]);
        $this->assertDatabaseHas(AuditLog::class, ['action' => 'faculty.alumni.created']);
        $this->assertDatabaseHas(AuditLog::class, ['action' => 'faculty.alumni.updated']);
        $this->assertDatabaseHas(AuditLog::class, ['action' => 'faculty.alumni.deleted']);
    }

    public function test_honor_student_write_events_create_audit_entries(): void
    {
        $faculty = $this->faculty();
        $student = HonorStudent::query()->create(['student_identifier' => 'H-1', 'faculty_id' => $faculty->getKey(), 'academic_year' => '2025-2026', 'sort_order' => 1, 'is_enabled' => true]);

        $this->assertTrue($this->service->recordHonorStudentCreated((int) $student->getKey(), (int) $this->user->getKey()));
        $student->forceFill(['sort_order' => 2])->save();
        $this->assertTrue($this->service->recordHonorStudentUpdated((int) $student->getKey(), (int) $this->user->getKey(), ['student_identifier' => 'H-1', 'faculty_id' => $faculty->getKey(), 'department_id' => null, 'academic_year' => '2025-2026', 'gpa' => null, 'sort_order' => 1, 'is_enabled' => true]));
        $this->assertTrue($this->service->deleteHonorStudent((int) $student->getKey(), (int) $this->user->getKey()));
        $this->assertSoftDeleted('honor_students', ['id' => $student->getKey()]);
        $this->assertDatabaseHas(AuditLog::class, ['action' => 'faculty.honor_student.created']);
        $this->assertDatabaseHas(AuditLog::class, ['action' => 'faculty.honor_student.updated']);
        $this->assertDatabaseHas(AuditLog::class, ['action' => 'faculty.honor_student.deleted']);
    }

    private function faculty(): Faculty
    {
        return $this->facultyWithSlug('pharmacy');
    }

    private function facultyWithSlug(string $slug): Faculty
    {
        return Faculty::query()->create([
            'slug' => $slug,
            'public_slug' => $slug,
            'faculty_scope_slug' => $slug,
            'sort_order' => 1,
            'is_enabled' => true,
        ]);
    }
}
