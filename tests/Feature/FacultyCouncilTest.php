<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Contracts\Cms\CmsWorkflowServiceInterface;
use App\Contracts\Faculty\FacultyLeadershipServiceInterface;
use App\Contracts\Page\FacultyPageServiceInterface;
use App\Enums\PublicationStatus;
use App\Filament\Pages\ManageMedicineFaculty;
use App\Models\Faculty\Faculty;
use App\Models\Faculty\FacultyPage;
use App\Models\Faculty\FacultySubpageCard;
use App\Models\Person\FacultyMember;
use App\Models\Person\Person;
use App\Models\User\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Each faculty has a Faculty Council page: the dean, vice deans and other positions,
 * and the council (the positions on it, the department heads and extra members).
 * Everyone is one of the faculty's academic staff, and one person can hold several
 * roles. The dean published there is also the dean on About › Leadership and in
 * the faculty's dean block.
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
            ->assertSee('Dean of the Faculty of Medicine')
            ->assertSee('Council Chair');

        // The page is one of the faculty's navigation cards.
        $faculty = app(FacultyPageServiceInterface::class)->getFaculty('medicine', 'en');
        $this->assertNotNull($faculty);
        $this->assertContains('council', $faculty->navigation->pluck('slug')->all());
        $this->get('/en/faculties/medicine')
            ->assertOk()
            ->assertSee('/council', false)
            ->assertSee('Faculty Council');

        $payload = app(FacultyPageServiceInterface::class)->getEditablePayload(self::TARGET);
        $this->assertSame((int) $dean->getKey(), $payload['translations']['ar']['positions'][0]['memberId']);
        $this->assertSame('dean', $payload['translations']['en']['positions'][0]['type']);
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
        $this->assertNotContains('council', app(FacultyPageServiceInterface::class)->getFaculty('medicine', 'en')?->navigation->pluck('slug')->all() ?? []);
    }

    public function test_the_published_council_lists_each_person_once_with_all_their_roles(): void
    {
        $dean = $this->member('council-dean', 'Dr. Dean Person', 'د. العميد');
        $vice = $this->member('council-vice', 'Dr. Vice Person', 'د. نائب العميد');
        $extra = $this->member('council-extra', 'Dr. Extra Member', 'د. عضو إضافي');
        $this->publishDepartmentHead($vice);
        $this->publishCouncil([
            ['id' => 'p-dean', 'type' => 'dean', 'memberId' => (int) $dean->getKey(), 'title' => ['', ''], 'onCouncil' => true],
            ['id' => 'p-vice', 'type' => 'vice_dean', 'memberId' => (int) $vice->getKey(), 'title' => ['نائب العميد للشؤون العلمية', 'Vice Dean for Academic Affairs'], 'onCouncil' => true],
            ['id' => 'p-secretary', 'type' => 'custom', 'memberId' => (int) $vice->getKey(), 'title' => ['أمين سر المجلس', 'Council Secretary'], 'onCouncil' => true],
        ], [
            ['id' => 'm-extra', 'memberId' => (int) $extra->getKey(), 'role' => ['', 'Staff representative']],
        ]);

        $page = app(FacultyPageServiceInterface::class)->getSubpage('medicine', 'council', 'en');
        $this->assertNotNull($page);
        $this->assertSame(['dean', 'vice_dean', 'custom'], array_column($page->detail['positions'], 'type'));
        $this->assertSame('Dean', $page->detail['positions'][0]['title']);

        $members = collect($page->items)->keyBy('memberId');
        $this->assertCount(3, $members);
        $this->assertTrue($members[(int) $dean->getKey()]['isChair']);
        $this->assertSame(
            ['Vice Dean for Academic Affairs', 'Council Secretary', 'Head, Department of Surgery'],
            $members[(int) $vice->getKey()]['roles'],
        );
        $this->assertSame(['Staff representative'], $members[(int) $extra->getKey()]['roles']);

        $this->get('/en/faculties/medicine/council')
            ->assertOk()
            ->assertSee('Dr. Vice Person')
            ->assertSee('Vice Dean for Academic Affairs · Council Secretary · Head, Department of Surgery')
            ->assertSee('/en/about/profile/council-extra', false);

        $this->get('/ar/faculties/medicine/council')
            ->assertOk()
            ->assertSee('مجلس الكلية')
            ->assertSee('عميد الكلية')
            ->assertSee('رئيس قسم الجراحة')
            ->assertSee('عضو');
    }

    public function test_the_council_rules(): void
    {
        $dean = $this->member('rules-dean', 'Dr. Rules Dean', 'د. عميد القواعد');
        $other = Faculty::query()->where('public_slug', 'pharmacy')->firstOrFail();
        $outsider = $this->member('rules-outsider', 'Dr. Outsider', 'د. من خارج الكلية', (int) $other->getKey());
        $workflow = app(CmsWorkflowServiceInterface::class);
        $deanPosition = ['id' => 'p-dean', 'type' => 'dean', 'memberId' => (int) $dean->getKey(), 'title' => ['', ''], 'onCouncil' => true];

        $this->assertTrue($workflow->readiness(self::TARGET, $this->councilPayload([$deanPosition]))->isReady);

        $noDean = $workflow->readiness(self::TARGET, $this->councilPayload([]));
        $this->assertContains('The faculty must have exactly one dean.', $noDean->errors['ar']);

        $twoDeans = $workflow->readiness(self::TARGET, $this->councilPayload([$deanPosition, [...$deanPosition, 'id' => 'p-dean-2']]));
        $this->assertContains('The faculty must have exactly one dean.', $twoDeans->errors['en']);

        $outsiderPosition = $workflow->readiness(self::TARGET, $this->councilPayload([
            $deanPosition,
            ['id' => 'p-out', 'type' => 'vice_dean', 'memberId' => (int) $outsider->getKey(), 'title' => ['', ''], 'onCouncil' => true],
        ]));
        $this->assertContains('Every position holder must be one of this faculty\'s academic staff.', $outsiderPosition->errors['ar']);

        $outsiderMember = $workflow->readiness(self::TARGET, $this->councilPayload([$deanPosition], [
            ['id' => 'm-out', 'memberId' => (int) $outsider->getKey(), 'role' => ['', '']],
        ]));
        $this->assertContains('Every council member must be one of this faculty\'s academic staff.', $outsiderMember->errors['en']);

        $untitled = $workflow->readiness(self::TARGET, $this->councilPayload([
            $deanPosition,
            ['id' => 'p-custom', 'type' => 'custom', 'memberId' => (int) $dean->getKey(), 'title' => ['', ''], 'onCouncil' => false],
        ]));
        $this->assertContains('A custom position needs a title.', $untitled->errors['ar']);

        $mismatch = $this->councilPayload([$deanPosition]);
        $mismatch['translations']['en']['positions'][0]['memberId'] = (int) $outsider->getKey();
        $this->assertArrayHasKey('translations', $workflow->readiness(self::TARGET, $mismatch)->errors);
    }

    public function test_a_hidden_profile_cannot_hold_a_position_or_sit_on_the_council(): void
    {
        // A hidden profile would vanish from the page, and About › Leadership would
        // fall back to the dean row's old copy (the previous dean's name).
        $dean = $this->member('hidden-rules-dean', 'Dr. Visible Dean', 'د. عميد ظاهر');
        $hidden = $this->member('hidden-rules-member', 'Dr. Hidden', 'د. مخفي');
        $hidden->forceFill(['is_enabled' => false])->save();
        $workflow = app(CmsWorkflowServiceInterface::class);

        $hiddenDean = $workflow->readiness(self::TARGET, $this->councilPayload([
            ['id' => 'p-dean', 'type' => 'dean', 'memberId' => (int) $hidden->getKey(), 'title' => ['', ''], 'onCouncil' => true],
        ]));
        $this->assertContains('Every position holder needs a published profile.', $hiddenDean->errors['ar']);

        $hiddenMember = $workflow->readiness(self::TARGET, $this->councilPayload([
            ['id' => 'p-dean', 'type' => 'dean', 'memberId' => (int) $dean->getKey(), 'title' => ['', ''], 'onCouncil' => true],
        ], [
            ['id' => 'm-hidden', 'memberId' => (int) $hidden->getKey(), 'role' => ['', '']],
        ]));
        $this->assertContains('Every council member needs a published profile.', $hiddenMember->errors['en']);
    }

    public function test_publishing_the_council_makes_its_dean_the_leadership_dean(): void
    {
        $oldDean = $this->member('previous-dean', 'Dr. Previous Dean', 'د. العميد السابق');
        $newDean = $this->member('new-dean', 'Dr. New Dean', 'د. العميد الجديد');
        $row = $this->leadershipDean($oldDean, 'Dean of the Faculty of Medicine', 'عميد كلية الطب البشري');

        $this->publishCouncil([
            ['id' => 'p-dean', 'type' => 'dean', 'memberId' => (int) $newDean->getKey(), 'title' => ['', ''], 'onCouncil' => true],
        ]);

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

        $this->publishCouncil([
            ['id' => 'p-dean', 'type' => 'dean', 'memberId' => (int) $dean->getKey(), 'title' => ['', ''], 'onCouncil' => true],
        ]);

        $this->assertSame((int) $dean->getKey(), (int) $public->fresh()?->faculty_member_id);
        $this->assertNull($hidden->fresh()?->faculty_member_id);
    }

    public function test_publishing_the_council_adds_a_leadership_dean_when_the_faculty_has_none(): void
    {
        $dean = $this->member('first-dean', 'Dr. First Dean', 'د. العميد الأول');
        Person::query()->where('category', 'dean')->whereIn('faculty_scope_slug', ['medicine', (string) $this->faculty->faculty_scope_slug])->forceDelete();

        $this->publishCouncil([
            ['id' => 'p-dean', 'type' => 'dean', 'memberId' => (int) $dean->getKey(), 'title' => ['', ''], 'onCouncil' => true],
        ]);

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
            ->assertSee('إضافة رؤساء الأقسام إلى المجلس تلقائياً')
            ->set('data.council_positions', [
                ['id' => 'p-dean', 'type' => 'dean', 'memberId' => (int) $dean->getKey(), 'titleAr' => '', 'titleEn' => '', 'onCouncil' => true],
                ['id' => 'p-vice', 'type' => 'vice_dean', 'memberId' => (int) $vice->getKey(), 'titleAr' => 'نائب العميد للشؤون الإدارية', 'titleEn' => 'Vice Dean for Administrative Affairs', 'onCouncil' => true],
            ])
            ->set('data.council_members', [])
            ->set('data.council_include_heads', false)
            ->call('save');

        $draft = app(CmsWorkflowServiceInterface::class)->latestEditableDraftPayload(self::TARGET, (int) $this->admin->getKey());
        $this->assertIsArray($draft);
        $this->assertSame('Vice Dean for Administrative Affairs', $draft['translations']['en']['positions'][1]['title']);
        $this->assertSame('نائب العميد للشؤون الإدارية', $draft['translations']['ar']['positions'][1]['title']);
        $this->assertSame((int) $vice->getKey(), $draft['translations']['en']['positions'][1]['memberId']);
        $this->assertFalse($draft['translations']['ar']['includeDepartmentHeads']);
        $this->assertTrue(app(CmsWorkflowServiceInterface::class)->readiness(self::TARGET, $draft)->isReady);

        // Reloading pairs the languages back into one row per position.
        $component = Livewire::test(ManageMedicineFaculty::class)
            ->set('data.target_key', self::TARGET)
            ->call('loadTarget', self::TARGET);
        $positions = array_values($component->get('data.council_positions'));
        $this->assertSame('Vice Dean for Administrative Affairs', $positions[1]['titleEn']);
        $this->assertSame('نائب العميد للشؤون الإدارية', $positions[1]['titleAr']);
    }

    /**
     * @param  list<array{id: string, type: string, memberId: int, title: array{0: string, 1: string}, onCouncil: bool}>  $positions
     * @param  list<array{id: string, memberId: int, role: array{0: string, 1: string}}>  $members
     * @return array<string, mixed>
     */
    private function councilPayload(array $positions, array $members = []): array
    {
        $payload = app(FacultyPageServiceInterface::class)->getEditablePayload(self::TARGET);

        foreach (['ar' => 0, 'en' => 1] as $locale => $index) {
            $payload['translations'][$locale]['positions'] = array_map(static fn (array $position): array => [
                ...$position,
                'title' => $position['title'][$index],
            ], $positions);
            $payload['translations'][$locale]['councilMembers'] = array_map(static fn (array $member): array => [
                ...$member,
                'role' => $member['role'][$index],
            ], $members);
            $payload['translations'][$locale]['includeDepartmentHeads'] = true;
        }

        return $payload;
    }

    /**
     * @param  list<array{id: string, type: string, memberId: int, title: array{0: string, 1: string}, onCouncil: bool}>  $positions
     * @param  list<array{id: string, memberId: int, role: array{0: string, 1: string}}>  $members
     */
    private function publishCouncil(array $positions, array $members = []): void
    {
        $workflow = app(CmsWorkflowServiceInterface::class);
        $payload = $this->councilPayload($positions, $members);
        $readiness = $workflow->readiness(self::TARGET, $payload);
        $this->assertTrue($readiness->isReady, json_encode($readiness->errors, JSON_UNESCAPED_UNICODE) ?: '');
        $workflow->saveDraft(self::TARGET, $payload, (int) $this->admin->getKey());
        $this->assertTrue($workflow->publish(self::TARGET, (int) $this->admin->getKey()));
    }

    private function publishDepartmentHead(FacultyMember $head): void
    {
        $workflow = app(CmsWorkflowServiceInterface::class);
        $payload = app(FacultyPageServiceInterface::class)->getEditablePayload(self::DEPARTMENTS_TARGET);

        foreach (['ar', 'en'] as $locale) {
            $payload['translations'][$locale]['items'] = [[
                'slug' => 'surgery-council-test',
                'title' => $locale === 'ar' ? 'قسم الجراحة' : 'Department of Surgery',
                'summary' => '',
                'type' => 'section',
                'headMemberId' => (int) $head->getKey(),
                'studyPlanDepartmentId' => null,
            ]];
        }

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

    private function member(string $slug, string $en, string $ar, ?int $facultyId = null): FacultyMember
    {
        $member = FacultyMember::query()->create([
            'slug' => $slug,
            'faculty_id' => $facultyId ?? (int) $this->faculty->getKey(),
            'is_enabled' => true,
            'publication_status' => PublicationStatus::Published->value,
            'published_at' => now()->subDay(),
        ]);
        $member->translations()->create(['locale' => 'en', 'full_name' => $en]);
        $member->translations()->create(['locale' => 'ar', 'full_name' => $ar]);

        return $member;
    }
}
