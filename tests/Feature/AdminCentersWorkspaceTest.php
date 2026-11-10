<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Contracts\Cms\AboutEntityCmsServiceInterface;
use App\Contracts\Cms\CmsWorkflowServiceInterface;
use App\Filament\Pages\ManageCenters;
use App\Filament\Resources\FacultyMemberResource\Pages\CreateFacultyMember;
use App\Models\Faculty\Faculty;
use App\Models\Person\FacultyMember;
use App\Models\User\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Admin › Centers edits the centers page and each center's page, staff page and
 * course list through draft, preview and publish.
 */
final class AdminCentersWorkspaceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);
    }

    public function test_an_editor_opens_each_center_page_in_both_admin_languages(): void
    {
        $editor = $this->editor();

        foreach (['centers.landing', 'centers.requirements-unit', 'centers.requirements-unit.staff', 'centers.requirements-unit.courses'] as $target) {
            $this->actingAs($editor, 'web')->withSession(['admin_locale' => 'en'])
                ->get('/admin/manage-centers?target='.$target)
                ->assertOk();
        }

        $this->actingAs($editor, 'web')->withSession(['admin_locale' => 'ar'])
            ->get('/admin/manage-centers?target=centers.requirements-unit.courses')
            ->assertOk()
            ->assertSee('المقررات');
    }

    public function test_a_user_without_the_centers_permission_cannot_open_it(): void
    {
        $user = User::factory()->create(['role_slug' => 'hr', 'is_locked' => false]);

        $this->actingAs($user, 'web')->get('/admin/manage-centers')->assertForbidden();
    }

    public function test_editing_a_course_and_publishing_changes_the_public_list(): void
    {
        $this->actingAs($this->editor(), 'web');

        $component = Livewire::withQueryParams(['target' => 'centers.requirements-unit.courses'])
            ->test(ManageCenters::class)
            ->assertSet('data.target_key', 'centers.requirements-unit.courses');

        $courses = $component->get('data.courses');
        $key = array_key_first(array_filter($courses, fn (array $course): bool => $course['id'] === 'introduction-to-law'));
        $component->set('data.courses.'.$key.'.titleEn', 'Introduction to Syrian Law')
            ->set('data.courses.'.$key.'.required', true)
            ->call('publish')
            ->assertHasNoErrors();

        $this->get('/en/centers/requirements-unit/required-courses')->assertOk()
            ->assertSee('id="course-introduction-to-law"', false)
            ->assertSee('Introduction to Syrian Law');
        $this->get('/en/centers/requirements-unit/elective-courses')->assertOk()
            ->assertDontSee('id="course-introduction-to-law"', false);
    }

    public function test_the_draft_preview_renders_unpublished_changes(): void
    {
        $editor = $this->editor();
        $this->actingAs($editor, 'web');

        Livewire::withQueryParams(['target' => 'centers.landing'])
            ->test(ManageCenters::class)
            ->set('data.en.hero.title', 'Draft Centers Title')
            ->call('save');

        $preview = app(CmsWorkflowServiceInterface::class)->preview('centers.landing', 'en', (int) $editor->getKey());

        $this->get($preview->previewUrl)->assertOk()->assertSee('Draft Centers Title');
        $this->get('/en/centers')->assertOk()->assertDontSee('Draft Centers Title');
    }

    public function test_a_center_staff_profile_is_created_without_a_faculty_and_published_to_the_staff_page(): void
    {
        $this->actingAs($this->editor(), 'web');

        Livewire::test(CreateFacultyMember::class)
            ->fillForm([
                'faculty_id' => null,
                'center_slug' => 'requirements-unit',
                'translations.ar.full_name' => 'أ. مدرسة اللغة',
                'translations.en.full_name' => 'Language Teacher',
                'translations.ar.position' => 'مدرّسة',
                'translations.en.position' => 'Lecturer',
                'is_enabled' => true,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $member = FacultyMember::query()->where('slug', 'language-teacher')->firstOrFail();
        $this->assertSame('requirements-unit', $member->center_slug);
        $this->assertNull($member->faculty_id);

        app(CmsWorkflowServiceInterface::class)->publish('entity.faculty-member.'.$member->getKey(), (int) auth()->id());

        $this->get('/en/centers/requirements-unit/staff')->assertOk()->assertSee('Language Teacher');
        $this->get('/ar/about/profile/language-teacher')->assertOk()->assertSee('وحدة المتطلبات');
    }

    public function test_a_profile_cannot_belong_to_a_faculty_and_a_center(): void
    {
        $payload = ['slug' => 'both', 'faculty_id' => Faculty::query()->value('id'), 'center_slug' => 'requirements-unit', 'translations' => ['ar' => ['full_name' => 'س'], 'en' => ['full_name' => 'S']]];

        $errors = app(AboutEntityCmsServiceInterface::class)->publishErrors('entity.faculty-member.999', $payload);

        $this->assertArrayHasKey('center_slug', $errors);
    }

    private function editor(): User
    {
        return User::factory()->create(['role_slug' => 'editor', 'is_locked' => false]);
    }
}
