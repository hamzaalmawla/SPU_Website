<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\Faculty\FacultyStudyPlanBoardService;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class FacultyStudyPlanBoardServiceTest extends TestCase
{
    private FacultyStudyPlanBoardService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = new FacultyStudyPlanBoardService;
    }

    public function test_board_lists_courses_by_term_and_warns_about_backward_prerequisites(): void
    {
        $payload = $this->payload();
        $payload = $this->service->saveCourse($payload, 'cs', $this->form($payload, 'cs-101', ['prerequisites' => ['cs-201']]));

        $board = $this->service->board($payload, 'en', 'cs');

        $this->assertSame('cs', $board['departmentId']);
        $this->assertSame(['1', '2'], array_column($board['terms'], 'id'));
        $this->assertSame('Programming', $board['terms'][0]['courses'][0]['title']);
        $this->assertTrue($board['terms'][0]['courses'][0]['warning']);
        $this->assertNotSame([], $board['warnings']);
        $this->assertSame(6, $board['department']['calculatedCredits']);
        $this->assertSame('CS-201 - Data Structures', $board['prerequisiteOptions']['cs-201']);
    }

    public function test_edges_that_were_already_broken_are_reported(): void
    {
        $payload = $this->payload();
        $this->assertSame([], $this->service->brokenPrerequisiteEdges($payload));

        foreach (['en', 'ar'] as $locale) {
            $payload['translations'][$locale]['payload']['plan']['departments'][0]['terms'][1]['courses'][0]['prerequisites'][] = 'gone';
        }

        $this->assertSame(['cs-201|gone'], $this->service->brokenPrerequisiteEdges($payload));
    }

    public function test_board_is_localized(): void
    {
        $board = $this->service->board($this->payload(), 'ar', 'cs');

        $this->assertSame('البرمجة', $board['terms'][0]['courses'][0]['title']);
        $this->assertSame('علوم الحاسوب', $board['departments'][0]['name']);
    }

    public function test_new_course_gets_a_unique_id_and_is_added_to_both_languages(): void
    {
        $payload = $this->payload();
        $form = $this->service->emptyCourseForm('2');
        $form = [...$form, 'code' => 'CS-101', 'titleEn' => 'Networks', 'titleAr' => 'الشبكات', 'credits' => 4.5, 'prerequisites' => ['cs-101']];

        $payload = $this->service->saveCourse($payload, 'cs', $form);

        foreach (['en', 'ar'] as $locale) {
            $courses = $payload['translations'][$locale]['payload']['plan']['departments'][0]['terms'][1]['courses'];
            $this->assertSame(['cs-201', 'cs-101-2'], array_column($courses, 'id'));
            $this->assertSame(4.5, $courses[1]['credits']);
            $this->assertSame(['cs-101'], $courses[1]['prerequisites']);
            $this->assertSame(2, $courses[1]['row']);
        }

        $this->assertSame('Networks', $payload['translations']['en']['payload']['plan']['departments'][0]['terms'][1]['courses'][1]['title']);
        $this->assertSame('الشبكات', $payload['translations']['ar']['payload']['plan']['departments'][0]['terms'][1]['courses'][1]['title']);
    }

    public function test_editing_keeps_position_and_unrelated_fields(): void
    {
        $payload = $this->payload();
        $payload = $this->service->saveCourse($payload, 'cs', $this->form($payload, 'cs-101', ['titleEn' => 'Intro Programming']));

        $course = $payload['translations']['en']['payload']['plan']['departments'][0]['terms'][0]['courses'][0];
        $this->assertSame('Intro Programming', $course['title']);
        $this->assertSame('Intro Programming', $course['titleEn']);
        $this->assertSame('البرمجة', $course['titleAr']);
        $this->assertSame(['name' => 'kept'], $course['instructor']);
    }

    public function test_moving_a_course_changes_its_term_in_both_languages(): void
    {
        $payload = $this->service->moveCourse($this->payload(), 'cs', 'cs-101', '2');

        foreach (['en', 'ar'] as $locale) {
            $terms = $payload['translations'][$locale]['payload']['plan']['departments'][0]['terms'];
            $this->assertSame([], $terms[0]['courses']);
            $this->assertSame(['cs-201', 'cs-101'], array_column($terms[1]['courses'], 'id'));
            $this->assertSame(2, $terms[1]['courses'][1]['term']);
        }
    }

    public function test_deleting_a_course_removes_it_from_other_prerequisites(): void
    {
        $payload = $this->service->deleteCourse($this->payload(), 'cs', 'cs-101');

        $remaining = $payload['translations']['en']['payload']['plan']['departments'][0]['terms'][1]['courses'][0];
        $this->assertSame('cs-201', $remaining['id']);
        $this->assertSame([], $remaining['prerequisites']);
    }

    public function test_invalid_courses_are_rejected(): void
    {
        $payload = $this->payload();
        $base = $this->form($payload, 'cs-101');

        foreach ([
            ['code' => ''],
            ['titleAr' => ''],
            ['credits' => 'abc'],
            ['termId' => '99'],
            ['prerequisites' => ['cs-101']],
            ['prerequisites' => ['nope']],
        ] as $override) {
            try {
                $this->service->saveCourse($payload, 'cs', [...$base, ...$override]);
                $this->fail('Expected rejection for '.json_encode($override));
            } catch (InvalidArgumentException) {
                $this->addToAssertionCount(1);
            }
        }
    }

    public function test_terms_can_be_added_and_only_empty_terms_removed(): void
    {
        $payload = $this->service->addTerm($this->payload(), 'cs');
        $this->assertSame(['1', '2', '3'], array_map('strval', array_column($payload['translations']['ar']['payload']['plan']['departments'][0]['terms'], 'id')));

        $payload = $this->service->removeTerm($payload, 'cs', '3');
        $this->assertCount(2, $payload['translations']['en']['payload']['plan']['departments'][0]['terms']);

        $this->expectException(InvalidArgumentException::class);
        $this->service->removeTerm($payload, 'cs', '1');
    }

    public function test_departments_can_be_renamed_added_and_removed(): void
    {
        $payload = $this->service->saveDepartment($this->payload(), 'cs', ['nameEn' => 'Computing', 'nameAr' => 'الحوسبة', 'totalCredits' => '180']);
        $this->assertSame('Computing', $payload['translations']['en']['payload']['plan']['departments'][0]['name']);
        $this->assertSame('الحوسبة', $payload['translations']['ar']['payload']['plan']['departments'][0]['name']);
        $this->assertSame(180, $payload['translations']['en']['payload']['plan']['departments'][0]['totalCredits']);

        $payload = $this->service->addDepartment($payload, ['nameEn' => 'Data Science', 'nameAr' => 'علم البيانات']);
        $departments = $payload['translations']['en']['payload']['plan']['departments'];
        $this->assertSame('data-science', $departments[1]['id']);
        $this->assertSame(['1', '2'], array_map('strval', array_column($departments[1]['terms'], 'id')));

        $payload = $this->service->removeDepartment($payload, 'cs');
        $this->assertSame(['data-science'], array_column($payload['translations']['ar']['payload']['plan']['departments'], 'id'));

        $this->expectException(InvalidArgumentException::class);
        $this->service->removeDepartment($payload, 'data-science');
    }

    public function test_lessons_are_added_with_unique_ids_and_bilingual_titles(): void
    {
        $payload = $this->service->addLessons($this->payload(), 'cs', 'cs-101', [
            ['titleAr' => 'المحاضرة 1', 'titleEn' => 'Lecture 1', 'pdfUrl' => '/storage/media/document/a.pdf'],
            ['titleAr' => 'المحاضرة 2', 'titleEn' => 'Lecture 2', 'pdfUrl' => '/storage/media/document/b.pdf'],
        ]);

        $lessons = $payload['translations']['ar']['payload']['plan']['departments'][0]['terms'][0]['courses'][0]['lessons'];
        $this->assertCount(2, $lessons);
        $this->assertSame(['cs-101-lecture-1', 'cs-101-lecture-2'], array_column($lessons, 'id'));
        $this->assertSame([1, 2], array_column($lessons, 'order'));
        $this->assertSame('المحاضرة 1', $lessons[0]['title']);
        $this->assertSame('Lecture 2', $payload['translations']['en']['payload']['plan']['departments'][0]['terms'][0]['courses'][0]['lessons'][1]['title']);

        $form = $this->service->courseForm($payload, 'cs', 'cs-101');
        $this->assertSame('/storage/media/document/b.pdf', $form['lessons'][1]['pdfUrl']);
    }

    public function test_lessons_can_be_removed_and_renamed_through_the_course_form(): void
    {
        $payload = $this->service->addLessons($this->payload(), 'cs', 'cs-101', [
            ['titleAr' => 'أ', 'titleEn' => 'A', 'pdfUrl' => '/storage/a.pdf'],
            ['titleAr' => 'ب', 'titleEn' => 'B', 'pdfUrl' => '/storage/b.pdf'],
        ]);
        $form = $this->service->courseForm($payload, 'cs', 'cs-101');
        $form['lessons'] = [[...$form['lessons'][1], 'titleEn' => 'B renamed']];

        $payload = $this->service->saveCourse($payload, 'cs', $form);

        $lessons = $payload['translations']['en']['payload']['plan']['departments'][0]['terms'][0]['courses'][0]['lessons'];
        $this->assertCount(1, $lessons);
        $this->assertSame('B renamed', $lessons[0]['title']);
        $this->assertSame(1, $lessons[0]['order']);
        $this->assertSame('/storage/b.pdf', $lessons[0]['pdfUrl']);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  array<string, mixed>  $override
     * @return array<string, mixed>
     */
    private function form(array $payload, string $courseId, array $override = []): array
    {
        $form = $this->service->courseForm($payload, 'cs', $courseId);
        $this->assertIsArray($form);

        return [...$form, ...$override];
    }

    /** @return array<string, mixed> */
    private function payload(): array
    {
        $course = static fn (string $id, string $code, string $en, string $ar, int $term, array $prerequisites, string $locale): array => [
            'id' => $id, 'code' => $code, 'titleEn' => $en, 'titleAr' => $ar, 'title' => $locale === 'ar' ? $ar : $en,
            'descriptionEn' => '', 'descriptionAr' => '', 'description' => '', 'term' => $term, 'row' => 1,
            'type' => 'specialization', 'required' => true, 'forced' => false, 'credits' => 3,
            'prerequisites' => $prerequisites, 'prerequisiteCredits' => 0, 'instructor' => ['name' => 'kept'],
            'hours' => ['theory' => 2, 'practical' => 2], 'lessons' => [],
        ];
        $department = static fn (string $locale): array => [
            'id' => 'cs', 'nameEn' => 'Computer Science', 'nameAr' => 'علوم الحاسوب',
            'name' => $locale === 'ar' ? 'علوم الحاسوب' : 'Computer Science', 'totalCredits' => 6,
            'terms' => [
                ['id' => 1, 'labelEn' => '1', 'labelAr' => '1', 'label' => '1', 'courses' => [
                    $course('cs-101', 'CS-101', 'Programming', 'البرمجة', 1, [], $locale),
                ]],
                ['id' => 2, 'labelEn' => '2', 'labelAr' => '2', 'label' => '2', 'courses' => [
                    $course('cs-201', 'CS-201', 'Data Structures', 'هياكل البيانات', 2, ['cs-101'], $locale),
                ]],
            ],
            'electivePools' => [], 'promotionRequirements' => [],
        ];

        return ['translations' => [
            'ar' => ['payload' => ['plan' => ['departments' => [$department('ar')]]]],
            'en' => ['payload' => ['plan' => ['departments' => [$department('en')]]]],
        ]];
    }
}
