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

    public function test_each_course_keeps_one_specification_that_survives_edits_and_can_be_removed(): void
    {
        $payload = $this->service->setSpecification($this->payload(), 'cs', 'cs-101', '/storage/media/document/spec-1.pdf');
        $payload = $this->service->setSpecification($payload, 'cs', 'cs-101', '/storage/media/document/spec-2.pdf');

        foreach (['ar', 'en'] as $locale) {
            $course = $payload['translations'][$locale]['payload']['plan']['departments'][0]['terms'][0]['courses'][0];
            $this->assertSame('/storage/media/document/spec-2.pdf', $course['specificationUrl']);
        }

        $payload = $this->service->saveCourse($payload, 'cs', $this->form($payload, 'cs-101', ['titleEn' => 'Renamed']));
        $payload = $this->service->moveCourse($payload, 'cs', 'cs-101', '2');
        $this->assertSame('/storage/media/document/spec-2.pdf', $this->service->courseForm($payload, 'cs', 'cs-101')['specificationUrl']);

        $payload = $this->service->saveCourse($payload, 'cs', $this->form($payload, 'cs-101', ['specificationUrl' => '']));

        $this->assertSame('', $this->service->courseForm($payload, 'cs', 'cs-101')['specificationUrl']);
        foreach ($payload['translations']['en']['payload']['plan']['departments'][0]['terms'] as $term) {
            foreach ($term['courses'] as $course) {
                $this->assertArrayNotHasKey('specificationUrl', $course);
            }
        }
    }

    public function test_a_shared_course_is_one_course_in_every_department_that_has_it(): void
    {
        $payload = $this->sharedPayload();

        $form = $this->form($payload, 'cs-101', ['titleEn' => 'Programming I', 'credits' => 4, 'theoryTeacherIds' => ['7']]);
        $this->assertSame(['is' => '2'], $form['placements']);

        $payload = $this->service->saveCourse($payload, 'cs', $form);

        foreach (['en', 'ar'] as $locale) {
            $is = $this->courseOf($payload, $locale, 'is', 'cs-101');
            $this->assertSame('Programming I', $is['titleEn']);
            $this->assertSame(4, $is['credits']);
            $this->assertSame([7], $is['teachers']['theory']['memberIds']);
            // Its place in the other department stays as it was.
            $this->assertSame(2, $is['term']);
        }

        // Editing from the other department changes the first one too.
        $other = $this->service->courseForm($payload, 'is', 'cs-101');
        $this->assertSame(['cs' => '1'], $other['placements']);
        $payload = $this->service->saveCourse($payload, 'is', [...$other, 'titleAr' => 'برمجة ١']);
        $this->assertSame('برمجة ١', $this->courseOf($payload, 'ar', 'cs', 'cs-101')['title']);
        $this->assertSame(1, $this->courseOf($payload, 'en', 'cs', 'cs-101')['term']);

        $board = $this->service->board($payload, 'en', 'cs');
        $this->assertSame(['Information Systems'], $board['terms'][0]['courses'][0]['sharedIn']);
        $this->assertSame([], $board['terms'][1]['courses'][0]['sharedIn']);
        $this->assertSame([['id' => '1', 'label' => '1'], ['id' => '2', 'label' => '2']], $board['departments'][1]['terms']);
    }

    public function test_a_course_can_be_added_to_and_removed_from_other_departments(): void
    {
        $payload = $this->sharedPayload();

        $payload = $this->service->saveCourse($payload, 'cs', $this->form($payload, 'cs-201', ['placements' => ['is' => '1']]));
        foreach (['en', 'ar'] as $locale) {
            $copy = $this->courseOf($payload, $locale, 'is', 'cs-201');
            $this->assertSame(1, $copy['term']);
            $this->assertSame('Data Structures', $copy['titleEn']);
            // Its prerequisite is a course there too, so the link comes along.
            $this->assertSame(['cs-101'], $copy['prerequisites']);
        }

        $payload = $this->service->saveCourse($payload, 'cs', $this->form($payload, 'cs-101', ['placements' => ['is' => '']]));
        $this->assertNull($this->courseOf($payload, 'en', 'is', 'cs-101'));
        $this->assertNotNull($this->courseOf($payload, 'en', 'cs', 'cs-101'));
        // The other department no longer has the course, so nothing there may need it.
        $this->assertSame([], $this->courseOf($payload, 'ar', 'is', 'cs-201')['prerequisites']);
        $this->assertSame(['cs-101'], $this->courseOf($payload, 'ar', 'cs', 'cs-201')['prerequisites']);
    }

    public function test_a_new_course_can_be_placed_in_several_departments(): void
    {
        $payload = $this->sharedPayload();
        $form = [...$this->service->emptyCourseForm('2'), 'code' => 'GEN-1', 'titleEn' => 'English', 'titleAr' => 'اللغة الإنكليزية', 'placements' => ['is' => '1']];

        $payload = $this->service->saveCourse($payload, 'cs', $form);

        foreach (['en', 'ar'] as $locale) {
            $this->assertSame(2, $this->courseOf($payload, $locale, 'cs', 'gen-1')['term']);
            $this->assertSame(1, $this->courseOf($payload, $locale, 'is', 'gen-1')['term']);
        }

        $this->expectException(InvalidArgumentException::class);
        $this->service->saveCourse($payload, 'cs', [...$form, 'code' => 'GEN-2', 'placements' => ['is' => '9']]);
    }

    public function test_moving_and_files_keep_the_shared_copies(): void
    {
        $payload = $this->sharedPayload();

        $payload = $this->service->moveCourse($payload, 'cs', 'cs-101', '2');
        $this->assertSame(2, $this->courseOf($payload, 'en', 'cs', 'cs-101')['term']);
        $this->assertSame(2, $this->courseOf($payload, 'en', 'is', 'cs-101')['term']);

        $payload = $this->service->addLessons($payload, 'is', 'cs-101', [['titleAr' => 'م١', 'titleEn' => 'L1', 'pdfUrl' => '/a.pdf']]);
        $payload = $this->service->setSpecification($payload, 'is', 'cs-101', '/spec.pdf');

        foreach (['cs', 'is'] as $department) {
            $course = $this->courseOf($payload, 'en', $department, 'cs-101');
            $this->assertSame(['L1'], array_column($course['lessons'], 'title'));
            $this->assertSame('/spec.pdf', $course['specificationUrl']);
        }
    }

    public function test_deleting_a_shared_course_removes_only_this_departments_copy(): void
    {
        $payload = $this->sharedPayload();

        $payload = $this->service->deleteCourse($payload, 'is', 'cs-101');

        $this->assertNull($this->courseOf($payload, 'en', 'is', 'cs-101'));
        $this->assertSame(['cs-101'], $this->courseOf($payload, 'en', 'cs', 'cs-201')['prerequisites']);

        $payload = $this->service->removeDepartment($this->sharedPayload(), 'is');
        $this->assertSame(['cs-101'], $this->courseOf($payload, 'en', 'cs', 'cs-201')['prerequisites']);
    }

    public function test_a_university_elective_box_needs_no_code_and_drops_every_prerequisite_link(): void
    {
        $payload = $this->payload();
        $form = [...$this->service->emptyCourseForm('2'), 'code' => '', 'titleAr' => '', 'titleEn' => '', 'credits' => 2,
            'type' => 'university', 'required' => false, 'electiveSlot' => true, 'prerequisites' => ['cs-101'], 'prerequisiteCredits' => 30];

        $payload = $this->service->saveCourse($payload, 'cs', $form);
        $box = $this->courseOf($payload, 'en', 'cs', 'university-elective');

        $this->assertIsArray($box);
        $this->assertTrue($box['electiveSlot']);
        $this->assertSame('', $box['code']);
        $this->assertSame('University elective course', $box['titleEn']);
        $this->assertSame('مقرر جامعة اختياري', $this->courseOf($payload, 'ar', 'cs', 'university-elective')['titleAr']);
        $this->assertSame([], $box['prerequisites']);
        $this->assertSame(0, $box['prerequisiteCredits']);

        $board = $this->service->board($payload, 'en', 'cs');
        $this->assertTrue($board['terms'][1]['courses'][1]['electiveSlot']);
        $this->assertArrayNotHasKey('university-elective', $board['prerequisiteOptions']);
        $this->assertTrue($this->service->courseForm($payload, 'cs', 'university-elective')['electiveSlot']);
    }

    public function test_turning_a_course_into_an_elective_box_removes_links_to_it(): void
    {
        $payload = $this->service->saveCourse($this->payload(), 'cs', $this->form($this->payload(), 'cs-101', [
            'type' => 'university', 'required' => false, 'electiveSlot' => true,
        ]));

        $this->assertTrue($this->courseOf($payload, 'en', 'cs', 'cs-101')['electiveSlot']);
        $this->assertSame([], $this->courseOf($payload, 'en', 'cs', 'cs-201')['prerequisites']);
        $this->assertSame([], $this->courseOf($payload, 'ar', 'cs', 'cs-201')['prerequisites']);

        // Made required again, it is an ordinary course and needs its code back.
        $payload = $this->service->saveCourse($payload, 'cs', $this->form($payload, 'cs-101', ['required' => true, 'code' => 'CS-101']));
        $this->assertArrayNotHasKey('electiveSlot', $this->courseOf($payload, 'en', 'cs', 'cs-101'));
        $this->assertSame('CS-101', $this->courseOf($payload, 'en', 'cs', 'cs-101')['code']);
    }

    public function test_an_elective_university_requirement_is_always_an_elective_box(): void
    {
        // University electives are picked at the Requirements Unit: the plan never lists one.
        $payload = $this->service->saveCourse($this->payload(), 'cs', $this->form($this->payload(), 'cs-101', [
            'type' => 'university', 'required' => false, 'electiveSlot' => false,
        ]));

        $course = $this->courseOf($payload, 'en', 'cs', 'cs-101');
        $this->assertTrue($course['electiveSlot']);
        $this->assertSame('', $course['code']);
        $this->assertSame([], $this->courseOf($payload, 'en', 'cs', 'cs-201')['prerequisites']);
    }

    public function test_only_an_elective_university_requirement_can_be_an_elective_box(): void
    {
        foreach ([['type' => 'faculty', 'required' => false], ['type' => 'university', 'required' => true]] as $override) {
            $payload = $this->service->saveCourse($this->payload(), 'cs', $this->form($this->payload(), 'cs-101', [...$override, 'electiveSlot' => true]));

            $this->assertArrayNotHasKey('electiveSlot', $this->courseOf($payload, 'en', 'cs', 'cs-101'));
            $this->assertSame(['cs-101'], $this->courseOf($payload, 'en', 'cs', 'cs-201')['prerequisites']);
        }

        $this->expectException(InvalidArgumentException::class);
        $this->service->saveCourse($this->payload(), 'cs', [...$this->service->emptyCourseForm('1'), 'code' => '', 'titleAr' => 'أ', 'titleEn' => 'A',
            'type' => 'university', 'required' => true, 'electiveSlot' => true]);
    }

    /** @return array<string, mixed>|null */
    private function courseOf(array $payload, string $locale, string $departmentId, string $courseId): ?array
    {
        foreach ($payload['translations'][$locale]['payload']['plan']['departments'] as $department) {
            if ($department['id'] !== $departmentId) {
                continue;
            }

            foreach ($department['terms'] as $term) {
                foreach ($term['courses'] as $course) {
                    if ($course['id'] === $courseId) {
                        return $course;
                    }
                }
            }
        }

        return null;
    }

    /**
     * The test plan with a second department, Information Systems, that shares cs-101 in its
     * second term and has its own course needing it.
     *
     * @return array<string, mixed>
     */
    private function sharedPayload(): array
    {
        $payload = $this->payload();

        foreach (['en', 'ar'] as $locale) {
            $cs = $payload['translations'][$locale]['payload']['plan']['departments'][0];
            $shared = [...$cs['terms'][0]['courses'][0], 'term' => 2, 'row' => 1];
            $own = [...$cs['terms'][1]['courses'][0], 'id' => 'is-301', 'code' => 'IS-301', 'term' => 2, 'row' => 2];
            $payload['translations'][$locale]['payload']['plan']['departments'][] = [
                'id' => 'is', 'nameEn' => 'Information Systems', 'nameAr' => 'نظم المعلومات',
                'name' => $locale === 'ar' ? 'نظم المعلومات' : 'Information Systems', 'totalCredits' => 6,
                'terms' => [
                    ['id' => 1, 'labelEn' => '1', 'labelAr' => '1', 'label' => '1', 'courses' => []],
                    ['id' => 2, 'labelEn' => '2', 'labelAr' => '2', 'label' => '2', 'courses' => [$shared, $own]],
                ],
                'electivePools' => [], 'promotionRequirements' => [],
            ];
        }

        return $payload;
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
