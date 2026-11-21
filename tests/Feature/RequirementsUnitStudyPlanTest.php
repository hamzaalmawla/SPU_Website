<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Contracts\Center\CenterCatalogServiceInterface;
use App\Contracts\Cms\CmsWorkflowServiceInterface;
use App\Contracts\Faculty\FacultyStudyPlanImportServiceInterface;
use App\Contracts\Faculty\FacultyStudyPlanUniversityElectivesServiceInterface;
use App\Contracts\Page\FacultyPageServiceInterface;
use App\Models\Cms\CmsDraft;
use App\Models\User\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * A Requirements Unit course is one record: a faculty study plan shows the unit
 * course's own data, a required unit course listing a faculty appears in that
 * faculty's plan by itself, and university electives are three boxes that open
 * the unit's elective list.
 */
final class RequirementsUnitStudyPlanTest extends TestCase
{
    use RefreshDatabase;

    private const PLAN = 'facilities.dentistry.study_plan';

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);
        $this->admin = User::query()->where('role_slug', 'super_admin')->firstOrFail();
    }

    public function test_a_linked_university_requirement_shows_the_unit_course_record(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('media/arabic-1.pdf', '%PDF-1.4');
        $this->publishUnitCourses(function (array $courses): array {
            foreach ($courses as $index => $course) {
                if ($course['id'] === 'arabic-language') {
                    $courses[$index]['descriptionEn'] = 'Reading and writing Arabic, taught by the unit.';
                    $courses[$index]['lessons'] = [['type' => 'lecture', 'titleAr' => 'المحاضرة الأولى', 'titleEn' => 'First lecture', 'pdfUrl' => '/storage/media/arabic-1.pdf']];
                    $courses[$index]['hours'] = ['theory' => 3, 'practical' => 0];
                }
            }

            return $courses;
        });
        $this->publishPlan([
            ['id' => 'unre103', 'code' => 'UNRE103', 'titleAr' => 'لغة عربية قديمة', 'titleEn' => 'Old Arabic title', 'type' => 'university', 'required' => true, 'credits' => 2, 'prerequisites' => [], 'hours' => ['theory' => 2, 'practical' => 0], 'lessons' => []],
        ]);

        $html = (string) $this->get('/en/faculties/dentistry/study-plan')->assertOk()->getContent();
        $this->assertStringContainsString('Reading and writing Arabic, taught by the unit.', $html);
        $this->assertStringContainsString('First lecture', $html);
        $this->assertStringNotContainsString('Old Arabic title', $html);
        // The faculty's own code stays.
        $this->assertStringContainsString('"code":"UNRE103"', $html);

        $departmentId = app(FacultyPageServiceInterface::class)->getEditablePayload(self::PLAN)['translations']['en']['payload']['plan']['departments'][0]['id'];
        $course = (string) $this->get('/en/faculties/dentistry/study-plan/course?department='.$departmentId.'&course=unre103')->assertOk()->getContent();
        $this->assertStringContainsString('First lecture', $course);
        $this->assertStringContainsString('/storage/media/arabic-1.pdf', $course);
    }

    public function test_a_required_unit_course_listing_a_faculty_appears_in_its_study_plan(): void
    {
        $this->publishUnitCourses(fn (array $courses): array => [
            ...$courses,
            $this->unitCourse('research-skills', 'Research Skills', 'مهارات البحث', true, [['faculty' => 'dentistry', 'code' => 'UNRE150', 'term' => 2]]),
            $this->unitCourse('photography', 'Photography', 'التصوير', false, [['faculty' => 'dentistry', 'code' => 'UNRE151']]),
            $this->unitCourse('law-ethics', 'Law and Ethics', 'القانون والأخلاق', true, [['faculty' => 'medicine', 'code' => 'REQU150', 'term' => 2]]),
        ]);

        $department = app(FacultyPageServiceInterface::class)->getEditablePayload(self::PLAN)['translations']['en']['payload']['plan']['departments'][0];
        $shown = app(CenterCatalogServiceInterface::class)->studyPlanDepartment('dentistry', $department, 'en');
        $added = $this->courseIn($shown, 'unre150');

        $this->assertIsArray($added);
        $this->assertSame('Research Skills', $added['title']);
        $this->assertTrue($added['fromRequirementsUnit']);
        $this->assertSame(2, $this->termOf($shown, 'unre150'));
        // An elective, or a course of another faculty, is not added.
        $this->assertNull($this->courseIn($shown, 'unre151'));
        $this->assertNull($this->courseIn($shown, 'requ150'));
        // A course already in the plan is not added twice.
        $arabic = array_filter($this->courses($shown), static fn (array $course): bool => ($course['requirementsUnitCourseId'] ?? '') === 'arabic-language');
        $this->assertCount(1, $arabic);

        $html = (string) $this->get('/ar/faculties/dentistry/study-plan')->assertOk()->getContent();
        $this->assertStringContainsString('مهارات البحث', $html);
        $this->assertStringContainsString('\\/ar\\/centers\\/requirements-unit\\/required-courses#course-research-skills', $html);
    }

    public function test_listed_university_electives_become_three_boxes_in_every_program(): void
    {
        $service = app(FacultyStudyPlanUniversityElectivesServiceInterface::class);
        $pages = app(FacultyPageServiceInterface::class);

        // Medicine lists six electives in term 1 and one in term 3.
        $this->importPlan('medicine');
        $result = $service->replaceElectives($pages->getEditablePayload('facilities.medicine.study_plan'));
        foreach (['ar', 'en'] as $locale) {
            $department = $result['payload']['translations'][$locale]['payload']['plan']['departments'][0];
            $electives = array_filter($this->courses($department), static fn (array $course): bool => ($course['type'] ?? '') === 'university' && ($course['required'] ?? true) === false);

            $this->assertCount(3, $electives);
            foreach ($electives as $box) {
                $this->assertTrue($box['electiveSlot']);
                $this->assertSame([], $box['prerequisites']);
            }
            $this->assertSame([1, 1, 3], array_map(fn (array $box): int => (int) $this->termOf($department, (string) $box['id']), array_values($electives)));
        }
        $this->assertCount(7, $result['programs'][0]['removed']);

        // Running it again changes nothing.
        $this->assertSame([], $service->replaceElectives($result['payload'])['programs']);

        // Petroleum lists none: its boxes go to the term with the most university requirements.
        $this->importPlan('petroleum');
        $petroleum = $service->replaceElectives($pages->getEditablePayload('facilities.petroleum.study_plan'))['payload'];
        $department = $petroleum['translations']['en']['payload']['plan']['departments'][0];
        $boxes = array_filter($this->courses($department), static fn (array $course): bool => ($course['electiveSlot'] ?? false) === true);
        $this->assertCount(3, $boxes);
    }

    public function test_the_command_saves_drafts_only_on_write_and_publishes_on_request(): void
    {
        $this->importPlan('dentistry');
        $drafts = CmsDraft::query()->where('target_key', self::PLAN)->count();
        $this->artisan('faculty:university-elective-boxes', ['--faculty' => 'dentistry'])
            ->expectsOutputToContain('dry run')
            ->assertSuccessful();
        $this->assertSame($drafts, CmsDraft::query()->where('target_key', self::PLAN)->count());

        $this->artisan('faculty:university-elective-boxes', ['--faculty' => 'dentistry', '--write' => true])->assertFailed();

        $this->artisan('faculty:university-elective-boxes', ['--faculty' => 'dentistry', '--write' => true, '--publish' => true, '--user' => (string) $this->admin->getKey()])
            ->expectsOutputToContain('Published: dentistry.')
            ->assertSuccessful();

        $published = app(CmsWorkflowServiceInterface::class)->getPublishedPayload(self::PLAN);
        $department = $published['translations']['en']['payload']['plan']['departments'][0];
        $electives = array_filter($this->courses($department), static fn (array $course): bool => ($course['type'] ?? '') === 'university' && ($course['required'] ?? true) === false);
        $this->assertCount(3, $electives);
        $this->assertSame(3, count(array_filter($electives, static fn (array $course): bool => ($course['electiveSlot'] ?? false) === true)));

        $html = (string) $this->get('/en/faculties/dentistry/study-plan')->assertOk()->getContent();
        $this->assertSame(3, substr_count($html, 'data-course-card data-elective-slot'));
    }

    /** Publishes the transcribed plan the release ships for a faculty. */
    private function importPlan(string $faculty): void
    {
        $source = json_decode((string) file_get_contents(database_path('seeders/Data/study_plans/'.$faculty.'.json')), true, 512, JSON_THROW_ON_ERROR);
        $result = app(FacultyStudyPlanImportServiceInterface::class)->import($faculty, $source, (string) $this->admin->getKey(), true, true, true);
        $this->assertTrue($result->published, $faculty.' did not import: '.json_encode($result->errors));
    }

    /** @param callable(list<array<string, mixed>>): list<array<string, mixed>> $change */
    private function publishUnitCourses(callable $change): void
    {
        $payload = app(CenterCatalogServiceInterface::class)->defaultPayload('centers.requirements-unit.courses');
        $payload['courses'] = $change($payload['courses']);
        $workflow = app(CmsWorkflowServiceInterface::class);
        $workflow->saveDraft('centers.requirements-unit.courses', $payload, (int) $this->admin->getKey());
        $this->assertTrue($workflow->publish('centers.requirements-unit.courses', (int) $this->admin->getKey()));
    }

    /** @param list<array<string, mixed>> $firstTermCourses */
    private function publishPlan(array $firstTermCourses): void
    {
        $payload = app(FacultyPageServiceInterface::class)->getEditablePayload(self::PLAN);
        foreach (['ar', 'en'] as $locale) {
            $payload['translations'][$locale]['payload']['plan']['departments'][0]['terms'][0]['courses'] = $firstTermCourses;
        }
        $workflow = app(CmsWorkflowServiceInterface::class);
        $workflow->saveDraft(self::PLAN, $payload, (int) $this->admin->getKey());
        $workflow->publish(self::PLAN, (int) $this->admin->getKey());
    }

    /**
     * @param  list<array<string, mixed>>  $faculties
     * @return array<string, mixed>
     */
    private function unitCourse(string $id, string $titleEn, string $titleAr, bool $required, array $faculties): array
    {
        return [
            'id' => $id, 'code' => '', 'titleAr' => $titleAr, 'titleEn' => $titleEn, 'descriptionAr' => '', 'descriptionEn' => '',
            'required' => $required, 'credits' => 2, 'hours' => ['theory' => 2, 'practical' => 0], 'prerequisites' => [],
            'faculties' => $faculties,
            'teachers' => ['theory' => ['memberIds' => [], 'enabled' => true], 'practical' => ['memberIds' => [], 'enabled' => true]],
            'specificationUrl' => '', 'lessons' => [],
        ];
    }

    /**
     * @param  array<string, mixed>  $department
     * @return list<array<string, mixed>>
     */
    private function courses(array $department): array
    {
        $courses = [];
        foreach ($department['terms'] as $term) {
            foreach ($term['courses'] as $course) {
                $courses[] = $course;
            }
        }

        return $courses;
    }

    /**
     * @param  array<string, mixed>  $department
     * @return array<string, mixed>|null
     */
    private function courseIn(array $department, string $id): ?array
    {
        foreach ($this->courses($department) as $course) {
            if (($course['id'] ?? null) === $id) {
                return $course;
            }
        }

        return null;
    }

    /** @param array<string, mixed> $department */
    private function termOf(array $department, string $id): ?int
    {
        foreach ($department['terms'] as $term) {
            foreach ($term['courses'] as $course) {
                if (($course['id'] ?? null) === $id) {
                    return (int) $term['id'];
                }
            }
        }

        return null;
    }
}
