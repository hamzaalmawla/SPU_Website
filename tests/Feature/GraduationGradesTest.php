<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Contracts\Career\GraduationGradeServiceInterface;
use App\Contracts\Career\StudentListImportServiceInterface;
use App\Contracts\Cms\CmsWorkflowServiceInterface;
use App\DTOs\Career\StudentListImportChoicesDTO;
use App\Filament\Pages\ManageArtificialIntelligenceFaculty;
use App\Filament\Pages\ManageDentistryFaculty;
use App\Models\Career\Alumni;
use App\Models\Career\AlumniTranslation;
use App\Models\Faculty\Faculty;
use App\Models\User\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\Support\DecisionDocxFactory;
use Tests\TestCase;

final class GraduationGradesTest extends TestCase
{
    use RefreshDatabase;

    private const FACULTY = 'artificial-intelligence';

    private const TARGET = 'facilities.'.self::FACULTY.'.alumni';

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);
        $this->admin = User::query()->where('role_slug', 'super_admin')->firstOrFail();
    }

    public function test_the_list_starts_with_the_four_decision_grades_in_both_languages(): void
    {
        $this->assertSame(
            ['excellent' => 'امتياز / Excellent', 'very_good' => 'جيد جداً / Very Good', 'good' => 'جيد / Good', 'pass' => 'مقبول / Pass'],
            $this->grades()->options(),
        );
        $this->assertSame('جيد جداً', $this->grades()->label('very_good', 'ar'));
        $this->assertSame('Very Good', $this->grades()->label('very_good', 'en'));
        $this->assertSame('excellent', $this->grades()->keyForText('اِمتياز'));
        $this->assertSame('very_good', $this->grades()->keyForText('very good'));
        $this->assertNull($this->grades()->label('', 'ar'));
    }

    public function test_the_grade_on_the_page_is_the_grade_the_editor_sees_and_can_change(): void
    {
        $alumni = $this->graduate('رهف سامي الأحمد', 'Rahaf Sami Alahmad', 'excellent');
        // A page published before grades were a list: a copy of the record without its grade.
        $this->publish(self::TARGET, [['id' => $alumni->getKey(), 'title' => 'رهف سامي الأحمد']], [['id' => $alumni->getKey(), 'title' => 'Rahaf Sami Alahmad']]);

        $this->get('/ar/faculties/'.self::FACULTY.'/alumni')->assertOk()->assertSee('التقدير: امتياز', false);

        $this->actingAs($this->admin, 'web');
        $page = Livewire::withQueryParams(['target' => self::TARGET])
            ->test(ManageArtificialIntelligenceFaculty::class)
            ->set('data.record_search', 'Rahaf')
            ->call('loadTarget', self::TARGET)
            ->assertSee('التقدير');

        $records = $page->get('data.student_records');
        $recordKey = array_key_first($records);
        $this->assertSame('excellent', $records[$recordKey]['gradeKey'] ?? null);

        $page->set('data.student_records.'.$recordKey.'.gradeKey', 'very_good')
            ->call('publish');

        $published = $this->publishedItems();
        $this->assertSame(['very_good'], array_column($published['ar'], 'gradeKey'));
        $this->assertSame(['very_good'], array_column($published['en'], 'gradeKey'));
        // Saved on the graduate's record too, so the Alumni directory agrees.
        $this->assertSame('very_good', $alumni->fresh()?->grade);

        $this->get('/ar/faculties/'.self::FACULTY.'/alumni')->assertOk()->assertSee('التقدير: جيد جداً', false);
        $this->get('/en/faculties/'.self::FACULTY.'/alumni')->assertOk()->assertSee('Grade: Very Good', false);
        $this->get('/en/alumni?q=Rahaf')->assertOk()->assertSee('Grade: Very Good', false);

        // No grade is a choice too.
        $page->set('data.record_search', 'Rahaf')
            ->call('loadTarget', self::TARGET);
        $recordKey = array_key_first($page->get('data.student_records'));
        $page->set('data.student_records.'.$recordKey.'.gradeKey', null)->call('publish');

        $this->assertNull($alumni->fresh()?->grade);
        $this->get('/ar/faculties/'.self::FACULTY.'/alumni')->assertOk()->assertSee('رهف سامي الأحمد')->assertDontSee('التقدير:', false);
    }

    public function test_a_super_admin_adds_and_renames_grades_for_every_alumni_page(): void
    {
        $this->graduate('مازن فادي الخطيب', 'Mazen Fadi Alkhatib', 'good', 'dentistry');
        $this->actingAs($this->admin, 'web');

        $page = Livewire::withQueryParams(['target' => self::TARGET])
            ->test(ManageArtificialIntelligenceFaculty::class)
            ->assertActionVisible('editGraduationGrades')
            ->mountAction('editGraduationGrades');
        $this->assertSame(['امتياز', 'جيد جداً', 'جيد', 'مقبول'], array_column(array_values($page->get('mountedActionsData.0.grades')), 'nameAr'));

        $page->setActionData(['grades' => [
            ['key' => 'excellent', 'nameAr' => 'امتياز', 'nameEn' => 'Excellent'],
            ['key' => 'very_good', 'nameAr' => 'جيد جداً', 'nameEn' => 'Very Good'],
            ['key' => 'good', 'nameAr' => 'جيد', 'nameEn' => 'Good standing'],
            ['key' => 'pass', 'nameAr' => 'مقبول', 'nameEn' => 'Pass'],
            ['key' => null, 'nameAr' => 'امتياز مع مرتبة الشرف', 'nameEn' => 'Excellent with Honors'],
        ]])
            ->callMountedAction()
            ->assertHasNoActionErrors()
            ->assertNotified(__('student_lists.grades_admin.saved'));

        $this->assertSame('امتياز مع مرتبة الشرف / Excellent with Honors', $this->grades()->options()['excellent_with_ho'] ?? null);
        $this->assertSame('excellent_with_ho', array_key_last($this->grades()->options()));

        // The renamed grade shows on another faculty's page and in the directory.
        $this->get('/en/faculties/dentistry/alumni')->assertOk()->assertSee('Grade: Good standing', false);
        $this->get('/en/alumni?q=Mazen')->assertOk()->assertSee('Grade: Good standing', false);

        // The new grade is offered on every faculty's Alumni page.
        $other = $this->graduate('ليلى سامي العلي', 'Layla Sami Alali', null, 'dentistry');
        $dentistry = Livewire::withQueryParams(['target' => 'facilities.dentistry.alumni'])
            ->test(ManageDentistryFaculty::class)
            ->set('data.record_search', 'Layla')
            ->call('loadTarget', 'facilities.dentistry.alumni');
        $recordKey = array_key_first($dentistry->get('data.student_records'));
        $this->assertNull($dentistry->get('data.student_records.'.$recordKey.'.gradeKey'));
        $dentistry->set('data.student_records.'.$recordKey.'.gradeKey', 'excellent_with_ho')->call('publish');

        $this->assertSame('excellent_with_ho', $other->fresh()?->grade);
        $this->get('/ar/faculties/dentistry/alumni')->assertOk()->assertSee('التقدير: امتياز مع مرتبة الشرف', false);
    }

    public function test_deleting_a_grade_moves_its_graduates_or_leaves_them_without_one(): void
    {
        $first = $this->graduate('سامر خالد الحلبي', 'Samer Khaled Alhalabi', 'pass');
        $second = $this->graduate('هبة ماهر يوسف', 'Heba Maher Youssef', 'pass');
        $third = $this->graduate('طارق نزار سليم', 'Tarek Nizar Salim', 'good');
        // A graduate typed onto the page with no record, holding the grade as a key.
        $this->publish(self::TARGET,
            [['id' => $first->getKey(), 'title' => 'سامر خالد الحلبي', 'gradeKey' => 'pass'], ['title' => 'خريج يدوي', 'gradeKey' => 'pass']],
            [['id' => $first->getKey(), 'title' => 'Samer Khaled Alhalabi', 'gradeKey' => 'pass'], ['title' => 'Typed Graduate', 'gradeKey' => 'pass']],
        );
        $this->actingAs($this->admin, 'web');

        $this->assertSame(3, $this->grades()->usageCount('pass'));

        Livewire::withQueryParams(['target' => self::TARGET])
            ->test(ManageArtificialIntelligenceFaculty::class)
            ->mountAction('deleteGraduationGrade')
            ->setActionData(['grade' => 'pass'])
            ->assertSee('3')
            ->setActionData(['grade' => 'pass', 'moveTo' => 'good'])
            ->callMountedAction()
            ->assertHasNoActionErrors()
            ->assertNotified();

        $this->assertArrayNotHasKey('pass', $this->grades()->options());
        $this->assertSame(['good', 'good', 'good'], [$first->fresh()?->grade, $second->fresh()?->grade, $third->fresh()?->grade]);
        // The page saved with the deleted grade reads the grade it moved to.
        $this->get('/en/faculties/'.self::FACULTY.'/alumni')->assertOk()->assertSee('Typed Graduate')->assertDontSee('Grade: Pass', false);
        $this->assertSame('good', $this->grades()->itemGradeKey(['gradeKey' => 'pass'], null));
        $this->assertSame(4, $this->grades()->usageCount('good'));

        // Deleting with no grade to move to leaves them without one, the older key too.
        $this->assertSame(3, $this->grades()->delete('good', null, (int) $this->admin->getKey()));
        $this->assertNull($first->fresh()?->grade);
        $this->assertNull($this->grades()->itemGradeKey(['gradeKey' => 'pass'], null));
        $this->get('/en/faculties/'.self::FACULTY.'/alumni')->assertOk()->assertSee('Typed Graduate')->assertDontSee('Grade:', false);

        // A deleted name can be added again; it gets a new key.
        $again = $this->grades()->create('جيد', 'Good', (int) $this->admin->getKey());
        $this->assertSame('good_2', $again->key);
    }

    public function test_only_super_admins_manage_the_list(): void
    {
        $editor = User::factory()->create(['role_slug' => 'editor', 'is_locked' => false]);
        $this->actingAs($editor, 'web');

        Livewire::withQueryParams(['target' => self::TARGET])
            ->test(ManageArtificialIntelligenceFaculty::class)
            ->assertActionHidden('editGraduationGrades')
            ->assertActionHidden('deleteGraduationGrade');

        $this->assertFalse($this->grades()->canManage($editor, 'create'));
        $this->expectException(AuthorizationException::class);
        $this->grades()->create('ممتاز', 'Outstanding', (int) $editor->getKey());
    }

    public function test_names_must_be_filled_and_unique(): void
    {
        try {
            $this->grades()->create('امتياز', 'Top', (int) $this->admin->getKey());
            $this->fail('A duplicate Arabic name was accepted.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('nameAr', $exception->errors());
        }

        try {
            $this->grades()->update('good', 'جيد', '', (int) $this->admin->getKey());
            $this->fail('An empty English name was accepted.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('nameEn', $exception->errors());
        }

        try {
            $this->grades()->delete('good', 'good', (int) $this->admin->getKey());
            $this->fail('A grade was moved into itself.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('moveTo', $exception->errors());
        }
    }

    public function test_decision_imports_read_grades_added_to_the_list(): void
    {
        Storage::fake('local');
        $this->grades()->create('ممتاز جداً', 'Outstanding', (int) $this->admin->getKey());
        $temporary = tempnam(sys_get_temp_dir(), 'decision');
        DecisionDocxFactory::graduates($temporary, [
            ['id' => '4300001', 'name' => 'نور سامي العلي', 'specialization' => 'الهندسة المعلوماتية/هندسة البرمجيات', 'points' => '3.9', 'percent' => '97', 'grade' => 'ممتاز جداً'],
            ['id' => '4300002', 'name' => 'رامي فادي حداد', 'specialization' => 'الهندسة المعلوماتية/هندسة البرمجيات', 'points' => '3.2', 'percent' => '84', 'grade' => 'جيد جداً'],
        ]);
        $path = StudentListImportServiceInterface::UPLOAD_DIRECTORY.'/'.uniqid('t', false).'-graduates.docx';
        Storage::disk('local')->put($path, (string) file_get_contents($temporary));
        @unlink($temporary);

        app(StudentListImportServiceInterface::class)->import(self::FACULTY, 'alumni', $path, 'graduates.docx', new StudentListImportChoicesDTO('2025/2026', 'second'), $this->admin);

        $this->assertSame('outstanding', Alumni::query()->where('student_identifier', '4300001')->value('grade'));
        $this->assertSame('very_good', Alumni::query()->where('student_identifier', '4300002')->value('grade'));
    }

    /** Read fresh each time: a service instance keeps the list it read. */
    private function grades(): GraduationGradeServiceInterface
    {
        return app(GraduationGradeServiceInterface::class);
    }

    private function graduate(string $nameAr, string $nameEn, ?string $grade, string $faculty = self::FACULTY): Alumni
    {
        $alumni = Alumni::query()->create([
            'faculty_id' => Faculty::query()->where('public_slug', $faculty)->value('id'),
            'student_identifier' => (string) random_int(1000000, 9999999),
            'graduation_year' => 2026,
            'grade' => $grade,
            'is_enabled' => true,
            'is_featured' => false,
        ]);

        foreach (['ar' => $nameAr, 'en' => $nameEn] as $locale => $name) {
            AlumniTranslation::query()->create(['alumni_id' => $alumni->getKey(), 'locale' => $locale, 'full_name' => $name]);
        }

        return $alumni;
    }

    /**
     * @param  list<array<string, mixed>>  $arItems
     * @param  list<array<string, mixed>>  $enItems
     */
    private function publish(string $target, array $arItems, array $enItems): void
    {
        $workflow = app(CmsWorkflowServiceInterface::class);
        $workflow->saveDraft($target, ['translations' => [
            'ar' => ['title' => 'الخريجون', 'items' => $arItems],
            'en' => ['title' => 'Alumni', 'items' => $enItems],
        ]], (int) $this->admin->getKey());
        $workflow->publish($target, (int) $this->admin->getKey());
    }

    /** @return array{ar: list<array<string, mixed>>, en: list<array<string, mixed>>} */
    private function publishedItems(): array
    {
        $payload = app(CmsWorkflowServiceInterface::class)->getPublishedPayload(self::TARGET) ?? [];

        return [
            'ar' => array_values($payload['translations']['ar']['items'] ?? []),
            'en' => array_values($payload['translations']['en']['items'] ?? []),
        ];
    }
}
