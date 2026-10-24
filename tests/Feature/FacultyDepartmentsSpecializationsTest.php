<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Contracts\Cms\AboutEntityCmsServiceInterface;
use App\Contracts\Cms\CmsWorkflowServiceInterface;
use App\Contracts\Content\ProfileAdminServiceInterface;
use App\Contracts\Faculty\FacultyStudyPlanLinkServiceInterface;
use App\Contracts\Page\FacultyPageServiceInterface;
use App\DTOs\Cms\AboutEntityCmsDataDTO;
use App\Enums\PublicationStatus;
use App\Filament\Pages\ManageArtificialIntelligenceFaculty;
use App\Models\Faculty\Department;
use App\Models\Faculty\Faculty;
use App\Models\Person\FacultyMember;
use App\Models\User\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * A faculty's departments page lists its academic departments (every faculty,
 * Basic Sciences among them, each with a head). A department's study plan is
 * optional: one without a plan shows no study plan button. A staff member has a
 * department and, optionally, the study plan program they teach in.
 */
final class FacultyDepartmentsSpecializationsTest extends TestCase
{
    use RefreshDatabase;

    private const TARGET = 'facilities.artificial-intelligence.departments';

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);
        $this->admin = User::query()->where('role_slug', 'super_admin')->firstOrFail();
    }

    public function test_departments_without_a_study_plan_show_no_study_plan_button(): void
    {
        $page = app(FacultyPageServiceInterface::class)->getSubpage('medicine', 'departments', 'en');
        $this->assertNotNull($page);
        $this->assertNotSame([], $page->items);

        foreach ($page->items as $item) {
            $this->assertNull($item['studyPlanUrl']);
        }

        // There is no separate specializations list any more.
        $this->get('/en/faculties/artificial-intelligence/departments')
            ->assertOk()
            ->assertDontSee('id="specializations"', false)
            ->assertDontSee('Taught by:');
    }

    public function test_the_editor_sets_basic_sciences_a_head_and_an_optional_study_plan(): void
    {
        $faculty = Faculty::query()->where('public_slug', 'artificial-intelligence')->firstOrFail();
        $head = $this->member('basic-sciences-head-test', 'Dr. Yasser Head', 'د. ياسر الرئيس', (int) $faculty->getKey());
        $workflow = app(CmsWorkflowServiceInterface::class);
        $payload = app(FacultyPageServiceInterface::class)->getEditablePayload(self::TARGET);

        foreach (['ar', 'en'] as $locale) {
            $isAr = $locale === 'ar';
            $payload['translations'][$locale]['items'] = [
                [
                    'slug' => 'ai-basic-sciences',
                    'title' => $isAr ? 'قسم العلوم الأساسية' : 'Department of Basic Sciences',
                    'summary' => $isAr ? 'يدرّس مقررات السنوات المشتركة.' : 'Teaches the common-years courses.',
                    'kind' => 'basic_sciences',
                    'headMemberId' => (int) $head->getKey(),
                    'studyPlanDepartmentId' => null,
                ],
                [
                    'slug' => 'ai-data-science',
                    'title' => $isAr ? 'قسم هندسة الذكاء الاصطناعي وعلوم البيانات' : 'Department of AI Engineering and Data Science',
                    'summary' => '',
                    'kind' => 'academic',
                    'studyPlanDepartmentId' => 'ai',
                ],
            ];
        }

        $this->assertTrue($workflow->readiness(self::TARGET, $payload)->isReady);
        $workflow->saveDraft(self::TARGET, $payload, (int) $this->admin->getKey());
        $this->assertTrue($workflow->publish(self::TARGET, (int) $this->admin->getKey()));

        $this->get('/en/faculties/artificial-intelligence/departments')
            ->assertOk()
            ->assertSee('Department of Basic Sciences')
            ->assertSee('Basic Sciences')
            ->assertSee('Head of Department')
            ->assertSee('Dr. Yasser Head')
            ->assertSee('/en/about/profile/basic-sciences-head-test', false)
            ->assertSee('href="/en/faculties/artificial-intelligence/study-plan?department=ai"', false);

        $page = app(FacultyPageServiceInterface::class)->getSubpage('artificial-intelligence', 'departments', 'en');
        $this->assertNotNull($page);
        $items = collect($page->items)->keyBy('slug');
        $this->assertSame('basic_sciences', $items['ai-basic-sciences']['kind']);
        // Basic Sciences has no study plan, so it shows no study plan button.
        $this->assertNull($items['ai-basic-sciences']['studyPlanUrl']);
        $this->assertSame('Dr. Yasser Head', $items['ai-basic-sciences']['head']['name']);
        $this->assertSame('/en/faculties/artificial-intelligence/study-plan?department=ai', $items['ai-data-science']['studyPlanUrl']);
        $this->assertArrayNotHasKey('specializations', $page->detail);

        $this->get('/ar/faculties/artificial-intelligence/departments')
            ->assertOk()
            ->assertSee('علوم أساسية')
            ->assertSee('رئيس القسم')
            ->assertSee('د. ياسر الرئيس');
    }

    public function test_the_admin_editor_has_the_department_fields_and_an_optional_study_plan(): void
    {
        $this->actingAs($this->admin, 'web');

        Livewire::test(ManageArtificialIntelligenceFaculty::class)
            ->set('data.target_key', self::TARGET)
            ->call('loadTarget', self::TARGET)
            ->assertSee('نوع القسم')
            ->assertSee('رئيس القسم')
            ->assertSee('الخطة الدراسية (اختياري)');
    }

    public function test_a_staff_member_has_a_department_and_an_optional_specialization(): void
    {
        $faculty = Faculty::query()->where('public_slug', 'artificial-intelligence')->firstOrFail();
        $department = Department::query()->where('faculty_id', $faculty->getKey())->where('is_enabled', true)->orderBy('sort_order')->firstOrFail();
        $programName = app(FacultyStudyPlanLinkServiceInterface::class)->programNames('artificial-intelligence', 'en')['ai'] ?? null;
        $this->assertNotNull($programName);
        $this->assertArrayHasKey('ai', app(ProfileAdminServiceInterface::class)->specializationOptions((int) $faculty->getKey()));

        $entities = app(AboutEntityCmsServiceInterface::class);
        $workflow = app(CmsWorkflowServiceInterface::class);
        $payload = [
            'slug' => 'staff-with-specialization',
            'faculty_id' => (int) $faculty->getKey(),
            'department_id' => (int) $department->getKey(),
            'specialization_id' => null,
            'is_enabled' => true,
            'translations' => [
                ['locale' => 'ar', 'full_name' => 'د. سلمى المختصة', 'position' => 'مدرسة'],
                ['locale' => 'en', 'full_name' => 'Dr. Salma Specialist', 'position' => 'Lecturer'],
            ],
            'educations' => [],
        ];

        $prepared = $entities->prepareDraft(new AboutEntityCmsDataDTO('faculty-member', null, $payload), (int) $this->admin->getKey());
        $targetKey = (string) $prepared->targetKey;
        // The specialization is optional: none is fine, but it must be a program of the plan.
        $this->assertTrue($workflow->readiness($targetKey, $prepared->payload)->isReady);
        $readiness = $workflow->readiness($targetKey, [...$prepared->payload, 'specialization_id' => 'no-such-program']);
        $this->assertFalse($readiness->isReady);
        $this->assertArrayHasKey('specialization_id', $readiness->errors);

        $workflow->saveDraft($targetKey, [...$prepared->payload, 'specialization_id' => 'ai'], (int) $this->admin->getKey());
        $this->assertTrue($workflow->publish($targetKey, (int) $this->admin->getKey()));

        $member = FacultyMember::query()->findOrFail($prepared->entityId);
        $this->assertSame((int) $department->getKey(), (int) $member->department_id);
        $this->assertSame('ai', $member->specialization_id);

        $departmentName = (string) $department->translations()->where('locale', 'en')->value('name');
        $this->get('/en/about/profile/staff-with-specialization')
            ->assertOk()
            ->assertSee($departmentName)
            ->assertSee('Specialization: '.$programName);
    }

    private function member(string $slug, string $en, string $ar, int $facultyId): FacultyMember
    {
        $member = FacultyMember::query()->create([
            'slug' => $slug,
            'faculty_id' => $facultyId,
            'is_enabled' => true,
            'publication_status' => PublicationStatus::Published->value,
            'published_at' => now()->subDay(),
        ]);
        $member->translations()->create(['locale' => 'en', 'full_name' => $en]);
        $member->translations()->create(['locale' => 'ar', 'full_name' => $ar]);

        return $member;
    }
}
