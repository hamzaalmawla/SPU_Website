<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Contracts\Cms\AboutEntityCmsServiceInterface;
use App\Contracts\Cms\CmsWorkflowServiceInterface;
use App\Contracts\Content\ProfileAdminServiceInterface;
use App\Contracts\Page\FacultyPageServiceInterface;
use App\DTOs\Content\EducationDataDTO;
use App\DTOs\Content\FacultyMemberDataDTO;
use App\DTOs\Content\FacultyMemberTranslationDataDTO;
use App\DTOs\Content\LocalizedEducationDataDTO;
use App\Filament\Pages\ManageMedicineFaculty;
use App\Models\Faculty\Faculty;
use App\Models\Faculty\FacultySubpageCard;
use App\Models\User\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Livewire\Livewire;
use Tests\TestCase;

final class FacultyMembersPageTest extends TestCase
{
    use RefreshDatabase;

    private ProfileAdminServiceInterface $adminService;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);
        $this->adminService = app(ProfileAdminServiceInterface::class);
        $this->admin = User::query()->where('role_slug', 'super_admin')->firstOrFail();
    }

    public function test_members_subpage_renders_professor_cards_linked_to_profiles(): void
    {
        $faculty = Faculty::query()->where('public_slug', 'medicine')->firstOrFail();
        $member = $this->adminService->createFacultyMember(
            $this->memberData('member-card', (int) $faculty->getKey()),
            (int) $this->admin->getKey(),
        );
        $this->publishMember((int) $member->id);

        $this->get('/en/faculties/medicine/members')
            ->assertOk()
            ->assertSee('Faculty Members', false)
            ->assertSee('English Faculty Member')
            ->assertSee('Lecturer')
            ->assertSee('/en/about/profile/member-card', false);

        $this->get('/ar/faculties/medicine/members')
            ->assertOk()
            ->assertSee('أعضاء الهيئة الأكاديمية')
            ->assertSee('عضو هيئة')
            ->assertSee('مدرس')
            ->assertSee('/ar/about/profile/member-card', false);
    }

    public function test_members_subpage_renders_rtl_direction_for_arabic(): void
    {
        $this->get('/ar/faculties/medicine/members')
            ->assertOk()
            ->assertSee('<html lang="ar" dir="rtl">', false);

        $this->get('/en/faculties/medicine/members')
            ->assertOk()
            ->assertSee('<html lang="en" dir="ltr">', false);
    }

    public function test_members_subpage_links_to_other_locale_preserving_path(): void
    {
        $this->get('/en/faculties/medicine/members')
            ->assertOk()
            ->assertSee('/ar/faculties/medicine/members', false);

        $this->get('/ar/faculties/medicine/members')
            ->assertOk()
            ->assertSee('/en/faculties/medicine/members', false);
    }

    public function test_only_published_members_appear_on_the_page(): void
    {
        $faculty = Faculty::query()->where('public_slug', 'dentistry')->firstOrFail();
        $published = $this->adminService->createFacultyMember(
            $this->memberData('published-member', (int) $faculty->getKey()),
            (int) $this->admin->getKey(),
        );
        $draft = $this->adminService->createFacultyMember(
            $this->memberData('draft-member', (int) $faculty->getKey()),
            (int) $this->admin->getKey(),
        );
        $this->publishMember((int) $published->id);

        $this->get('/en/faculties/dentistry/members')
            ->assertOk()
            ->assertSee('English Faculty Member')
            ->assertSee('/en/about/profile/published-member', false)
            ->assertDontSee('/en/about/profile/draft-member', false);
    }

    public function test_members_subpage_404s_when_card_is_hidden(): void
    {
        $this->get('/en/faculties/medicine/members')->assertOk();

        FacultySubpageCard::query()
            ->where('faculty_slug', 'medicine')
            ->where('subpage_slug', 'members')
            ->update(['is_visible' => false]);

        Cache::flush();

        $this->get('/en/faculties/medicine/members')->assertNotFound();
        $this->get('/en/faculties/medicine')->assertOk();
    }

    public function test_members_workspace_target_is_editable_and_can_be_published(): void
    {
        $facilities = app(FacultyPageServiceInterface::class);
        $targetKey = 'facilities.medicine.members';

        $payload = $facilities->getEditablePayload($targetKey);

        $this->assertArrayHasKey('translations', $payload);
        $this->assertArrayHasKey('ar', $payload['translations']);
        $this->assertArrayHasKey('en', $payload['translations']);

        $payload['translations']['en']['title'] = 'Published Faculty Members';
        $workflow = app(CmsWorkflowServiceInterface::class);
        $workflow->saveDraft($targetKey, $payload, (int) $this->admin->getKey());

        $this->get('/en/faculties/medicine/members')->assertDontSee('Published Faculty Members');

        $this->assertTrue($workflow->publish($targetKey, (int) $this->admin->getKey()));

        $this->get('/en/faculties/medicine/members')
            ->assertOk()
            ->assertSee('Published Faculty Members');
    }

    public function test_members_subpage_appears_in_faculty_highlights_section(): void
    {
        $this->get('/en/faculties/medicine')
            ->assertOk()
            ->assertSee('/en/faculties/medicine/members', false);

        $this->get('/ar/faculties/medicine')
            ->assertOk()
            ->assertSee('/ar/faculties/medicine/members', false);
    }

    public function test_members_workspace_target_renders_its_editor_without_errors(): void
    {
        $this->actingAs($this->admin, 'web');

        Livewire::test(ManageMedicineFaculty::class)
            ->set('data.target_key', 'facilities.medicine.members')
            ->call('loadTarget', 'facilities.medicine.members')
            ->assertOk()
            ->assertSee('مقدمة الصفحة');
    }

    public function test_members_page_renders_each_faculty_independently(): void
    {
        $medicine = Faculty::query()->where('public_slug', 'medicine')->firstOrFail();
        $business = Faculty::query()->where('public_slug', 'business-administration')->firstOrFail();
        $member = $this->adminService->createFacultyMember(
            $this->memberData('medicine-only-member', (int) $medicine->getKey()),
            (int) $this->admin->getKey(),
        );
        $this->publishMember((int) $member->id);

        $this->get('/en/faculties/medicine/members')
            ->assertOk()
            ->assertSee('/en/about/profile/medicine-only-member', false);

        $this->get('/en/faculties/business-administration/members')
            ->assertOk()
            ->assertDontSee('/en/about/profile/medicine-only-member', false);
    }

    /** @return array<string, mixed> */
    private function memberData(string $slug, ?int $facultyId = null): FacultyMemberDataDTO
    {
        return new FacultyMemberDataDTO(
            id: null,
            slug: $slug,
            facultyId: $facultyId,
            departmentId: null,
            email: 'faculty@example.test',
            phone: null,
            officeLocation: null,
            photoMediaId: null,
            cvMediaId: null,
            socialLinks: null,
            sortOrder: 0,
            isEnabled: true,
            translations: [
                new FacultyMemberTranslationDataDTO('ar', 'عضو هيئة', null, 'مدرس', null, ['الذكاء الصنعي']),
                new FacultyMemberTranslationDataDTO('en', 'English Faculty Member', null, 'Lecturer', null, ['Applied AI']),
            ],
            educations: [$this->educationData()],
        );
    }

    private function educationData(): EducationDataDTO
    {
        return new EducationDataDTO(
            id: null,
            sortOrder: 0,
            isEnabled: true,
            translations: [
                new LocalizedEducationDataDTO('ar', 'دكتوراه', 'الجامعة السورية الخاصة', null, 2018, 2022, null),
                new LocalizedEducationDataDTO('en', 'PhD', 'Syrian Private University', null, 2018, 2022, null),
            ],
        );
    }

    private function publishMember(int $memberId): void
    {
        $targetKey = 'entity.faculty-member.'.$memberId;
        $payload = app(AboutEntityCmsServiceInterface::class)->getStoredData($targetKey)?->payload;
        $this->assertIsArray($payload);
        app(CmsWorkflowServiceInterface::class)->saveDraft($targetKey, $payload, (int) $this->admin->getKey());
        $this->assertTrue(app(CmsWorkflowServiceInterface::class)->publish($targetKey, (int) $this->admin->getKey()));
    }
}
