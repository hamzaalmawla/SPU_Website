<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Contracts\Faculty\FacultyStudyPlanEditorServiceInterface;
use Tests\TestCase;

final class FacultyStudyPlanEditorServiceTest extends TestCase
{
    public function test_paired_workspace_merges_both_locales_without_losing_unselected_or_unknown_data(): void
    {
        $service = app(FacultyStudyPlanEditorServiceInterface::class);
        $payload = $this->payload();
        $untouchedArabicTerm = $payload['translations']['ar']['payload']['plan']['departments'][0]['terms'][1];
        $untouchedDepartment = $payload['translations']['en']['payload']['plan']['departments'][1];
        $untouchedTerm = $payload['translations']['en']['payload']['plan']['departments'][0]['terms'][1];

        $workspace = $service->buildWorkspace($payload, 'department-one', 'term-one');

        $this->assertCount(2, $workspace['courses']);
        $this->assertSame('العنوان العربي الحالي', $workspace['courses'][0]['titleAr']);
        $this->assertSame('Current English title', $workspace['courses'][0]['titleEn']);
        $this->assertMatchesRegularExpression('/^promotion-[0-9a-z]{26}$/', $workspace['promotionRequirements'][0]['id']);

        $workspace['courses'][0]['code'] = 'EDIT101';
        $workspace['courses'][0]['titleAr'] = 'عنوان عربي محرر';
        $workspace['courses'][0]['titleEn'] = 'Edited English title';
        $workspace['courses'][0]['descriptionAr'] = 'وصف عربي محرر';
        $workspace['courses'][0]['descriptionEn'] = 'Edited English description';
        $workspace['courses'][1]['prerequisites'] = [];
        $workspace['electivePools'][0]['requiredHours'] = 9;
        $workspace['electivePools'][0]['descriptionAr'] = 'وصف اختياري';
        $workspace['electivePools'][0]['descriptionEn'] = 'Elective description';
        $workspace['promotionRequirements'][0]['requiredCredits'] = 75;
        $workspace['courses'][] = [
            'code' => 'NEW201',
            'credits' => 3,
            'type' => 'faculty',
            'required' => true,
            'prerequisites' => ['course-one'],
            'titleAr' => 'مقرر جديد',
            'titleEn' => 'New course',
            'descriptionAr' => '',
            'descriptionEn' => '',
            'instructor' => ['staffSlug' => '', 'nameAr' => '', 'nameEn' => ''],
            'lessons' => [[
                'order' => 1,
                'type' => 'lab',
                'pdfUrl' => '',
                'titleAr' => 'درس جديد',
                'titleEn' => 'New lesson',
                'descriptionAr' => '',
                'descriptionEn' => '',
            ]],
        ];

        $merged = $service->mergeWorkspace($payload, $workspace, 'department-one', 'term-one');
        $arDepartment = $merged['translations']['ar']['payload']['plan']['departments'][0];
        $enDepartment = $merged['translations']['en']['payload']['plan']['departments'][0];
        $arCourses = $arDepartment['terms'][0]['courses'];
        $enCourses = $enDepartment['terms'][0]['courses'];
        $newCourseId = $enCourses[2]['id'];

        $this->assertSame('course-one', $arCourses[0]['id']);
        $this->assertSame('course-one', $enCourses[0]['id']);
        $this->assertSame('EDIT101', $arCourses[0]['code']);
        $this->assertSame('EDIT101', $enCourses[0]['code']);
        $this->assertSame('عنوان عربي محرر', $arCourses[0]['title']);
        $this->assertSame('Edited English title', $enCourses[0]['title']);
        $this->assertSame('عنوان عربي محرر', $enCourses[0]['titleAr']);
        $this->assertSame('Edited English title', $arCourses[0]['titleEn']);
        $this->assertSame('keep-row', $enCourses[0]['futureField']);
        $this->assertSame(['theory' => 2, 'practical' => 1], $enCourses[0]['hours']);
        $this->assertSame([], $enCourses[1]['prerequisites']);
        $this->assertArrayNotHasKey('opensCourseIds', $enCourses[0]);
        $this->assertMatchesRegularExpression('/^course-[0-9a-z]{26}$/', $newCourseId);
        $this->assertSame($newCourseId, $arCourses[2]['id']);
        $this->assertMatchesRegularExpression('/^lesson-[0-9a-z]{26}$/', $enCourses[2]['lessons'][0]['id']);
        $this->assertSame($enCourses[2]['lessons'][0]['id'], $arCourses[2]['lessons'][0]['id']);
        $this->assertSame(9, $arDepartment['electivePools'][0]['requiredHours']);
        $this->assertSame('Elective description', $enDepartment['electivePools'][0]['description']);
        $this->assertSame(75, $enDepartment['promotionRequirements'][0]['requiredCredits']);
        $this->assertSame($workspace['promotionRequirements'][0]['id'], $enDepartment['promotionRequirements'][0]['id']);
        $this->assertSame($untouchedArabicTerm, $merged['translations']['ar']['payload']['plan']['departments'][0]['terms'][1]);
        $this->assertSame($untouchedTerm, $merged['translations']['en']['payload']['plan']['departments'][0]['terms'][1]);
        $this->assertSame($untouchedDepartment, $merged['translations']['en']['payload']['plan']['departments'][1]);
    }

    public function test_pairing_uses_position_when_only_one_locale_is_missing_an_id(): void
    {
        $payload = $this->payload();
        unset($payload['translations']['ar']['payload']['plan']['departments'][0]['terms'][0]['courses'][0]['id']);

        $workspace = app(FacultyStudyPlanEditorServiceInterface::class)
            ->buildWorkspace($payload, 'department-one', 'term-one');

        $this->assertCount(2, $workspace['courses']);
        $this->assertSame('course-one', $workspace['courses'][0]['id']);
        $this->assertSame('العنوان العربي الحالي', $workspace['courses'][0]['titleAr']);
        $this->assertSame('Current English title', $workspace['courses'][0]['titleEn']);
    }

    public function test_prepared_workspace_preserves_existing_ids_and_stabilizes_generated_ids(): void
    {
        $service = app(FacultyStudyPlanEditorServiceInterface::class);
        $workspace = $service->buildWorkspace($this->payload(), 'department-one', 'term-one');
        $workspace['courses'][0]['id'] = 'tampered-course';
        $workspace['courses'][0]['lessons'][0]['id'] = 'tampered-lesson';
        $workspace['courses'][] = ['titleAr' => 'جديد', 'titleEn' => 'New', 'lessons' => [['titleAr' => 'درس', 'titleEn' => 'Lesson']]];
        $workspace['electivePools'][] = ['requiredHours' => 3];
        $workspace['promotionRequirements'][] = ['fromYear' => 2, 'toYear' => 3, 'requiredCredits' => 90];

        $prepared = $service->prepareWorkspace($workspace);
        $preparedAgain = $service->prepareWorkspace($prepared);

        $this->assertSame('course-one', $prepared['courses'][0]['id']);
        $this->assertSame('lesson-one', $prepared['courses'][0]['lessons'][0]['id']);
        $this->assertMatchesRegularExpression('/^course-[0-9a-z]{26}$/', $prepared['courses'][2]['id']);
        $this->assertMatchesRegularExpression('/^lesson-[0-9a-z]{26}$/', $prepared['courses'][2]['lessons'][0]['id']);
        $this->assertMatchesRegularExpression('/^pool-[0-9a-z]{26}$/', $prepared['electivePools'][1]['id']);
        $this->assertMatchesRegularExpression('/^promotion-[0-9a-z]{26}$/', $prepared['promotionRequirements'][1]['id']);
        $this->assertSame($prepared, $preparedAgain);
    }

    public function test_lesson_type_options_include_configured_values_and_legacy_seminar(): void
    {
        app()->setLocale('ar');
        $options = app(FacultyStudyPlanEditorServiceInterface::class)
            ->lessonTypeOptions($this->payload(), 'department-one');

        foreach (['lecture', 'practical', 'lab', 'reference', 'exam', 'seminar'] as $type) {
            $this->assertArrayHasKey($type, $options);
        }

        $this->assertSame('مخبر', $options['lab']);
        $this->assertSame('ندوة', $options['seminar']);
        app()->setLocale('en');
    }

    public function test_validation_rejects_dangling_and_cyclic_prerequisites(): void
    {
        $service = app(FacultyStudyPlanEditorServiceInterface::class);
        $dangling = $this->payload();

        foreach (['ar', 'en'] as $locale) {
            $dangling['translations'][$locale]['payload']['plan']['departments'][0]['terms'][0]['courses'][1]['prerequisites'] = ['missing-course'];
        }

        $danglingErrors = $service->validationErrors($dangling);
        $this->assertContains('Every prerequisite must reference a course in the faculty Study Plan.', $danglingErrors['ar']);

        $cycle = $this->payload();

        foreach (['ar', 'en'] as $locale) {
            $cycle['translations'][$locale]['payload']['plan']['departments'][0]['terms'][0]['courses'][0]['prerequisites'] = ['course-two'];
            $cycle['translations'][$locale]['payload']['plan']['departments'][0]['terms'][0]['courses'][1]['prerequisites'] = ['course-one'];
        }

        $cycleErrors = $service->validationErrors($cycle);
        $this->assertContains('Study Plan prerequisites cannot contain a cycle.', $cycleErrors['en']);

        $self = $this->payload();

        foreach (['ar', 'en'] as $locale) {
            $self['translations'][$locale]['payload']['plan']['departments'][0]['terms'][0]['courses'][0]['prerequisites'] = ['course-one'];
        }

        $selfErrors = $service->validationErrors($self);
        $this->assertContains('A course cannot be its own prerequisite.', $selfErrors['ar']);
    }

    public function test_validation_rejects_identity_shared_value_and_title_errors_without_cross_department_collisions(): void
    {
        $service = app(FacultyStudyPlanEditorServiceInterface::class);
        $invalid = $this->payload();

        foreach (['ar', 'en'] as $locale) {
            $duplicate = $invalid['translations'][$locale]['payload']['plan']['departments'][0]['terms'][0]['courses'][0];
            $duplicate['title'] = $locale === 'ar' ? 'مكرر' : 'Duplicate';
            $invalid['translations'][$locale]['payload']['plan']['departments'][0]['terms'][0]['courses'][] = $duplicate;
            $invalid['translations'][$locale]['payload']['plan']['departments'][0]['electivePools'][0]['id'] = 'INVALID POOL';
            $invalid['translations'][$locale]['payload']['plan']['departments'][0]['promotionRequirements'][0]['id'] = 'INVALID PROMOTION';
        }

        unset(
            $invalid['translations']['en']['payload']['plan']['departments'][0]['terms'][0]['courses'][1]['title'],
            $invalid['translations']['en']['payload']['plan']['departments'][0]['terms'][0]['courses'][1]['titleAr'],
            $invalid['translations']['en']['payload']['plan']['departments'][0]['terms'][0]['courses'][1]['titleEn'],
        );
        $invalid['translations']['en']['payload']['plan']['departments'][0]['terms'][0]['courses'][0]['credits'] = 4;

        $errors = $service->validationErrors($invalid);

        $this->assertContains('Course IDs must be unique within each department.', $errors['en']);
        $this->assertContains('Every course requires a localized title.', $errors['en']);
        $this->assertContains('Elective pool IDs must be lowercase URL-safe identifiers.', $errors['ar']);
        $this->assertContains('Promotion requirement IDs must be lowercase URL-safe identifiers.', $errors['en']);
        $this->assertContains('Shared course values must match across Arabic and English.', $errors['translations']);

        $reusedAcrossDepartments = $this->payload();

        foreach (['ar', 'en'] as $locale) {
            $course = $reusedAcrossDepartments['translations'][$locale]['payload']['plan']['departments'][0]['terms'][0]['courses'][0];
            $reusedAcrossDepartments['translations'][$locale]['payload']['plan']['departments'][1]['terms'][0]['courses'] = [$course];
        }

        $reuseErrors = $service->validationErrors($reusedAcrossDepartments);
        $this->assertNotContains('Course IDs must be unique within each department.', $reuseErrors['ar'] ?? []);
        $this->assertNotContains('Course IDs must be unique within each department.', $reuseErrors['en'] ?? []);
    }

    /** @return array<string, mixed> */
    private function payload(): array
    {
        $translation = static function (string $locale): array {
            $isArabic = $locale === 'ar';

            return [
                'title' => $isArabic ? 'الخطة الدراسية' : 'Study Plan',
                'body' => $isArabic ? 'محتوى' : 'Content',
                'payload' => [
                    'lessonTypes' => [
                        'seminar' => ['label' => $isArabic ? 'ندوة' : 'Seminar'],
                    ],
                    'plan' => [
                        'departments' => [
                            [
                                'id' => 'department-one',
                                'futureDepartmentField' => 'keep-department',
                                'terms' => [
                                    [
                                        'id' => 'term-one',
                                        'futureTermField' => 'keep-term',
                                        'courses' => [
                                            [
                                                'id' => 'course-one',
                                                'code' => 'OLD101',
                                                'credits' => 3,
                                                'type' => 'faculty',
                                                'required' => true,
                                                'prerequisites' => [],
                                                'opensCourseIds' => ['course-two'],
                                                'title' => $isArabic ? 'العنوان العربي الحالي' : 'Current English title',
                                                'titleAr' => 'عنوان عربي قديم',
                                                'titleEn' => 'Stale English title',
                                                'description' => $isArabic ? 'الوصف العربي الحالي' : 'Current English description',
                                                'descriptionAr' => 'وصف عربي قديم',
                                                'descriptionEn' => 'Stale English description',
                                                'futureField' => 'keep-row',
                                                'hours' => ['theory' => 2, 'practical' => 1],
                                                'instructor' => [
                                                    'staffSlug' => 'staff-one',
                                                    'name' => $isArabic ? 'مدرس عربي' : 'English Instructor',
                                                    'futureInstructorField' => 'keep-instructor',
                                                ],
                                                'lessons' => [[
                                                    'id' => 'lesson-one',
                                                    'order' => 1,
                                                    'type' => 'seminar',
                                                    'pdfUrl' => '',
                                                    'title' => $isArabic ? 'درس عربي' : 'English lesson',
                                                    'description' => $isArabic ? 'وصف الدرس' : 'Lesson description',
                                                    'futureLessonField' => 'keep-lesson',
                                                ]],
                                            ],
                                            [
                                                'id' => 'course-two',
                                                'code' => 'OLD102',
                                                'credits' => 3,
                                                'type' => 'faculty',
                                                'required' => true,
                                                'prerequisites' => ['course-one'],
                                                'title' => $isArabic ? 'المقرر الثاني' : 'Second course',
                                                'description' => '',
                                                'instructor' => ['staffSlug' => '', 'name' => ''],
                                                'lessons' => [],
                                            ],
                                        ],
                                    ],
                                    [
                                        'id' => 'term-two',
                                        'label' => 'Untouched',
                                        'courses' => [[
                                            'id' => 'course-three',
                                            'code' => 'OLD201',
                                            'credits' => 2,
                                            'type' => 'faculty',
                                            'required' => true,
                                            'opensCourseIds' => ['course-one'],
                                            'title' => $isArabic ? 'مقرر غير محدد' : 'Unselected course',
                                            'lessons' => [],
                                        ]],
                                    ],
                                ],
                                'electivePools' => [[
                                    'id' => 'faculty',
                                    'requiredHours' => 6,
                                    'description' => $isArabic ? 'اختياري' : 'Elective',
                                    'futurePoolField' => 'keep-pool',
                                ]],
                                'promotionRequirements' => [[
                                    'fromYear' => 1,
                                    'toYear' => 2,
                                    'requiredCredits' => 70,
                                    'futurePromotionField' => 'keep-promotion',
                                ]],
                            ],
                            [
                                'id' => 'department-two',
                                'marker' => 'untouched',
                                'terms' => [['id' => 'term-one', 'courses' => []]],
                            ],
                        ],
                    ],
                ],
            ];
        };

        return ['translations' => ['ar' => $translation('ar'), 'en' => $translation('en')]];
    }
}
