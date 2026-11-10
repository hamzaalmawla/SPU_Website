<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Contracts\Cms\CmsWorkflowServiceInterface;
use App\Contracts\Faculty\FacultyStudyPlanLinkServiceInterface;
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
 * Every faculty has an academic programs page: each department offers programs
 * (bachelor's, master's, ...), and each program, not the department, has its study
 * plan. Until an editor publishes the page it lists the study plan programs, each in
 * the department that teaches it, and the master's program. A program may have a
 * director (مدير البرنامج) from the faculty's own staff.
 */
final class FacultyProgramsTest extends TestCase
{
    use RefreshDatabase;

    private const TARGET = 'facilities.artificial-intelligence.programs';

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);
        $this->admin = User::query()->where('role_slug', 'super_admin')->firstOrFail();
    }

    public function test_before_it_is_published_the_page_lists_the_study_plan_programs_and_the_masters_program(): void
    {
        $this->publishAiDepartment();
        $page = app(FacultyPageServiceInterface::class)->getSubpage('artificial-intelligence', 'programs', 'en');
        $this->assertNotNull($page);

        $levels = array_count_values(array_column($page->items, 'level'));
        $this->assertSame(count(app(FacultyStudyPlanLinkServiceInterface::class)->programNames('artificial-intelligence', 'en')), $levels['bachelor'] ?? 0);
        $this->assertSame(1, $levels['master'] ?? 0);

        $bachelor = collect($page->items)->firstWhere('id', 'bachelor-ai');
        $this->assertNotNull($bachelor);
        $this->assertSame('/en/faculties/artificial-intelligence/study-plan?department=ai', $bachelor['studyPlanUrl']);

        $master = collect($page->items)->firstWhere('level', 'master');
        $this->assertSame('/en/faculties/artificial-intelligence/master-program', $master['pageUrl']);
        $this->assertNull($master['studyPlanUrl']);
        // The master's program sits in the department that teaches the AI program, as the bachelor's does.
        $this->assertSame('ai-engineering-test', $bachelor['department']['slug'] ?? null);
        $this->assertSame($bachelor['department'], $master['department']);

        $this->get('/en/faculties/artificial-intelligence/programs')
            ->assertOk()
            ->assertSee("Bachelor's Programs")
            ->assertSee("Master's Programs")
            ->assertSee('Study Plan')
            ->assertSee('Program Page')
            ->assertSee('href="/en/faculties/artificial-intelligence/master-program"', false);

        $this->get('/ar/faculties/artificial-intelligence/programs')
            ->assertOk()
            ->assertSee('برامج الإجازة')
            ->assertSee('برامج الماجستير')
            ->assertSee('الخطة الدراسية');

        // A faculty without a master's program page has bachelor programs only, and the page is in its navigation.
        $medicine = app(FacultyPageServiceInterface::class)->getSubpage('medicine', 'programs', 'ar');
        $this->assertNotNull($medicine);
        $this->assertNotContains('master', array_column($medicine->items, 'level'));
        $this->assertContains('programs', $medicine->navigation->pluck('slug')->all());
        $this->assertSame('البرامج الأكاديمية', $medicine->navigation->firstWhere('slug', 'programs')?->label);
    }

    public function test_a_published_programs_page_follows_the_rules_and_replaces_the_default(): void
    {
        $this->publishAiDepartment();
        $workflow = app(CmsWorkflowServiceInterface::class);
        $payload = app(FacultyPageServiceInterface::class)->getEditablePayload(self::TARGET);
        $departmentSlug = (string) collect($payload['translations']['en']['programs'])->firstWhere('id', 'bachelor-ai')['departmentSlug'];
        $this->assertSame('ai-engineering-test', $departmentSlug);

        foreach (['ar' => ['دكتوراه في الذكاء الاصطناعي', 'إجازة في الذكاء الاصطناعي'], 'en' => ['Doctorate in AI', 'Bachelor in AI']] as $locale => [$phd, $bachelor]) {
            $payload['translations'][$locale]['programs'] = [
                ['id' => 'ai-bachelor', 'level' => 'bachelor', 'title' => $bachelor, 'summary' => '', 'duration' => '', 'departmentSlug' => $departmentSlug, 'studyPlanProgramId' => 'ai'],
                ['id' => 'ai-phd', 'level' => 'phd', 'title' => $phd, 'summary' => '', 'duration' => '', 'departmentSlug' => $departmentSlug, 'studyPlanProgramId' => null],
            ];
        }

        $ready = $workflow->readiness(self::TARGET, $payload);
        $this->assertTrue($ready->isReady, json_encode($ready->errors, JSON_UNESCAPED_UNICODE) ?: '');

        $bad = $payload;
        $bad['translations']['ar']['programs'][0]['studyPlanProgramId'] = 'no-such-program';
        $bad['translations']['ar']['programs'][1]['departmentSlug'] = 'no-such-department';
        $bad['translations']['ar']['programs'][1]['level'] = 'nursery';
        $errors = $workflow->readiness(self::TARGET, $bad)->errors;
        $this->assertContains('A program\'s study plan must be a program of the faculty\'s published study plan.', $errors['ar']);
        $this->assertContains('A program\'s department must be one of this faculty\'s departments.', $errors['ar']);
        $this->assertContains('Every program needs a level: bachelor, master, doctorate or diploma.', $errors['ar']);
        $this->assertArrayHasKey('translations', $errors);

        $workflow->saveDraft(self::TARGET, $payload, (int) $this->admin->getKey());
        $this->assertTrue($workflow->publish(self::TARGET, (int) $this->admin->getKey()));

        $page = app(FacultyPageServiceInterface::class)->getSubpage('artificial-intelligence', 'programs', 'en');
        $this->assertSame(['ai-bachelor', 'ai-phd'], array_column($page?->items ?? [], 'id'));
        $this->assertSame(['bachelor', 'phd'], array_column($page?->detail['levels'] ?? [], 'level'));

        // The department lists the published programs.
        $departments = app(FacultyPageServiceInterface::class)->getSubpage('artificial-intelligence', 'departments', 'en');
        $department = collect($departments?->items ?? [])->firstWhere('slug', $departmentSlug);
        $this->assertSame(['Bachelor in AI', 'Doctorate in AI'], array_column($department['programs'] ?? [], 'title'));
    }

    public function test_the_admin_edits_programs_once_for_both_languages(): void
    {
        $this->actingAs($this->admin, 'web');

        $component = Livewire::test(ManageArtificialIntelligenceFaculty::class)
            ->set('data.target_key', self::TARGET)
            ->call('loadTarget', self::TARGET)
            ->assertSee('البرامج الأكاديمية')
            ->assertSee('الدرجة');

        $rows = $component->get('data.program_rows');
        $this->assertCount(count(app(FacultyStudyPlanLinkServiceInterface::class)->programNames('artificial-intelligence', 'en')) + 1, $rows);

        $component
            ->set('data.program_rows', [
                'row-1' => ['id' => 'ai-diploma', 'level' => 'diploma', 'departmentSlug' => null, 'studyPlanProgramId' => null, 'titleAr' => 'دبلوم الذكاء الاصطناعي', 'titleEn' => 'AI Diploma', 'durationAr' => 'سنة', 'durationEn' => 'One year', 'summaryAr' => '', 'summaryEn' => ''],
            ])
            ->call('save')
            ->assertHasNoErrors();

        $payload = app(CmsWorkflowServiceInterface::class)->latestEditableDraftPayload(self::TARGET, (int) $this->admin->getKey());
        $this->assertSame('دبلوم الذكاء الاصطناعي', $payload['translations']['ar']['programs'][0]['title'] ?? null);
        $this->assertSame('AI Diploma', $payload['translations']['en']['programs'][0]['title'] ?? null);
        $this->assertSame('diploma', $payload['translations']['en']['programs'][0]['level'] ?? null);
        $this->assertSame('One year', $payload['translations']['en']['programs'][0]['duration'] ?? null);
    }

    public function test_a_program_has_a_director_from_the_facultys_own_staff(): void
    {
        $this->publishAiDepartment();
        $director = $this->member('ai-program-director', 'Dr. Program Director', 'د. مدير البرنامج', 'artificial-intelligence');
        $outsider = $this->member('medicine-not-a-director', 'Dr. Outsider', 'د. من خارج الكلية', 'medicine');
        $workflow = app(CmsWorkflowServiceInterface::class);
        $payload = app(FacultyPageServiceInterface::class)->getEditablePayload(self::TARGET);

        foreach (['ar' => 'إجازة في الذكاء الاصطناعي', 'en' => 'Bachelor in AI'] as $locale => $title) {
            $payload['translations'][$locale]['programs'] = [
                ['id' => 'ai-bachelor', 'level' => 'bachelor', 'title' => $title, 'summary' => '', 'duration' => '', 'departmentSlug' => 'ai-engineering-test', 'studyPlanProgramId' => 'ai', 'managerMemberId' => (int) $director->getKey()],
            ];
        }

        $outside = $payload;
        $outside['translations']['ar']['programs'][0]['managerMemberId'] = (int) $outsider->getKey();
        $outside['translations']['en']['programs'][0]['managerMemberId'] = (int) $outsider->getKey();
        $this->assertContains('A program director must be one of this faculty\'s teaching staff.', $workflow->readiness(self::TARGET, $outside)->errors['ar'] ?? []);

        $mismatch = $payload;
        $mismatch['translations']['en']['programs'][0]['managerMemberId'] = null;
        $this->assertArrayHasKey('translations', $workflow->readiness(self::TARGET, $mismatch)->errors);

        $workflow->saveDraft(self::TARGET, $payload, (int) $this->admin->getKey());
        $this->assertTrue($workflow->publish(self::TARGET, (int) $this->admin->getKey()));

        $this->get('/en/faculties/artificial-intelligence/programs')
            ->assertOk()
            ->assertSee('Program Director')
            ->assertSee('Dr. Program Director')
            ->assertSee('href="/en/about/profile/ai-program-director"', false);
        $this->get('/ar/faculties/artificial-intelligence/departments')
            ->assertOk()
            ->assertSee('مدير البرنامج')
            ->assertSee('د. مدير البرنامج');

        // The director's profile names the program among the positions held.
        $this->get('/ar/about/profile/ai-program-director')->assertOk()->assertSee('مدير برنامج إجازة في الذكاء الاصطناعي');
        $this->get('/en/about/profile/ai-program-director')->assertOk()->assertSee('Program Director, Bachelor in AI');
    }

    public function test_the_admin_picks_a_program_director(): void
    {
        $director = $this->member('ai-program-director', 'Dr. Program Director', 'د. مدير البرنامج', 'artificial-intelligence');
        $this->actingAs($this->admin, 'web');

        Livewire::test(ManageArtificialIntelligenceFaculty::class)
            ->set('data.target_key', self::TARGET)
            ->call('loadTarget', self::TARGET)
            ->assertSee('مدير البرنامج')
            ->set('data.program_rows', [
                'row-1' => ['id' => 'ai-diploma', 'level' => 'diploma', 'departmentSlug' => null, 'studyPlanProgramId' => null, 'managerMemberId' => (string) $director->getKey(), 'titleAr' => 'دبلوم الذكاء الاصطناعي', 'titleEn' => 'AI Diploma', 'durationAr' => '', 'durationEn' => '', 'summaryAr' => '', 'summaryEn' => ''],
            ])
            ->call('save')
            ->assertHasNoErrors();

        $payload = app(CmsWorkflowServiceInterface::class)->latestEditableDraftPayload(self::TARGET, (int) $this->admin->getKey());
        $this->assertSame((int) $director->getKey(), $payload['translations']['ar']['programs'][0]['managerMemberId'] ?? null);
        $this->assertSame((int) $director->getKey(), $payload['translations']['en']['programs'][0]['managerMemberId'] ?? null);
    }

    private function member(string $slug, string $en, string $ar, string $facultySlug): FacultyMember
    {
        $member = FacultyMember::query()->create([
            'slug' => $slug,
            'faculty_id' => (int) Faculty::query()->where('public_slug', $facultySlug)->value('id'),
            'is_enabled' => true,
            'publication_status' => PublicationStatus::Published->value,
            'published_at' => now()->subDay(),
        ]);
        $member->translations()->create(['locale' => 'en', 'full_name' => $en]);
        $member->translations()->create(['locale' => 'ar', 'full_name' => $ar]);

        return $member;
    }

    /** Publishes the AI departments page with the department that teaches the `ai` study plan program. */
    private function publishAiDepartment(): void
    {
        $target = 'facilities.artificial-intelligence.departments';
        $workflow = app(CmsWorkflowServiceInterface::class);
        $payload = app(FacultyPageServiceInterface::class)->getEditablePayload($target);

        foreach (['ar' => 'قسم هندسة الذكاء الاصطناعي', 'en' => 'Department of AI Engineering'] as $locale => $title) {
            $payload['translations'][$locale]['items'] = [
                ['slug' => 'ai-basic-sciences-test', 'title' => $locale === 'ar' ? 'قسم العلوم الأساسية' : 'Basic Sciences', 'summary' => ''],
                ['slug' => 'ai-engineering-test', 'title' => $title, 'summary' => '', 'type' => 'specialization', 'studyPlanDepartmentId' => 'ai'],
            ];
        }

        $workflow->saveDraft($target, $payload, (int) $this->admin->getKey());
        $this->assertTrue($workflow->publish($target, (int) $this->admin->getKey()));
    }
}
