<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Contracts\Cms\CmsWorkflowServiceInterface;
use App\Contracts\Staff\StaffIntakeLinkServiceInterface;
use App\Contracts\Staff\StaffIntakeReviewServiceInterface;
use App\DTOs\Staff\StaffIntakeLinkCreatedDTO;
use App\DTOs\Staff\StaffIntakeLinkInputDTO;
use App\Enums\PublicationStatus;
use App\Filament\Resources\StaffIntakeLinkResource;
use App\Filament\Resources\StaffIntakeLinkResource\Pages\ListStaffIntakeLinks;
use App\Filament\Resources\StaffIntakeRequestResource;
use App\Filament\Resources\StaffIntakeRequestResource\Pages\ViewStaffIntakeRequest;
use App\Mail\StaffIntakeSubmittedNotification;
use App\Models\Cms\CmsDraft;
use App\Models\Faculty\Department;
use App\Models\Faculty\Faculty;
use App\Models\Person\FacultyMember;
use App\Models\Staff\StaffIntakeAccessLog;
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

final class StaffIntakeTest extends TestCase
{
    use RefreshDatabase;

    private Faculty $dentistry;

    private Faculty $pharmacy;

    private User $facultyEditor;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
        Storage::fake((string) config('filesystems.media_disk', 'public'));
        $this->seed(DatabaseSeeder::class);
        $this->dentistry = Faculty::query()->where('public_slug', 'dentistry')->firstOrFail();
        $this->pharmacy = Faculty::query()->where('public_slug', 'pharmacy')->firstOrFail();
        $this->facultyEditor = $this->user('faculty_editor', 'dentistry');
        Mail::fake();
    }

    public function test_a_faculty_editor_makes_links_only_for_their_own_faculty(): void
    {
        $created = $this->link();
        $this->assertStringContainsString('/ar/staff-intake/', $created->urlAr);
        $this->assertSame((int) $this->dentistry->getKey(), $created->link->facultyId);
        $this->assertDatabaseHas('audit_logs', ['action' => 'staff_intake_link.created', 'entity_id' => $created->link->id]);

        // The token is stored only as a hash.
        $token = $this->token($created);
        $this->assertDatabaseMissing('staff_intake_links', ['token_hash' => $token]);
        $this->assertDatabaseHas('staff_intake_links', ['token_hash' => hash('sha256', $token)]);

        $this->expectException(AuthorizationException::class);
        app(StaffIntakeLinkServiceInterface::class)->create(
            new StaffIntakeLinkInputDTO((int) $this->pharmacy->getKey(), null, null, null),
            (int) $this->facultyEditor->getKey(),
        );
    }

    public function test_the_page_is_private_and_closed_links_are_not_found(): void
    {
        $created = $this->link();
        $token = $this->token($created);

        $response = $this->get('/ar/staff-intake/'.$token);
        $response->assertOk()
            ->assertSee('noindex,nofollow', false)
            ->assertHeader('X-Robots-Tag', 'noindex, nofollow, noarchive');
        $this->assertStringContainsString('no-store', (string) $response->headers->get('Cache-Control'));
        $this->assertDatabaseHas('staff_intake_access_logs', ['staff_intake_link_id' => $created->link->id, 'event' => 'opened']);

        $this->get('/ar/staff-intake/'.str_repeat('a', 40))->assertNotFound();

        $rotated = app(StaffIntakeLinkServiceInterface::class)->rotate($created->link->id, (int) $this->facultyEditor->getKey());
        $this->get('/ar/staff-intake/'.$token)->assertNotFound();
        $this->get('/en/staff-intake/'.$this->token($rotated))->assertOk();

        app(StaffIntakeLinkServiceInterface::class)->revoke($created->link->id, (int) $this->facultyEditor->getKey());
        $this->get('/en/staff-intake/'.$this->token($rotated))->assertNotFound();

        $expired = $this->link();
        StaffIntakeLink::query()->whereKey($expired->link->id)->update(['expires_at' => now()->subMinute()]);
        $this->get('/ar/staff-intake/'.$this->token($expired))->assertNotFound();
    }

    public function test_suggestions_find_a_name_however_it_is_typed_and_show_only_public_fields(): void
    {
        $member = $this->member('ahmad-staffintake', 'أحمد ستافنتيك', 'Ahmad Staffintake', $this->dentistry, 'ahmad.private@spu.edu.sy');
        $token = $this->token($this->link());

        foreach (['احمد ستافنتيك', 'أَحْمَد ستافنتيك', 'ahmed staffintake', 'AHMAD STAFFINTAKE', 'Ahmed Staff'] as $typed) {
            $response = $this->getJson('/ar/staff-intake/'.$token.'/suggestions?q='.urlencode($typed))->assertOk();
            $this->assertSame('Ahmad Staffintake', $response->json('items.0.nameEn'), 'Not found when typed as: '.$typed);
            $this->assertStringNotContainsString('ahmad.private@spu.edu.sy', $response->getContent());
        }

        // Without JavaScript the same suggestions are rendered on the page.
        $this->get('/en/staff-intake/'.$token.'?q=ahmed+staffintake')->assertOk()->assertSee('Ahmad Staffintake');

        // Picking the profile opens it with everything we hold, contact details included.
        $url = (string) $this->getJson('/ar/staff-intake/'.$token.'/suggestions?q=ahmad+staffintake')->json('items.0.url');
        $this->get($url)->assertOk()->assertSee('ahmad.private@spu.edu.sy');
        $this->assertDatabaseHas('staff_intake_access_logs', ['event' => 'claimed', 'faculty_member_id' => $member->getKey()]);
    }

    public function test_a_teacher_correcting_their_profile_sends_a_request_and_the_profile_waits_for_approval(): void
    {
        $member = $this->member('sara-intake', 'سارة إنتيك', 'Sara Intake', $this->dentistry, 'sara@spu.edu.sy');
        $created = $this->link(maxSubmissions: 5);
        $token = $this->token($created);
        $ref = $this->refFor($token, 'Sara Intake');

        $this->post('/en/staff-intake/'.$token.'/form', $this->formData($ref, [
            'name_ar' => 'سارة إنتيك',
            'name_en' => 'Sara Intake',
            'email' => 'sara.new@spu.edu.sy',
            'position_ar' => 'مدرّسة',
            'position_en' => 'Lecturer',
        ]), ['HTTP_USER_AGENT' => 'IntakeTestBrowser/1.0'])->assertRedirect('/en/staff-intake/'.$token.'/sent');

        $request = StaffIntakeRequest::query()->sole();
        $this->assertSame(StaffIntakeRequest::TYPE_UPDATE, $request->type);
        $this->assertSame((int) $member->getKey(), (int) $request->faculty_member_id);
        $this->assertSame(StaffIntakeRequest::STATUS_PENDING, $request->status);
        $this->assertSame('sara@spu.edu.sy', $member->fresh()?->email, 'The profile changed before review.');
        $this->assertSame(1, (int) StaffIntakeLink::query()->whereKey($created->link->id)->value('submissions_count'));

        $log = StaffIntakeAccessLog::query()->where('event', 'submitted')->sole();
        $this->assertSame(['position_ar', 'position_en', 'email', 'linkedin', 'orcid', 'scopus', 'researchgate', 'scholar', 'photo', 'cv'], $log->changed_fields);
        $this->assertSame('IntakeTestBrowser/1.0', $log->user_agent);
        $this->assertNotNull($log->ip_address);
        Mail::assertQueued(StaffIntakeSubmittedNotification::class, fn (StaffIntakeSubmittedNotification $mail): bool => $mail->hasTo($this->facultyEditor->email));

        // A second submission for the same profile replaces the first.
        $this->post('/en/staff-intake/'.$token.'/form', $this->formData($ref, ['name_ar' => 'سارة إنتيك', 'name_en' => 'Sara Intake', 'position_ar' => 'مدرّسة', 'position_en' => 'Lecturer', 'email' => 'sara.final@spu.edu.sy']))->assertRedirect();
        $this->assertSame(StaffIntakeRequest::STATUS_SUPERSEDED, $request->fresh()?->status);
        $this->assertSame(1, StaffIntakeRequest::query()->where('status', StaffIntakeRequest::STATUS_PENDING)->count());

        $latest = StaffIntakeRequest::query()->where('status', StaffIntakeRequest::STATUS_PENDING)->sole();
        $editor = $this->user('editor');
        $result = app(StaffIntakeReviewServiceInterface::class)->approve((int) $latest->getKey(), (int) $editor->getKey());

        $this->assertTrue($result->published);
        $this->assertSame((int) $member->getKey(), $result->facultyMemberId);
        $this->assertSame('sara.final@spu.edu.sy', $member->fresh()?->email);
        $this->assertSame(PublicationStatus::Published->value, $member->fresh()?->publication_status);
        $this->assertSame(StaffIntakeRequest::STATUS_APPROVED, $latest->fresh()?->status);
        $this->assertDatabaseHas('audit_logs', ['action' => 'staff_intake_request.approved', 'entity_id' => $latest->getKey()]);
    }

    public function test_a_new_teacher_with_an_existing_name_must_confirm_before_a_new_profile_is_requested(): void
    {
        $this->member('omar-intake', 'عمر إنتيك', 'Omar Intake', $this->dentistry);
        $token = $this->token($this->link());
        $this->get('/ar/staff-intake/'.$token.'/form?name='.urlencode('Omar Intake'))->assertOk();
        $ref = $this->newRef($token, 'Omar Intake');

        $this->from('/ar/staff-intake/'.$token.'/form')
            ->post('/ar/staff-intake/'.$token.'/form', $this->formData($ref, ['name_ar' => 'عمر انتيك', 'name_en' => 'Omar Intake']))
            ->assertRedirect('/ar/staff-intake/'.$token.'/form')
            ->assertSessionHas('staff_intake_duplicates');
        $this->assertSame(0, StaffIntakeRequest::query()->count());

        $this->post('/ar/staff-intake/'.$token.'/form', $this->formData($ref, ['name_ar' => 'عمر انتيك', 'name_en' => 'Omar Intake', 'confirm_not_duplicate' => '1']))
            ->assertRedirect('/ar/staff-intake/'.$token.'/sent');

        $request = StaffIntakeRequest::query()->sole();
        $this->assertSame(StaffIntakeRequest::TYPE_CREATE, $request->type);
        $this->assertTrue($request->possible_duplicate);
        $this->assertCount(1, $request->duplicate_candidates);
    }

    public function test_approving_a_new_teacher_by_a_faculty_editor_leaves_an_unpublished_profile(): void
    {
        $department = Department::query()->create(['faculty_id' => $this->dentistry->getKey(), 'slug' => 'intake-orthodontics']);
        $department->translations()->create(['locale' => 'ar', 'name' => 'التقويم']);
        $department->translations()->create(['locale' => 'en', 'name' => 'Orthodontics']);
        $token = $this->token($this->link());
        $ref = $this->newRef($token, 'Lina Newintake');

        $this->post('/en/staff-intake/'.$token.'/form', $this->formData($ref, [
            'name_ar' => 'لينا نيوإنتيك',
            'name_en' => 'Lina Newintake',
            'position_ar' => 'أستاذة مساعدة',
            'position_en' => 'Assistant Professor',
            'department_id' => (string) $department->getKey(),
            'specializations_en' => 'Orthodontics, Implants',
            'educations' => [[
                'degree_ar' => 'دكتوراه', 'degree_en' => 'PhD',
                'institution_ar' => 'جامعة دمشق', 'institution_en' => 'Damascus University',
                'year_start' => '٢٠١٠', 'year_end' => '2014',
            ]],
        ]))->assertRedirect('/en/staff-intake/'.$token.'/sent');

        $request = StaffIntakeRequest::query()->sole();
        $result = app(StaffIntakeReviewServiceInterface::class)->approve((int) $request->getKey(), (int) $this->facultyEditor->getKey());

        $this->assertFalse($result->published, 'A faculty editor cannot publish; an editor does.');
        $member = FacultyMember::query()->with('translations')->findOrFail($result->facultyMemberId);
        $this->assertSame('lina-newintake', $member->slug);
        $this->assertSame((int) $this->dentistry->getKey(), (int) $member->faculty_id);
        $this->assertSame(PublicationStatus::Draft->value, $member->publication_status);
        $this->assertDatabaseHas('cms_drafts', ['target_key' => 'entity.faculty-member.'.$member->getKey(), 'status' => PublicationStatus::Draft->value]);

        $editor = $this->user('editor');
        $this->assertTrue(app(CmsWorkflowServiceInterface::class)->publish('entity.faculty-member.'.$member->getKey(), (int) $editor->getKey()));
        $member->refresh()->load(['translations', 'educations.translations']);
        $this->assertSame((int) $department->getKey(), (int) $member->department_id);
        $this->assertSame(['Orthodontics', 'Implants'], $member->translations->firstWhere('locale', 'en')?->specializations);
        $this->assertSame(2010, (int) $member->educations->first()?->translations->firstWhere('locale', 'en')?->year_start);
    }

    public function test_requests_are_checked_against_the_links_faculty_and_reviewer_scope(): void
    {
        $foreignDepartment = Department::query()->create(['faculty_id' => $this->pharmacy->getKey(), 'slug' => 'intake-pharmacology']);
        $token = $this->token($this->link());
        $ref = $this->newRef($token, 'Rami Scopetest');

        $this->post('/en/staff-intake/'.$token.'/form', $this->formData($ref, ['name_ar' => 'رامي سكوب', 'name_en' => 'Rami Scopetest', 'department_id' => (string) $foreignDepartment->getKey()]))
            ->assertSessionHasErrors('department_id');
        $this->post('/en/staff-intake/'.$token.'/form', $this->formData($ref, ['name_ar' => 'رامي سكوب', 'name_en' => 'Rami Scopetest', 'website' => 'http://spam.example']))
            ->assertSessionHasErrors('form');
        $this->post('/en/staff-intake/'.$token.'/form', $this->formData('not-a-real-ref', ['name_ar' => 'رامي سكوب', 'name_en' => 'Rami Scopetest']))
            ->assertSessionHasErrors('form');
        $this->assertSame(0, StaffIntakeRequest::query()->count());

        $this->post('/en/staff-intake/'.$token.'/form', $this->formData($ref, ['name_ar' => 'رامي سكوب', 'name_en' => 'Rami Scopetest']))->assertRedirect();
        $request = StaffIntakeRequest::query()->sole();

        $pharmacyEditor = $this->user('faculty_editor', 'pharmacy');
        $this->assertSame(0, app(StaffIntakeReviewServiceInterface::class)->pendingCount((int) $pharmacyEditor->getKey()));
        $this->expectException(AuthorizationException::class);
        app(StaffIntakeReviewServiceInterface::class)->approve((int) $request->getKey(), (int) $pharmacyEditor->getKey());
    }

    public function test_a_link_stops_at_its_submission_limit(): void
    {
        $token = $this->token($this->link(maxSubmissions: 1));
        $ref = $this->newRef($token, 'Hala Limit');

        $this->post('/en/staff-intake/'.$token.'/form', $this->formData($ref, ['name_ar' => 'هلا ليميت', 'name_en' => 'Hala Limit']))->assertRedirect('/en/staff-intake/'.$token.'/sent');
        $this->get('/en/staff-intake/'.$token)->assertNotFound();
        $this->post('/en/staff-intake/'.$token.'/form', $this->formData($ref, ['name_ar' => 'هلا ليميت', 'name_en' => 'Hala Limit']))->assertSessionHasErrors('form');
        $this->assertSame(1, StaffIntakeRequest::query()->count());
    }

    public function test_an_approval_the_cms_cannot_publish_yet_is_kept_as_a_draft(): void
    {
        $member = $this->member('maya-intake', 'مايا إنتيك', 'Maya Intake', $this->dentistry);
        $token = $this->token($this->link());
        $ref = $this->refFor($token, 'Maya Intake');
        // Without a position or biography the CMS will not publish a profile.
        $this->post('/en/staff-intake/'.$token.'/form', $this->formData($ref, ['name_ar' => 'مايا إنتيك', 'name_en' => 'Maya Intake', 'phone' => '٠٩٤٤١٢٣٤٥٦']))->assertRedirect();

        $request = StaffIntakeRequest::query()->sole();
        $result = app(StaffIntakeReviewServiceInterface::class)->approve((int) $request->getKey(), (int) $this->user('editor')->getKey());

        $this->assertFalse($result->published);
        $this->assertNotSame([], $result->publishErrors);
        $this->assertSame(StaffIntakeRequest::STATUS_APPROVED, $request->fresh()?->status);
        $this->assertNull($member->fresh()?->phone, 'The live profile changed without being published.');
        $this->assertSame('0944123456', CmsDraft::query()->where('target_key', 'entity.faculty-member.'.$member->getKey())->latest('id')->firstOrFail()->payload_json['phone'] ?? null);
    }

    public function test_rejecting_leaves_the_profile_alone(): void
    {
        $member = $this->member('nour-intake', 'نور إنتيك', 'Nour Intake', $this->dentistry, 'nour@spu.edu.sy');
        $token = $this->token($this->link());
        $ref = $this->refFor($token, 'Nour Intake');
        $this->post('/en/staff-intake/'.$token.'/form', $this->formData($ref, ['name_ar' => 'نور إنتيك', 'name_en' => 'Nour Intake', 'email' => 'someone.else@example.com']))->assertRedirect();

        $request = StaffIntakeRequest::query()->sole();
        $this->assertTrue(app(StaffIntakeReviewServiceInterface::class)->reject((int) $request->getKey(), (int) $this->facultyEditor->getKey(), 'Not sent by Nour.'));
        $this->assertSame(StaffIntakeRequest::STATUS_REJECTED, $request->fresh()?->status);
        $this->assertSame('nour@spu.edu.sy', $member->fresh()?->email);
    }

    public function test_the_admin_screens_make_links_and_review_requests_within_the_editors_faculty(): void
    {
        $this->actingAs($this->facultyEditor, 'web');
        $this->get(StaffIntakeLinkResource::getUrl('index'))->assertOk();

        Livewire::test(ListStaffIntakeLinks::class)
            ->callAction('create_link', ['faculty_id' => (int) $this->dentistry->getKey(), 'label' => 'October group', 'max_submissions' => 40])
            ->assertHasNoActionErrors()
            ->assertNotified();
        $link = StaffIntakeLink::query()->where('label', 'October group')->sole();
        $this->assertSame(40, $link->max_submissions);
        $this->assertSame((int) $this->facultyEditor->getKey(), (int) $link->created_by);

        Livewire::test(ListStaffIntakeLinks::class)
            ->assertCanSeeTableRecords([$link])
            ->callTableAction('revoke', $link)
            ->assertNotified();
        $this->assertNotNull($link->fresh()?->revoked_at);

        $this->member('karim-intake', 'كريم إنتيك', 'Karim Intake', $this->dentistry, 'karim@spu.edu.sy');
        $token = $this->token($this->link());
        $ref = $this->refFor($token, 'Karim Intake');
        $this->post('/en/staff-intake/'.$token.'/form', $this->formData($ref, ['name_ar' => 'كريم إنتيك', 'name_en' => 'Karim Intake', 'email' => 'karim.new@spu.edu.sy']))->assertRedirect();
        $request = StaffIntakeRequest::query()->sole();

        $this->get(StaffIntakeRequestResource::getUrl('view', ['record' => $request]))
            ->assertOk()
            ->assertSee('karim.new@spu.edu.sy')
            ->assertSee('karim@spu.edu.sy');

        Livewire::test(ViewStaffIntakeRequest::class, ['record' => $request->getKey()])
            ->callAction('approve', ['target' => 'claimed'])
            ->assertNotified();
        $this->assertSame(StaffIntakeRequest::STATUS_APPROVED, $request->fresh()?->status);

        // Another faculty's editor neither sees the request nor the links.
        $this->actingAs($this->user('faculty_editor', 'pharmacy'), 'web');
        $this->get(StaffIntakeRequestResource::getUrl('view', ['record' => $request]))->assertNotFound();
        Livewire::test(ListStaffIntakeLinks::class)->assertCanNotSeeTableRecords([$link]);
    }

    public function test_photo_email_cv_and_academic_profiles_are_required(): void
    {
        $token = $this->token($this->link());
        $ref = $this->newRef($token, 'Zaid Required');
        $form = $this->get('/en/staff-intake/'.$token.'/form?name=Zaid+Required')->assertOk();
        foreach (['email', 'linkedin', 'orcid', 'scopus', 'researchgate', 'scholar', 'photo', 'cv'] as $field) {
            $this->assertMatchesRegularExpression('/name="'.$field.'"[^>]*required/', (string) $form->getContent(), $field.' is not marked required');
        }

        $missing = ['ref' => $ref, 'name_ar' => 'زيد مطلوب', 'name_en' => 'Zaid Required'];
        $this->post('/en/staff-intake/'.$token.'/form', $missing)
            ->assertSessionHasErrors(['email', 'linkedin', 'orcid', 'scopus', 'researchgate', 'scholar']);
        $this->post('/en/staff-intake/'.$token.'/form', [...$missing, ...$this->requiredFields(), 'photo' => null, 'cv' => null])
            ->assertSessionHasErrors(['photo', 'cv']);
        $this->post('/en/staff-intake/'.$token.'/form', [...$missing, ...$this->requiredFields(), 'orcid' => 'not a link'])
            ->assertSessionHasErrors(['orcid']);
        $this->assertSame(0, StaffIntakeRequest::query()->count());

        // X (Twitter) stays optional.
        $this->post('/en/staff-intake/'.$token.'/form', [...$missing, ...$this->requiredFields()])->assertRedirect('/en/staff-intake/'.$token.'/sent');
    }

    public function test_a_profile_that_already_has_a_photo_and_cv_need_not_send_them_again(): void
    {
        $member = $this->member('huda-files', 'هدى ملفات', 'Huda Files', $this->dentistry, 'huda@spu.edu.sy');
        $member->forceFill(['legacy_photo_path' => 'images/staff/huda.jpg', 'legacy_cv_path' => 'files/huda.pdf'])->save();
        $token = $this->token($this->link());
        $ref = $this->refFor($token, 'Huda Files');

        $form = $this->get('/en/staff-intake/'.$token.'/form?ref='.urlencode($ref))->getContent();
        $this->assertDoesNotMatchRegularExpression('/name="photo"[^>]*required/', (string) $form);

        $this->post('/en/staff-intake/'.$token.'/form', $this->formData($ref, ['name_ar' => 'هدى ملفات', 'name_en' => 'Huda Files', 'photo' => null, 'cv' => null]))
            ->assertRedirect('/en/staff-intake/'.$token.'/sent');
        $this->assertSame([], StaffIntakeRequest::query()->sole()->payload['uploads']);
    }

    public function test_staff_intake_needs_its_own_permissions(): void
    {
        $hr = $this->user('hr');
        $this->assertSame([], app(StaffIntakeLinkServiceInterface::class)->facultyOptions((int) $hr->getKey()));
        $this->assertSame([], app(StaffIntakeReviewServiceInterface::class)->reviewableFacultyIds((int) $hr->getKey()));

        $this->actingAs($hr, 'web');
        $this->get(StaffIntakeLinkResource::getUrl('index'))->assertForbidden();
        $this->get(StaffIntakeRequestResource::getUrl('index'))->assertForbidden();
    }

    private function link(?int $maxSubmissions = null): StaffIntakeLinkCreatedDTO
    {
        return app(StaffIntakeLinkServiceInterface::class)->create(
            new StaffIntakeLinkInputDTO((int) $this->dentistry->getKey(), 'Staff group', now()->addMonth(), $maxSubmissions),
            (int) $this->facultyEditor->getKey(),
        );
    }

    private function token(StaffIntakeLinkCreatedDTO $created): string
    {
        return (string) preg_replace('#^.*/staff-intake/([A-Za-z0-9]{40}).*$#', '$1', $created->urlAr);
    }

    /** The encrypted reference the suggestions hand out for the first match. */
    private function refFor(string $token, string $name): string
    {
        $url = (string) $this->getJson('/en/staff-intake/'.$token.'/suggestions?q='.urlencode($name))->json('items.0.url');
        parse_str((string) parse_url($url, PHP_URL_QUERY), $query);
        $this->assertIsString($query['ref'] ?? null, 'No suggestion for '.$name);

        return $this->formRef($this->get($url)->getContent());
    }

    private function newRef(string $token, string $name): string
    {
        return $this->formRef($this->get('/en/staff-intake/'.$token.'/form?name='.urlencode($name))->getContent());
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
        return ['ref' => $ref, ...$this->requiredFields(), ...$fields];
    }

    /** @return array<string, mixed> The fields every submission must carry. */
    private function requiredFields(): array
    {
        return [
            'email' => 'teacher@spu.edu.sy',
            'linkedin' => 'https://www.linkedin.com/in/teacher',
            'orcid' => 'https://orcid.org/0000-0001-2345-6789',
            'scopus' => 'https://www.scopus.com/authid/detail.uri?authorId=1',
            'researchgate' => 'https://www.researchgate.net/profile/Teacher',
            'scholar' => 'https://scholar.google.com/citations?user=abc',
            'photo' => UploadedFile::fake()->image('photo.jpg', 400, 400),
            'cv' => UploadedFile::fake()->createWithContent('cv.pdf', "%PDF-1.4\n1 0 obj << /Type /Catalog >> endobj\ntrailer << /Root 1 0 R >>\n%%EOF\n"),
        ];
    }

    private function member(string $slug, string $nameAr, string $nameEn, Faculty $faculty, ?string $email = null): FacultyMember
    {
        $member = FacultyMember::query()->create([
            'slug' => $slug,
            'faculty_id' => $faculty->getKey(),
            'email' => $email,
            'is_enabled' => true,
            'publication_status' => PublicationStatus::Published->value,
            'published_at' => now()->subDay(),
        ]);
        $member->translations()->create(['locale' => 'ar', 'full_name' => $nameAr]);
        $member->translations()->create(['locale' => 'en', 'full_name' => $nameEn]);

        return $member;
    }

    private function user(string $role, ?string $scope = null): User
    {
        return User::factory()->create([
            'role_slug' => $role,
            'faculty_scopes' => $scope !== null ? [$scope] : null,
            'is_locked' => false,
        ]);
    }
}
