<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Contracts\Cms\CmsWorkflowServiceInterface;
use App\Contracts\Council\CouncilCatalogServiceInterface;
use App\Filament\Resources\CouncilResource\Pages\CreateCouncil;
use App\Filament\Resources\CouncilResource\Pages\EditCouncil;
use App\Models\Content\UniversityCouncil;
use App\Models\Person\FacultyMember;
use App\Models\User\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * About › Councils: one template for every council page, and a list that shows
 * each published council by itself.
 */
final class CouncilsPagesTest extends TestCase
{
    use RefreshDatabase;

    public function test_councils_page_lists_every_published_shown_council_in_order(): void
    {
        $user = $this->admin();
        $second = $this->council('graduate-studies-council', 2);
        $first = $this->council('university-council', 1);
        $hidden = $this->council('hidden-council', 3, enabled: false);
        $unpublished = $this->council('draft-council', 4);

        $this->publish($first, $user, 'University Council', 'مجلس الجامعة');
        $this->publish($second, $user, 'Graduate Studies Council', 'مجلس الدراسات العليا');
        $this->publish($hidden, $user, 'Hidden Council', 'مجلس مخفي');
        app(CmsWorkflowServiceInterface::class)->saveDraft($this->catalog()->targetKey($unpublished), $this->payload('Draft Council', 'مجلس مسودة'), (int) $user->getKey());

        $content = (string) $this->get('/en/about/councils')
            ->assertOk()
            ->assertSee('/en/about/councils/university-council', false)
            ->assertSee('/en/about/councils/graduate-studies-council', false)
            ->assertDontSee('Hidden Council')
            ->assertDontSee('Draft Council')
            ->getContent();
        $this->assertLessThan(strpos($content, 'Graduate Studies Council'), strpos($content, 'University Council'));

        $this->get('/ar/about/councils')
            ->assertOk()
            ->assertSee('مجلس الجامعة')
            ->assertSee('/ar/about/councils/graduate-studies-council', false);

        $this->get('/en/about/councils/hidden-council')->assertNotFound();
        $this->get('/en/about/councils/draft-council')->assertNotFound();
        $this->get('/en/about/councils/no-such-council')->assertNotFound();
    }

    public function test_council_page_shows_heads_staff_and_typed_in_members_and_functions(): void
    {
        $user = $this->admin();
        $head = $this->staff('council-head', 'Dr. Council Head', 'head@spu.edu.sy');
        $hidden = $this->staff('council-hidden', 'Dr. Unpublished Staff', 'hidden@spu.edu.sy', published: false);
        $id = $this->council('university-council', 1);
        $members = fn (string $guest): array => [
            ['id' => 'm1', 'role' => 'head', 'isStaff' => true, 'staffId' => $head->getKey(), 'name' => '', 'position' => 'University President', 'image' => null],
            ['id' => 'm2', 'role' => 'member', 'isStaff' => false, 'staffId' => null, 'name' => $guest, 'position' => 'External expert', 'image' => null],
            ['id' => 'm3', 'role' => 'member', 'isStaff' => true, 'staffId' => $hidden->getKey(), 'name' => '', 'position' => '', 'image' => null],
        ];
        $this->publish($id, $user, 'University Council', 'مجلس الجامعة', $members('Guest Member'), $members('عضو ضيف'));

        $response = $this->get('/en/about/councils/university-council')
            ->assertOk()
            ->assertSee('University Council')
            ->assertSee('Dr. Council Head')
            ->assertSee('head@spu.edu.sy')
            ->assertSee('/en/about/profile/council-head', false)
            ->assertSee('Council Head Label')
            ->assertSee('University President')
            ->assertSee('Guest Member')
            ->assertSee('External expert')
            ->assertSee('Council Member Label')
            ->assertSee('Approve the academic calendar')
            ->assertSee('/en/about/councils', false)
            ->assertDontSee('Dr. Unpublished Staff');
        $this->assertSame(1, substr_count((string) $response->getContent(), 'class="rc-link"'));

        $this->get('/ar/about/councils/university-council')
            ->assertOk()
            ->assertSee('dir="rtl"', false)
            ->assertSee('عضو ضيف');

        // The list card names the head and counts the shown members and functions.
        $this->get('/en/about/councils')
            ->assertOk()
            ->assertSee('Dr. Council Head')
            ->assertSee('Members: 2')
            ->assertSee('Functions: 1');
    }

    public function test_publish_is_refused_when_a_member_is_incomplete_or_the_languages_differ(): void
    {
        $user = $this->admin();
        $id = $this->council('university-council', 1);
        $payload = $this->payload('University Council', 'مجلس الجامعة',
            [['id' => 'm1', 'role' => 'head', 'isStaff' => true, 'staffId' => null, 'name' => '', 'position' => '', 'image' => null]],
            [['id' => 'other', 'role' => 'head', 'isStaff' => false, 'staffId' => null, 'name' => '', 'position' => '', 'image' => null]],
        );

        $errors = $this->catalog()->validationErrors($payload);

        $this->assertNotEmpty($errors['members'] ?? []);
        $workflow = app(CmsWorkflowServiceInterface::class);
        $workflow->saveDraft($this->catalog()->targetKey($id), $payload, (int) $user->getKey());
        $this->expectException(ValidationException::class);
        $workflow->publish($this->catalog()->targetKey($id), (int) $user->getKey());
    }

    public function test_research_office_address_redirects_to_its_council_once_published(): void
    {
        $user = $this->admin();
        $id = (int) UniversityCouncil::query()->where('former_path', '/research/office')->value('id');
        $this->assertGreaterThan(0, $id, 'The migration adds the research council.');

        $this->get('/en/research/office')->assertNotFound();

        $this->publish($id, $user, 'Research and Scientific Publication Council', 'مجلس البحث والنشر العلمي');

        $this->get('/en/research/office')->assertRedirect('/en/about/councils/research-council')->assertStatus(301);
        $this->get('/ar/research/office')->assertRedirect('/ar/about/councils/research-council');
        $this->get('/en/about/councils/research-council')->assertOk()->assertSee('Research and Scientific Publication Council');
    }

    public function test_admin_creates_a_council_edits_its_members_and_publishes_it(): void
    {
        $user = $this->admin();
        $head = $this->staff('board-head', 'Dr. Board Head', 'board@spu.edu.sy');
        $this->actingAs($user, 'web');

        Livewire::test(CreateCouncil::class)
            ->fillForm([
                'slug' => 'quality-council',
                'sort_order' => 5,
                'is_enabled' => true,
                'title_ar' => 'مجلس الجودة',
                'title_en' => 'Quality Council',
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $council = UniversityCouncil::query()->where('slug', 'quality-council')->firstOrFail();
        $targetKey = $this->catalog()->targetKey((int) $council->getKey());
        $draft = app(CmsWorkflowServiceInterface::class)->latestEditableDraftPayload($targetKey, (int) $user->getKey());
        $this->assertSame('Quality Council', $draft['translations']['en']['hero']['title'] ?? null);
        $this->get('/en/about/councils/quality-council')->assertNotFound();

        Livewire::test(EditCouncil::class, ['record' => $council->getKey()])
            ->set('data.content.en.hero.summary', 'Assures academic quality.')
            ->set('data.content.ar.hero.summary', 'يضمن الجودة الأكاديمية.')
            ->set('data.members', [
                ['id' => 'h1', 'role' => 'head', 'isStaff' => true, 'staffId' => $head->getKey(), 'nameAr' => '', 'nameEn' => '', 'positionAr' => 'رئيس المجلس', 'positionEn' => 'Council Chair', 'image' => null],
                ['id' => 'g1', 'role' => 'member', 'isStaff' => false, 'staffId' => null, 'nameAr' => 'خبير خارجي', 'nameEn' => 'Outside Expert', 'positionAr' => '', 'positionEn' => '', 'image' => null],
            ])
            ->set('data.content.en.functions.items', [['title' => 'Review programs', 'description' => 'Each year.']])
            ->set('data.content.ar.functions.items', [['title' => 'مراجعة البرامج', 'description' => 'كل عام.']])
            ->call('save')
            ->assertHasNoFormErrors()
            ->callAction('publish');

        $this->get('/en/about/councils/quality-council')
            ->assertOk()
            ->assertSee('Quality Council')
            ->assertSee('Assures academic quality.')
            ->assertSee('Dr. Board Head')
            ->assertSee('Council Chair')
            ->assertSee('Outside Expert')
            ->assertSee('Review programs');
        $this->get('/ar/about/councils/quality-council')
            ->assertOk()
            ->assertSee('خبير خارجي')
            ->assertSee('مراجعة البرامج');
        $this->get('/en/about/councils')->assertOk()->assertSee('/en/about/councils/quality-council', false);
    }

    public function test_hiding_or_deleting_a_council_takes_its_page_down(): void
    {
        $user = $this->admin();
        $id = $this->council('university-council', 1);
        $this->publish($id, $user, 'University Council', 'مجلس الجامعة');
        $this->get('/en/about/councils/university-council')->assertOk();

        $this->catalog()->updateSettings($id, 1, false);
        $this->get('/en/about/councils/university-council')->assertNotFound();
        $this->get('/en/about/councils')->assertOk()->assertDontSee('/en/about/councils/university-council', false);

        $this->catalog()->updateSettings($id, 1, true);
        $this->get('/en/about/councils/university-council')->assertOk();

        $this->catalog()->deleteCouncil($id);
        $this->get('/en/about/councils/university-council')->assertNotFound();
        $this->assertFalse(DB::table('cms_target_contents')->where('target_key', 'entity.council.'.$id)->where('status', 'published')->exists());
    }

    public function test_editors_can_manage_councils_and_others_cannot(): void
    {
        $editor = User::factory()->create(['role_slug' => 'editor']);
        DB::table('role_user')->insertOrIgnore(['user_id' => $editor->getKey(), 'role_id' => DB::table('roles')->where('slug', 'editor')->value('id')]);
        $this->actingAs($editor, 'web')->get('/admin/councils')->assertOk();

        $hr = User::factory()->create(['role_slug' => 'hr']);
        DB::table('role_user')->insertOrIgnore(['user_id' => $hr->getKey(), 'role_id' => DB::table('roles')->where('slug', 'hr')->value('id')]);
        $this->actingAs($hr, 'web')->get('/admin/councils')->assertForbidden();
    }

    public function test_migration_moves_the_research_council_and_adds_councils_to_the_menu(): void
    {
        DB::table('university_councils')->delete();
        $content = fn (string $title): array => [
            'hero' => ['eyebrow' => 'Research', 'title' => $title, 'summary' => 'Kept summary.', 'breadcrumbs' => [['label' => 'Research', 'url' => '/research']]],
            'council' => ['title' => 'Council Members', 'members' => []],
            'functions' => ['title' => 'Council Functions', 'items' => [['title' => 'Review proposals', 'description' => '']]],
        ];
        DB::table('cms_target_contents')->insert([
            'target_key' => 'research.office',
            'status' => 'published',
            'payload_json' => json_encode(['translations' => ['ar' => $content('مجلس البحث والنشر العلمي'), 'en' => $content('Research and Scientific Publication Council')]]),
            'published_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        foreach (['ar', 'en'] as $locale) {
            $about = DB::table('menu_items')->insertGetId(['type' => 'header', 'label' => 'About', 'locale' => $locale, 'target_kind' => 'page', 'depth' => 0, 'sort_order' => 1, 'created_at' => now(), 'updated_at' => now()]);
            DB::table('menu_items')->insert([
                ['parent_id' => $about, 'type' => 'header', 'label' => 'History', 'locale' => $locale, 'target_kind' => 'url', 'url' => '/'.$locale.'/about/history', 'depth' => 1, 'sort_order' => 1, 'created_at' => now(), 'updated_at' => now()],
                ['parent_id' => $about, 'type' => 'header', 'label' => 'Partnerships', 'locale' => $locale, 'target_kind' => 'url', 'url' => '/'.$locale.'/about/partnerships', 'depth' => 1, 'sort_order' => 2, 'created_at' => now(), 'updated_at' => now()],
                ['parent_id' => $about, 'type' => 'header', 'label' => 'Contact', 'locale' => $locale, 'target_kind' => 'url', 'url' => '/'.$locale.'/about/contact', 'depth' => 1, 'sort_order' => 3, 'created_at' => now(), 'updated_at' => now()],
                ['parent_id' => null, 'type' => 'header', 'label' => 'Council', 'locale' => $locale, 'target_kind' => 'url', 'url' => '/'.$locale.'/research/office', 'depth' => 0, 'sort_order' => 9, 'created_at' => now(), 'updated_at' => now()],
            ]);
        }

        $migration = require database_path('migrations/2026_11_08_000004_move_research_council_into_councils.php');
        $migration->up();
        $migration->up();

        $council = DB::table('university_councils')->first();
        $this->assertSame('research-council', $council->slug);
        $this->assertSame(1, DB::table('university_councils')->count());
        $payload = json_decode((string) DB::table('cms_target_contents')->where('target_key', 'entity.council.'.$council->id)->value('payload_json'), true);
        $this->assertSame('Research and Scientific Publication Council', $payload['translations']['en']['hero']['title']);
        $this->assertSame('Kept summary.', $payload['translations']['en']['hero']['summary']);
        $this->assertArrayNotHasKey('breadcrumbs', $payload['translations']['en']['hero']);
        $this->assertSame('published', DB::table('cms_target_contents')->where('target_key', 'entity.council.'.$council->id)->value('status'));

        foreach (['ar' => 'المجالس', 'en' => 'Councils'] as $locale => $label) {
            $this->assertFalse(DB::table('menu_items')->where('url', '/'.$locale.'/research/office')->exists());
            $this->assertTrue(DB::table('menu_items')->where('url', '/'.$locale.'/about/councils/research-council')->exists());
            $items = DB::table('menu_items')->where('locale', $locale)->where('depth', 1)->orderBy('sort_order')->pluck('label')->all();
            $this->assertSame(['History', 'Partnerships', $label, 'Contact'], $items);
        }

        $this->get('/en/research/office')->assertRedirect('/en/about/councils/research-council');
    }

    private function admin(): User
    {
        return User::factory()->create(['role_slug' => 'super_admin']);
    }

    private function catalog(): CouncilCatalogServiceInterface
    {
        return app(CouncilCatalogServiceInterface::class);
    }

    private function council(string $slug, int $sortOrder, bool $enabled = true): int
    {
        return $this->catalog()->createCouncil($slug, $sortOrder, $enabled);
    }

    /**
     * @param  list<array<string, mixed>>  $membersEn
     * @param  list<array<string, mixed>>  $membersAr
     */
    private function publish(int $id, User $user, string $titleEn, string $titleAr, array $membersEn = [], array $membersAr = []): void
    {
        $workflow = app(CmsWorkflowServiceInterface::class);
        $targetKey = $this->catalog()->targetKey($id);
        $workflow->saveDraft($targetKey, $this->payload($titleEn, $titleAr, $membersEn, $membersAr), (int) $user->getKey());
        $workflow->publish($targetKey, (int) $user->getKey());
    }

    /**
     * @param  list<array<string, mixed>>  $membersEn
     * @param  list<array<string, mixed>>  $membersAr
     * @return array<string, mixed>
     */
    private function payload(string $titleEn, string $titleAr, array $membersEn = [], array $membersAr = []): array
    {
        $content = fn (string $title, array $members, string $function): array => [
            'hero' => ['title' => $title, 'summary' => $title.' summary.', 'backgroundImage' => null],
            'council' => [
                'title' => 'Council Members',
                'subtitle' => '',
                'headLabel' => 'Council Head Label',
                'memberLabel' => 'Council Member Label',
                'profileLabel' => 'View profile',
                'members' => $members,
            ],
            'functions' => [
                'title' => 'Council Functions',
                'subtitle' => '',
                'items' => [['title' => $function, 'description' => 'Function text.']],
            ],
        ];

        return ['translations' => [
            'ar' => $content($titleAr, $membersAr, 'اعتماد التقويم الأكاديمي'),
            'en' => $content($titleEn, $membersEn, 'Approve the academic calendar'),
        ]];
    }

    private function staff(string $slug, string $name, string $email, bool $published = true): FacultyMember
    {
        $member = FacultyMember::query()->create([
            'slug' => $slug,
            'email' => $email,
            'is_enabled' => true,
            'publication_status' => $published ? 'published' : 'draft',
            'published_at' => $published ? now()->subDay() : null,
        ]);
        foreach (['ar', 'en'] as $locale) {
            $member->translations()->create(['locale' => $locale, 'full_name' => $name]);
        }

        return $member;
    }
}
