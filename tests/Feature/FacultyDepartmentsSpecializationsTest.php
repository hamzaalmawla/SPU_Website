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
 * Basic Sciences among them, each with a head from the faculty's own staff). A
 * department is a specialization, which links to its study plan program, or a
 * section, which has no study plan and shows no study plan button. A staff
 * member has a department and, optionally, the study plan program they teach in.
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

    public function test_the_editor_sets_a_section_with_a_head_and_a_specialization_with_its_study_plan(): void
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
                    'type' => 'section',
                    'headMemberId' => (int) $head->getKey(),
                    'studyPlanDepartmentId' => null,
                ],
                [
                    'slug' => 'ai-data-science',
                    'title' => $isAr ? 'قسم هندسة الذكاء الاصطناعي وعلوم البيانات' : 'Department of AI Engineering and Data Science',
                    'summary' => '',
                    'type' => 'specialization',
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
            ->assertSee('Section')
            ->assertSee('Specialization')
            ->assertSee('Head of Department')
            ->assertSee('Dr. Yasser Head')
            ->assertSee('/en/about/profile/basic-sciences-head-test', false)
            ->assertSee('href="/en/faculties/artificial-intelligence/study-plan?department=ai"', false);

        $page = app(FacultyPageServiceInterface::class)->getSubpage('artificial-intelligence', 'departments', 'en');
        $this->assertNotNull($page);
        $items = collect($page->items)->keyBy('slug');
        $this->assertSame('section', $items['ai-basic-sciences']['type']);
        $this->assertSame('specialization', $items['ai-data-science']['type']);
        // Basic Sciences is a section: it has no study plan, so it shows no study plan button.
        $this->assertNull($items['ai-basic-sciences']['studyPlanUrl']);
        $this->assertSame('Dr. Yasser Head', $items['ai-basic-sciences']['head']['name']);
        $this->assertSame('/en/faculties/artificial-intelligence/study-plan?department=ai', $items['ai-data-science']['studyPlanUrl']);
        $this->assertArrayNotHasKey('specializations', $page->detail);

        $this->get('/ar/faculties/artificial-intelligence/departments')
            ->assertOk()
            ->assertSee('اختصاص')
            ->assertSee('رئيس القسم')
            ->assertSee('د. ياسر الرئيس');
    }

    public function test_a_department_follows_the_type_and_head_rules(): void
    {
        $faculty = Faculty::query()->where('public_slug', 'artificial-intelligence')->firstOrFail();
        $other = Faculty::query()->where('public_slug', 'medicine')->firstOrFail();
        $ownStaff = $this->member('ai-own-staff', 'Dr. Own Staff', 'د. من الكلية', (int) $faculty->getKey());
        $otherStaff = $this->member('medicine-other-staff', 'Dr. Other Faculty', 'د. من كلية أخرى', (int) $other->getKey());
        $workflow = app(CmsWorkflowServiceInterface::class);
        $payload = app(FacultyPageServiceInterface::class)->getEditablePayload(self::TARGET);
        $withItem = function (array $item) use ($payload): array {
            foreach (['ar', 'en'] as $locale) {
                $payload['translations'][$locale]['items'] = [['slug' => 'rules-test', 'title' => 'Rules test '.$locale, ...$item]];
            }

            return $payload;
        };

        $this->assertTrue($workflow->readiness(self::TARGET, $withItem(['type' => 'section', 'headMemberId' => (int) $ownStaff->getKey()]))->isReady);
        $this->assertTrue($workflow->readiness(self::TARGET, $withItem(['type' => 'section']))->isReady, 'The head is optional.');

        $otherHead = $workflow->readiness(self::TARGET, $withItem(['type' => 'section', 'headMemberId' => (int) $otherStaff->getKey()]));
        $this->assertFalse($otherHead->isReady);
        $this->assertContains('A department head must be one of this faculty\'s academic staff.', $otherHead->errors['ar']);

        $noPlan = $workflow->readiness(self::TARGET, $withItem(['type' => 'specialization', 'studyPlanDepartmentId' => null]));
        $this->assertContains('A specialization must link to its study plan program.', $noPlan->errors['en']);

        $sectionWithPlan = $workflow->readiness(self::TARGET, $withItem(['type' => 'section', 'studyPlanDepartmentId' => 'ai']));
        $this->assertContains('A section has no study plan.', $sectionWithPlan->errors['ar']);

        $mismatch = $withItem(['type' => 'section', 'headMemberId' => (int) $ownStaff->getKey()]);
        $mismatch['translations']['en']['items'][0]['headMemberId'] = null;
        $this->assertArrayHasKey('translations', $workflow->readiness(self::TARGET, $mismatch)->errors);
    }

    public function test_the_admin_editor_has_the_type_field_and_offers_only_this_facultys_staff_as_head(): void
    {
        $faculty = Faculty::query()->where('public_slug', 'artificial-intelligence')->firstOrFail();
        $other = Faculty::query()->where('public_slug', 'medicine')->firstOrFail();
        $candidate = $this->member('ai-head-candidate', 'Dr. Head Candidate', 'د. مرشح للرئاسة', (int) $faculty->getKey());
        $outsider = $this->member('medicine-not-a-candidate', 'Dr. Not A Candidate', 'د. ليس من الكلية', (int) $other->getKey());
        $this->actingAs($this->admin, 'web');

        $component = Livewire::test(ManageArtificialIntelligenceFaculty::class)
            ->set('data.target_key', self::TARGET)
            ->call('loadTarget', self::TARGET)
            ->assertSee('النوع')
            ->assertSee('اختصاص (له خطة دراسية)')
            ->assertSee('رئيس القسم')
            ->assertDontSee('كليات أخرى');

        $options = (fn (): array => $this->facultyStaffOptions())->call($component->instance());
        $this->assertArrayHasKey((int) $candidate->getKey(), $options);
        $this->assertArrayNotHasKey((int) $outsider->getKey(), $options);
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
