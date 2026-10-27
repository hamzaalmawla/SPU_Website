<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Contracts\Career\StudentListImportServiceInterface;
use App\Contracts\Cms\CmsWorkflowServiceInterface;
use App\DTOs\Career\StudentListImportChoicesDTO;
use App\DTOs\Career\StudentListImportResultDTO;
use App\DTOs\Career\StudentListImportRowDTO;
use App\Filament\Pages\ManageArtificialIntelligenceFaculty;
use App\Models\Career\Alumni;
use App\Models\Career\HonorStudent;
use App\Models\Career\StudentListImport;
use App\Models\Faculty\Department;
use App\Models\Faculty\DepartmentTranslation;
use App\Models\Faculty\Faculty;
use App\Models\User\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;
use Livewire\Livewire;
use Tests\Support\DecisionDocxFactory;
use Tests\TestCase;

final class StudentListImportTest extends TestCase
{
    use RefreshDatabase;

    private const FACULTY = 'artificial-intelligence';

    private Faculty $faculty;

    private Department $software;

    private Department $communications;

    private User $editor;

    private StudentListImportServiceInterface $imports;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);
        Storage::fake('local');
        $this->faculty = Faculty::query()->where('public_slug', self::FACULTY)->firstOrFail();
        $this->software = $this->department('software-test', 'قسم هندسة البرمجيات ونظم المعلومات الذكية', 'Software Engineering and Intelligent Information Systems');
        $this->communications = $this->department('communications-test', 'قسم هندسة الاتصالات الذكية', 'Intelligent Communications Engineering');
        $this->editor = $this->user('editor');
        $this->imports = app(StudentListImportServiceInterface::class);
    }

    public function test_graduates_are_previewed_imported_and_shown_with_their_grade(): void
    {
        $path = $this->upload('graduates.docx', fn (string $file): string => DecisionDocxFactory::graduates($file, $this->graduates()));

        $preview = $this->imports->preview(self::FACULTY, 'alumni', $path, 'قرار 5.docx');
        $this->assertSame(['5/14', '2025/2026', 'second'], [$preview->decisionNumber, $preview->academicYear, $preview->semester]);
        $this->assertSame([3, 0, 0], [$preview->createCount, $preview->updateCount, $preview->skipCount]);
        $this->assertSame((int) $this->software->getKey(), $preview->rows[0]->departmentId);
        $this->assertSame((int) $this->communications->getKey(), $preview->rows[2]->departmentId);

        $result = $this->imports->import(self::FACULTY, 'alumni', $path, 'قرار 5.docx', $this->choices(), $this->editor);

        $this->assertSame([3, 0, 0], [$result->created, $result->updated, $result->skipped]);
        Storage::disk('local')->assertMissing($path);
        $alumni = Alumni::query()->where('student_identifier', '4200001')->firstOrFail();
        $this->assertSame(['excellent', '2025/2026', 'second', 2026, (int) $this->software->getKey(), true], [
            $alumni->grade, $alumni->academic_year, $alumni->semester, $alumni->graduation_year, (int) $alumni->department_id, (bool) $alumni->is_enabled,
        ]);
        $this->assertSame('3.55', (string) $alumni->gpa_points);
        $this->assertDatabaseHas('alumni_translations', ['alumni_id' => $alumni->getKey(), 'locale' => 'ar', 'full_name' => 'سامر خالد الحلبي']);
        $this->assertDatabaseHas('audit_logs', ['action' => 'student_list.imported', 'entity_id' => $result->importId]);

        $this->get('/ar/faculties/'.self::FACULTY.'/alumni?q=سامر')
            ->assertOk()
            ->assertSee('سامر خالد الحلبي')
            ->assertSee('التقدير: امتياز')
            ->assertSee('الفصل الثاني')
            ->assertDontSee('4200001');
        $this->get('/en/faculties/'.self::FACULTY.'/alumni?q=سامر')
            ->assertOk()
            ->assertSee('Grade: Excellent')
            ->assertSee('Second Semester');
        $this->get('/en/alumni?q=سامر')->assertOk()->assertSee('Grade: Excellent');
    }

    public function test_importing_the_same_decision_again_updates_instead_of_adding(): void
    {
        $first = $this->importGraduates();
        $students = $this->graduates();
        $students[0]['name'] = 'سامر خالد الحلبي المصحح';
        $path = $this->upload('again.docx', fn (string $file): string => DecisionDocxFactory::graduates($file, $students));

        $preview = $this->imports->preview(self::FACULTY, 'alumni', $path, 'again.docx');
        $this->assertSame([0, 3], [$preview->createCount, $preview->updateCount]);
        // The departments confirmed last time are remembered.
        $this->assertTrue($preview->specializations[0]->remembered);

        $second = $this->imports->import(self::FACULTY, 'alumni', $path, 'again.docx', $this->choices(), $this->editor);

        $this->assertSame(3, Alumni::query()->whereIn('student_identifier', ['4200001', '4200002', '4200003'])->count());
        $this->assertDatabaseHas('alumni_translations', ['locale' => 'ar', 'full_name' => 'سامر خالد الحلبي المصحح']);

        // Undo goes newest first and restores the previous values.
        try {
            $this->imports->undo($first->importId, $this->editor);
            $this->fail('An older import was undone before a newer one.');
        } catch (InvalidArgumentException $exception) {
            $this->assertSame('student_lists.errors.undo_newer_first', $exception->getMessage());
        }

        $this->imports->undo($second->importId, $this->editor);
        $this->assertDatabaseHas('alumni_translations', ['locale' => 'ar', 'full_name' => 'سامر خالد الحلبي']);
        $this->assertSame(3, Alumni::query()->whereIn('student_identifier', ['4200001', '4200002', '4200003'])->count());

        $this->imports->undo($first->importId, $this->editor);
        $this->assertSame(0, Alumni::query()->whereIn('student_identifier', ['4200001', '4200002', '4200003'])->count());
        $this->assertDatabaseHas('audit_logs', ['action' => 'student_list.undone', 'entity_id' => $first->importId]);
        $this->assertNotNull(StudentListImport::query()->findOrFail($first->importId)->undone_at);
    }

    public function test_top_students_are_imported_by_semester_and_the_semester_filter_works(): void
    {
        $path = $this->upload('top.docx', fn (string $file): string => DecisionDocxFactory::topStudents($file, $this->topStudents()));

        $result = $this->imports->import(self::FACULTY, 'honor_students', $path, 'top.docx', $this->choices(), $this->editor);

        $this->assertSame(3, $result->created);
        $student = HonorStudent::query()->where('student_identifier', '4229901')->firstOrFail();
        $this->assertSame([4, 'second', '2025/2026', 1, (int) $this->software->getKey()], [
            $student->study_year, $student->semester, $student->academic_year, $student->sort_order, (int) $student->department_id,
        ]);

        // The same student in another semester is another honor-list entry.
        $path = $this->upload('top-first.docx', fn (string $file): string => DecisionDocxFactory::topStudents($file, $this->topStudents(), 'للفصل الأول من العام الدراسي 2025/2026'));
        $preview = $this->imports->preview(self::FACULTY, 'honor_students', $path, 'top-first.docx');
        $this->assertSame(['first', 3, 0], [$preview->semester, $preview->createCount, $preview->updateCount]);

        $this->get('/ar/faculties/'.self::FACULTY.'/valedictorians?semester=second&q=نادر')
            ->assertOk()
            ->assertSee('نادر سليم الأحمد')
            ->assertSee('السنة الرابعة')
            ->assertSee('الفصل الثاني');
        $this->get('/ar/faculties/'.self::FACULTY.'/valedictorians?semester=first&q=نادر')
            ->assertOk()
            ->assertDontSee('نادر سليم الأحمد');
    }

    public function test_the_faculty_degree_gets_no_department_and_earlier_imports_are_repaired(): void
    {
        $facultyName = (string) $this->faculty->translations()->where('locale', 'ar')->value('name');
        $degree = 'دكتور في '.preg_replace('/^كلية\s+/u', '', $facultyName);
        $students = array_map(fn (array $student): array => ['specialization' => $degree] + $student, $this->graduates());
        $path = $this->upload('graduates.docx', fn (string $file): string => DecisionDocxFactory::graduates($file, $students));

        // As it was before the fix: an editor confirmed a department for the degree.
        $wrong = $this->imports->import(self::FACULTY, 'alumni', $path, 'graduates.docx', new StudentListImportChoicesDTO('2025/2026', 'second', [$degree => (int) $this->software->getKey()]), $this->editor);
        $this->assertSame(3, Alumni::query()->where('student_list_import_id', $wrong->importId)->where('department_id', $this->software->getKey())->count());

        // A new preview neither reuses that choice nor asks for one.
        $path = $this->upload('graduates.docx', fn (string $file): string => DecisionDocxFactory::graduates($file, $students));
        $preview = $this->imports->preview(self::FACULTY, 'alumni', $path, 'graduates.docx');
        $this->assertNull($preview->specializations[0]->departmentId);
        $this->assertFalse($preview->specializations[0]->needsChoice);
        $this->assertNotContains('departments_need_choice', $preview->warnings);

        $this->assertCount(1, $this->imports->repairFacultyWideDepartments(false));
        $this->assertSame(3, Alumni::query()->where('department_id', $this->software->getKey())->count());

        $this->assertCount(1, $this->imports->repairFacultyWideDepartments(true));
        $this->assertSame(3, Alumni::query()->where('student_list_import_id', $wrong->importId)->whereNull('department_id')->count());
        $this->assertSame([], $this->imports->repairFacultyWideDepartments(false));
    }

    public function test_top_students_of_two_semesters_side_by_side_are_imported(): void
    {
        $path = $this->upload('top-20252026.docx', fn (string $file): string => DecisionDocxFactory::topStudentsSideBySide($file, [
            'السنة الأولى( الفصل الأول )' => [['id' => '2250013', 'name' => 'محمد عدنان الطعمه'], ['id' => '2250079', 'name' => 'مسك خلدون العدوي']],
        ], [
            'السنة الأولى( الفصل الثاني )' => [['id' => '2250081', 'name' => 'انتصار عبد الحميد الخليلي'], ['id' => '2250013', 'name' => 'محمد عدنان الطعمه']],
        ]));

        $preview = $this->imports->preview(self::FACULTY, 'honor_students', $path, 'top 20252026.docx');
        $this->assertSame(['2025/2026', 4, 0], [$preview->academicYear, $preview->createCount, $preview->skipCount]);

        $result = $this->imports->import(self::FACULTY, 'honor_students', $path, 'top 20252026.docx', $this->choices(), $this->editor);

        $this->assertSame(4, $result->created);
        $this->assertSame(['first', 'second'], HonorStudent::query()->where('student_identifier', '2250013')->orderBy('semester')->pluck('semester')->all());
        $this->assertNull(HonorStudent::query()->where('student_identifier', '2250079')->value('gpa'));

        $this->get('/ar/faculties/'.self::FACULTY.'/valedictorians?semester=first&q=مسك')
            ->assertOk()
            ->assertSee('مسك خلدون العدوي');
    }

    public function test_imported_students_appear_even_when_an_editor_published_the_list(): void
    {
        $workflow = app(CmsWorkflowServiceInterface::class);
        $target = 'facilities.'.self::FACULTY.'.alumni';
        $payload = ['translations' => [
            'ar' => ['title' => 'الخريجون', 'items' => [['id' => 999999, 'title' => 'خريج منشور يدوياً']]],
            'en' => ['title' => 'Alumni', 'items' => [['id' => 999999, 'title' => 'Hand-published graduate']]],
        ]];
        $admin = User::query()->where('role_slug', 'super_admin')->firstOrFail();
        $workflow->saveDraft($target, $payload, (int) $admin->getKey());
        $workflow->publish($target, (int) $admin->getKey());

        $this->importGraduates();

        $this->get('/ar/faculties/'.self::FACULTY.'/alumni')
            ->assertOk()
            ->assertSee('خريج منشور يدوياً')
            ->assertSee('سامر خالد الحلبي');
    }

    public function test_only_users_who_may_publish_the_page_can_import(): void
    {
        $facultyEditor = $this->user('faculty_editor', self::FACULTY);
        $otherFacultyEditor = $this->user('editor', 'dentistry');

        $this->assertTrue($this->imports->canImport($this->editor, self::FACULTY, 'alumni'));
        $this->assertTrue($this->imports->canImport($facultyEditor, self::FACULTY, 'alumni'));
        $this->assertFalse($this->imports->canImport($otherFacultyEditor, self::FACULTY, 'alumni'));

        $path = $this->upload('graduates.docx', fn (string $file): string => DecisionDocxFactory::graduates($file, $this->graduates()));
        $this->expectExceptionMessage('student_lists.errors.forbidden');
        $this->imports->import(self::FACULTY, 'alumni', $path, 'graduates.docx', $this->choices(), $otherFacultyEditor);
    }

    public function test_the_wrong_kind_of_file_and_bad_choices_are_refused(): void
    {
        $topStudents = $this->upload('top.docx', fn (string $file): string => DecisionDocxFactory::topStudents($file, $this->topStudents()));
        $this->assertRefused('student_lists.errors.wrong_kind', fn () => $this->imports->preview(self::FACULTY, 'alumni', $topStudents, 'top.docx'));

        $graduates = $this->upload('graduates.docx', fn (string $file): string => DecisionDocxFactory::graduates($file, $this->graduates()));
        $this->assertRefused('student_lists.errors.bad_academic_year', fn () => $this->imports->import(self::FACULTY, 'alumni', $graduates, 'g.docx', new StudentListImportChoicesDTO('2025/2027', 'second'), $this->editor));
        $this->assertRefused('student_lists.errors.bad_semester', fn () => $this->imports->import(self::FACULTY, 'alumni', $graduates, 'g.docx', new StudentListImportChoicesDTO('2025/2026', 'third'), $this->editor));

        $otherFaculty = Department::query()->where('faculty_id', '!=', $this->faculty->getKey())->firstOrFail();
        $this->assertRefused('student_lists.errors.bad_department', fn () => $this->imports->import(self::FACULTY, 'alumni', $graduates, 'g.docx', new StudentListImportChoicesDTO('2025/2026', 'second', ['هندسة البرمجيات ونظم المعلومات' => (int) $otherFaculty->getKey()]), $this->editor));

        // Paths outside the upload directory are never read.
        Storage::disk('local')->put('secrets/other.docx', 'x');
        $this->assertRefused('student_lists.errors.upload_missing', fn () => $this->imports->preview(self::FACULTY, 'alumni', 'secrets/other.docx', 'x.docx'));
        $this->assertRefused('student_lists.errors.upload_missing', fn () => $this->imports->preview(self::FACULTY, 'alumni', 'student-list-imports/../secrets/other.docx', 'x.docx'));
    }

    public function test_rows_that_cannot_be_read_are_skipped_and_reported(): void
    {
        $students = $this->graduates();
        $students[1]['id'] = '';
        $students[2]['id'] = $students[0]['id'];
        $path = $this->upload('graduates.docx', fn (string $file): string => DecisionDocxFactory::graduates($file, $students));

        $preview = $this->imports->preview(self::FACULTY, 'alumni', $path, 'graduates.docx');

        $this->assertSame([1, 2], [$preview->createCount, $preview->skipCount]);
        $this->assertSame(['missing_id'], $preview->rows[1]->issues);
        $this->assertSame(['duplicate_in_file'], $preview->rows[2]->issues);
        $this->assertSame(StudentListImportRowDTO::ACTION_SKIP, $preview->rows[2]->action);
    }

    public function test_an_editor_imports_from_the_faculty_alumni_admin_page(): void
    {
        $this->actingAs($this->editor, 'web');
        $path = $this->upload('graduates.docx', fn (string $file): string => DecisionDocxFactory::graduates($file, $this->graduates()));

        $page = Livewire::withQueryParams(['target' => 'facilities.'.self::FACULTY.'.alumni'])
            ->test(ManageArtificialIntelligenceFaculty::class)
            ->assertActionVisible('importStudentList')
            ->assertActionVisible('studentListHistory');

        // The upload step stores the file and opens the review; here the stored
        // file is handed over the way the upload step does.
        $page->set('studentListUpload', $path)
            ->set('studentListUploadName', 'قرار 5.docx')
            ->mountAction('confirmStudentListImport')
            ->assertSee('سامر خالد الحلبي')
            ->assertSee('4200001')
            ->assertActionDataSet(['academic_year' => '2025/2026', 'semester' => 'second'])
            ->callMountedAction()
            ->assertHasNoActionErrors()
            ->assertNotified();

        $this->assertSame(3, Alumni::query()->whereIn('student_identifier', ['4200001', '4200002', '4200003'])->count());
        $this->assertSame(1, StudentListImport::query()->where('imported_by', $this->editor->getKey())->count());

        $page->mountAction('studentListHistory')
            ->assertSee('قرار 5.docx');
    }

    public function test_the_import_actions_are_hidden_on_other_pages_and_shown_to_the_faculty_editor(): void
    {
        $this->actingAs($this->editor, 'web');
        Livewire::withQueryParams(['target' => 'facilities.'.self::FACULTY.'.labs'])
            ->test(ManageArtificialIntelligenceFaculty::class)
            ->assertActionHidden('importStudentList');

        $this->actingAs($this->user('faculty_editor', self::FACULTY), 'web');
        Livewire::withQueryParams(['target' => 'facilities.'.self::FACULTY.'.valedictorians'])
            ->test(ManageArtificialIntelligenceFaculty::class)
            ->assertActionVisible('importStudentList');
    }

    private function importGraduates(): StudentListImportResultDTO
    {
        $path = $this->upload('graduates.docx', fn (string $file): string => DecisionDocxFactory::graduates($file, $this->graduates()));

        return $this->imports->import(self::FACULTY, 'alumni', $path, 'graduates.docx', $this->choices(), $this->editor);
    }

    private function choices(): StudentListImportChoicesDTO
    {
        return new StudentListImportChoicesDTO('2025/2026', 'second');
    }

    /** @param callable(string): string $build */
    private function upload(string $name, callable $build): string
    {
        $temporary = tempnam(sys_get_temp_dir(), 'decision');
        $build($temporary);
        $path = StudentListImportServiceInterface::UPLOAD_DIRECTORY.'/'.uniqid('t', false).'-'.$name;
        Storage::disk('local')->put($path, (string) file_get_contents($temporary));
        @unlink($temporary);

        return $path;
    }

    private function assertRefused(string $key, callable $call): void
    {
        try {
            $call();
            $this->fail('Expected '.$key);
        } catch (InvalidArgumentException $exception) {
            $this->assertSame($key, $exception->getMessage());
        }
    }

    private function department(string $slug, string $nameAr, string $nameEn): Department
    {
        $department = Department::query()->create([
            'faculty_id' => $this->faculty->getKey(),
            'slug' => $slug,
            'sort_order' => 90,
            'is_enabled' => true,
        ]);

        foreach (['ar' => $nameAr, 'en' => $nameEn] as $locale => $name) {
            DepartmentTranslation::query()->create(['department_id' => $department->getKey(), 'locale' => $locale, 'name' => $name]);
        }

        return $department;
    }

    private function user(string $role, ?string $scope = null): User
    {
        return User::factory()->create([
            'role_slug' => $role,
            'faculty_scopes' => $scope !== null ? [$scope] : null,
            'is_locked' => false,
        ]);
    }

    /** @return list<array{id: string, name: string, specialization: string, points: string, percent: string, grade: string}> */
    private function graduates(): array
    {
        return [
            ['id' => '4200001', 'name' => 'سامر خالد الحلبي', 'specialization' => 'الهندسة المعلوماتية/هندسة البرمجيات ونظم المعلومات', 'points' => '3.55', 'percent' => '91', 'grade' => 'امتياز'],
            ['id' => '4200002', 'name' => 'هبة ماهر يوسف', 'specialization' => 'الهندسة المعلوماتية/هندسة البرمجيات ونظم المعلومات', 'points' => '3.21', 'percent' => '84.2', 'grade' => 'جيد جداً'],
            ['id' => '4200003', 'name' => 'طارق نزار سليم', 'specialization' => 'هندسة الاتصالات و الشبكات', 'points' => '2.07', 'percent' => '61.4', 'grade' => 'مقبول'],
        ];
    }

    /** @return array<string, list<array{id: string, name: string, gpa: string}>> */
    private function topStudents(): array
    {
        return [
            'السنة الأولى' => [
                ['id' => '4250001', 'name' => 'ليلى سامي العلي', 'gpa' => '95.8'],
                ['id' => '4250002', 'name' => 'مازن فادي الخطيب', 'gpa' => '95.2'],
            ],
            'السنة الرابعة / هندسة البرمجيات ونظم المعلومات' => [
                ['id' => '4229901', 'name' => 'نادر سليم الأحمد', 'gpa' => '92.2'],
            ],
        ];
    }
}
