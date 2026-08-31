<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Contracts\Cms\CmsWorkflowServiceInterface;
use App\Contracts\Page\AboutPageServiceInterface;
use App\Enums\PublicationStatus;
use App\Filament\Pages\ManageAbout;
use App\Models\Cms\CmsDraft;
use App\Models\Content\Partnership;
use App\Models\User\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\TestCase;

final class AboutWorkflowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);
    }

    public function test_about_landing_workflow_draft_does_not_leak_until_published(): void
    {
        $about = app(AboutPageServiceInterface::class);
        $workflow = app(CmsWorkflowServiceInterface::class);
        $author = User::query()->where('role_slug', 'super_admin')->firstOrFail();
        $payload = $about->getEditablePayload('about.landing');
        $payload['translations']['en']['headline'] = 'About Published Workflow';
        $payload['translations']['ar']['headline'] = 'نبذة منشورة';

        $workflow->saveDraft('about.landing', $payload, (int) $author->id);

        $this->get('/en/about')
            ->assertOk()
            ->assertDontSee('About Published Workflow');

        $this->assertTrue($workflow->publish('about.landing', (int) $author->id));

        $this->get('/en/about')
            ->assertOk()
            ->assertSee('About Published Workflow');
    }

    public function test_about_landing_preview_renders_draft_snapshot(): void
    {
        $about = app(AboutPageServiceInterface::class);
        $workflow = app(CmsWorkflowServiceInterface::class);
        $author = User::query()->where('role_slug', 'super_admin')->firstOrFail();
        $payload = $about->getEditablePayload('about.landing');
        $payload['translations']['en']['headline'] = 'About Preview Workflow';
        $payload['translations']['ar']['headline'] = 'معاينة عن الجامعة';

        $workflow->saveDraft('about.landing', $payload, (int) $author->id);
        $preview = $workflow->preview('about.landing', 'en', (int) $author->id);

        $this->get($preview->previewUrl)
            ->assertOk()
            ->assertSee('About Preview Workflow')
            ->assertSee('Preview mode');

        $this->get('/en/about')
            ->assertOk()
            ->assertDontSee('About Preview Workflow');
    }

    public function test_vision_mission_renders_bilingual_structured_content_and_seo(): void
    {
        $this->get('/en/about/vision-mission')
            ->assertOk()
            ->assertSee('Vision and Mission')
            ->assertSee('To be a distinguished scientific center')
            ->assertSee('Strategic Pillars')
            ->assertSee('Accredited Education')
            ->assertSee('href="/en/about/vision-mission"', false)
            ->assertSee('rel="canonical" href="http://localhost/en/about/vision-mission"', false)
            ->assertSee('"@type":"BreadcrumbList"', false)
            ->assertSee('x-data="aboutNavigation()"', false)
            ->assertSee('aria-controls="about-navigation-track"', false)
            ->assertSee('src="/images/about/hero-img.jpg"', false);

        $this->get('/en/about/vision-mission/')->assertOk();
        $this->get('/en/about/vision-mission.html')
            ->assertStatus(301)
            ->assertRedirect('/en/about/vision-mission');

        $this->get('/ar/about/vision-mission')
            ->assertOk()
            ->assertSee('الرؤية والرسالة')
            ->assertSee('أن تكون الجامعة مركزاً علمياً متميزاً')
            ->assertSee('الأعمدة الاستراتيجية')
            ->assertSee('تعليم معتمد')
            ->assertSee('dir="rtl"', false);

        $this->assertFileExists(public_path('images/about/hero-img.jpg'));
        $this->assertFileExists(public_path('images/icon-search-outline.svg'));
        $this->assertFileExists(public_path('images/icon-award-outline.svg'));
        $this->assertFileExists(public_path('images/icon-handshake-outline.svg'));
    }

    public function test_vision_mission_draft_preview_and_publish_workflow(): void
    {
        $about = app(AboutPageServiceInterface::class);
        $workflow = app(CmsWorkflowServiceInterface::class);
        $author = User::query()->where('role_slug', 'super_admin')->firstOrFail();
        $payload = $about->getEditablePayload('about.vision-mission');
        $payload['translations']['en']['sections']['cards'][0]['title'] = 'Curated Vision Draft';
        $payload['translations']['ar']['sections']['cards'][0]['title'] = 'مسودة رؤية منسقة';

        $workflow->saveDraft('about.vision-mission', $payload, (int) $author->id);

        $this->get('/en/about/vision-mission')
            ->assertOk()
            ->assertDontSee('Curated Vision Draft');

        $preview = $workflow->preview('about.vision-mission', 'en', (int) $author->id);
        $this->get($preview->previewUrl)
            ->assertOk()
            ->assertSee('Curated Vision Draft')
            ->assertSee('Preview mode');

        $this->assertTrue($workflow->publish('about.vision-mission', (int) $author->id));
        $this->get('/en/about/vision-mission')
            ->assertOk()
            ->assertSee('Curated Vision Draft');
    }

    public function test_manage_about_uses_curated_vision_mission_editor(): void
    {
        $this->actingAs(User::query()->where('role_slug', 'super_admin')->firstOrFail(), 'web');

        $component = Livewire::test(ManageAbout::class)
            ->set('data.target_key', 'about.vision-mission')
            ->call('loadTarget', 'about.vision-mission')
            ->assertSee('Hero and SEO')
            ->assertSee('Vision, Mission and Values')
            ->assertSee('Strategic Pillars')
            ->assertDontSee('Subpage Schema Pending');

        /** @var array<string, mixed> $data */
        $data = $component->get('data');
        $cards = is_array($data['en_vision_mission']['cards'] ?? null) ? $data['en_vision_mission']['cards'] : [];
        $firstCardKey = array_key_first($cards);
        $this->assertNotNull($firstCardKey);
        $this->assertIsArray($cards[$firstCardKey]);
        $cards[$firstCardKey]['title'] = 'Curated Vision Editor';

        $component
            ->set('data.en_vision_mission.cards', $cards)
            ->call('save');

        $draft = CmsDraft::query()->where('target_key', 'about.vision-mission')->latest('id')->firstOrFail();

        $this->assertSame('Curated Vision Editor', $draft->payload_json['translations']['en']['sections']['cards'][0]['title'] ?? null);
    }

    public function test_vision_mission_publish_requires_complete_bilingual_structure(): void
    {
        $about = app(AboutPageServiceInterface::class);
        $workflow = app(CmsWorkflowServiceInterface::class);
        $author = User::query()->where('role_slug', 'super_admin')->firstOrFail();
        $payload = $about->getEditablePayload('about.vision-mission');
        $payload['translations']['en']['sections']['pillars'] = [];

        $workflow->saveDraft('about.vision-mission', $payload, (int) $author->id);

        $this->expectException(ValidationException::class);
        $workflow->publish('about.vision-mission', (int) $author->id);
    }

    public function test_manage_about_uses_curated_landing_editor_and_saves_payload(): void
    {
        $this->actingAs(User::query()->where('role_slug', 'super_admin')->firstOrFail(), 'web');

        $component = Livewire::test(ManageAbout::class)
            ->assertSet('data.target_key', 'about.landing')
            ->assertSee('Hero and Story')
            ->assertSee('Stats');

        /** @var array<string, mixed> $data */
        $data = $component->get('data');
        $stats = is_array($data['en_landing']['stats'] ?? null) ? $data['en_landing']['stats'] : [];
        $stats[] = [
            'value' => '99',
            'label' => 'Curated About Stat',
            'icon' => '/images/icon-award-outline.svg',
        ];

        $component
            ->set('data.en_landing.headline', 'Curated About Landing')
            ->set('data.en_landing.stats', $stats)
            ->call('save');

        $draft = CmsDraft::query()->where('target_key', 'about.landing')->latest('id')->firstOrFail();
        $statLabels = collect($draft->payload_json['translations']['en']['stats'] ?? [])->pluck('label')->all();

        $this->assertSame('Curated About Landing', $draft->payload_json['translations']['en']['headline'] ?? null);
        $this->assertContains('Curated About Stat', $statLabels);
    }

    public function test_manage_about_all_targets_have_curated_editors(): void
    {
        $this->actingAs(User::query()->where('role_slug', 'super_admin')->firstOrFail(), 'web');

        Livewire::test(ManageAbout::class)
            ->set('data.target_key', 'about.directorates_staff')
            ->call('loadTarget', 'about.directorates_staff')
            ->assertSee('Hero')
            ->assertDontSee('Subpage Schema Pending');
    }

    public function test_manage_about_partnerships_exposes_and_saves_goal_cards(): void
    {
        $this->actingAs(User::query()->where('role_slug', 'super_admin')->firstOrFail(), 'web');

        $component = Livewire::test(ManageAbout::class)
            ->set('data.target_key', 'about.partnerships')
            ->call('loadTarget', 'about.partnerships')
            ->assertSee('Partnership Goals');

        /** @var array<string, mixed> $data */
        $data = $component->get('data');
        $goals = is_array($data['en_partnerships']['sections'] ?? null) ? $data['en_partnerships']['sections'] : [];
        $goals[] = [
            'icon' => 'o',
            'title' => 'Managed Partnership Goal',
            'body' => 'This goal is editable from the About workspace.',
        ];

        $component
            ->set('data.en_partnerships.sections', $goals)
            ->call('save');

        $draft = CmsDraft::query()->where('target_key', 'about.partnerships')->latest('id')->firstOrFail();
        $titles = collect($draft->payload_json['translations']['en']['sections'] ?? [])->pluck('title')->all();

        $this->assertContains('Managed Partnership Goal', $titles);
    }

    public function test_about_landing_uses_verified_managed_content_and_complete_metadata(): void
    {
        $this->get('/en/about')
            ->assertOk()
            ->assertSee('Explore Vision &amp; Mission', false)
            ->assertSee('Licensed Programs')
            ->assertSee('/images/about/hero-img.jpg', false)
            ->assertSee('Establishing Decree')
            ->assertDontSee('Global Accreditation')
            ->assertDontSee('50</span>', false)
            ->assertSee('property="og:type" content="website"', false)
            ->assertSee('name="twitter:card" content="summary_large_image"', false)
            ->assertSee('"@type":"WebPage"', false)
            ->assertSee('"@type":"BreadcrumbList"', false);

        $this->get('/ar/about')
            ->assertOk()
            ->assertSee('استكشف الرؤية والرسالة')
            ->assertSee('برامج مرخصة')
            ->assertSee('dir="rtl"', false);
    }

    public function test_accreditation_and_why_spu_have_complete_bilingual_editors_and_content(): void
    {
        $this->get('/en/about/accreditation')
            ->assertOk()
            ->assertSee('National Accreditation')
            ->assertSee('Republican Decree No. 339')
            ->assertSee('Program Licensing');
        $this->get('/ar/about/why-spu')
            ->assertOk()
            ->assertSee('اختر مسارك')
            ->assertSee('الحرم الجامعي والمرافق')
            ->assertSee('المشاركة المجتمعية');

        $this->actingAs(User::query()->where('role_slug', 'super_admin')->firstOrFail(), 'web');
        foreach (['about.accreditation', 'about.why-spu'] as $targetKey) {
            Livewire::test(ManageAbout::class)
                ->set('data.target_key', $targetKey)
                ->call('loadTarget', $targetKey)
                ->assertSee('Introduction')
                ->assertSee('Facts')
                ->assertSee('Content Cards')
                ->assertDontSee('Subpage Schema Pending');
        }
    }

    public function test_partnership_directory_search_filter_pagination_and_proposal_are_functional(): void
    {
        $this->get('/en/about/partnerships')
            ->assertOk()
            ->assertSee('Association of Arab Universities')
            ->assertDontSee('Coursera')
            ->assertDontSee('World Health Organization')
            ->assertSee('name="q"', false)
            ->assertSee('/en/contact?topic=partnership#contact-form', false);

        $this->get('/en/about/partnerships?category=clinical')
            ->assertOk()
            ->assertSee('No matching partnerships')
            ->assertSee('/ar/about/partnerships?category=clinical', false);
        $this->get('/en/about/partnerships?q=Association')
            ->assertOk()
            ->assertSee('1 partnership found')
            ->assertSee('Association of Arab Universities');
        $this->get('/en/contact?topic=partnership#contact-form')
            ->assertOk()
            ->assertSee('value="Partnership proposal for SPU"', false);

        foreach (range(1, 7) as $index) {
            $partnership = Partnership::query()->create([
                'slug' => 'verified-partner-'.$index,
                'category_key' => 'research',
                'status_key' => 'active',
                'sort_order' => 20 + $index,
                'is_enabled' => true,
                'publication_status' => PublicationStatus::Published->value,
                'published_at' => now(),
            ]);
            $partnership->translations()->createMany([
                ['locale' => 'ar', 'name' => 'شريك موثق '.$index, 'category' => 'بحثي', 'status' => 'نشط', 'description' => 'وصف موثق'],
                ['locale' => 'en', 'name' => 'Verified Partner '.$index, 'category' => 'Research', 'status' => 'Active', 'description' => 'Verified description'],
            ]);
        }

        $this->get('/en/about/partnerships?category=research')
            ->assertOk()
            ->assertSee('7 partnerships found')
            ->assertSee('/en/about/partnerships?category=research&amp;page=2', false)
            ->assertDontSee('Verified Partner 7');
        $this->get('/en/about/partnerships?category=research&page=2')
            ->assertOk()
            ->assertSee('Verified Partner 7');
    }

    public function test_directorate_details_are_complete_localized_and_listed_in_sitemap(): void
    {
        foreach (['scientific-research', 'student-affairs', 'public-relations'] as $slug) {
            $response = $this->get('/en/about/directorates/'.$slug)
                ->assertOk()
                ->assertSee('Key Services')
                ->assertSee('Contact Us')
                ->assertSee('All Directorates')
                ->assertSee('id="about-navigation"', false)
                ->assertDontSee('<main class="lg:col-span-3">', false);
            $this->assertStringNotContainsString('Main Building', (string) $response->getContent());
        }

        $this->get('/en/about/directorates/it-services')
            ->assertRedirect('/en/e-services/it-support');

        $this->get('/ar/about/directorates/scientific-research')
            ->assertOk()
            ->assertSee('الخدمات الرئيسية')
            ->assertSee('تواصل معنا');

        $sitemap = $this->get('/sitemap.xml')->assertOk()->getContent();
        $this->assertStringContainsString('/en/about/directorates/scientific-research', (string) $sitemap);
        $this->assertStringContainsString('/ar/about/accreditation', (string) $sitemap);
        $this->assertStringContainsString('/en/about/profile/rector', (string) $sitemap);
    }

    public function test_imported_about_pages_render_redirects_and_have_curated_editors(): void
    {
        $this->get('/en/about/university-council')->assertRedirect('/en/about/leadership');
        $this->get('/en/about/partnership')->assertRedirect('/en/about/partnerships');

        foreach ([
            'about.quality-policy' => ['path' => '/en/about/quality-policy', 'state' => 'en_quality_policy', 'title' => 'Quality Policy at SPU'],
            'about.ethical-charter' => ['path' => '/en/about/ethical-charter', 'state' => 'en_ethical_charter', 'title' => 'Ethical Charter of SPU'],
            'about.organizational-structure' => ['path' => '/en/about/organizational-structure', 'state' => 'en_organizational_structure', 'title' => 'Organizational Structure of SPU'],
        ] as $targetKey => $case) {
            $this->get($case['path'])
                ->assertOk()
                ->assertSee($case['title']);
        }

        $this->actingAs(User::query()->where('role_slug', 'super_admin')->firstOrFail(), 'web');

        foreach ([
            'about.quality-policy' => 'en_quality_policy',
            'about.ethical-charter' => 'en_ethical_charter',
            'about.organizational-structure' => 'en_organizational_structure',
        ] as $targetKey => $stateKey) {
            $component = Livewire::test(ManageAbout::class)
                ->set('data.target_key', $targetKey)
                ->call('loadTarget', $targetKey)
                ->assertSee('Content Cards')
                ->assertDontSee('Subpage Schema Pending');

            /** @var array<string, mixed> $data */
            $data = $component->get('data');
            $sections = is_array($data[$stateKey]['sections'] ?? null) ? $data[$stateKey]['sections'] : [];
            $sections[] = ['title' => 'Curated Imported About Card', 'body' => 'Saved through the imported about editor.'];

            $component
                ->set('data.'.$stateKey.'.sections', $sections)
                ->call('save');

            $draft = CmsDraft::query()->where('target_key', $targetKey)->latest('id')->firstOrFail();
            $sectionTitles = collect($draft->payload_json['translations']['en']['sections'] ?? [])->pluck('title')->all();

            $this->assertContains('Curated Imported About Card', $sectionTitles);
        }
    }

    public function test_about_history_workflow_draft_does_not_leak_until_published(): void
    {
        $about = app(AboutPageServiceInterface::class);
        $workflow = app(CmsWorkflowServiceInterface::class);
        $author = User::query()->where('role_slug', 'super_admin')->firstOrFail();
        $payload = $about->getEditablePayload('about.history');
        $payload['translations']['en']['sections']['foundingTitle'] = 'History Published Workflow';
        $payload['translations']['ar']['sections']['foundingTitle'] = 'تاريخ منشور';

        $workflow->saveDraft('about.history', $payload, (int) $author->id);

        $this->get('/en/about/history')
            ->assertOk()
            ->assertDontSee('History Published Workflow');

        $this->assertTrue($workflow->publish('about.history', (int) $author->id));

        $this->get('/en/about/history')
            ->assertOk()
            ->assertSee('History Published Workflow');
    }

    public function test_about_history_preview_renders_draft_snapshot(): void
    {
        $about = app(AboutPageServiceInterface::class);
        $workflow = app(CmsWorkflowServiceInterface::class);
        $author = User::query()->where('role_slug', 'super_admin')->firstOrFail();
        $payload = $about->getEditablePayload('about.history');
        $payload['translations']['en']['sections']['foundingTitle'] = 'History Preview Workflow';
        $payload['translations']['ar']['sections']['foundingTitle'] = 'معاينة التاريخ';

        $workflow->saveDraft('about.history', $payload, (int) $author->id);
        $preview = $workflow->preview('about.history', 'en', (int) $author->id);

        $this->get($preview->previewUrl)
            ->assertOk()
            ->assertSee('History Preview Workflow')
            ->assertSee('Preview mode');

        $this->get('/en/about/history')
            ->assertOk()
            ->assertDontSee('History Preview Workflow');
    }

    public function test_manage_about_history_uses_curated_editor_and_saves_payload(): void
    {
        $this->actingAs(User::query()->where('role_slug', 'super_admin')->firstOrFail(), 'web');

        $component = Livewire::test(ManageAbout::class)
            ->set('data.target_key', 'about.history')
            ->call('loadTarget', 'about.history')
            ->assertSee('Founding Vision')
            ->assertSee('Institutional Timeline')
            ->assertSee('Narratives')
            ->assertSee('Legacy');

        /** @var array<string, mixed> $data */
        $data = $component->get('data');
        $timeline = is_array($data['en_history']['timeline'] ?? null) ? $data['en_history']['timeline'] : [];
        $timeline[] = [
            'year' => '2030',
            'title' => 'Curated History Milestone',
            'body' => 'A milestone added through the curated history editor.',
        ];

        $component
            ->set('data.en_history.foundingTitle', 'Curated Founding Vision')
            ->set('data.en_history.timeline', $timeline)
            ->call('save');

        $draft = CmsDraft::query()->where('target_key', 'about.history')->latest('id')->firstOrFail();
        $timelineTitles = collect($draft->payload_json['translations']['en']['sections']['timeline'] ?? [])->pluck('title')->all();

        $this->assertSame('Curated Founding Vision', $draft->payload_json['translations']['en']['sections']['foundingTitle'] ?? null);
        $this->assertContains('Curated History Milestone', $timelineTitles);
    }

    public function test_about_leadership_workflow_draft_does_not_leak_until_published(): void
    {
        $about = app(AboutPageServiceInterface::class);
        $workflow = app(CmsWorkflowServiceInterface::class);
        $author = User::query()->where('role_slug', 'super_admin')->firstOrFail();
        $payload = $about->getEditablePayload('about.leadership');
        $payload['translations']['en']['headline'] = 'Leadership Published Workflow';
        $payload['translations']['ar']['headline'] = 'قيادة منشورة';

        $workflow->saveDraft('about.leadership', $payload, (int) $author->id);

        $this->get('/en/about/leadership')
            ->assertOk()
            ->assertDontSee('Leadership Published Workflow');

        $this->assertTrue($workflow->publish('about.leadership', (int) $author->id));

        $this->get('/en/about/leadership')
            ->assertOk()
            ->assertSee('Leadership Published Workflow');
    }

    public function test_leadership_faculty_filter_and_dean_carousel_are_functional_and_localized(): void
    {
        $response = $this->get('/en/about/leadership?faculty=medicine')
            ->assertOk()
            ->assertSee('View by Faculty')
            ->assertSee('Faculty of Medicine')
            ->assertSee('Faculty of Dentistry')
            ->assertSee('data-initial-faculty="medicine"', false)
            ->assertSee('x-model="faculty"', false)
            ->assertSee('@change="changeFaculty()"', false)
            ->assertSee('@click="previousDean()"', false)
            ->assertSee('@click="nextDean()"', false)
            ->assertSee('@keydown.left.prevent="handleArrowLeft()"', false)
            ->assertSee('@touchend.passive="endTouch($event)"', false)
            ->assertSee('aria-roledescription="carousel"', false)
            ->assertSee('/ar/about/leadership?faculty=medicine', false)
            ->assertSee('Dr. Ayman Ali')
            ->assertSee('Dr. Ammar Ghada')
            ->assertSee('/en/about/profile/ayman-ali', false);

        $this->assertSame(7, substr_count((string) $response->getContent(), 'class="dean-card reveal'));

        $this->get('/ar/about/leadership?faculty=petroleum')
            ->assertOk()
            ->assertSee('عرض حسب الكلية')
            ->assertSee('كلية هندسة البترول')
            ->assertSee('د. محمود حديد')
            ->assertSee('data-initial-faculty="petroleum"', false);
    }

    public function test_leadership_rejects_unknown_faculty_filter_state(): void
    {
        $directory = app(AboutPageServiceInterface::class)->getLeadershipDirectory('en', 'not-a-faculty');

        $this->assertSame('', $directory->activeFaculty);
        $this->assertCount(7, $directory->facultyFilters);

        $this->get('/en/about/leadership?faculty=not-a-faculty')
            ->assertOk()
            ->assertSee('data-initial-faculty=""', false)
            ->assertDontSee('/ar/about/leadership?faculty=not-a-faculty', false);
    }

    public function test_about_leadership_preview_renders_draft_snapshot(): void
    {
        $about = app(AboutPageServiceInterface::class);
        $workflow = app(CmsWorkflowServiceInterface::class);
        $author = User::query()->where('role_slug', 'super_admin')->firstOrFail();
        $payload = $about->getEditablePayload('about.leadership');
        $payload['translations']['en']['headline'] = 'Leadership Preview Workflow';
        $payload['translations']['ar']['headline'] = 'معاينة القيادة';

        $workflow->saveDraft('about.leadership', $payload, (int) $author->id);
        $preview = $workflow->preview('about.leadership', 'en', (int) $author->id);

        $this->get($preview->previewUrl)
            ->assertOk()
            ->assertSee('Leadership Preview Workflow')
            ->assertSee('Preview mode');

        $this->get('/en/about/leadership')
            ->assertOk()
            ->assertDontSee('Leadership Preview Workflow');
    }

    public function test_manage_about_leadership_uses_curated_editor_and_saves_payload(): void
    {
        $this->actingAs(User::query()->where('role_slug', 'super_admin')->firstOrFail(), 'web');

        Livewire::test(ManageAbout::class)
            ->set('data.target_key', 'about.leadership')
            ->call('loadTarget', 'about.leadership')
            ->assertSee('Hero')
            ->assertSee('Leadership Presentation')
            ->set('data.en_leadership.headline', 'Curated Leadership Shell')
            ->call('save');

        $draft = CmsDraft::query()->where('target_key', 'about.leadership')->latest('id')->firstOrFail();

        $this->assertSame('Curated Leadership Shell', $draft->payload_json['translations']['en']['headline'] ?? null);
    }

    public function test_about_directorates_and_partnerships_workflow_drafts_do_not_leak_until_published(): void
    {
        $about = app(AboutPageServiceInterface::class);
        $workflow = app(CmsWorkflowServiceInterface::class);
        $author = User::query()->where('role_slug', 'super_admin')->firstOrFail();

        foreach ([
            'about.directorates' => '/en/about/directorates',
            'about.directorates_staff' => '/en/about/directorates/staff',
            'about.partnerships' => '/en/about/partnerships',
        ] as $targetKey => $path) {
            $payload = $about->getEditablePayload($targetKey);
            $payload['translations']['en']['headline'] = 'Published '.$targetKey;
            $payload['translations']['ar']['headline'] = 'منشور '.$targetKey;

            $workflow->saveDraft($targetKey, $payload, (int) $author->id);

            $this->get($path)
                ->assertOk()
                ->assertDontSee('Published '.$targetKey);

            $this->assertTrue($workflow->publish($targetKey, (int) $author->id));

            $this->get($path)
                ->assertOk()
                ->assertSee('Published '.$targetKey);
        }
    }

    public function test_staff_directory_filters_and_paginates_with_canonical_query_state(): void
    {
        $firstPage = $this->get('/en/about/directorates/staff')
            ->assertOk()
            ->assertSee('View by Faculty')
            ->assertSee('Apply Filter')
            ->assertSee('10 results')
            ->assertSee('Prof. Dr. Abdul Razzaq Al-Hussein')
            ->assertDontSee('Dr. Ammar Ghada')
            ->assertSee('/en/about/directorates/staff?page=2#staff-directory', false)
            ->assertSee('rel="next"', false);

        $this->assertSame(9, substr_count((string) $firstPage->getContent(), 'class="staff-card reveal'));

        $this->get('/en/about/directorates/staff?page=2')
            ->assertOk()
            ->assertSee('Dr. Ammar Ghada')
            ->assertDontSee('Prof. Dr. Abdul Razzaq Al-Hussein')
            ->assertSee('/en/about/directorates/staff#staff-directory', false)
            ->assertSee('rel="prev"', false);

        $this->get('/en/about/directorates/staff?faculty=petroleum&page=99')
            ->assertOk()
            ->assertSee('1 results')
            ->assertSee('Dr. Mahmoud Hadid')
            ->assertDontSee('href="http://localhost/en/about/profile/ayman-ali" class="staff-card', false)
            ->assertSee('/ar/about/directorates/staff?faculty=petroleum', false)
            ->assertDontSee('Staff pagination');
    }

    public function test_staff_directory_handles_arabic_and_invalid_query_values(): void
    {
        $this->get('/ar/about/directorates/staff?faculty=medicine')
            ->assertOk()
            ->assertSee('عرض حسب الكلية')
            ->assertSee('تطبيق التصفية')
            ->assertSee('عدد النتائج: 1')
            ->assertSee('د. أيمن علي')
            ->assertSee('/en/about/directorates/staff?faculty=medicine', false);

        $directory = app(AboutPageServiceInterface::class)->getStaffDirectory('en', 'invalid-faculty', 500);
        $this->assertSame('', $directory->activeFaculty);
        $this->assertSame(2, $directory->currentPage);
        $this->assertSame(2, $directory->totalPages);
        $this->assertCount(1, $directory->items);

        $this->get('/en/about/directorates/staff?faculty=invalid-faculty&page=-5')
            ->assertOk()
            ->assertSee('10 results')
            ->assertDontSee('/ar/about/directorates/staff?faculty=invalid-faculty', false);
    }

    public function test_about_directorates_and_partnerships_previews_render_draft_snapshots(): void
    {
        $about = app(AboutPageServiceInterface::class);
        $workflow = app(CmsWorkflowServiceInterface::class);
        $author = User::query()->where('role_slug', 'super_admin')->firstOrFail();

        foreach (['about.directorates', 'about.directorates_staff', 'about.partnerships'] as $targetKey) {
            $payload = $about->getEditablePayload($targetKey);
            $payload['translations']['en']['headline'] = 'Preview '.$targetKey;
            $payload['translations']['ar']['headline'] = 'معاينة '.$targetKey;

            $workflow->saveDraft($targetKey, $payload, (int) $author->id);
            $preview = $workflow->preview($targetKey, 'en', (int) $author->id);

            $this->get($preview->previewUrl)
                ->assertOk()
                ->assertSee('Preview '.$targetKey)
                ->assertSee('Preview mode');
        }
    }

    public function test_manage_about_directorates_and_partnerships_use_curated_shell_editors(): void
    {
        $this->actingAs(User::query()->where('role_slug', 'super_admin')->firstOrFail(), 'web');

        foreach (['about.directorates' => 'en_directorates', 'about.directorates_staff' => 'en_directorates_staff', 'about.partnerships' => 'en_partnerships'] as $targetKey => $stateKey) {
            Livewire::test(ManageAbout::class)
                ->set('data.target_key', $targetKey)
                ->call('loadTarget', $targetKey)
                ->assertSee('Hero')
                ->set('data.'.$stateKey.'.headline', 'Curated '.$targetKey)
                ->call('save');

            $draft = CmsDraft::query()->where('target_key', $targetKey)->latest('id')->firstOrFail();

            $this->assertSame('Curated '.$targetKey, $draft->payload_json['translations']['en']['headline'] ?? null);
        }
    }
}
