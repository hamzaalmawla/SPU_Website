<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Contracts\Cms\CmsWorkflowServiceInterface;
use App\Contracts\Faculty\FacultyLeadershipServiceInterface;
use App\Contracts\Faculty\FacultyPositionServiceInterface;
use App\Contracts\Page\FacultyPageServiceInterface;
use App\Contracts\Page\ProfilePageServiceInterface;
use App\Enums\PublicationStatus;
use App\Filament\Pages\ManageMedicineFaculty;
use App\Models\Cms\CmsTargetContent;
use App\Models\Faculty\Faculty;
use App\Models\Faculty\FacultyPage;
use App\Models\Faculty\FacultySubpageCard;
use App\Models\Person\FacultyMember;
use App\Models\Person\Person;
use App\Models\User\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Each faculty has a Faculty Council page. Every row is a position from one list
 * all faculties share (dean, vice dean and the positions editors add) and a profile
 * of the faculty's own academic staff. A position's placement shows its holders in
 * the leadership cards or the council list; heads of department come from the
 * departments page. The dean published there is also the dean on About › Leadership
 * and in the faculty's dean block, and profiles show the positions they hold.
 */
final class FacultyCouncilTest extends TestCase
{
    use RefreshDatabase;

    private const TARGET = 'facilities.medicine.council';

    private const DEPARTMENTS_TARGET = 'facilities.medicine.departments';

    private User $admin;

    private Faculty $faculty;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);
        $this->admin = User::query()->where('role_slug', 'super_admin')->firstOrFail();
        $this->faculty = Faculty::query()->where('public_slug', 'medicine')->firstOrFail();
    }

    public function test_before_it_is_published_the_council_page_shows_the_leadership_dean(): void
    {
        $dean = $this->member('council-default-dean', 'Dr. Default Dean', 'د. العميد الحالي');
        $this->leadershipDean($dean, 'Dean of the Faculty of Medicine', 'عميد كلية الطب البشري');

        $this->get('/en/faculties/medicine/council')
            ->assertOk()
            ->assertSee('Faculty Council')
            ->assertSee('Faculty Leadership')
            ->assertSee('Dr. Default Dean')
            ->assertSee('Council Chair')
            ->assertSee('View Profile');

        // The page is one of the faculty's navigation cards.
        $faculty = app(FacultyPageServiceInterface::class)->getFaculty('medicine', 'en');
        $this->assertNotNull($faculty);
        $this->assertContains('council', $faculty->navigation->pluck('slug')->all());

        $payload = app(FacultyPageServiceInterface::class)->getEditablePayload(self::TARGET);
        $this->assertSame((int) $dean->getKey(), $payload['translations']['ar']['positions'][0]['memberId']);
        $this->assertSame('dean', $payload['translations']['en']['positions'][0]['positionSlug']);
    }

    public function test_a_faculty_without_a_council_row_or_card_has_the_page_in_code(): void
    {
        // Live faculties have no council page row or card: no data migration adds them.
        FacultyPage::query()->where('faculty_id', $this->faculty->getKey())->where('slug', 'council')->delete();
        FacultySubpageCard::query()->where('faculty_slug', 'medicine')->where('subpage_slug', 'council')->delete();
        Cache::flush();

        $this->get('/en/faculties/medicine/council')->assertOk()->assertSee('Faculty Council');
        $navigation = app(FacultyPageServiceInterface::class)->getFaculty('medicine', 'en')?->navigation->pluck('slug')->all() ?? [];
        $this->assertSame('council', end($navigation));

        // A card an editor hides keeps the page off.
        FacultySubpageCard::query()->create([
            'faculty_slug' => 'medicine',
            'subpage_slug' => 'council',
            'sort_order' => 99,
            'is_visible' => false,
            'status' => 'published',
            'published_at' => now()->subDay(),
        ]);
        Cache::flush();

        $this->get('/en/faculties/medicine/council')->assertNotFound();
    }

    public function test_the_published_council_places_each_position_and_lists_the_department_heads(): void
    {
        $dean = $this->member('council-dean', 'Dr. Dean Person', 'د. العميد');
        $vice = $this->member('council-vice', 'Dr. Vice Person', 'د. نائب العميد');
        $secretary = $this->member('council-secretary', 'Dr. Secretary Person', 'د. أمين السر');
        $secretaryPosition = $this->addPosition('أمين سر المجلس', 'Council Secretary', 'council');
        $representative = $this->addPosition('ممثل أعضاء الهيئة التدريسية', 'Staff representative', 'council');
        $this->publishDepartments([
            ['slug' => 'surgery-council-test', 'title' => ['قسم الجراحة', 'Department of Surgery'], 'headMemberId' => (int) $vice->getKey()],
        ]);
        $this->publishCouncil([
            ['positionSlug' => 'dean', 'memberId' => (int) $dean->getKey()],
            ['positionSlug' => 'vice_dean', 'memberId' => (int) $vice->getKey()],
            ['positionSlug' => $secretaryPosition, 'memberId' => (int) $secretary->getKey()],
            ['positionSlug' => $representative, 'memberId' => (int) $secretary->getKey()],
        ]);

        $page = app(FacultyPageServiceInterface::class)->getSubpage('medicine', 'council', 'en');
        $this->assertNotNull($page);
        $this->assertSame('Dr. Dean Person', $page->detail['dean']['name']);
        $this->assertSame('Dean', $page->detail['dean']['title']);
        $this->assertSame(['Vice Dean'], $page->detail['leadership'][0]['titles']);
        // One council card per person, with every position they hold there.
        $this->assertCount(1, $page->items);
        $this->assertSame(['Council Secretary', 'Staff representative'], $page->items[0]['roles']);
        $this->assertSame('Department of Surgery', $page->detail['heads'][0]['department']);
        $this->assertSame('Head of Department', $page->detail['heads'][0]['title']);
        $this->assertSame('Dr. Vice Person', $page->detail['heads'][0]['name']);

        $this->get('/ar/faculties/medicine/council')
            ->assertOk()
            ->assertSee('مجلس الكلية')
            ->assertSee('عميد الكلية')
            ->assertSee('رئيس مجلس الكلية')
            ->assertSee('نائب العميد')
            ->assertSee('أمين سر المجلس')
            ->assertSee('رؤساء الأقسام')
            ->assertSee('قسم الجراحة')
            ->assertSee('/ar/about/profile/council-secretary', false);
    }

    public function test_the_positions_list_is_shared_and_renaming_a_position_renames_it_everywhere(): void
    {
        $service = app(FacultyPositionServiceInterface::class);
        $slug = $this->addPosition('مقرر المجلس', 'Council Rapporteur', 'council');

        $this->assertArrayHasKey($slug, $service->councilOptions('en'));
        $this->assertArrayHasKey('dean', $service->councilOptions('ar'));
        $this->assertArrayHasKey('vice_dean', $service->councilOptions('ar'));
        // Head of department is set on the departments page, never picked here.
        $this->assertArrayNotHasKey('head_of_department', $service->councilOptions('ar'));

        $dean = $this->member('rename-dean', 'Dr. Rename Dean', 'د. عميد التسمية');
        $vice = $this->member('rename-vice', 'Dr. Rename Vice', 'د. نائب التسمية');
        $this->publishCouncil([
            ['positionSlug' => 'dean', 'memberId' => (int) $dean->getKey()],
            ['positionSlug' => 'vice_dean', 'memberId' => (int) $vice->getKey()],
        ]);

        // Renaming the vice dean and moving it to the council list changes the page.
        $service->save('vice_dean', 'نائب العميد للشؤون العلمية', 'Vice Dean for Academic Affairs', 'council', self::TARGET, (int) $this->admin->getKey());
        $page = app(FacultyPageServiceInterface::class)->getSubpage('medicine', 'council', 'en');
        $this->assertNotNull($page);
        $this->assertSame([], $page->detail['leadership']);
        $this->assertSame(['Vice Dean for Academic Affairs'], $page->items[0]['roles']);
        $this->assertDatabaseHas('audit_logs', ['action' => 'faculty.position.updated']);

        // The dean always leads, whatever placement is asked for.
        $service->save('dean', 'عميد الكلية', 'Dean', 'council', self::TARGET, (int) $this->admin->getKey());
        $this->assertSame('leadership', $service->find('dean')?->placement);

        // Two positions never share a name.
        try {
            $service->save(null, 'نائب العميد للشؤون العلمية', 'Another one', 'council', self::TARGET, (int) $this->admin->getKey());
            $this->fail('A position with a taken name was saved.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('nameAr', $exception->errors());
        }

        // Only someone who can edit a council page manages the list.
        $viewer = User::factory()->create(['role_slug' => 'hr']);
        $this->expectException(AuthorizationException::class);
        $service->save(null, 'منصب', 'Position', 'council', self::TARGET, (int) $viewer->getKey());
    }

    public function test_a_department_heads_title_and_council_seat_are_set_on_the_departments_page(): void
    {
        $chair = $this->member('titled-head', 'Dr. Titled Head', 'د. رئيسة القسم');
        $offCouncil = $this->member('off-council-head', 'Dr. Off Council', 'د. خارج المجلس');
        $this->publishDepartments([
            ['slug' => 'surgery-titled', 'title' => ['قسم الجراحة', 'Department of Surgery'], 'headMemberId' => (int) $chair->getKey(), 'headTitle' => ['رئيسة قسم الجراحة', 'Chair, Department of Surgery']],
            ['slug' => 'pediatrics-off', 'title' => ['قسم الأطفال', 'Department of Pediatrics'], 'headMemberId' => (int) $offCouncil->getKey(), 'headOnCouncil' => false],
        ]);

        $page = app(FacultyPageServiceInterface::class)->getSubpage('medicine', 'council', 'en');
        $this->assertNotNull($page);
        $this->assertSame(['Chair, Department of Surgery'], array_column($page->detail['heads'], 'title'));
        $this->assertSame([(int) $chair->getKey()], array_column($page->detail['heads'], 'memberId'));

        $this->get('/ar/faculties/medicine/council')
            ->assertOk()
            ->assertSee('رئيسة قسم الجراحة')
            ->assertDontSee('د. خارج المجلس');

        // The departments page shows the head's own title, or the default.
        $this->get('/en/faculties/medicine/departments')
            ->assertOk()
            ->assertSee('Chair, Department of Surgery')
            ->assertSee('Head of Department')
            ->assertSee('Dr. Off Council');

        // The council seat matches in both languages.
        $payload = app(FacultyPageServiceInterface::class)->getEditablePayload(self::DEPARTMENTS_TARGET);
        $payload['translations']['en']['items'][1]['headOnCouncil'] = true;
        $this->assertArrayHasKey('translations', app(CmsWorkflowServiceInterface::class)->readiness(self::DEPARTMENTS_TARGET, $payload)->errors);
    }

    public function test_the_admin_keeps_a_head_saved_before_the_council_switch_on_the_council(): void
    {
        $head = $this->member('legacy-switch-head', 'Dr. Legacy Head', 'د. رئيس سابق للمفتاح');
        $this->publishDepartments([
            ['slug' => 'surgery-legacy', 'title' => ['قسم الجراحة', 'Department of Surgery'], 'headMemberId' => (int) $head->getKey(), 'legacy' => true],
        ]);
        $this->actingAs($this->admin, 'web');

        Livewire::test(ManageMedicineFaculty::class)
            ->set('data.target_key', self::DEPARTMENTS_TARGET)
            ->call('loadTarget', self::DEPARTMENTS_TARGET)
            ->assertSee('رئيس القسم عضو في مجلس الكلية')
            ->call('save');

        $draft = app(CmsWorkflowServiceInterface::class)->latestEditableDraftPayload(self::DEPARTMENTS_TARGET, (int) $this->admin->getKey());
        $this->assertIsArray($draft);
        $this->assertTrue($draft['translations']['ar']['items'][0]['headOnCouncil']);
        $this->assertTrue($draft['translations']['en']['items'][0]['headOnCouncil']);
    }

    public function test_the_page_intro_body_shows_on_the_council_page(): void
    {
        $dean = $this->member('body-dean', 'Dr. Body Dean', 'د. عميد النص');
        $payload = $this->councilPayload([['positionSlug' => 'dean', 'memberId' => (int) $dean->getKey()]]);
        $payload['translations']['ar']['body'] = 'يجتمع مجلس الكلية مرة كل شهر.';
        $payload['translations']['en']['body'] = 'The faculty council meets once a month.';
        $workflow = app(CmsWorkflowServiceInterface::class);
        $workflow->saveDraft(self::TARGET, $payload, (int) $this->admin->getKey());
        $this->assertTrue($workflow->publish(self::TARGET, (int) $this->admin->getKey()));

        $this->get('/en/faculties/medicine/council')->assertOk()->assertSee('The faculty council meets once a month.');
        $this->get('/ar/faculties/medicine/council')->assertOk()->assertSee('يجتمع مجلس الكلية مرة كل شهر.');
    }

    public function test_the_council_rules(): void
    {
        $dean = $this->member('rules-dean', 'Dr. Rules Dean', 'د. عميد القواعد');
        $other = Faculty::query()->where('public_slug', 'pharmacy')->firstOrFail();
        $outsider = $this->member('rules-outsider', 'Dr. Outsider', 'د. من خارج الكلية', (int) $other->getKey());
        $hidden = $this->member('rules-hidden', 'Dr. Hidden', 'د. مخفي');
        $hidden->forceFill(['is_enabled' => false])->save();
        $workflow = app(CmsWorkflowServiceInterface::class);
        $deanRow = ['positionSlug' => 'dean', 'memberId' => (int) $dean->getKey()];

        $this->assertTrue($workflow->readiness(self::TARGET, $this->councilPayload([$deanRow]))->isReady);

        $noDean = $workflow->readiness(self::TARGET, $this->councilPayload([]));
        $this->assertContains('The faculty must have exactly one dean.', $noDean->errors['ar']);

        $twoDeans = $workflow->readiness(self::TARGET, $this->councilPayload([$deanRow, ['positionSlug' => 'dean', 'memberId' => (int) $hidden->getKey()]]));
        $this->assertContains('The faculty must have exactly one dean.', $twoDeans->errors['en']);

        $noPosition = $workflow->readiness(self::TARGET, $this->councilPayload([$deanRow, ['positionSlug' => null, 'memberId' => (int) $dean->getKey()]]));
        $this->assertContains('Every position must be picked from the positions list.', $noPosition->errors['ar']);

        $headPicked = $workflow->readiness(self::TARGET, $this->councilPayload([$deanRow, ['positionSlug' => 'head_of_department', 'memberId' => (int) $dean->getKey()]]));
        $this->assertContains('Every position must be picked from the positions list.', $headPicked->errors['ar']);

        $noPerson = $workflow->readiness(self::TARGET, $this->councilPayload([$deanRow, ['positionSlug' => 'vice_dean', 'memberId' => null]]));
        $this->assertContains('Every position needs a staff member.', $noPerson->errors['en']);

        $outsiderRow = $workflow->readiness(self::TARGET, $this->councilPayload([$deanRow, ['positionSlug' => 'vice_dean', 'memberId' => (int) $outsider->getKey()]]));
        $this->assertContains('Every position holder must be one of this faculty\'s academic staff.', $outsiderRow->errors['ar']);

        // A hidden profile would vanish from the page, and About › Leadership would
        // fall back to the dean row's old copy (the previous dean's name).
        $hiddenRow = $workflow->readiness(self::TARGET, $this->councilPayload([['positionSlug' => 'dean', 'memberId' => (int) $hidden->getKey()]]));
        $this->assertContains('Every position holder needs a published profile.', $hiddenRow->errors['ar']);

        $twice = $workflow->readiness(self::TARGET, $this->councilPayload([$deanRow, ['positionSlug' => 'dean', 'memberId' => (int) $dean->getKey(), 'id' => 'p-again']]));
        $this->assertContains('A person holds the same position twice.', $twice->errors['ar']);

        $mismatch = $this->councilPayload([$deanRow]);
        $mismatch['translations']['en']['positions'][0]['memberId'] = (int) $outsider->getKey();
        $this->assertArrayHasKey('translations', $workflow->readiness(self::TARGET, $mismatch)->errors);
    }

    public function test_a_page_saved_before_the_positions_list_still_reads(): void
    {
        $dean = $this->member('legacy-dean', 'Dr. Legacy Dean', 'د. عميد سابق');
        $custom = $this->member('legacy-custom', 'Dr. Legacy Custom', 'د. منصب سابق');
        $extra = $this->member('legacy-extra', 'Dr. Legacy Extra', 'د. عضو سابق');
        $legacy = [];

        foreach (['ar' => 'أمين السر', 'en' => 'Secretary'] as $locale => $title) {
            $legacy['translations'][$locale] = [
                'title' => $locale === 'ar' ? 'مجلس الكلية' : 'Faculty Council',
                'positions' => [
                    ['id' => 'p-dean', 'type' => 'dean', 'memberId' => (int) $dean->getKey(), 'title' => '', 'onCouncil' => true],
                    ['id' => 'p-custom', 'type' => 'custom', 'memberId' => (int) $custom->getKey(), 'title' => $title, 'onCouncil' => true],
                ],
                'councilMembers' => [['id' => 'm-extra', 'memberId' => (int) $extra->getKey(), 'role' => $locale === 'ar' ? 'عضو' : 'Member']],
                'includeDepartmentHeads' => true,
            ];
        }

        CmsTargetContent::query()->updateOrCreate(['target_key' => self::TARGET], [
            'status' => PublicationStatus::Published->value,
            'payload_json' => $legacy,
            'published_at' => now()->subDay(),
        ]);
        Cache::flush();

        $page = app(FacultyPageServiceInterface::class)->getSubpage('medicine', 'council', 'en');
        $this->assertNotNull($page);
        $this->assertSame('Dr. Legacy Dean', $page->detail['dean']['name']);
        $this->assertSame(['Secretary'], $page->detail['leadership'][0]['titles']);
        $this->assertSame(['Member'], $page->items[0]['roles']);
        $this->assertSame('legacy-dean', app(FacultyLeadershipServiceInterface::class)->deanProfileSlug('medicine'));

        // The editor loads the dean as a position and the rest with a position to pick.
        $this->actingAs($this->admin, 'web');
        $rows = array_values(Livewire::test(ManageMedicineFaculty::class)
            ->set('data.target_key', self::TARGET)
            ->call('loadTarget', self::TARGET)
            ->get('data.council_positions'));
        $this->assertSame(['dean', null, null], array_column($rows, 'positionSlug'));
        $this->assertSame([(int) $dean->getKey(), (int) $custom->getKey(), (int) $extra->getKey()], array_column($rows, 'memberId'));
    }

    public function test_profiles_show_the_positions_they_hold(): void
    {
        $dean = $this->member('profile-dean', 'Dr. Profile Dean', 'د. عميد الملف');
        $vice = $this->member('profile-vice', 'Dr. Profile Vice', 'د. نائب الملف', position: ['نائب العميد', 'Vice Dean (typed)']);
        $profiles = app(ProfilePageServiceInterface::class);

        // Until a council page is published, the typed one shows.
        $this->assertSame('Vice Dean (typed)', $profiles->getProfile('en', 'faculty-member', 'profile-vice')?->position);

        $this->publishDepartments([
            ['slug' => 'surgery-profile', 'title' => ['قسم الجراحة', 'Department of Surgery'], 'headMemberId' => (int) $vice->getKey()],
        ]);
        $this->publishCouncil([
            ['positionSlug' => 'dean', 'memberId' => (int) $dean->getKey()],
            ['positionSlug' => 'vice_dean', 'memberId' => (int) $vice->getKey()],
        ]);
        Cache::flush();

        $this->assertSame('Vice Dean · Head, Department of Surgery', $profiles->getProfile('en', 'faculty-member', 'profile-vice')?->position);
        $this->assertSame(['نائب العميد', 'رئيس قسم الجراحة'], app(FacultyLeadershipServiceInterface::class)->positionsOfMember((int) $vice->getKey(), 'ar'));

        $members = collect(app(FacultyPageServiceInterface::class)->getSubpage('medicine', 'members', 'en')?->items ?? [])->keyBy('slug');
        $this->assertSame('Dean', $members['profile-dean']['position']);
    }

    public function test_publishing_the_council_makes_its_dean_the_leadership_dean(): void
    {
        $oldDean = $this->member('previous-dean', 'Dr. Previous Dean', 'د. العميد السابق');
        $newDean = $this->member('new-dean', 'Dr. New Dean', 'د. العميد الجديد');
        $row = $this->leadershipDean($oldDean, 'Dean of the Faculty of Medicine', 'عميد كلية الطب البشري');

        $this->publishCouncil([['positionSlug' => 'dean', 'memberId' => (int) $newDean->getKey()]]);

        $deanRows = Person::query()->where('category', 'dean')->where('faculty_scope_slug', 'medicine')->count();
        $this->assertSame((int) $newDean->getKey(), (int) $row->fresh()?->faculty_member_id);
        // The row that was the dean is updated; no second dean row is added.
        $this->assertSame($deanRows, Person::query()->where('category', 'dean')->where('faculty_scope_slug', 'medicine')->count());
        $this->assertTrue(app(FacultyLeadershipServiceInterface::class)->managesDean('medicine'));
        $this->assertSame('new-dean', app(FacultyLeadershipServiceInterface::class)->deanProfileSlug('medicine'));
        $this->assertDatabaseHas('audit_logs', ['action' => 'faculty.council.dean_synced']);

        // The dean block on the faculty overview shows the council's dean.
        $overview = app(FacultyPageServiceInterface::class)->getSubpage('medicine', 'overview', 'en');
        $this->assertNotNull($overview);
        $this->assertNotSame([], $overview->page['payload']['dean'] ?? []);
        $this->assertSame('Dr. New Dean', $overview->deanProfile?->name);
    }

    public function test_publishing_the_council_updates_the_public_dean_row_not_a_hidden_one(): void
    {
        $dean = $this->member('public-row-dean', 'Dr. Public Row Dean', 'د. عميد الصف الظاهر');
        Person::query()->where('category', 'dean')->whereIn('faculty_scope_slug', ['medicine', (string) $this->faculty->faculty_scope_slug])->forceDelete();
        // A former dean's row, hidden and sorted first, and the public row, linked to no profile yet.
        $hidden = $this->deanRow('former-hidden-dean', 1, false);
        $public = $this->deanRow('current-public-dean', 5, true);

        $this->publishCouncil([['positionSlug' => 'dean', 'memberId' => (int) $dean->getKey()]]);

        $this->assertSame((int) $dean->getKey(), (int) $public->fresh()?->faculty_member_id);
        $this->assertNull($hidden->fresh()?->faculty_member_id);
    }

    public function test_publishing_the_council_adds_a_leadership_dean_when_the_faculty_has_none(): void
    {
        $dean = $this->member('first-dean', 'Dr. First Dean', 'د. العميد الأول');
        Person::query()->where('category', 'dean')->whereIn('faculty_scope_slug', ['medicine', (string) $this->faculty->faculty_scope_slug])->forceDelete();

        $this->publishCouncil([['positionSlug' => 'dean', 'memberId' => (int) $dean->getKey()]]);

        $row = Person::query()->with('translations')->where('category', 'dean')->where('faculty_scope_slug', 'medicine')->firstOrFail();
        $this->assertSame((int) $dean->getKey(), (int) $row->faculty_member_id);
        $this->assertSame(PublicationStatus::Published->value, $row->publication_status);
        $this->assertNotSame('', (string) $row->translations->firstWhere('locale', 'ar')?->role);
        $this->assertStringStartsWith('Dean of the ', (string) $row->translations->firstWhere('locale', 'en')?->role);
    }

    public function test_the_admin_edits_positions_once_for_both_languages(): void
    {
        $dean = $this->member('admin-dean', 'Dr. Admin Dean', 'د. عميد الإدارة');
        $vice = $this->member('admin-vice', 'Dr. Admin Vice', 'د. نائب الإدارة');
        $this->actingAs($this->admin, 'web');

        Livewire::test(ManageMedicineFaculty::class)
            ->set('data.target_key', self::TARGET)
            ->call('loadTarget', self::TARGET)
            ->assertSee('قيادة الكلية ومجلسها')
            ->assertSee('المنصب')
            ->set('data.council_positions', [
                ['id' => 'p-dean', 'positionSlug' => 'dean', 'memberId' => (int) $dean->getKey()],
                ['id' => 'p-vice', 'positionSlug' => 'vice_dean', 'memberId' => (int) $vice->getKey()],
            ])
            ->set('data.council_include_heads', false)
            ->call('save');

        $draft = app(CmsWorkflowServiceInterface::class)->latestEditableDraftPayload(self::TARGET, (int) $this->admin->getKey());
        $this->assertIsArray($draft);
        $this->assertSame($draft['translations']['ar']['positions'], $draft['translations']['en']['positions']);
        $this->assertSame('vice_dean', $draft['translations']['en']['positions'][1]['positionSlug']);
        $this->assertSame((int) $vice->getKey(), $draft['translations']['en']['positions'][1]['memberId']);
        $this->assertFalse($draft['translations']['ar']['includeDepartmentHeads']);
        $this->assertArrayNotHasKey('councilMembers', $draft['translations']['ar']);
        $this->assertTrue(app(CmsWorkflowServiceInterface::class)->readiness(self::TARGET, $draft)->isReady);

        // Reloading gives the same rows.
        $component = Livewire::test(ManageMedicineFaculty::class)
            ->set('data.target_key', self::TARGET)
            ->call('loadTarget', self::TARGET);
        $positions = array_values($component->get('data.council_positions'));
        $this->assertSame(['dean', 'vice_dean'], array_column($positions, 'positionSlug'));
    }

    public function test_the_admin_adds_and_edits_positions_from_the_dropdown(): void
    {
        $this->actingAs($this->admin, 'web');
        $component = Livewire::test(ManageMedicineFaculty::class)
            ->set('data.target_key', self::TARGET)
            ->call('loadTarget', self::TARGET)
            ->set('data.council_positions', ['row-1' => ['id' => 'p-1', 'positionSlug' => null, 'memberId' => null]])
            ->callFormComponentAction('council_positions.row-1.positionSlug', 'createOption', [
                'nameAr' => 'أمين سر مجلس الكلية',
                'nameEn' => 'Council Secretary',
                'placement' => 'council',
            ])
            ->assertHasNoFormComponentActionErrors();

        $positions = app(FacultyPositionServiceInterface::class);
        $created = collect($positions->all())->firstWhere('nameEn', 'Council Secretary');
        $this->assertNotNull($created);
        $this->assertSame('council', $created->placement);
        $this->assertSame($created->slug, $component->get('data.council_positions.row-1.positionSlug'));

        $component->callFormComponentAction('council_positions.row-1.positionSlug', 'editOption', [
            'nameAr' => 'أمين السر',
            'nameEn' => 'Secretary',
            'placement' => 'leadership',
        ]);

        $edited = $positions->find($created->slug);
        $this->assertSame('Secretary', $edited?->nameEn);
        $this->assertSame('leadership', $edited?->placement);

        // A taken name keeps the dialog open and saves nothing.
        $component->callFormComponentAction('council_positions.row-1.positionSlug', 'createOption', [
            'nameAr' => 'نائب العميد',
            'nameEn' => 'Another vice dean',
            'placement' => 'council',
        ]);
        $this->assertNull(collect($positions->all())->firstWhere('nameEn', 'Another vice dean'));
    }

    public function test_typed_profile_positions_are_matched_onto_a_council_draft(): void
    {
        $vice = $this->member('typed-vice', 'Dr. Typed Vice', 'د. نائب مكتوب', position: ['نائب العميد للشؤون العلمية', 'Vice Dean for Academic Affairs']);
        $head = $this->member('typed-head', 'Dr. Typed Head', 'د. رئيس مكتوب', position: ['رئيس قسم الجراحة', 'Head of Surgery']);
        $other = $this->member('typed-other', 'Dr. Typed Other', 'د. مكتوب آخر', position: ['أستاذ مساعد', 'Assistant Professor']);

        $this->artisan('faculty:match-positions', ['--faculty' => 'medicine'])
            ->expectsOutputToContain('dry run')
            ->assertSuccessful();
        // A dry run saves nothing.
        $this->assertNull(app(CmsWorkflowServiceInterface::class)->latestEditableDraftPayload(self::TARGET, (int) $this->admin->getKey()));

        $this->artisan('faculty:match-positions', ['--faculty' => 'medicine', '--write' => true, '--user' => (string) $this->admin->getKey()])
            ->assertSuccessful();

        $draft = app(CmsWorkflowServiceInterface::class)->latestEditableDraftPayload(self::TARGET, (int) $this->admin->getKey());
        $this->assertIsArray($draft);
        $rows = collect($draft['translations']['ar']['positions'])->map(fn (array $row): array => [$row['positionSlug'] ?? $row['type'] ?? null, $row['memberId']]);
        $this->assertContains(['vice_dean', (int) $vice->getKey()], $rows->all());
        // Heads of department and text that fits no position are left alone.
        $this->assertNotContains((int) $head->getKey(), $rows->pluck(1)->all());
        $this->assertNotContains((int) $other->getKey(), $rows->pluck(1)->all());
        $this->assertSame($draft['translations']['ar']['positions'], $draft['translations']['en']['positions']);
        // It is a draft: nothing is published.
        $this->assertNull(CmsTargetContent::query()->where('target_key', self::TARGET)->where('status', PublicationStatus::Published->value)->value('id'));

        // Running it again adds nothing twice.
        $this->artisan('faculty:match-positions', ['--faculty' => 'medicine', '--write' => true, '--user' => (string) $this->admin->getKey()])->assertSuccessful();
        $again = app(CmsWorkflowServiceInterface::class)->latestEditableDraftPayload(self::TARGET, (int) $this->admin->getKey());
        $this->assertCount(count($draft['translations']['ar']['positions']), $again['translations']['ar']['positions'] ?? []);
    }

    public function test_matched_positions_can_be_published_when_the_page_is_ready(): void
    {
        $dean = $this->member('published-dean', 'Dr. Published Dean', 'د. العميد المنشور');
        $this->publishCouncil([['positionSlug' => 'dean', 'memberId' => (int) $dean->getKey()]]);
        $vice = $this->member('typed-vice', 'Dr. Typed Vice', 'د. نائب مكتوب', position: ['نائب العميد للشؤون العلمية', 'Vice Dean for Academic Affairs']);

        $this->artisan('faculty:match-positions', ['--faculty' => 'medicine', '--publish' => true])->assertFailed();

        $this->artisan('faculty:match-positions', ['--faculty' => 'medicine', '--write' => true, '--publish' => true, '--user' => (string) $this->admin->getKey()])
            ->expectsOutputToContain('Published: medicine.')
            ->assertSuccessful();

        $published = app(CmsWorkflowServiceInterface::class)->getPublishedPayload(self::TARGET);
        $this->assertContains((int) $vice->getKey(), array_column($published['translations']['ar']['positions'], 'memberId'));
        $this->assertContains((int) $dean->getKey(), array_column($published['translations']['en']['positions'], 'memberId'));
    }

    public function test_matched_positions_are_not_published_over_an_open_draft_or_without_a_dean(): void
    {
        $workflow = app(CmsWorkflowServiceInterface::class);
        $this->member('typed-vice', 'Dr. Typed Vice', 'د. نائب مكتوب', position: ['نائب العميد للشؤون العلمية', 'Vice Dean for Academic Affairs']);

        // No dean: the page fails the council rules, so it stays a draft.
        $this->artisan('faculty:match-positions', ['--faculty' => 'medicine', '--write' => true, '--publish' => true, '--user' => (string) $this->admin->getKey()])
            ->expectsOutputToContain('Left as a draft: medicine')
            ->assertSuccessful();
        $this->assertNull(CmsTargetContent::query()->where('target_key', self::TARGET)->where('status', PublicationStatus::Published->value)->value('id'));
        $this->assertIsArray($workflow->latestEditableDraftPayload(self::TARGET, (int) $this->admin->getKey()));

        // That draft is now an open one. Even with a dean added, a later run leaves it for the editor.
        $draft = $workflow->latestEditableDraftPayload(self::TARGET, (int) $this->admin->getKey());
        $dean = $this->member('open-draft-dean', 'Dr. Open Draft Dean', 'د. عميد المسودة');
        foreach (['ar', 'en'] as $locale) {
            $draft['translations'][$locale]['positions'][] = ['id' => 'dean-row', 'positionSlug' => 'dean', 'memberId' => (int) $dean->getKey()];
        }
        $workflow->saveDraft(self::TARGET, $draft, (int) $this->admin->getKey());
        $this->member('typed-vice-two', 'Dr. Second Vice', 'د. نائب ثان', position: ['نائب العميد للشؤون الإدارية', 'Vice Dean for Administration']);

        $this->artisan('faculty:match-positions', ['--faculty' => 'medicine', '--write' => true, '--publish' => true, '--user' => (string) $this->admin->getKey()])
            ->expectsOutputToContain('an editor already had a draft open')
            ->assertSuccessful();
        $this->assertNull(CmsTargetContent::query()->where('target_key', self::TARGET)->where('status', PublicationStatus::Published->value)->value('id'));
    }

    /**
     * @param  list<array{positionSlug: ?string, memberId: ?int, id?: string}>  $rows
     * @return array<string, mixed>
     */
    private function councilPayload(array $rows): array
    {
        $payload = app(FacultyPageServiceInterface::class)->getEditablePayload(self::TARGET);
        $positions = array_map(static fn (array $row, int $index): array => [
            'id' => $row['id'] ?? 'p-'.$index,
            'positionSlug' => $row['positionSlug'],
            'memberId' => $row['memberId'],
        ], $rows, array_keys($rows));

        foreach (['ar', 'en'] as $locale) {
            $payload['translations'][$locale]['positions'] = $positions;
            $payload['translations'][$locale]['includeDepartmentHeads'] = true;
        }

        return $payload;
    }

    /** @param  list<array{positionSlug: ?string, memberId: ?int, id?: string}>  $rows */
    private function publishCouncil(array $rows): void
    {
        $workflow = app(CmsWorkflowServiceInterface::class);
        $payload = $this->councilPayload($rows);
        $readiness = $workflow->readiness(self::TARGET, $payload);
        $this->assertTrue($readiness->isReady, json_encode($readiness->errors, JSON_UNESCAPED_UNICODE) ?: '');
        $workflow->saveDraft(self::TARGET, $payload, (int) $this->admin->getKey());
        $this->assertTrue($workflow->publish(self::TARGET, (int) $this->admin->getKey()));
    }

    private function addPosition(string $arabic, string $english, string $placement): string
    {
        return app(FacultyPositionServiceInterface::class)->save(null, $arabic, $english, $placement, self::TARGET, (int) $this->admin->getKey())->slug;
    }

    /**
     * @param  list<array{slug: string, title: array{0: string, 1: string}, headMemberId: int, headTitle?: array{0: string, 1: string}, headOnCouncil?: bool, legacy?: bool}>  $departments
     */
    private function publishDepartments(array $departments): void
    {
        $workflow = app(CmsWorkflowServiceInterface::class);
        $payload = app(FacultyPageServiceInterface::class)->getEditablePayload(self::DEPARTMENTS_TARGET);

        foreach (['ar' => 0, 'en' => 1] as $locale => $index) {
            $payload['translations'][$locale]['items'] = array_map(static function (array $department) use ($index): array {
                $item = [
                    'slug' => $department['slug'],
                    'title' => $department['title'][$index],
                    'summary' => '',
                    'type' => 'section',
                    'headMemberId' => $department['headMemberId'],
                    'studyPlanDepartmentId' => null,
                ];

                // A department saved before the head's title and council switch existed has neither.
                if (! ($department['legacy'] ?? false)) {
                    $item['headTitle'] = isset($department['headTitle']) ? $department['headTitle'][$index] : '';
                    $item['headOnCouncil'] = $department['headOnCouncil'] ?? true;
                }

                return $item;
            }, $departments);
        }

        $readiness = $workflow->readiness(self::DEPARTMENTS_TARGET, $payload);
        $this->assertTrue($readiness->isReady, json_encode($readiness->errors, JSON_UNESCAPED_UNICODE) ?: '');
        $workflow->saveDraft(self::DEPARTMENTS_TARGET, $payload, (int) $this->admin->getKey());
        $this->assertTrue($workflow->publish(self::DEPARTMENTS_TARGET, (int) $this->admin->getKey()));
    }

    private function leadershipDean(FacultyMember $member, string $roleEn, string $roleAr): Person
    {
        $person = Person::query()->create([
            'faculty_member_id' => (int) $member->getKey(),
            'slug' => $member->slug.'-leadership',
            'category' => 'dean',
            'faculty_scope_slug' => 'medicine',
            'sort_order' => 5,
            'is_enabled' => true,
            'publication_status' => PublicationStatus::Published->value,
            'published_at' => now()->subDay(),
        ]);
        $person->translations()->create(['locale' => 'en', 'name' => 'Old copy', 'role' => $roleEn]);
        $person->translations()->create(['locale' => 'ar', 'name' => 'نسخة قديمة', 'role' => $roleAr]);

        return $person;
    }

    private function deanRow(string $slug, int $sortOrder, bool $public): Person
    {
        $person = Person::query()->create([
            'slug' => $slug,
            'category' => 'dean',
            'faculty_scope_slug' => 'medicine',
            'sort_order' => $sortOrder,
            'is_enabled' => $public,
            'publication_status' => PublicationStatus::Published->value,
            'published_at' => now()->subDay(),
        ]);
        $person->translations()->create(['locale' => 'en', 'name' => $slug, 'role' => 'Dean of the Faculty of Medicine']);
        $person->translations()->create(['locale' => 'ar', 'name' => $slug, 'role' => 'عميد كلية الطب البشري']);

        return $person;
    }

    /** @param  array{0: string, 1: string}|null  $position  the position typed on the profile, in Arabic and English */
    private function member(string $slug, string $en, string $ar, ?int $facultyId = null, ?array $position = null): FacultyMember
    {
        $member = FacultyMember::query()->create([
            'slug' => $slug,
            'faculty_id' => $facultyId ?? (int) $this->faculty->getKey(),
            'is_enabled' => true,
            'publication_status' => PublicationStatus::Published->value,
            'published_at' => now()->subDay(),
        ]);
        $member->translations()->create(['locale' => 'en', 'full_name' => $en, 'position' => $position[1] ?? null]);
        $member->translations()->create(['locale' => 'ar', 'full_name' => $ar, 'position' => $position[0] ?? null]);

        return $member;
    }
}
