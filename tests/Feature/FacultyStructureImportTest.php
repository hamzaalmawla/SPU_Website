<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Contracts\Cms\CmsPageTextImportServiceInterface;
use App\Contracts\Faculty\FacultyStructureImportServiceInterface;
use App\Filament\Pages\ManageDentistryFaculty;
use App\Filament\Pages\ManageMedicineFaculty;
use App\Filament\Pages\ManagePharmacyFaculty;
use App\Models\Faculty\Department;
use App\Models\Faculty\Faculty;
use App\Models\Faculty\FacultyLab;
use App\Models\Faculty\FacultySubpageCard;
use App\Models\User\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class FacultyStructureImportTest extends TestCase
{
    use RefreshDatabase;

    private const STRUCTURE = 'database/seeders/Data/legacy/faculty_structure.json';

    private const PAGE_TEXTS = 'database/seeders/Data/legacy/page_texts/faculty-pages.json';

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);
    }

    public function test_the_real_departments_and_labs_replace_the_invented_ones(): void
    {
        $medicine = Faculty::query()->where('public_slug', 'medicine')->firstOrFail();
        $invented = Department::query()->where('faculty_id', $medicine->getKey())->where('is_enabled', true)->pluck('slug')->all();
        $service = app(FacultyStructureImportServiceInterface::class);

        $dry = $service->import($this->structure(), false);
        self::assertFalse($dry->written);
        self::assertSame(12, $dry->faculties['medicine']['departments']);
        self::assertSame($invented, Department::query()->where('faculty_id', $medicine->getKey())->where('is_enabled', true)->pluck('slug')->all());

        $service->import($this->structure(), true);

        $enabled = Department::query()->where('faculty_id', $medicine->getKey())->where('is_enabled', true)->pluck('slug')->all();
        self::assertCount(12, $enabled);
        self::assertSame([], array_values(array_intersect($invented, $enabled)));
        self::assertSame(11, FacultyLab::query()->where('faculty_id', $medicine->getKey())->where('is_enabled', true)->count());

        $this->get('/ar/faculties/medicine/departments')
            ->assertOk()
            ->assertSee('قسم التعليم الطبي والتدريب السريري')
            ->assertSee('الأقسام العلمية في كلية الطب البشري.')
            ->assertDontSee('بيانات تجريبية');
        $this->get('/en/faculties/pharmacy/labs')->assertOk()->assertSee('23 research and training labs')->assertSee('Code: M1');

        // The old site lists no AI labs: the invented ones are switched off and the page is gone.
        $this->get('/en/faculties/artificial-intelligence/labs')->assertNotFound();

        // Running it again changes nothing.
        $again = $service->import($this->structure(), true);
        self::assertSame(0, $again->faculties['medicine']['departmentsCreated']);
        self::assertSame(0, $again->faculties['medicine']['departmentsDisabled']);
    }

    public function test_the_new_faculty_pages_are_created_and_show_the_old_text(): void
    {
        $admin = User::factory()->create(['role_slug' => 'super_admin', 'is_locked' => false]);
        app(FacultyStructureImportServiceInterface::class)->import($this->structure(), true);

        self::assertTrue(FacultySubpageCard::query()->where('faculty_slug', 'dentistry')->where('subpage_slug', 'clinical-cases')->where('is_visible', true)->exists());

        $results = app(CmsPageTextImportServiceInterface::class)->import(base_path(self::PAGE_TEXTS), (string) $admin->getKey(), true);
        foreach ($results as $result) {
            self::assertStringStartsWith('published', $result['result'], $result['target']);
        }

        $this->get('/ar/faculties/dentistry')->assertOk()->assertSee('/ar/faculties/dentistry/clinical-cases', false);
        $this->get('/ar/faculties/dentistry/clinical-cases')
            ->assertOk()
            ->assertSee('حالة سريرية 8: تدبير اختلاط معالجة لبية')
            ->assertSee('https://www.youtube.com/watch?v=zWz4p2r0tsA', false);
        $this->get('/en/faculties/dentistry/clinical-cases')->assertOk()->assertSee('Watch the case video');

        $this->get('/ar/faculties/pharmacy/training')
            ->assertOk()
            ->assertSee('نظام الملازمة والتدريب')
            ->assertSee('يحتفظ الطالب بعلامة المرحلة الأولى')
            ->assertSee('/downloads/files/1497172569_66-05.pdf', false);

        $this->get('/en/faculties/medicine/final-exams')
            ->assertOk()
            ->assertSee('Summer semester 2025/2026')
            ->assertSee('/downloads/files/1784487408_Exam_Schedule_B.pdf', false);

        // A faculty-specific page is not reachable from another faculty.
        $this->get('/en/faculties/medicine/clinical-cases')->assertNotFound();
        $this->get('/en/faculties/medicine/training')->assertNotFound();
    }

    public function test_the_admin_edits_the_new_pages(): void
    {
        $admin = User::factory()->create(['role_slug' => 'super_admin', 'is_locked' => false]);
        app(FacultyStructureImportServiceInterface::class)->import($this->structure(), true);
        $this->actingAs($admin);

        $this->get(ManageDentistryFaculty::getUrl(['target' => 'facilities.dentistry.clinical-cases']))
            ->assertOk()
            ->assertSee(__('admin.faculty_workspace.editor.sections.clinical_cases'));
        $this->get(ManageMedicineFaculty::getUrl(['target' => 'facilities.medicine.final-exams']))
            ->assertOk()
            ->assertSee(__('admin.faculty_workspace.editor.sections.exam_schedules'));
        $this->get(ManagePharmacyFaculty::getUrl(['target' => 'facilities.pharmacy.training']))
            ->assertOk()
            ->assertSee(__('admin.faculty_workspace.editor.sections.training_regulations'));
    }

    /** @return array<string, mixed> */
    private function structure(): array
    {
        return json_decode((string) file_get_contents(base_path(self::STRUCTURE)), true, 512, JSON_THROW_ON_ERROR);
    }
}
