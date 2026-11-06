<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Contracts\Cms\CmsWorkflowServiceInterface;
use App\Contracts\Faculty\FacultyStudyPlanBoardServiceInterface;
use App\Contracts\Faculty\FacultyStudyPlanImportServiceInterface;
use App\Contracts\Faculty\LegacyCourseSpecificationImportServiceInterface;
use App\Models\User\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class LegacyCourseSpecificationImportTest extends TestCase
{
    use RefreshDatabase;

    private const TARGET = 'facilities.dentistry.study_plan';

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);
        $this->user = User::query()->where('role_slug', 'super_admin')->firstOrFail();
    }

    public function test_specifications_are_attached_by_arabic_title_and_published(): void
    {
        $this->publishPlan();

        $result = $this->importer()->import($this->source(), (string) $this->user->getKey(), true);

        $this->assertSame(['attached', 'attached', 'no_match'], array_column($result->rows, 'status'));
        $this->assertSame(['dentistry'], $result->published);

        $courses = $this->courses('ar');
        // "الإحصاء الحيوي" on the old site is "إحصاء حيوي" in the plan.
        $this->assertSame('/downloads/files/1783452129_2.pdf', $courses['stats']['specificationUrl']);
        // Matched through an alias.
        $this->assertSame('/downloads/files/1774716510_1.pdf', $courses['acc']['specificationUrl']);
        $this->assertSame('/downloads/files/1774716510_1.pdf', $this->courses('en')['acc']['specificationUrl']);
        // No lecture files come with it.
        $this->assertSame([], $courses['acc']['lessons']);

        $this->get('/ar/faculties/dentistry/study-plan/course?department=one&course=stats')
            ->assertOk()
            ->assertSee('توصيف المقرر')
            ->assertSee('/downloads/files/1783452129_2.pdf', false);
        $this->get('/en/faculties/dentistry/study-plan/course?department=one&course=other')
            ->assertOk()
            ->assertDontSee('Course specification');
    }

    public function test_a_course_that_already_has_a_specification_keeps_it(): void
    {
        $this->publishPlan();
        $this->importer()->import($this->source(), (string) $this->user->getKey(), true);

        $source = $this->source();
        $source['courses'][0]['specification'] = '/downloads/files/999_new.pdf';
        $result = $this->importer()->import($source, (string) $this->user->getKey(), true);

        $this->assertSame(['unchanged', 'unchanged', 'no_match'], array_column($result->rows, 'status'));
        $this->assertSame([], $result->published);
        $this->assertSame('/downloads/files/1783452129_2.pdf', $this->courses('ar')['stats']['specificationUrl']);
    }

    public function test_a_faculty_without_a_published_plan_is_skipped(): void
    {
        $result = $this->importer()->import($this->source(), (string) $this->user->getKey(), true);

        $this->assertSame(['no_plan', 'no_plan', 'no_plan'], array_column($result->rows, 'status'));
        $this->assertNull(app(CmsWorkflowServiceInterface::class)->getPublishedPayload(self::TARGET));
    }

    public function test_the_course_form_keeps_the_specification(): void
    {
        $this->publishPlan();
        $board = app(FacultyStudyPlanBoardServiceInterface::class);
        $payload = app(CmsWorkflowServiceInterface::class)->getPublishedPayload(self::TARGET);
        $this->assertIsArray($payload);

        $form = $board->courseForm($payload, 'one', 'stats');
        $this->assertSame('', $form['specificationUrl']);

        $payload = $board->saveCourse($payload, 'one', [...$form, 'specificationUrl' => '/storage/media/document/2026/10/spec.pdf']);
        $this->assertSame('/storage/media/document/2026/10/spec.pdf', $board->courseForm($payload, 'one', 'stats')['specificationUrl']);
    }

    public function test_the_command_dry_run_changes_nothing(): void
    {
        $this->publishPlan();

        $this->artisan('faculty:import-course-specifications', ['--user' => (string) $this->user->getKey()])
            ->expectsOutputToContain('Dry run')
            ->assertSuccessful();

        $this->assertArrayNotHasKey('specificationUrl', $this->courses('ar')['stats']);
    }

    private function publishPlan(): void
    {
        $course = static fn (string $id, string $titleEn, string $titleAr): array => [
            'id' => $id, 'code' => strtoupper($id), 'titleEn' => $titleEn, 'titleAr' => $titleAr, 'term' => 1,
            'type' => 'specialization', 'required' => true, 'forced' => false, 'credits' => 3, 'prerequisites' => [],
            'prerequisiteCredits' => 0, 'instructor' => null, 'hours' => ['theory' => 2, 'practical' => 2],
            'descriptionEn' => '', 'descriptionAr' => '', 'lessons' => [],
        ];

        $result = app(FacultyStudyPlanImportServiceInterface::class)->import('dentistry', ['departments' => [[
            'id' => 'one', 'nameEn' => 'Dept One', 'nameAr' => 'القسم الأول', 'totalCredits' => 9,
            'terms' => [['id' => 1, 'labelEn' => '1', 'labelAr' => '1', 'courses' => [
                $course('stats', 'Biostatistics', 'إحصاء حيوي'),
                $course('acc', 'Principles of Accounting 1', 'مبادئ المحاسبة1'),
                $course('other', 'Other', 'مقرر آخر'),
            ]]],
        ]]], (string) $this->user->getKey(), true, false, true);

        $this->assertTrue($result->published);
    }

    /** @return array<string, mixed> */
    private function source(): array
    {
        return ['courses' => [
            ['faculty' => 'dentistry', 'titleAr' => 'الإحصاء الحيوي', 'matchTitles' => ['الإحصاء الحيوي'], 'specification' => '/downloads/files/1783452129_2.pdf'],
            ['faculty' => 'dentistry', 'titleAr' => 'محاسبة (1)', 'matchTitles' => ['محاسبة (1)', 'مبادئ المحاسبة1'], 'specification' => '/downloads/files/1774716510_1.pdf'],
            ['faculty' => 'dentistry', 'titleAr' => 'تنمية الموارد البشرية', 'matchTitles' => ['تنمية الموارد البشرية'], 'specification' => '/downloads/files/1771825718_BAHR510.pdf'],
        ]];
    }

    /** @return array<string, array<string, mixed>> */
    private function courses(string $locale): array
    {
        $payload = app(CmsWorkflowServiceInterface::class)->getPublishedPayload(self::TARGET);
        $courses = [];

        foreach ($payload['translations'][$locale]['payload']['plan']['departments'][0]['terms'] as $term) {
            foreach ($term['courses'] as $course) {
                $courses[$course['id']] = $course;
            }
        }

        return $courses;
    }

    private function importer(): LegacyCourseSpecificationImportServiceInterface
    {
        return app(LegacyCourseSpecificationImportServiceInterface::class);
    }
}
