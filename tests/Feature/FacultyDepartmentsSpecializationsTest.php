<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Contracts\Cms\CmsWorkflowServiceInterface;
use App\Contracts\Page\FacultyPageServiceInterface;
use App\Enums\PublicationStatus;
use App\Filament\Pages\ManageArtificialIntelligenceFaculty;
use App\Models\Faculty\Faculty;
use App\Models\Person\FacultyMember;
use App\Models\User\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * A faculty's departments page lists its academic departments (every faculty,
 * Basic Sciences among them, each with a head) apart from the specializations
 * a student enrolls in, which are the programs of its study plan.
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

    public function test_a_faculty_with_several_programs_lists_them_as_specializations_without_editor_input(): void
    {
        $this->get('/en/faculties/artificial-intelligence/departments')
            ->assertOk()
            ->assertSee('Specializations')
            ->assertSee('Academic Departments')
            ->assertSee('href="/en/faculties/artificial-intelligence/study-plan?department=ai"', false)
            ->assertSee('href="/en/faculties/artificial-intelligence/study-plan?department=se"', false);

        $this->get('/ar/faculties/artificial-intelligence/departments')
            ->assertOk()
            ->assertSee('الاختصاصات')
            ->assertSee('الأقسام العلمية');
    }

    public function test_a_single_program_faculty_shows_departments_only(): void
    {
        $this->get('/en/faculties/medicine/departments')
            ->assertOk()
            ->assertDontSee('id="specializations"', false)
            ->assertSee('href="/en/faculties/medicine/study-plan"', false);
    }

    public function test_the_editor_sets_basic_sciences_a_head_and_the_specializations(): void
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
                ],
                [
                    'slug' => 'ai-data-science',
                    'title' => $isAr ? 'قسم هندسة الذكاء الاصطناعي وعلوم البيانات' : 'Department of AI Engineering and Data Science',
                    'summary' => '',
                    'kind' => 'academic',
                    'studyPlanDepartmentId' => 'ai',
                ],
            ];
            $payload['translations'][$locale]['specializations'] = [[
                'slug' => 'spec-ai',
                'studyPlanDepartmentId' => 'ai',
                'title' => $isAr ? 'الذكاء الاصطناعي وعلوم البيانات' : 'AI and Data Science',
                'summary' => $isAr ? 'تعلم الآلة والبيانات.' : 'Machine learning and data.',
                'degrees' => '',
                'tags' => [],
            ]];
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
            ->assertSee('AI and Data Science')
            ->assertSee('Machine learning and data.')
            ->assertSee('Taught by:')
            ->assertSee('Teaches the specialization:')
            ->assertSee('href="/en/faculties/artificial-intelligence/study-plan?department=ai"', false)
            // Only the listed specialization is shown.
            ->assertDontSee('study-plan?department=se', false);

        $page = app(FacultyPageServiceInterface::class)->getSubpage('artificial-intelligence', 'departments', 'en');
        $this->assertNotNull($page);
        $basicSciences = collect($page->items)->firstWhere('slug', 'ai-basic-sciences');
        $this->assertSame('basic_sciences', $basicSciences['kind']);
        // Basic Sciences teaches no specialization, so it has no study plan link of its own.
        $this->assertNull($basicSciences['studyPlanUrl']);
        $this->assertSame('Dr. Yasser Head', $basicSciences['head']['name']);
        $this->assertSame(['spec-ai'], array_column($page->detail['specializations'], 'slug'));
        $this->assertSame('ai-data-science', $page->detail['specializations'][0]['department']['slug']);

        $this->get('/ar/faculties/artificial-intelligence/departments')
            ->assertOk()
            ->assertSee('علوم أساسية')
            ->assertSee('رئيس القسم')
            ->assertSee('د. ياسر الرئيس');
    }

    public function test_a_specialization_must_be_a_program_of_the_plan_in_both_languages(): void
    {
        $workflow = app(CmsWorkflowServiceInterface::class);
        $payload = app(FacultyPageServiceInterface::class)->getEditablePayload(self::TARGET);
        $payload['translations']['ar']['specializations'] = [['studyPlanDepartmentId' => 'no-such-program']];
        $payload['translations']['en']['specializations'] = [['studyPlanDepartmentId' => 'no-such-program']];

        $readiness = $workflow->readiness(self::TARGET, $payload);
        $this->assertFalse($readiness->isReady);
        $this->assertArrayHasKey('ar', $readiness->errors);

        $payload['translations']['ar']['specializations'] = [['studyPlanDepartmentId' => 'ai']];
        $payload['translations']['en']['specializations'] = [['studyPlanDepartmentId' => 'se']];

        $readiness = $workflow->readiness(self::TARGET, $payload);
        $this->assertFalse($readiness->isReady);
        $this->assertArrayHasKey('translations', $readiness->errors);
    }

    public function test_the_admin_editor_has_the_department_and_specialization_fields(): void
    {
        $this->actingAs($this->admin, 'web');

        Livewire::test(ManageArtificialIntelligenceFaculty::class)
            ->set('data.target_key', self::TARGET)
            ->call('loadTarget', self::TARGET)
            ->assertSee('الاختصاصات')
            ->assertSee('نوع القسم')
            ->assertSee('رئيس القسم');
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
