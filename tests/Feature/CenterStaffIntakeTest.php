<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Contracts\Center\CenterCatalogServiceInterface;
use App\Contracts\Staff\StaffIntakeLinkServiceInterface;
use App\Contracts\Staff\StaffIntakeReviewServiceInterface;
use App\DTOs\Staff\StaffIntakeLinkCreatedDTO;
use App\DTOs\Staff\StaffIntakeLinkInputDTO;
use App\Enums\PublicationStatus;
use App\Filament\Resources\CenterStaffLinkResource;
use App\Filament\Resources\CenterStaffLinkResource\Pages\ListCenterStaffLinks;
use App\Filament\Resources\CenterStaffRequestResource;
use App\Filament\Resources\CenterStaffRequestResource\Pages\ListCenterStaffRequests;
use App\Filament\Resources\CenterStaffRequestResource\Pages\ViewCenterStaffRequest;
use App\Filament\Resources\StaffIntakeLinkResource\Pages\ListStaffIntakeLinks;
use App\Filament\Resources\StaffIntakeRequestResource\Pages\ListStaffIntakeRequests;
use App\Models\Faculty\Faculty;
use App\Models\Person\FacultyMember;
use App\Models\Staff\StaffIntakeLink;
use App\Models\Staff\StaffIntakeRequest;
use App\Models\User\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Staff intake links for centers: the faculty staff links, with the center
 * (config/centers.php) in place of the faculty. Approved profiles have no
 * faculty, carry the center's slug and show on the center's staff page.
 */
final class CenterStaffIntakeTest extends TestCase
{
    use RefreshDatabase;

    private const CENTER = 'requirements-unit';

    private User $admin;

    private Faculty $dentistry;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
        Storage::fake((string) config('filesystems.media_disk', 'public'));
        $this->seed(DatabaseSeeder::class);
        $this->dentistry = Faculty::query()->where('public_slug', 'dentistry')->firstOrFail();
        $this->admin = $this->user('super_admin');
        Mail::fake();
    }

    public function test_only_super_admins_can_make_center_links_or_review_their_requests_for_now(): void
    {
        foreach (['editor', 'faculty_editor', 'hr'] as $role) {
            $user = $this->user($role);
            $this->assertFalse(app(StaffIntakeLinkServiceInterface::class)->canUseCenters((int) $user->getKey()), $role.' can use center links.');

            $this->actingAs($user, 'web');
            $this->get(CenterStaffLinkResource::getUrl('index'))->assertForbidden();
            $this->get(CenterStaffRequestResource::getUrl('index'))->assertForbidden();
        }

        $this->expectException(AuthorizationException::class);
        app(StaffIntakeLinkServiceInterface::class)->create(
            new StaffIntakeLinkInputDTO(null, null, null, null, self::CENTER),
            (int) $this->user('editor')->getKey(),
        );
    }

    public function test_a_center_teacher_updates_their_profile_and_it_shows_on_the_staff_page(): void
    {
        $member = $this->member('maya-unit', 'مايا يونت', 'Maya Unit', center: self::CENTER);
        $created = $this->link();
        $this->assertNull($created->link->facultyId);
        $this->assertSame(self::CENTER, $created->link->centerSlug);
        $token = $this->token($created);

        $this->get('/en/staff-intake/'.$token)->assertOk()
            ->assertSee('Center staff details')
            ->assertSee('Requirements Unit');

        $url = (string) $this->getJson('/en/staff-intake/'.$token.'/suggestions?q=maya+unit')->assertOk()->json('items.0.url');
        $form = $this->get($url)->assertOk()->assertDontSee('name="department_id"', false);
        $ref = $this->formRef($form->getContent());

        // A center link requires the title, position, email and phone too; the rest stays optional.
        $this->from('/en/staff-intake/'.$token.'/form')
            ->post('/en/staff-intake/'.$token.'/form', ['email' => '', 'phone' => '', 'title_ar' => '', 'title_en' => ''] + $this->formData($ref, ['name_ar' => 'مايا يونت', 'name_en' => 'Maya Unit', 'email' => '', 'phone' => '', 'title_ar' => '', 'title_en' => '']))
            ->assertSessionHasErrors(['title_ar', 'title_en', 'position_ar', 'position_en', 'email', 'phone'])
            ->assertSessionDoesntHaveErrors(['office_location', 'linkedin', 'orcid', 'specializations_ar']);

        $this->post('/en/staff-intake/'.$token.'/form', $this->formData($ref, [
            'name_ar' => 'مايا يونت',
            'name_en' => 'Maya Unit',
            'position_ar' => 'منسقة الوحدة',
            'position_en' => 'Unit coordinator',
            'department_id' => 999999,
        ]))->assertRedirect('/en/staff-intake/'.$token.'/sent');

        $request = StaffIntakeRequest::query()->sole();
        $this->assertNull($request->faculty_id);
        $this->assertSame(self::CENTER, $request->center_slug);
        $this->assertSame(StaffIntakeRequest::TYPE_UPDATE, $request->type);
        $this->assertSame((int) $member->getKey(), (int) $request->faculty_member_id);
        $this->assertNull($request->payload['fields']['department_id']);

        // Faculty reviewers neither count nor decide center requests.
        $editor = (int) $this->user('editor')->getKey();
        $this->assertSame(0, app(StaffIntakeReviewServiceInterface::class)->pendingCount($editor));
        $this->assertFalse(app(StaffIntakeReviewServiceInterface::class)->canDecide((int) $request->getKey(), $editor));
        $this->assertSame(1, app(StaffIntakeReviewServiceInterface::class)->pendingCenterCount((int) $this->admin->getKey()));

        $result = app(StaffIntakeReviewServiceInterface::class)->approve((int) $request->getKey(), (int) $this->admin->getKey());

        $this->assertTrue($result->published, implode("\n", $result->publishErrors));
        $this->assertSame((int) $member->getKey(), $result->facultyMemberId);
        $this->assertNull($member->fresh()?->faculty_id);
        $this->assertSame(self::CENTER, $member->fresh()?->center_slug);

        $this->get('/en/centers/requirements-unit/staff')->assertOk()
            ->assertSee('Maya Unit')
            ->assertSee('Unit coordinator');
    }

    public function test_someone_new_gets_a_center_profile_with_no_faculty(): void
    {
        $token = $this->token($this->link());
        $ref = $this->formRef($this->get('/ar/staff-intake/'.$token.'/form?name='.urlencode('رامي يونتنيو'))->assertOk()->getContent());

        $this->post('/ar/staff-intake/'.$token.'/form', $this->formData($ref, [
            'name_ar' => 'رامي يونتنيو',
            'name_en' => 'Rami Unitnew',
            'position_ar' => 'مدرّس',
            'position_en' => 'Lecturer',
        ]))->assertRedirect('/ar/staff-intake/'.$token.'/sent');

        $request = StaffIntakeRequest::query()->sole();
        $this->assertSame(StaffIntakeRequest::TYPE_CREATE, $request->type);

        $result = app(StaffIntakeReviewServiceInterface::class)->approve((int) $request->getKey(), (int) $this->admin->getKey());

        $member = FacultyMember::query()->findOrFail($result->facultyMemberId);
        $this->assertNull($member->faculty_id);
        $this->assertSame(self::CENTER, $member->center_slug);
        $this->assertSame(PublicationStatus::Published->value, $member->publication_status);
        $this->get('/ar/centers/requirements-unit/staff')->assertOk()->assertSee('رامي يونتنيو');
    }

    public function test_a_faculty_profile_is_only_a_starting_point_for_a_center_profile_and_back(): void
    {
        $facultyMember = $this->member('hadi-faculty', 'هادي فاكلتي', 'Hadi Faculty', faculty: $this->dentistry);
        $token = $this->token($this->link());

        $url = (string) $this->getJson('/en/staff-intake/'.$token.'/suggestions?q=hadi+faculty')->json('items.0.url');
        $this->get($url)->assertOk()->assertSee('A profile in this center will be added');
        $this->post('/en/staff-intake/'.$token.'/form', $this->formData($this->formRef($this->get($url)->getContent()), [
            'name_ar' => 'هادي فاكلتي',
            'name_en' => 'Hadi Faculty',
            'position_ar' => 'مدرّس',
            'position_en' => 'Lecturer',
        ]))->assertRedirect('/en/staff-intake/'.$token.'/sent');

        $request = StaffIntakeRequest::query()->sole();
        $this->assertSame(StaffIntakeRequest::TYPE_CREATE, $request->type);
        $this->assertSame((int) $facultyMember->getKey(), (int) $request->related_faculty_member_id);

        $result = app(StaffIntakeReviewServiceInterface::class)->approve((int) $request->getKey(), (int) $this->admin->getKey());
        $this->assertNotSame((int) $facultyMember->getKey(), $result->facultyMemberId);
        $this->assertSame((int) $this->dentistry->getKey(), (int) $facultyMember->fresh()?->faculty_id, 'The faculty profile was moved.');

        // A faculty link does not pull a center's profile into the faculty either.
        $centerMember = $this->member('nour-center', 'نور سنتر', 'Nour Center', center: self::CENTER);
        $facultyLink = app(StaffIntakeLinkServiceInterface::class)->create(
            new StaffIntakeLinkInputDTO((int) $this->dentistry->getKey(), null, null, null),
            (int) $this->admin->getKey(),
        );
        $facultyToken = $this->token($facultyLink);
        $url = (string) $this->getJson('/en/staff-intake/'.$facultyToken.'/suggestions?q=nour+center')->json('items.0.url');
        $this->post('/en/staff-intake/'.$facultyToken.'/form', $this->formData($this->formRef($this->get($url)->getContent()), [
            'name_ar' => 'نور سنتر',
            'name_en' => 'Nour Center',
            'email' => 'nour@spu.edu.sy',
            'phone' => '+963 944 000 111',
        ]))->assertRedirect('/en/staff-intake/'.$facultyToken.'/sent');
        $facultyRequest = StaffIntakeRequest::query()->latest('id')->firstOrFail();
        $this->assertSame(StaffIntakeRequest::TYPE_CREATE, $facultyRequest->type);
        $this->assertSame((int) $centerMember->getKey(), (int) $facultyRequest->related_faculty_member_id);
    }

    public function test_the_admin_screens_make_center_links_and_review_requests_apart_from_the_faculties(): void
    {
        $this->actingAs($this->admin, 'web');
        $this->get(CenterStaffLinkResource::getUrl('index'))->assertOk()->assertSee(__('staff_intake.center.links_intro'));

        Livewire::test(ListCenterStaffLinks::class)
            ->callAction('create_link', ['center_slug' => self::CENTER, 'label' => 'Unit group'])
            ->assertHasNoActionErrors()
            ->assertNotified();
        $link = StaffIntakeLink::query()->where('label', 'Unit group')->sole();
        $this->assertSame(self::CENTER, $link->center_slug);
        $this->assertNull($link->faculty_id);
        Livewire::test(ListCenterStaffLinks::class)->assertCanSeeTableRecords([$link])->callTableAction('show_link', $link)->assertNotified();
        Livewire::test(ListStaffIntakeLinks::class)->assertCanNotSeeTableRecords([$link]);

        $this->member('omar-unit', 'عمر يونت', 'Omar Unit', center: self::CENTER);
        $token = $this->token($this->link());
        $url = (string) $this->getJson('/en/staff-intake/'.$token.'/suggestions?q=omar+unit')->json('items.0.url');
        $this->post('/en/staff-intake/'.$token.'/form', $this->formData($this->formRef($this->get($url)->getContent()), [
            'name_ar' => 'عمر يونت',
            'name_en' => 'Omar Unit',
            'position_ar' => 'منسق',
            'position_en' => 'Coordinator',
        ]))->assertRedirect('/en/staff-intake/'.$token.'/sent');
        $request = StaffIntakeRequest::query()->sole();

        Livewire::test(ListStaffIntakeRequests::class)->assertCanNotSeeTableRecords([$request]);
        Livewire::test(ListCenterStaffRequests::class)->assertCanSeeTableRecords([$request]);

        $this->get(CenterStaffRequestResource::getUrl('view', ['record' => $request]))->assertOk()
            ->assertSee((string) app(CenterCatalogServiceInterface::class)->centerName(self::CENTER, 'ar'))
            ->assertSee('Coordinator');

        Livewire::test(ViewCenterStaffRequest::class, ['record' => $request->getKey()])
            ->callAction('approve', ['target' => 'claimed'])
            ->assertNotified();
        $this->assertSame(StaffIntakeRequest::STATUS_APPROVED, $request->fresh()?->status);
    }

    private function link(): StaffIntakeLinkCreatedDTO
    {
        return app(StaffIntakeLinkServiceInterface::class)->create(
            new StaffIntakeLinkInputDTO(null, 'Unit staff', now()->addMonth(), null, self::CENTER),
            (int) $this->admin->getKey(),
        );
    }

    private function token(StaffIntakeLinkCreatedDTO $created): string
    {
        return (string) preg_replace('#^.*/staff-intake/([A-Za-z0-9]{40}).*$#', '$1', $created->urlAr);
    }

    private function formRef(string|false $html): string
    {
        $this->assertIsString($html);
        $this->assertSame(1, preg_match('/name="ref" value="([^"]+)"/', $html, $matches));

        return html_entity_decode($matches[1]);
    }

    /**
     * @param  array<string, mixed>  $fields
     * @return array<string, mixed>
     */
    private function formData(string $ref, array $fields): array
    {
        return [
            'ref' => $ref,
            'email' => 'person@spu.edu.sy',
            'phone' => '+963 11 123 4567',
            'title_ar' => 'د.',
            'title_en' => 'Dr.',
            'bio_ar' => 'نبذة.',
            'bio_en' => 'About.',
            'photo' => UploadedFile::fake()->image('photo.jpg', 400, 400),
            'cv' => UploadedFile::fake()->createWithContent('cv.pdf', "%PDF-1.4\n1 0 obj << /Type /Catalog >> endobj\ntrailer << /Root 1 0 R >>\n%%EOF\n"),
            ...$fields,
        ];
    }

    private function member(string $slug, string $nameAr, string $nameEn, ?Faculty $faculty = null, ?string $center = null): FacultyMember
    {
        $member = FacultyMember::query()->create([
            'slug' => $slug,
            'faculty_id' => $faculty?->getKey(),
            'center_slug' => $center,
            'is_enabled' => true,
            'publication_status' => PublicationStatus::Published->value,
            'published_at' => now()->subDay(),
        ]);
        $member->translations()->create(['locale' => 'ar', 'full_name' => $nameAr, 'bio' => 'نبذة.']);
        $member->translations()->create(['locale' => 'en', 'full_name' => $nameEn, 'bio' => 'About.']);

        return $member;
    }

    private function user(string $role): User
    {
        return User::factory()->create(['role_slug' => $role, 'is_locked' => false]);
    }
}
