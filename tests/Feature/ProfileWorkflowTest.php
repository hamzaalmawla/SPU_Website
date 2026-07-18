<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Contracts\Cms\AboutEntityCmsServiceInterface;
use App\Contracts\Cms\CmsWorkflowServiceInterface;
use App\Contracts\Content\ProfileAdminServiceInterface;
use App\Contracts\Page\ProfilePageServiceInterface;
use App\DTOs\Content\EducationDataDTO;
use App\DTOs\Content\FacultyMemberDataDTO;
use App\DTOs\Content\FacultyMemberTranslationDataDTO;
use App\DTOs\Content\LocalizedEducationDataDTO;
use App\DTOs\Content\PersonDataDTO;
use App\DTOs\Content\PersonTranslationDataDTO;
use App\Filament\Resources\FacultyMemberResource;
use App\Models\Faculty\Department;
use App\Models\Faculty\Faculty;
use App\Models\Media\MediaAsset;
use App\Models\Person\FacultyMemberTranslation;
use App\Models\Person\Person;
use App\Models\Research\ResearchPublication;
use App\Models\Research\ResearchPublicationTranslation;
use App\Models\User\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

final class ProfileWorkflowTest extends TestCase
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

    public function test_person_aggregate_persists_localized_education_and_renders_canonical_profile(): void
    {
        $person = $this->adminService->createPerson($this->personData('profile-person'), (int) $this->admin->getKey());

        $this->assertNotNull($person->id);
        $this->assertDatabaseHas('person_educations', ['person_id' => $person->id, 'is_enabled' => true]);
        $this->assertDatabaseHas('person_education_translations', ['locale' => 'en', 'degree' => 'PhD']);
        $this->get('/en/about/profile/person/profile-person')->assertNotFound();
        $this->publishPerson((int) $person->id);

        $this->get('/en/about/profile/person/profile-person')
            ->assertOk()
            ->assertSee('English Profile')
            ->assertSee('PhD');

        $this->get('/en/about/profile?slug=profile-person')
            ->assertRedirect('/en/about/profile/person/profile-person');
    }

    public function test_shared_public_shell_assets_are_present(): void
    {
        foreach ([
            'images/logo-spu.png',
            'images/icon-search-outline.svg',
            'images/icon-map-outline.svg',
            'images/icon-envelope-outline.svg',
            'images/uni-main-place.JPG',
            'images/about-hero-1.webp',
            'images/about-hero-2.webp',
            'images/slider-1.webp',
        ] as $asset) {
            $this->assertFileExists(public_path($asset));
        }
    }

    public function test_profile_sources_do_not_collide_when_slugs_match(): void
    {
        $person = $this->adminService->createPerson($this->personData('shared-profile'), (int) $this->admin->getKey());
        $member = $this->adminService->createFacultyMember($this->facultyMemberData('shared-profile'), (int) $this->admin->getKey());
        $this->publishPerson((int) $person->id);
        $this->publishFacultyMember((int) $member->id);

        $this->get('/en/about/profile/person/shared-profile')
            ->assertOk()
            ->assertSee('English Profile');

        $this->get('/en/about/profile/faculty-member/shared-profile')
            ->assertOk()
            ->assertSee('English Faculty Member');
    }

    public function test_managed_faculty_member_appears_in_staff_directory_with_canonical_profile_link(): void
    {
        $faculty = Faculty::query()->where('public_slug', 'medicine')->firstOrFail();
        $member = $this->adminService->createFacultyMember(
            $this->facultyMemberData('directory-member', (int) $faculty->getKey()),
            (int) $this->admin->getKey(),
        );
        $this->get('/en/about/profile/faculty-member/directory-member')->assertNotFound();
        $this->publishFacultyMember((int) $member->id);

        $this->get('/en/about/directorates/staff?faculty=medicine')
            ->assertOk()
            ->assertSee('English Faculty Member')
            ->assertSee('/en/about/profile/faculty-member/directory-member', false);
    }

    public function test_missing_profile_and_education_translations_are_handled_without_errors(): void
    {
        $person = Person::query()->create([
            'slug' => 'missing-translations',
            'category' => 'director',
            'sort_order' => 0,
            'is_enabled' => true,
        ]);
        $person->educations()->create(['sort_order' => 0, 'is_enabled' => true]);

        $this->get('/en/about/profile/person/missing-translations')->assertNotFound();
    }

    public function test_legacy_specialization_shapes_are_normalized_before_rendering(): void
    {
        $memberData = $this->adminService->createFacultyMember($this->facultyMemberData('specialist'), (int) $this->admin->getKey());
        FacultyMemberTranslation::query()
            ->where('faculty_member_id', $memberData->id)
            ->where('locale', 'en')
            ->update(['specializations' => [['name' => 'Applied AI'], 'Digital Health', ['name' => 'Applied AI']]]);
        $this->publishFacultyMember((int) $memberData->id);

        $profile = app(ProfilePageServiceInterface::class)->getProfile('en', 'faculty-member', 'specialist');

        $this->assertNotNull($profile);
        $this->assertSame(['Applied AI', 'Digital Health'], $profile->specializations);
        $this->get('/en/about/profile/faculty-member/specialist')
            ->assertOk()
            ->assertSee('Applied AI');
    }

    public function test_faculty_profile_limits_publications(): void
    {
        $memberData = $this->adminService->createFacultyMember($this->facultyMemberData('researcher'), (int) $this->admin->getKey());

        foreach (range(1, 25) as $index) {
            $publication = ResearchPublication::query()->create([
                'faculty_member_id' => $memberData->id,
                'category_key' => 'article',
                'published_at' => now()->subDays($index),
                'sort_order' => $index,
                'is_enabled' => true,
            ]);
            ResearchPublicationTranslation::query()->create([
                'research_publication_id' => $publication->getKey(),
                'locale' => 'en',
                'title' => 'Publication '.$index,
            ]);
        }
        $this->publishFacultyMember((int) $memberData->id);

        $profile = app(ProfilePageServiceInterface::class)->getProfile('en', 'faculty-member', 'researcher');

        $this->assertNotNull($profile);
        $this->assertCount(20, $profile->publications);
    }

    public function test_department_must_belong_to_selected_faculty(): void
    {
        $firstFaculty = Faculty::query()->create(['slug' => 'first-faculty', 'sort_order' => 1, 'is_enabled' => true]);
        $secondFaculty = Faculty::query()->create(['slug' => 'second-faculty', 'sort_order' => 2, 'is_enabled' => true]);
        $department = Department::query()->create([
            'faculty_id' => $secondFaculty->getKey(),
            'slug' => 'second-department',
            'sort_order' => 0,
            'is_enabled' => true,
        ]);
        $data = $this->facultyMemberData('invalid-department', (int) $firstFaculty->getKey(), (int) $department->getKey());

        $this->expectException(ValidationException::class);

        $this->adminService->createFacultyMember($data, (int) $this->admin->getKey());
    }

    public function test_faculty_editor_is_scoped_to_assigned_faculty(): void
    {
        $firstFaculty = Faculty::query()->create([
            'slug' => 'scoped-faculty',
            'faculty_scope_slug' => 'scoped-faculty',
            'sort_order' => 1,
            'is_enabled' => true,
        ]);
        $secondFaculty = Faculty::query()->create([
            'slug' => 'other-faculty',
            'faculty_scope_slug' => 'other-faculty',
            'sort_order' => 2,
            'is_enabled' => true,
        ]);
        $firstMember = $this->adminService->createFacultyMember($this->facultyMemberData('scoped-member', (int) $firstFaculty->getKey()), (int) $this->admin->getKey());
        $this->adminService->createFacultyMember($this->facultyMemberData('other-member', (int) $secondFaculty->getKey()), (int) $this->admin->getKey());
        $editor = User::factory()->create([
            'role_slug' => 'faculty_editor',
            'faculty_scope_slug' => 'scoped-faculty',
            'is_locked' => false,
        ]);
        $this->actingAs($editor);

        $this->assertTrue(FacultyMemberResource::canAccess());
        $this->assertSame([$firstMember->id], FacultyMemberResource::getEloquentQuery()->pluck('faculty_members.id')->all());

        $this->expectException(AuthorizationException::class);
        $this->adminService->createFacultyMember(
            $this->facultyMemberData('forbidden-member', (int) $secondFaculty->getKey()),
            (int) $editor->getKey(),
        );
    }

    public function test_faculty_editor_cannot_move_an_out_of_scope_member_into_their_faculty(): void
    {
        $assignedFaculty = Faculty::query()->create([
            'slug' => 'assigned-faculty',
            'faculty_scope_slug' => 'assigned-faculty',
            'sort_order' => 1,
            'is_enabled' => true,
        ]);
        $otherFaculty = Faculty::query()->create([
            'slug' => 'outside-faculty',
            'faculty_scope_slug' => 'outside-faculty',
            'sort_order' => 2,
            'is_enabled' => true,
        ]);
        $member = $this->adminService->createFacultyMember(
            $this->facultyMemberData('outside-member', (int) $otherFaculty->getKey()),
            (int) $this->admin->getKey(),
        );
        $editor = User::factory()->create([
            'role_slug' => 'faculty_editor',
            'faculty_scope_slug' => 'assigned-faculty',
            'is_locked' => false,
        ]);

        $this->expectException(AuthorizationException::class);

        $this->adminService->updateFacultyMember(
            (int) $member->id,
            $this->facultyMemberData('outside-member', (int) $assignedFaculty->getKey()),
            (int) $editor->getKey(),
        );
    }

    public function test_faculty_media_ids_are_validated_and_persisted(): void
    {
        $photo = $this->mediaAsset('profile.jpg', 'image/jpeg', 'image');
        $cv = $this->mediaAsset('profile.pdf', 'application/pdf', 'pdf');

        $member = $this->adminService->createFacultyMember(
            $this->facultyMemberData('member-with-media', photoMediaId: (int) $photo->getKey(), cvMediaId: (int) $cv->getKey()),
            (int) $this->admin->getKey(),
        );

        $this->assertSame((int) $photo->getKey(), $member->photoMediaId);
        $this->assertSame((int) $cv->getKey(), $member->cvMediaId);
    }

    public function test_filament_profile_pages_build_for_super_admin(): void
    {
        $person = $this->adminService->createPerson($this->personData('admin-person'), (int) $this->admin->getKey());
        $member = $this->adminService->createFacultyMember($this->facultyMemberData('admin-member'), (int) $this->admin->getKey());
        $this->actingAs($this->admin);

        $this->get('/admin/people/create')->assertOk();
        $this->get('/admin/people/'.$person->id)->assertOk();
        $this->get('/admin/people/'.$person->id.'/edit')->assertOk();
        $this->get('/admin/faculty-members/create')->assertOk();
        $this->get('/admin/faculty-members/'.$member->id)->assertOk();
        $this->get('/admin/faculty-members/'.$member->id.'/edit')->assertOk();
    }

    private function personData(string $slug): PersonDataDTO
    {
        return new PersonDataDTO(
            id: null,
            slug: $slug,
            category: 'director',
            title: 'Dr.',
            position: 'Director',
            facultyScopeSlug: null,
            image: null,
            email: 'person@example.test',
            phone: null,
            officeLocation: null,
            profileUrl: null,
            socialLinks: null,
            sortOrder: 0,
            isEnabled: true,
            translations: [
                new PersonTranslationDataDTO('ar', 'ملف عربي', 'مدير', null, null),
                new PersonTranslationDataDTO('en', 'English Profile', 'Director', 'Profile biography', null),
            ],
            educations: [$this->educationData()],
        );
    }

    private function facultyMemberData(
        string $slug,
        ?int $facultyId = null,
        ?int $departmentId = null,
        ?int $photoMediaId = null,
        ?int $cvMediaId = null,
    ): FacultyMemberDataDTO {
        return new FacultyMemberDataDTO(
            id: null,
            slug: $slug,
            facultyId: $facultyId,
            departmentId: $departmentId,
            email: 'faculty@example.test',
            phone: null,
            officeLocation: null,
            photoMediaId: $photoMediaId,
            cvMediaId: $cvMediaId,
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

    private function mediaAsset(string $filename, string $mimeType, string $mediaType): MediaAsset
    {
        $path = 'media/'.$mediaType.'/'.$filename;

        return MediaAsset::query()->create([
            'disk' => 'public',
            'directory' => dirname($path),
            'filename' => $filename,
            'original_name' => $filename,
            'mime_type' => $mimeType,
            'extension' => pathinfo($filename, PATHINFO_EXTENSION),
            'size_bytes' => 100,
            'checksum' => hash('sha256', $path),
            'media_type' => $mediaType,
            'library_scope' => 'main',
            'metadata_status' => 'missing',
            'path' => $path,
        ]);
    }

    private function publishPerson(int $personId): void
    {
        $targetKey = 'entity.person.'.$personId;
        $payload = app(AboutEntityCmsServiceInterface::class)->getStoredData($targetKey)?->payload;
        $this->assertIsArray($payload);
        app(CmsWorkflowServiceInterface::class)->saveDraft($targetKey, $payload, (int) $this->admin->getKey());
        $this->assertTrue(app(CmsWorkflowServiceInterface::class)->publish($targetKey, (int) $this->admin->getKey()));
    }

    private function publishFacultyMember(int $memberId): void
    {
        $targetKey = 'entity.faculty-member.'.$memberId;
        $payload = app(AboutEntityCmsServiceInterface::class)->getStoredData($targetKey)?->payload;
        $this->assertIsArray($payload);
        app(CmsWorkflowServiceInterface::class)->saveDraft($targetKey, $payload, (int) $this->admin->getKey());
        $this->assertTrue(app(CmsWorkflowServiceInterface::class)->publish($targetKey, (int) $this->admin->getKey()));
    }
}
