<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Contracts\Cms\CmsWorkflowServiceInterface;
use App\Contracts\Page\FacultyPageServiceInterface;
use App\Filament\Pages\ManageDentistryFaculty;
use App\Models\Cms\CmsDraft;
use App\Models\User\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use Tests\TestCase;

final class AdminStudyPlanBoardTest extends TestCase
{
    use RefreshDatabase;

    private const TARGET = 'facilities.dentistry.study_plan';

    private User $editor;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);
        $this->editor = User::factory()->create(['role_slug' => 'editor', 'is_locked' => false]);
        $this->actingAs($this->editor, 'web');
    }

    public function test_the_page_shows_the_board_instead_of_the_old_term_form(): void
    {
        $this->withSession(['admin_locale' => 'en'])
            ->get('/admin/manage-dentistry-faculty?target='.self::TARGET)
            ->assertOk()
            ->assertSee('Plan board')
            ->assertSee('data-board-card', false)
            ->assertSee('Add term')
            ->assertDontSee('Bilingual term workspace')
            ->assertDontSee('Choose an academic term');
    }

    public function test_a_course_can_be_added_edited_moved_and_deleted(): void
    {
        $page = $this->page();
        $before = $this->courseCount();

        $page->call('newBoardCourse', '1')
            ->set('boardCourse.code', 'DEN-901')
            ->set('boardCourse.titleEn', 'Board Test Course')
            ->set('boardCourse.titleAr', 'مقرر تجريبي')
            ->set('boardCourse.credits', 3)
            ->call('saveBoardCourse')
            ->assertSet('boardCourse', null);

        $this->assertSame($before + 1, $this->courseCount());
        $course = $this->course('den-901');
        $this->assertSame('Board Test Course', $course['title']);
        $this->assertSame(1, $course['term']);

        $page->call('openBoardCourse', 'den-901')
            ->assertSet('boardCourse.code', 'DEN-901')
            ->set('boardCourse.titleEn', 'Renamed Course')
            ->set('boardCourse.termId', '2')
            ->call('saveBoardCourse');

        $this->assertSame('Renamed Course', $this->course('den-901')['title']);
        $this->assertSame(2, $this->course('den-901')['term']);

        $page->call('moveBoardCourse', 'den-901', '3');
        $this->assertSame(3, $this->course('den-901')['term']);

        $page->call('openBoardCourse', 'den-901')->call('deleteBoardCourse');
        $this->assertNull($this->course('den-901'));
        $this->assertSame($before, $this->courseCount());
    }

    public function test_an_invalid_course_is_not_saved(): void
    {
        $page = $this->page();
        $before = CmsDraft::query()->where('target_key', self::TARGET)->count();

        $page->call('newBoardCourse', '1')
            ->set('boardCourse.code', 'DEN-902')
            ->set('boardCourse.titleEn', 'Only English')
            ->set('boardCourse.titleAr', '')
            ->call('saveBoardCourse')
            ->assertNotSet('boardCourse', null);

        $this->assertSame($before, CmsDraft::query()->where('target_key', self::TARGET)->count());
    }

    public function test_terms_and_departments_can_be_managed(): void
    {
        $page = $this->page();
        $terms = $this->termCount();

        $page->call('addBoardTerm');
        $this->assertSame($terms + 1, $this->termCount());

        $page->call('removeBoardTerm', (string) ($terms + 1));
        $this->assertSame($terms, $this->termCount());

        $page->call('newBoardDepartment')
            ->set('boardDepartmentForm.nameEn', 'Oral Surgery')
            ->set('boardDepartmentForm.nameAr', 'جراحة الفم')
            ->call('saveBoardDepartment')
            ->assertSet('boardDepartmentForm', null)
            ->assertSet('boardDepartmentId', 'oral-surgery');

        $page->call('editBoardDepartment')
            ->set('boardDepartmentForm.nameEn', 'Oral and Maxillofacial Surgery')
            ->call('saveBoardDepartment');

        $names = array_column($this->payload()['translations']['en']['payload']['plan']['departments'], 'name', 'id');
        $this->assertSame('Oral and Maxillofacial Surgery', $names['oral-surgery']);

        $page->call('deleteBoardDepartment');
        $this->assertArrayNotHasKey('oral-surgery', array_column($this->payload()['translations']['en']['payload']['plan']['departments'], 'name', 'id'));
    }

    public function test_elective_groups_and_promotion_rules_are_edited_on_the_department_panel(): void
    {
        $page = $this->page()->call('editBoardDepartment');

        $page->call('addBoardPool')
            ->set('boardDepartmentForm.electivePools.0.id', 'faculty')
            ->set('boardDepartmentForm.electivePools.0.requiredHours', 4)
            ->set('boardDepartmentForm.electivePools.0.descriptionEn', 'Choose 4 hours')
            ->set('boardDepartmentForm.electivePools.0.descriptionAr', 'اختر 4 ساعات')
            ->call('addBoardPromotion')
            ->set('boardDepartmentForm.promotionRequirements.0.fromYear', 1)
            ->set('boardDepartmentForm.promotionRequirements.0.toYear', 2)
            ->set('boardDepartmentForm.promotionRequirements.0.requiredCredits', 60)
            ->call('saveBoardDepartment')
            ->assertSet('boardDepartmentForm', null);

        $department = $this->payload()['translations']['ar']['payload']['plan']['departments'][0];
        $pool = collect($department['electivePools'])->firstWhere('id', 'faculty');
        $this->assertSame(4, $pool['requiredHours']);
        $this->assertSame('اختر 4 ساعات', $pool['description']);
        $this->assertContains(60, array_column($department['promotionRequirements'], 'requiredCredits'));

        $page->call('editBoardDepartment')
            ->assertSet('boardDepartmentForm.electivePools.'.(count($department['electivePools']) - 1).'.descriptionEn', 'Choose 4 hours');
    }

    public function test_several_lecture_pdfs_can_be_uploaded_at_once(): void
    {
        Storage::fake((string) config('filesystems.media_disk', 'public'));
        $page = $this->page();
        $pdf = static fn (string $name): UploadedFile => UploadedFile::fake()->createWithContent($name, "%PDF-1.4\n1 0 obj\n<< /Type /Catalog >>\nendobj\ntrailer\n<< /Root 1 0 R >>\n%%EOF\n");

        $page->call('newBoardCourse', '1')
            ->set('boardCourse.code', 'DEN-903')
            ->set('boardCourse.titleEn', 'Lecture Course')
            ->set('boardCourse.titleAr', 'مقرر المحاضرات')
            ->call('saveBoardCourse')
            ->call('openBoardCourse', 'den-903')
            ->set('lectureUploads', [$pdf('Week 1 Intro.pdf'), $pdf('Week_2-Anatomy.pdf')])
            ->call('uploadBoardLectures')
            ->assertHasNoErrors();

        $lessons = $this->course('den-903')['lessons'];
        $this->assertCount(2, $lessons);
        $this->assertSame('Week 1 Intro', $lessons[0]['title']);
        $this->assertSame('Week 2 Anatomy', $lessons[1]['title']);
        $this->assertStringStartsWith('/storage/', $lessons[0]['pdfUrl']);
        $this->assertStringEndsWith('.pdf', $lessons[1]['pdfUrl']);
        $this->assertSame([1, 2], array_column($lessons, 'order'));
    }

    public function test_saving_the_course_uploads_chosen_lectures_and_specification(): void
    {
        Storage::fake((string) config('filesystems.media_disk', 'public'));
        $page = $this->page();
        $pdf = static fn (string $name): UploadedFile => UploadedFile::fake()->createWithContent($name, "%PDF-1.4\n1 0 obj\n<< /Type /Catalog >>\nendobj\ntrailer\n<< /Root 1 0 R >>\n%%EOF\n");

        $page->call('newBoardCourse', '1')
            ->set('boardCourse.code', 'DEN-906')
            ->set('boardCourse.titleEn', 'Saved With Files')
            ->set('boardCourse.titleAr', 'مقرر مع ملفات')
            ->call('saveBoardCourse')
            ->call('openBoardCourse', 'den-906')
            ->set('lectureUploads', [$pdf('Week 1.pdf')])
            ->set('specificationUpload', $pdf('Specification.pdf'))
            ->call('saveBoardCourse')
            ->assertHasNoErrors()
            ->assertSet('boardCourse', null)
            ->assertSet('lectureUploads', []);

        $course = $this->course('den-906');
        $this->assertSame(['Week 1'], array_column($course['lessons'], 'title'));
        $this->assertStringEndsWith('.pdf', $course['specificationUrl']);

        $page->call('openBoardCourse', 'den-906')
            ->assertCount('boardCourse.lessons', 1)
            ->assertSet('boardCourse.specificationUrl', $course['specificationUrl']);
    }

    public function test_saved_prerequisites_are_listed_first(): void
    {
        $page = $this->page();
        $board = $page->instance()->studyPlanBoardData();
        $ids = [];
        foreach ($board['terms'] as $term) {
            foreach ($term['courses'] as $card) {
                $ids[] = $card['id'];
            }
        }
        $last = $ids[count($ids) - 1];
        $first = $ids[0];

        $page->call('openBoardCourse', $first)
            ->set('boardCourse.prerequisites', [$last])
            ->call('saveBoardCourse')
            ->call('openBoardCourse', $first)
            ->assertSet('boardCourse.prerequisites', [$last]);

        $this->assertSame((string) $last, (string) array_key_first($page->instance()->boardPrerequisiteChoices()));
    }

    public function test_non_pdf_lectures_are_rejected(): void
    {
        Storage::fake((string) config('filesystems.media_disk', 'public'));
        $page = $this->page();

        $page->call('newBoardCourse', '1')
            ->set('boardCourse.code', 'DEN-904')
            ->set('boardCourse.titleEn', 'No Lectures')
            ->set('boardCourse.titleAr', 'بلا محاضرات')
            ->call('saveBoardCourse')
            ->call('openBoardCourse', 'den-904')
            ->set('lectureUploads', [UploadedFile::fake()->create('notes.txt', 10, 'text/plain')])
            ->call('uploadBoardLectures')
            ->assertHasErrors(['lectureUploads.0']);

        $this->assertSame([], $this->course('den-904')['lessons']);
    }

    public function test_saving_the_form_does_not_overwrite_board_changes(): void
    {
        $page = $this->page();
        $page->call('newBoardCourse', '1')
            ->set('boardCourse.code', 'DEN-905')
            ->set('boardCourse.titleEn', 'Survives Save')
            ->set('boardCourse.titleAr', 'يبقى بعد الحفظ')
            ->call('saveBoardCourse')
            ->call('save');

        $this->assertNotNull($this->course('den-905'));
    }

    private function page(): Testable
    {
        return Livewire::withQueryParams(['target' => self::TARGET])->test(ManageDentistryFaculty::class);
    }

    /** @return array<string, mixed> */
    private function payload(): array
    {
        $payload = app(CmsWorkflowServiceInterface::class)->latestEditableDraftPayload(self::TARGET, (int) $this->editor->getKey());

        if (! is_array($payload)) {
            $payload = app(FacultyPageServiceInterface::class)->getEditablePayload(self::TARGET);
        }

        return $payload;
    }

    /** @return array<string, mixed>|null */
    private function course(string $id): ?array
    {
        foreach ($this->payload()['translations']['en']['payload']['plan']['departments'] as $department) {
            foreach ($department['terms'] as $term) {
                foreach ($term['courses'] as $course) {
                    if ($course['id'] === $id) {
                        return $course;
                    }
                }
            }
        }

        return null;
    }

    private function courseCount(): int
    {
        $count = 0;

        foreach ($this->payload()['translations']['en']['payload']['plan']['departments'][0]['terms'] as $term) {
            $count += count($term['courses']);
        }

        return $count;
    }

    private function termCount(): int
    {
        return count($this->payload()['translations']['en']['payload']['plan']['departments'][0]['terms']);
    }
}
