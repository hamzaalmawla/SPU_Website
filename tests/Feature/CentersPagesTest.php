<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Contracts\Center\CenterCatalogServiceInterface;
use App\Contracts\Cms\CmsWorkflowServiceInterface;
use App\Contracts\Faculty\FacultyStudyPlanEditorServiceInterface;
use App\Contracts\Page\FacultyPageServiceInterface;
use App\Enums\PublicationStatus;
use App\Models\Person\FacultyMember;
use App\Models\User\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The centers section: /centers lists every center, and the Requirements Unit
 * has its page, staff page and required / elective course lists, built from the
 * default content until editors publish their own.
 */
final class CentersPagesTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);
        $this->admin = User::query()->where('role_slug', 'super_admin')->firstOrFail();
    }

    public function test_the_centers_page_lists_the_requirements_unit_in_both_languages(): void
    {
        $this->get('/en/centers')->assertOk()
            ->assertSee('Centers and Units')
            ->assertSee('Requirements Unit')
            ->assertSee('/en/centers/requirements-unit', false)
            ->assertSee('What the centers offer');

        $this->get('/ar/centers')->assertOk()
            ->assertSee('المراكز والوحدات')
            ->assertSee('وحدة المتطلبات')
            ->assertSee('dir="rtl"', false);
    }

    public function test_the_unit_page_links_its_three_subpages(): void
    {
        $this->get('/en/centers/requirements-unit')->assertOk()
            ->assertSee('Requirements Unit')
            ->assertSee('/en/centers/requirements-unit/staff', false)
            ->assertSee('/en/centers/requirements-unit/required-courses', false)
            ->assertSee('/en/centers/requirements-unit/elective-courses', false);

        $this->get('/ar/centers/requirements-unit')->assertOk()->assertSee('وحدة المتطلبات');
        $this->get('/en/centers/unknown-center')->assertNotFound();
        $this->get('/en/centers/requirements-unit/projects')->assertNotFound();
    }

    public function test_required_and_elective_courses_are_listed_apart_with_each_faculty_code(): void
    {
        $required = $this->get('/en/centers/requirements-unit/required-courses')->assertOk();
        $required->assertSee('id="course-arabic-language"', false)
            ->assertSee('Arabic Language')
            ->assertSee('UNRE103')
            ->assertDontSee('Introduction to Law');

        $elective = $this->get('/en/centers/requirements-unit/elective-courses')->assertOk();
        $elective->assertSee('id="course-introduction-to-law"', false)
            ->assertDontSee('id="course-arabic-language"', false);

        $this->get('/ar/centers/requirements-unit/required-courses')->assertOk()->assertSee('اللغة العربية');
    }

    public function test_the_faculty_filter_narrows_a_course_list(): void
    {
        // Dentistry has no "Physical and Mental Motor Skills" course; Business does.
        $this->get('/en/centers/requirements-unit/elective-courses?faculty=dentistry')->assertOk()
            ->assertSee('id="course-sociology"', false)
            ->assertDontSee('id="course-physical-mental-skills"', false);

        $this->get('/en/centers/requirements-unit/elective-courses?faculty=business-administration')->assertOk()
            ->assertSee('id="course-physical-mental-skills"', false);

        // Petroleum has no elective here, so it is not offered and the list stays whole.
        $this->get('/en/centers/requirements-unit/elective-courses?faculty=petroleum')->assertOk()
            ->assertSee('id="course-physical-mental-skills"', false)
            ->assertDontSee('?faculty=petroleum', false);
    }

    public function test_the_staff_page_lists_published_profiles_of_the_center_and_the_manager_first(): void
    {
        $teacher = $this->member('teacher-one', 'Teacher One', 'المدرّس الأول', 1);
        $manager = $this->member('unit-manager', 'Unit Head Person', 'مدير الوحدة شخص', 2);
        $this->member('draft-teacher', 'Draft Teacher', 'مدرس مسودة', 3, published: false);

        $payload = app(CenterCatalogServiceInterface::class)->defaultPayload('centers.requirements-unit');
        $payload['managerMemberId'] = $manager->getKey();
        $payload['translations']['en']['manager']['message'] = 'Welcome to the unit.';
        $workflow = app(CmsWorkflowServiceInterface::class);
        $workflow->saveDraft('centers.requirements-unit', $payload, (int) $this->admin->getKey());
        $workflow->publish('centers.requirements-unit', (int) $this->admin->getKey());

        $html = (string) $this->get('/en/centers/requirements-unit/staff')->assertOk()
            ->assertSee('Teacher One')
            ->assertDontSee('Draft Teacher')
            ->getContent();
        $this->assertLessThan(strpos($html, 'Teacher One'), strpos($html, 'Unit Head Person'));

        $this->get('/en/centers/requirements-unit')->assertOk()
            ->assertSee('Unit Head Person')
            ->assertSee('Welcome to the unit.')
            ->assertSee('/en/about/profile/unit-manager', false);

        $this->get('/en/about/profile/teacher-one')->assertOk()->assertSee('Requirements Unit');
    }

    public function test_publishing_rejects_a_manager_outside_the_center_and_broken_courses(): void
    {
        $outsider = $this->member('outsider', 'Outsider', 'شخص', 1, center: null);
        $workflow = app(CmsWorkflowServiceInterface::class);
        $catalog = app(CenterCatalogServiceInterface::class);

        $page = $catalog->defaultPayload('centers.requirements-unit');
        $page['managerMemberId'] = $outsider->getKey();
        $this->assertArrayHasKey('managerMemberId', $workflow->readiness('centers.requirements-unit', $page)->errors);

        $courses = $catalog->defaultPayload('centers.requirements-unit.courses');
        $this->assertTrue($workflow->readiness('centers.requirements-unit.courses', $courses)->isReady);

        $courses['courses'][1]['faculties'][] = ['faculty' => 'medicine', 'code' => 'REQU103'];
        $courses['courses'][2]['prerequisites'][] = 'no-such-course';
        $errors = $workflow->readiness('centers.requirements-unit.courses', $courses)->errors['courses'] ?? [];
        $this->assertNotEmpty(array_filter($errors, fn (string $error): bool => str_contains($error, 'REQU103')));
        $this->assertNotEmpty(array_filter($errors, fn (string $error): bool => str_contains($error, 'no-such-course')));
    }

    public function test_a_faculty_study_plan_links_university_requirements_to_the_unit(): void
    {
        $payload = app(FacultyPageServiceInterface::class)->getEditablePayload('facilities.dentistry.study_plan');
        foreach (['ar', 'en'] as $locale) {
            $payload['translations'][$locale]['payload']['plan']['departments'][0]['terms'][0]['courses'] = [
                ['id' => 'unre103', 'code' => 'UNRE103', 'titleAr' => 'لغة عربية', 'titleEn' => 'Arabic Language', 'type' => 'university', 'required' => true, 'credits' => 2, 'prerequisites' => [], 'hours' => ['theory' => 2, 'practical' => 0]],
                ['id' => 'unre108', 'code' => 'UNRE108', 'titleAr' => 'علم اجتماع', 'titleEn' => 'Social Science', 'type' => 'university', 'required' => false, 'credits' => 2, 'prerequisites' => [], 'hours' => ['theory' => 2, 'practical' => 0]],
            ];
        }
        $workflow = app(CmsWorkflowServiceInterface::class);
        $workflow->saveDraft('facilities.dentistry.study_plan', $payload, (int) $this->admin->getKey());
        $workflow->publish('facilities.dentistry.study_plan', (int) $this->admin->getKey());

        $html = (string) $this->get('/en/faculties/dentistry/study-plan')->assertOk()->getContent();

        // UNRE103 is Dentistry's code for Arabic Language; UNRE108 for Sociology (elective).
        $this->assertStringContainsString('"code":"UNRE103"', $html);
        $this->assertStringContainsString('\\/en\\/centers\\/requirements-unit\\/required-courses#course-arabic-language', $html);
        $this->assertStringContainsString('\\/en\\/centers\\/requirements-unit\\/elective-courses#course-sociology', $html);
        $this->assertStringContainsString('data-modal-unit', $html);
    }

    public function test_a_course_with_no_matching_unit_course_opens_the_list(): void
    {
        $catalog = app(CenterCatalogServiceInterface::class);

        $this->assertSame('/ar/centers/requirements-unit/elective-courses', $catalog->studyPlanCourseUrl('pharmacy', ['type' => 'university', 'code' => 'ELEC-U1', 'required' => false], 'ar'));
        $this->assertSame('/en/centers/requirements-unit/required-courses#course-computer-skills', $catalog->studyPlanCourseUrl('pharmacy', ['type' => 'university', 'code' => 'requ103'], 'en'));
        $this->assertSame('/en/centers/requirements-unit/elective-courses#course-sociology', $catalog->studyPlanCourseUrl('pharmacy', ['type' => 'university', 'code' => 'X1', 'requirementsUnitCourseId' => 'sociology'], 'en'));
        $this->assertNull($catalog->studyPlanCourseUrl('pharmacy', ['type' => 'faculty', 'code' => 'REQU103'], 'en'));
    }

    public function test_a_university_elective_box_opens_the_elective_list_in_a_new_tab(): void
    {
        $payload = app(FacultyPageServiceInterface::class)->getEditablePayload('facilities.dentistry.study_plan');
        foreach (['ar', 'en'] as $locale) {
            $payload['translations'][$locale]['payload']['plan']['departments'][0]['terms'][0]['courses'] = [
                ['id' => 'university-elective', 'code' => '', 'titleAr' => 'مقرر جامعة اختياري', 'titleEn' => 'University elective course', 'type' => 'university', 'required' => false, 'electiveSlot' => true, 'credits' => 2, 'prerequisites' => [], 'hours' => ['theory' => 2, 'practical' => 0]],
                ['id' => 'unre103', 'code' => 'UNRE103', 'titleAr' => 'لغة عربية', 'titleEn' => 'Arabic Language', 'type' => 'university', 'required' => true, 'credits' => 2, 'prerequisites' => [], 'hours' => ['theory' => 2, 'practical' => 0]],
            ];
        }
        $workflow = app(CmsWorkflowServiceInterface::class);
        $workflow->saveDraft('facilities.dentistry.study_plan', $payload, (int) $this->admin->getKey());
        $workflow->publish('facilities.dentistry.study_plan', (int) $this->admin->getKey());

        foreach (['en' => 'University elective course', 'ar' => 'مقرر جامعة اختياري'] as $locale => $title) {
            $html = (string) $this->get('/'.$locale.'/faculties/dentistry/study-plan')->assertOk()->getContent();

            $this->assertMatchesRegularExpression('#<a href="/'.$locale.'/centers/requirements-unit/elective-courses" target="_blank" rel="noopener" data-course-card data-elective-slot data-course-id="university-elective"#', $html);
            $this->assertMatchesRegularExpression('#<a href="/'.$locale.'/centers/requirements-unit/elective-courses" target="_blank" rel="noopener" data-list-card data-elective-slot#', $html);
            $this->assertStringContainsString($title, $html);
            $this->assertStringContainsString(__('centers.elective_slot_open', [], $locale), $html);
            $this->assertStringContainsString(__('centers.elective_slot_legend', [], $locale), $html);
            // An ordinary course still opens its dialog.
            $this->assertMatchesRegularExpression('#<button type="button" data-course-card data-course-id="unre103"#', $html);
        }
    }

    public function test_publishing_rejects_a_course_that_needs_a_university_elective_box(): void
    {
        $payload = app(FacultyPageServiceInterface::class)->getEditablePayload('facilities.dentistry.study_plan');
        foreach (['ar', 'en'] as $locale) {
            $payload['translations'][$locale]['payload']['plan']['departments'][0]['terms'][0]['courses'] = [
                ['id' => 'university-elective', 'code' => '', 'titleAr' => 'مقرر جامعة اختياري', 'titleEn' => 'University elective course', 'type' => 'university', 'required' => false, 'electiveSlot' => true, 'credits' => 2, 'prerequisites' => []],
                ['id' => 'unre103', 'code' => 'UNRE103', 'titleAr' => 'لغة عربية', 'titleEn' => 'Arabic Language', 'type' => 'faculty', 'required' => true, 'electiveSlot' => true, 'credits' => 2, 'prerequisites' => ['university-elective']],
            ];
        }

        $errors = app(FacultyStudyPlanEditorServiceInterface::class)->validationErrors($payload);

        $this->assertContains('No course can need a university elective box.', $errors['en']);
        $this->assertContains('A university elective box must be an elective university requirement.', $errors['ar']);
        $this->assertContains('A university elective box has no prerequisites.', $errors['ar']);
    }

    public function test_a_university_elective_box_leads_to_the_elective_list_even_with_a_matching_code(): void
    {
        $catalog = app(CenterCatalogServiceInterface::class);

        $this->assertSame('/en/centers/requirements-unit/elective-courses', $catalog->studyPlanCourseUrl('pharmacy', ['type' => 'university', 'code' => 'X1', 'required' => false, 'electiveSlot' => true, 'requirementsUnitCourseId' => 'sociology'], 'en'));
    }

    private function member(string $slug, string $nameEn, string $nameAr, int $order, bool $published = true, ?string $center = 'requirements-unit'): FacultyMember
    {
        $member = FacultyMember::query()->create([
            'slug' => $slug,
            'center_slug' => $center,
            'sort_order' => $order,
            'is_enabled' => true,
            'publication_status' => $published ? PublicationStatus::Published->value : PublicationStatus::Draft->value,
            'published_at' => $published ? now()->subDay() : null,
        ]);
        $member->translations()->create(['locale' => 'en', 'full_name' => $nameEn, 'position' => 'Lecturer']);
        $member->translations()->create(['locale' => 'ar', 'full_name' => $nameAr, 'position' => 'محاضر']);

        return $member;
    }
}
