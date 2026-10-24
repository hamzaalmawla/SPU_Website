<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Contracts\Cms\CmsWorkflowServiceInterface;
use App\Contracts\Page\FacultyPageServiceInterface;
use App\Enums\PublicationStatus;
use App\Filament\Pages\ManageDentistryFaculty;
use App\Models\Faculty\Faculty;
use App\Models\Person\FacultyMember;
use App\Models\User\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Each course names who teaches its theory and its practical part, by
 * faculty profile, and either part can be hidden.
 */
final class CourseTeachersTest extends TestCase
{
    use RefreshDatabase;

    private const TARGET = 'facilities.dentistry.study_plan';

    private User $admin;

    private FacultyMember $theory;

    private FacultyMember $practical;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);
        $this->admin = User::query()->where('role_slug', 'super_admin')->firstOrFail();
        $dentistry = Faculty::query()->where('public_slug', 'dentistry')->firstOrFail();
        $this->theory = $this->member('theory-teacher-test', 'Dr. Lina Theorist', 'د. لينا النظرية', (int) $dentistry->getKey());
        $this->practical = $this->member('practical-teacher-test', 'Dr. Omar Practician', 'د. عمر العملي', (int) $dentistry->getKey());
    }

    public function test_the_board_saves_both_teachers_and_whether_each_is_shown(): void
    {
        $this->actingAs($this->admin, 'web');
        $page = Livewire::withQueryParams(['target' => self::TARGET])->test(ManageDentistryFaculty::class);

        $page->call('newBoardCourse', '1')
            ->assertSet('boardCourse.theoryTeacherEnabled', true)
            ->assertSee('د. لينا النظرية')
            ->set('boardCourse.code', 'DEN-951')
            ->set('boardCourse.titleEn', 'Teachers Course')
            ->set('boardCourse.titleAr', 'مقرر المدرسين')
            ->set('boardCourse.theoryTeacherId', (string) $this->theory->getKey())
            ->set('boardCourse.practicalTeacherId', (string) $this->practical->getKey())
            ->set('boardCourse.practicalTeacherEnabled', false)
            ->call('saveBoardCourse')
            ->assertSet('boardCourse', null);

        $course = $this->course('den-951');
        $this->assertSame(['memberId' => (int) $this->theory->getKey(), 'enabled' => true], $course['teachers']['theory']);
        $this->assertSame(['memberId' => (int) $this->practical->getKey(), 'enabled' => false], $course['teachers']['practical']);

        // Reopening shows the choices, and other edits keep them.
        $page->call('openBoardCourse', 'den-951')
            ->assertSet('boardCourse.theoryTeacherId', (string) $this->theory->getKey())
            ->assertSet('boardCourse.practicalTeacherEnabled', false)
            ->set('boardCourse.titleEn', 'Renamed Teachers Course')
            ->call('saveBoardCourse');
        $this->assertSame((int) $this->practical->getKey(), $this->course('den-951')['teachers']['practical']['memberId']);

        $page->call('moveBoardCourse', 'den-951', '2');
        $this->assertSame((int) $this->theory->getKey(), $this->course('den-951')['teachers']['theory']['memberId']);
    }

    public function test_an_unknown_teacher_value_is_rejected(): void
    {
        $this->actingAs($this->admin, 'web');

        Livewire::withQueryParams(['target' => self::TARGET])->test(ManageDentistryFaculty::class)
            ->call('newBoardCourse', '1')
            ->set('boardCourse.code', 'DEN-952')
            ->set('boardCourse.titleEn', 'Bad Teacher')
            ->set('boardCourse.titleAr', 'مدرس خطأ')
            ->set('boardCourse.theoryTeacherId', 'drop table')
            ->call('saveBoardCourse')
            ->assertNotSet('boardCourse', null);

        $this->assertNull($this->course('den-952'));
    }

    public function test_the_course_page_shows_the_teachers_from_their_profiles_and_hides_a_switched_off_part(): void
    {
        $facilities = app(FacultyPageServiceInterface::class);
        $workflow = app(CmsWorkflowServiceInterface::class);
        $payload = $facilities->getEditablePayload(self::TARGET);
        $departmentId = (string) $payload['translations']['en']['payload']['plan']['departments'][0]['id'];
        $courseId = (string) $payload['translations']['en']['payload']['plan']['departments'][0]['terms'][0]['courses'][0]['id'];
        foreach (['ar', 'en'] as $locale) {
            $payload['translations'][$locale]['payload']['plan']['departments'][0]['terms'][0]['courses'][0]['teachers'] = [
                'theory' => ['memberId' => (int) $this->theory->getKey(), 'enabled' => true],
                'practical' => ['memberId' => (int) $this->practical->getKey(), 'enabled' => false],
            ];
        }
        $workflow->saveDraft(self::TARGET, $payload, (int) $this->admin->getKey());
        $this->assertTrue($workflow->publish(self::TARGET, (int) $this->admin->getKey()));

        // The name is the profile's as it is now.
        $this->theory->translations()->where('locale', 'en')->update(['full_name' => 'Prof. Lina Renamed']);
        Cache::flush();

        $path = '/en/faculties/dentistry/study-plan/course?department='.$departmentId.'&course='.$courseId;
        $this->get($path)
            ->assertOk()
            ->assertSee('Theory teacher')
            ->assertSee('Prof. Lina Renamed')
            ->assertSee('/en/about/profile/theory-teacher-test', false)
            ->assertDontSee('Practical teacher')
            ->assertDontSee('Dr. Omar Practician');
        $this->get('/ar/faculties/dentistry/study-plan/course?department='.$departmentId.'&course='.$courseId)
            ->assertOk()
            ->assertSee('مدرّس القسم النظري')
            ->assertSee('د. لينا النظرية');

        // A hidden profile is not shown.
        $this->theory->forceFill(['is_enabled' => false])->save();
        Cache::flush();
        $this->get($path)->assertOk()->assertDontSee('Theory teacher');
    }

    private function member(string $slug, string $en, string $ar, int $facultyId): FacultyMember
    {
        $member = FacultyMember::query()->create([
            'slug' => $slug,
            'faculty_id' => $facultyId,
            'is_enabled' => true,
            'publication_status' => PublicationStatus::Published->value,
            'published_at' => now()->subDay(),
        ]);
        $member->translations()->create(['locale' => 'en', 'full_name' => $en]);
        $member->translations()->create(['locale' => 'ar', 'full_name' => $ar]);

        return $member;
    }

    /** @return array<string, mixed>|null */
    private function course(string $id): ?array
    {
        $payload = app(CmsWorkflowServiceInterface::class)->latestEditableDraftPayload(self::TARGET, (int) $this->admin->getKey())
            ?? app(FacultyPageServiceInterface::class)->getEditablePayload(self::TARGET);

        foreach ($payload['translations']['en']['payload']['plan']['departments'] as $department) {
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
}
