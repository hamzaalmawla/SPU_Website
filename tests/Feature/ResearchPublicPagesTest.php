<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Contracts\Cms\CmsWorkflowServiceInterface;
use App\Contracts\Research\ResearchPageServiceInterface;
use App\Filament\Pages\ManageResearch;
use App\Models\Cms\CmsDraft;
use App\Models\Legacy\LegacyExactRedirect;
use App\Models\Media\MediaAsset;
use App\Models\Research\LegacyResearchFileReference;
use App\Models\Research\ResearchPublication;
use App\Models\Research\ResearchPublicationTranslation;
use App\Models\Shared\MigrationLog;
use App\Models\User\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

final class ResearchPublicPagesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);
        $this->publishResearchCatalogs();
    }

    public function test_english_research_landing_returns_ok_with_frontend_content(): void
    {
        $this->get('/en/research')
            ->assertOk()
            ->assertSee('Research at SPU')
            ->assertSee('Expert Finder')
            ->assertDontSee('Conferences &amp; Seminars', false)
            ->assertSee('FEATURED PUBLICATION')
            ->assertSee('Research Gateway')
            ->assertSee('/en/research/publications/ai-dental-diagnostics', false);
    }

    public function test_publications_and_projects_are_sorted_newest_first_before_pagination(): void
    {
        $this->createImportedResearchPublication();
        $research = app(ResearchPageServiceInterface::class);
        $publicationYears = collect($research->publications('en')->data['items'] ?? [])->pluck('year')->map(fn (mixed $year): int => (int) $year)->all();
        $projectYears = collect($research->projects('en')->data['items'] ?? [])->pluck('startYear')->map(fn (mixed $year): int => (int) $year)->all();

        $this->assertSame(collect($publicationYears)->sortDesc()->values()->all(), $publicationYears);
        $this->assertSame(collect($projectYears)->sortDesc()->values()->all(), $projectYears);
    }

    public function test_structured_database_publication_metadata_is_exposed_without_reparsing_or_fabrication(): void
    {
        $publication = ResearchPublication::query()->create([
            'published_at' => '2025-01-15',
            'publication_year' => 2025,
            'doi' => '10.1234/spu.2025.1',
            'journal_rank' => 'Q2',
            'is_enabled' => true,
        ]);
        ResearchPublicationTranslation::query()->create([
            'research_publication_id' => $publication->getKey(),
            'locale' => 'en',
            'title' => 'Structured Metadata Publication',
            'authors' => 'Researcher One and Researcher Two',
            'abstract' => 'Structured abstract.',
            'publisher' => 'SPU Research Journal',
            'citation' => 'SPU Research Journal 10(2), 2025',
            'keywords' => ['structured data', 'migration'],
        ]);

        $item = collect(app(ResearchPageServiceInterface::class)->publications('en', ['q' => 'Structured Metadata Publication'])->data['items'] ?? [])->first();

        $this->assertSame('Researcher One and Researcher Two', $item['author'] ?? null);
        $this->assertSame('SPU Research Journal', $item['publisher'] ?? null);
        $this->assertSame('10.1234/spu.2025.1', $item['doi'] ?? null);
        $this->assertSame('Q2', $item['rate'] ?? null);
        $this->assertSame('2025', $item['year'] ?? null);
        $this->assertSame(['structured data', 'migration'], $item['keywords'] ?? null);
    }

    public function test_homepage_research_selector_uses_public_database_publications_and_detail_links(): void
    {
        $publication = ResearchPublication::query()->create([
            'published_at' => '2026-01-15',
            'publication_year' => 2026,
            'is_enabled' => true,
        ]);
        ResearchPublicationTranslation::query()->create([
            'research_publication_id' => $publication->getKey(),
            'locale' => 'en',
            'title' => 'Homepage Database Research',
            'excerpt' => 'Research selected from the public database catalog.',
            'authors' => 'Researcher One; Researcher Two',
        ]);
        $research = app(ResearchPageServiceInterface::class);
        $cards = $research->getHomepagePublicationCards('en', [], 'Homepage Database Research');

        $this->assertCount(1, $cards);
        $this->assertSame('Homepage Database Research', $cards->first()?->title);
        $this->assertSame(['Researcher One', 'Researcher Two'], $cards->first()?->authors);
        $this->assertStringStartsWith('/en/research/publications/', (string) $cards->first()?->url);
        $this->get((string) $cards->first()?->url)->assertOk()->assertSee('Homepage Database Research');
    }

    public function test_arabic_research_landing_returns_ok_with_arabic_content(): void
    {
        $this->get('/ar/research')
            ->assertOk()
            ->assertSee('البحث في الجامعة السورية الخاصة')
            ->assertSee('بوابة البحث')
            ->assertSee('منشور مميز');
    }

    public function test_discovered_research_listing_routes_return_ok(): void
    {
        // Data-backed listings always render: "no matching records" is an ordinary
        // empty-results state, not a retirement.
        foreach ([
            '/en/research/repository',
            '/en/research/publications',
            '/en/research/researchers',
            '/en/research/expert-finder',
        ] as $uri) {
            $this->get($uri)->assertOk();
        }

        // Published CMS sections render normally. setUp() publishes centers,
        // projects and themes.
        foreach ([
            '/en/research/centers',
            '/en/research/projects',
            '/en/research/themes',
        ] as $uri) {
            $this->get($uri)->assertOk();
        }

        // The rest are CMS-only with nothing published, so they are retired and
        // 404. They must never render a page telling visitors content is awaiting
        // review.
        foreach ([
            '/en/research/conferences',
            '/en/research/library',
            '/en/research/office',
            '/en/research/policies',
        ] as $uri) {
            $this->get($uri)->assertNotFound();
        }
    }

    public function test_publication_filters_pagination_empty_state_and_locale_links_are_functional(): void
    {
        $this->get('/en/research/publications?faculty=artificial-intelligence')
            ->assertOk()
            ->assertSee('Natural Language Processing for Arabic Medical Record Summarization')
            ->assertDontSee('Machine Learning Applications in Pharmaceutical Quality Control')
            ->assertSee('/ar/research/publications?faculty=artificial-intelligence', false);

        $this->get('/en/research/publications?q=dental')
            ->assertOk()
            ->assertSee('AI-Driven Predictive Models for Early Dental Caries Detection')
            ->assertDontSee('Deep Learning Framework for Reservoir Permeability Prediction');

        $this->get('/en/research/publications?page=2')
            ->assertOk()
            ->assertSee('Business Analytics for Healthcare Supply Chain Resilience')
            ->assertDontSee('Machine Learning Applications in Pharmaceutical Quality Control')
            ->assertSee('aria-current="page"', false)
            ->assertSee('/ar/research/publications?page=2', false)
            ->assertSee('<link rel="canonical" href="'.config('app.url').'/en/research/publications?page=2">', false);

        $this->get('/en/research/publications?q=no-such-publication')
            ->assertOk()
            ->assertSee('No results found')
            ->assertSee('Clear filters');
    }

    public function test_repository_owns_its_filter_pagination_and_locale_state(): void
    {
        $this->get('/en/research/repository?faculty=artificial-intelligence')
            ->assertOk()
            ->assertSee('Natural Language Processing for Arabic Medical Record Summarization')
            ->assertDontSee('Machine Learning Applications in Pharmaceutical Quality Control')
            ->assertSee('action="/en/research/repository"', false)
            ->assertSee('/ar/research/repository?faculty=artificial-intelligence', false);

        $this->get('/en/research/repository?page=2')
            ->assertOk()
            ->assertSee('/en/research/repository?page=2', false)
            ->assertSee('<link rel="canonical" href="'.config('app.url').'/en/research/repository?page=2">', false);
    }

    public function test_project_filters_search_and_empty_state_are_functional(): void
    {
        $this->get('/en/research/projects?status=ongoing')
            ->assertOk()
            ->assertSee('AI Dental Caries Detection System')
            ->assertSee('Arabic Clinical NLP System')
            ->assertDontSee('Earthquake-Resistant Concrete for Syrian Reconstruction');

        $response = $this->get('/en/research/projects?faculty=artificial-intelligence')
            ->assertOk()
            ->assertSee('Arabic Clinical NLP System');
        $this->assertStringNotContainsString('AI Dental Caries Detection System', $this->mainContent($response->getContent()));

        $this->get('/en/research/projects?theme=pharmaceutical-sciences&q=quality')
            ->assertOk()
            ->assertSee('Pharmaceutical Quality Monitoring')
            ->assertSee('/ar/research/projects?q=quality&amp;theme=pharmaceutical-sciences', false);

        $this->get('/en/research/projects?q=no-such-project')
            ->assertOk()
            ->assertSee('No results found')
            ->assertSee('Clear filters');
    }

    public function test_researcher_and_expert_finder_controls_are_functional(): void
    {
        $response = $this->get('/en/research/researchers?q=Ayman')
            ->assertOk()
            ->assertSee('Dr. Ayman Ali');
        $this->assertStringNotContainsString('Dr. Mouhib Alnoukari', $this->mainContent($response->getContent()));

        $response = $this->get('/en/research/researchers?faculty=medicine')
            ->assertOk()
            ->assertSee('Dr. Ayman Ali');
        $this->assertStringNotContainsString('Dr. Mahmoud Hadid', $this->mainContent($response->getContent()));

        $this->get('/en/research/researchers?expertise=clinical-medicine')
            ->assertOk()
            ->assertSee('Dr. Ayman Ali')
            ->assertSee('/ar/research/researchers?expertise=clinical-medicine', false);

        $response = $this->get('/en/research/expert-finder?faculty=petroleum')
            ->assertOk()
            ->assertSee('Dr. Mahmoud Hadid');
        $this->assertStringNotContainsString('Dr. Ayman Ali', $this->mainContent($response->getContent()));

        $this->get('/en/research/expert-finder?q=no-such-expert')
            ->assertOk()
            ->assertSee('No results found')
            ->assertSee('Clear filters');
    }

    public function test_discovered_research_detail_routes_return_ok(): void
    {
        foreach ([
            '/en/research/publications/machine-learning-pharmaceutical-quality-control',
            '/en/research/publications/ai-dental-diagnostics',
            '/en/research/publications/structural-analysis-earthquake-resistant-concrete',
            '/en/research/publications/deep-learning-reservoir-permeability',
            '/en/research/publications/clinical-simulation-training-medical-students',
            '/en/research/publications/arabic-medical-record-nlp',
            '/en/research/publications/business-analytics-healthcare-supply-chain',
            '/en/research/publications/renewable-energy-integration-syrian-grid',
            '/en/research/projects/earthquake-resistant-concrete-syria',
            '/en/research/projects/ai-dental-diagnostics-system',
            '/en/research/projects/arabic-clinical-nlp-system',
            '/en/research/projects/pharmaceutical-quality-monitoring',
            '/en/research/projects/reservoir-characterization-ai',
            '/en/research/centers/ai-digital-innovation',
            '/en/research/centers/clinical-research-simulation',
            '/en/research/centers/energy-sustainable-systems',
            '/en/research/themes/ai-ml',
            '/en/research/themes/pharmaceutical-sciences',
            '/en/research/themes/clinical-medicine',
            '/en/research/themes/dental-sciences',
            '/en/research/themes/petroleum-engineering',
            '/en/research/themes/construction-engineering',
            '/en/research/themes/business-administration',
            '/en/research/themes/medical-education',
            '/en/research/themes/biomedical-engineering',
            '/en/research/themes/energy-systems',
            '/en/research/themes/data-science',
            '/en/research/themes/structural-engineering',
            '/en/research/researchers/ayman-ali',
            '/en/research/researchers/mouhib-alnoukari',
        ] as $uri) {
            $this->get($uri)->assertOk();
        }
    }

    public function test_researcher_cards_link_to_researcher_profile_pages(): void
    {
        $this->get('/en/research/researchers')
            ->assertOk()
            ->assertSee('/en/research/researchers/ayman-ali', false)
            ->assertSee('Dr. Ayman Ali');

        $this->get('/en/research/researchers/ayman-ali')
            ->assertOk()
            ->assertSee('Professional Biography')
            ->assertSee('Dr. Ayman Ali')
            ->assertSee('Dean of Medicine')
            ->assertSee('Leads academic programs and clinical training in the Faculty of Medicine.')
            ->assertDontSee('View all on Google Scholar');
    }

    public function test_publication_detail_renders_imported_data_and_navigation(): void
    {
        $this->get('/en/research/publications/ai-dental-diagnostics')
            ->assertOk()
            ->assertSee('AI-Driven Predictive Models for Early Dental Caries Detection')
            ->assertSee('A convolutional neural network model trained on 12,000 dental radiographs')
            ->assertSee('Related Publications')
            ->assertSee('Previous')
            ->assertSee('Next')
            ->assertSee('/en/research/themes/dental-sciences', false)
            ->assertSee('property="og:type" content="article"', false)
            ->assertSee('name="citation_title"', false)
            ->assertSee('"@type":"ScholarlyArticle"', false)
            ->assertDontSee('10.1234/');
    }

    public function test_missing_publication_detail_slug_returns_404(): void
    {
        $this->get('/en/research/publications/not-a-real-publication')->assertNotFound();
    }

    public function test_research_card_links_use_localized_laravel_routes(): void
    {
        $response = $this->get('/en/research/publications')
            ->assertOk()
            ->assertSee('/en/research/publications/ai-dental-diagnostics', false);

        $response->assertDontSee('/research/detail/?id=', false);
    }

    public function test_publication_listing_filters_imported_database_publications(): void
    {
        $this->createImportedResearchPublication();

        $this->get('/en/research/publications?q=Kaddar&type=published-research&year=2020')
            ->assertOk()
            ->assertSee('Legacy Filter Target')
            ->assertSee('Kaddar Abir')
            ->assertSee('Legacy Journal, 2020')
            ->assertSee('value="Kaddar"', false)
            ->assertDontSee('AI-Driven Predictive Models for Early Dental Caries Detection');
    }

    public function test_imported_publication_detail_shows_parsed_legacy_metadata(): void
    {
        $this->createImportedResearchPublication();

        $this->get('/en/research/publications/legacy-filter-target-9001')
            ->assertOk()
            ->assertSee('Legacy Filter Target')
            ->assertSee('Kaddar Abir')
            ->assertSee('Legacy Journal, 2020')
            ->assertSee('Legacy imported abstract body.')
            ->assertDontSee('Authors')
            ->assertDontSee('Published in');
    }

    public function test_imported_publication_detail_exposes_verified_media_download(): void
    {
        $publication = $this->createImportedResearchPublication();
        $media = MediaAsset::query()->create([
            'disk' => 'public',
            'directory' => 'research',
            'filename' => 'legacy-filter-target.pdf',
            'original_name' => 'legacy-filter-target.pdf',
            'mime_type' => 'application/pdf',
            'extension' => 'pdf',
            'size_bytes' => 1024,
            'path' => 'research/legacy-filter-target.pdf',
        ]);
        $publication->update(['file_media_id' => $media->getKey()]);

        $this->get('/en/research/publications/legacy-filter-target-9001')
            ->assertOk()
            ->assertSee('Publication Files')
            ->assertSee('Publication file')
            ->assertSee('/storage/research/legacy-filter-target.pdf', false)
            ->assertSee('PDF');
    }

    public function test_imported_publication_detail_exposes_legacy_paper_download(): void
    {
        $publication = $this->createImportedResearchPublication();
        LegacyResearchFileReference::query()->create([
            'research_publication_id' => $publication->getKey(),
            'legacy_source_table' => 'jx_member_items',
            'legacy_source_id' => 7001,
            'legacy_path' => 'research/papers/legacy-filter-target.pdf',
            'label_en' => 'Legacy paper',
            'label_ar' => 'البحث القديم',
            'sort_order' => 0,
            'status' => 'deferred',
        ]);

        $this->get('/en/research/publications/legacy-filter-target-9001')
            ->assertOk()
            ->assertSee('Publication Files')
            ->assertSee('Legacy paper')
            ->assertSee('href="/research/papers/legacy-filter-target.pdf"', false)
            ->assertSee('PDF');
    }

    public function test_future_and_undated_database_publications_are_not_public(): void
    {
        foreach ([null, now()->addYear()] as $index => $publishedAt) {
            $publication = ResearchPublication::query()->create([
                'published_at' => $publishedAt,
                'is_enabled' => true,
            ]);
            ResearchPublicationTranslation::query()->create([
                'research_publication_id' => $publication->getKey(),
                'locale' => 'en',
                'title' => 'Hidden Database Publication '.($index + 1),
            ]);
        }

        $this->get('/en/research/publications')
            ->assertOk()
            ->assertDontSee('Hidden Database Publication 1')
            ->assertDontSee('Hidden Database Publication 2');
    }

    public function test_legacy_query_detail_redirects_to_canonical_publication_route(): void
    {
        LegacyExactRedirect::query()->create([
            'legacy_path' => '/en/research/detail',
            'query_signature' => 'id=pub-002',
            'destination_url' => '/en/research/publications/ai-dental-diagnostics',
            'status_code' => 301,
            'locale' => 'en',
            'is_active' => true,
        ]);

        $this->get('/en/research/detail?id=pub-002')
            ->assertRedirect('/en/research/publications/ai-dental-diagnostics');
    }

    public function test_research_publications_use_published_cms_payload_for_listing_and_detail(): void
    {
        $user = User::factory()->create(['role_slug' => 'super_admin']);
        $payload = [
            'translations' => [
                'ar' => $this->cmsPublicationContent('عنوان منشورات عربي', 'منشور بحثي من CMS', 'ملخص منشور عربي', 'مقدمة تفصيلية عربية'),
                'en' => $this->cmsPublicationContent('CMS Publications Title', 'CMS Controlled Publication', 'CMS publication summary.', 'CMS detail lead copy.'),
            ],
        ];

        $workflow = app(CmsWorkflowServiceInterface::class);
        $workflow->saveDraft('research.publications', $payload, (int) $user->getKey());
        $workflow->publish('research.publications', (int) $user->getKey());

        $this->get('/en/research/publications')
            ->assertOk()
            ->assertSee('CMS Publications Title')
            ->assertSee('CMS Controlled Publication')
            ->assertDontSee('Machine Learning Applications in Pharmaceutical Quality Control');

        $this->get('/en/research/publications/cms-controlled-publication')
            ->assertOk()
            ->assertSee('CMS Controlled Publication')
            ->assertSee('CMS detail lead copy.')
            ->assertSee('CMS Keyword')
            ->assertDontSee('10.1234/cms.research.1')
            ->assertDontSee('name="citation_doi"', false);
    }

    public function test_research_experts_use_published_cms_payload_for_finder_but_not_cms_only_profiles(): void
    {
        $user = User::factory()->create(['role_slug' => 'super_admin']);
        $payload = [
            'translations' => [
                'ar' => $this->cmsExpertContent('باحثو CMS', 'د. خبيرة CMS', 'أستاذة بحث من CMS', 'سيرة مهنية عربية'),
                'en' => $this->cmsExpertContent('CMS Expert Finder', 'Dr. CMS Expert', 'CMS Research Professor', 'CMS professional biography.'),
            ],
        ];

        $workflow = app(CmsWorkflowServiceInterface::class);
        $workflow->saveDraft('research.experts', $payload, (int) $user->getKey());
        $workflow->publish('research.experts', (int) $user->getKey());

        $response = $this->get('/en/research/expert-finder')
            ->assertOk()
            ->assertSee('CMS Expert Finder')
            ->assertSee('Dr. CMS Expert');
        $mainContent = explode('</main>', explode('<main', $response->getContent(), 2)[1] ?? '', 2)[0] ?? '';
        $this->assertStringNotContainsString('Dr. Ayman Ali', $mainContent);

        $this->get('/en/research/researchers/cms-expert')->assertNotFound();
    }

    public function test_research_centers_support_draft_preview_publish_detail_and_unpublish(): void
    {
        $user = User::factory()->create(['role_slug' => 'editor']);
        $payload = app(ResearchPageServiceInterface::class)->getEditablePayload('research.centers');
        $payload['translations']['en']['hero']['title'] = 'CMS Research Centers';
        $payload['translations']['ar']['hero']['title'] = 'مراكز بحث CMS';
        $payload['translations']['en']['items'][0]['name'] = 'CMS Center for Applied AI';
        $payload['translations']['en']['items'][0]['mission'] = 'CMS controlled center mission.';
        $payload['translations']['ar']['items'][0]['name'] = 'مركز CMS للذكاء التطبيقي';
        $payload['translations']['ar']['items'][0]['mission'] = 'رسالة مركز محكومة من CMS.';

        $workflow = app(CmsWorkflowServiceInterface::class);
        $workflow->saveDraft('research.centers', $payload, (int) $user->getKey());

        $this->get('/en/research/centers')
            ->assertOk()
            ->assertDontSee('CMS Research Centers')
            ->assertDontSee('CMS Center for Applied AI');

        $preview = $workflow->preview('research.centers', 'en', (int) $user->getKey());
        $this->get($preview->previewUrl)
            ->assertOk()
            ->assertSee('CMS Research Centers')
            ->assertSee('CMS Center for Applied AI')
            ->assertSee('noindex,nofollow,noarchive');
        $this->get($preview->previewUrl.'&center=ai-digital-innovation')
            ->assertOk()
            ->assertSee('CMS Center for Applied AI')
            ->assertSee('CMS controlled center mission.');

        $this->assertTrue($workflow->publish('research.centers', (int) $user->getKey()));

        $this->get('/en/research/centers')
            ->assertOk()
            ->assertSee('CMS Research Centers')
            ->assertSee('CMS Center for Applied AI');
        $this->get('/ar/research/centers/ai-digital-innovation')
            ->assertOk()
            ->assertSee('مركز CMS للذكاء التطبيقي')
            ->assertSee('رسالة مركز محكومة من CMS.');

        // Unpublishing retires the section outright. It is CMS-only with no
        // database equivalent and is dropped from navigation, so it 404s rather
        // than rendering a page that tells visitors content is pending review.
        $this->assertTrue($workflow->unpublish('research.centers', (int) $user->getKey()));
        $this->get('/en/research/centers')->assertNotFound();
        $this->get('/ar/research/centers')->assertNotFound();
    }

    public function test_research_admin_loads_and_serializes_center_catalog_without_publication_fallback(): void
    {
        $this->actingAs(User::query()->where('role_slug', 'super_admin')->firstOrFail());

        Livewire::test(ManageResearch::class)
            ->call('loadTarget', 'research.centers')
            ->assertSet('data.target_key', 'research.centers')
            ->assertSet('data.en_centers.hero.title', 'Research Centers & Labs')
            ->assertSet('data.en_centers.items', fn (mixed $items): bool => is_array($items)
                && collect($items)->contains(fn (mixed $item): bool => is_array($item) && ($item['facultySlug'] ?? null) === 'artificial-intelligence'))
            ->call('save')
            ->assertHasNoErrors();

        $draft = CmsDraft::query()->where('target_key', 'research.centers')->latest('id')->firstOrFail();
        $this->assertSame('Center for AI & Digital Innovation', $draft->payload_json['translations']['en']['items'][0]['name'] ?? null);
        $this->assertArrayNotHasKey('filters', $draft->payload_json['translations']['en'] ?? []);
    }

    public function test_research_centers_and_details_are_in_the_sitemap(): void
    {
        $user = User::factory()->create(['role_slug' => 'editor']);
        $workflow = app(CmsWorkflowServiceInterface::class);
        $workflow->saveDraft(
            'research.centers',
            app(ResearchPageServiceInterface::class)->getEditablePayload('research.centers'),
            (int) $user->getKey(),
        );
        $workflow->publish('research.centers', (int) $user->getKey());

        $this->get('/sitemaps/sitemap-research.xml')
            ->assertOk()
            ->assertSee('/en/research/centers</loc>', false)
            ->assertSee('/ar/research/centers</loc>', false)
            ->assertSee('/en/research/centers/ai-digital-innovation', false)
            ->assertSee('/ar/research/centers/clinical-research-simulation', false)
            ->assertSee('/en/research/centers/energy-sustainable-systems', false);
    }

    public function test_project_and_theme_catalogs_support_isolated_preview_publish_relations_and_unpublish(): void
    {
        $user = User::factory()->create(['role_slug' => 'editor']);
        $research = app(ResearchPageServiceInterface::class);
        $workflow = app(CmsWorkflowServiceInterface::class);
        $projects = $research->getEditablePayload('research.projects');
        $themes = $research->getEditablePayload('research.themes');
        $projects['translations']['en']['hero']['title'] = 'CMS Research Projects';
        $projects['translations']['ar']['hero']['title'] = 'مشاريع بحث CMS';
        $projects['translations']['en']['cardLabels']['viewProject'] = 'Inspect Research Project';
        $projects['translations']['en']['cardLabels']['since'] = 'Commenced';
        $projects['translations']['ar']['cardLabels']['viewProject'] = 'استعراض المشروع البحثي';
        $projects['translations']['ar']['cardLabels']['since'] = 'بدأ عام';
        $projects['translations']['en']['items'][0]['title'] = 'CMS Seismic Project';
        $projects['translations']['ar']['items'][0]['title'] = 'مشروع CMS الزلزالي';
        $projects['translations']['en']['items'][0]['endYear'] = '2030';
        $projects['translations']['ar']['items'][0]['endYear'] = '2030';
        $projects['translations']['en']['items'][0]['themeSlug'] = 'ai-ml';
        $projects['translations']['ar']['items'][0]['themeSlug'] = 'ai-ml';
        $projects['translations']['en']['items'][0]['theme'] = 'Artificial Intelligence & Machine Learning';
        $projects['translations']['ar']['items'][0]['theme'] = 'الذكاء الاصطناعي وتعلم الآلة';
        $themes['translations']['en']['hero']['title'] = 'CMS Research Themes';
        $themes['translations']['ar']['hero']['title'] = 'مجالات بحث CMS';
        $themes['translations']['en']['items'][0]['name'] = 'CMS AI Theme';
        $themes['translations']['ar']['items'][0]['name'] = 'مجال CMS للذكاء الاصطناعي';

        $workflow->saveDraft('research.projects', $projects, (int) $user->getKey());
        $workflow->saveDraft('research.themes', $themes, (int) $user->getKey());

        $this->get('/en/research/projects')->assertOk()->assertDontSee('CMS Research Projects')->assertDontSee('CMS Seismic Project');
        $this->get('/en/research/themes')->assertOk()->assertDontSee('CMS Research Themes')->assertDontSee('CMS AI Theme');

        $projectPreview = $workflow->preview('research.projects', 'en', (int) $user->getKey());
        $this->get($projectPreview->previewUrl)
            ->assertOk()
            ->assertSee('CMS Research Projects')
            ->assertSee('CMS Seismic Project')
            ->assertSee('&amp;project=earthquake-resistant-concrete-syria', false);
        $this->get($projectPreview->previewUrl.'&project=earthquake-resistant-concrete-syria')
            ->assertOk()
            ->assertSee('CMS Seismic Project');

        $themePreview = $workflow->preview('research.themes', 'en', (int) $user->getKey());
        $this->get($themePreview->previewUrl)
            ->assertOk()
            ->assertSee('CMS Research Themes')
            ->assertSee('CMS AI Theme')
            ->assertSee('&amp;theme=ai-ml', false);
        $this->get($themePreview->previewUrl.'&theme=ai-ml')
            ->assertOk()
            ->assertSee('CMS AI Theme');

        $this->assertTrue($workflow->publish('research.projects', (int) $user->getKey()));
        $this->assertTrue($workflow->publish('research.themes', (int) $user->getKey()));

        $this->get('/en/research/projects?theme=ai-ml&q=seismic')
            ->assertOk()
            ->assertSee('CMS Seismic Project')
            ->assertSee('Inspect Research Project')
            ->assertSee('Commenced')
            ->assertSee('2030')
            ->assertDontSee('AI-Powered Dental Caries Detection System');
        $this->get('/ar/research/projects/earthquake-resistant-concrete-syria')->assertOk()->assertSee('مشروع CMS الزلزالي');
        $this->get('/en/research/themes/ai-ml')->assertOk()->assertSee('CMS AI Theme')->assertSee('CMS Seismic Project');
        $this->get('/ar/research/themes/ai-ml')->assertOk()->assertSee('مجال CMS للذكاء الاصطناعي');

        $this->assertTrue($workflow->unpublish('research.projects', (int) $user->getKey()));
        $this->assertTrue($workflow->unpublish('research.themes', (int) $user->getKey()));
        $this->assertSame([], $research->projects('en')->data['items'] ?? []);
        $this->assertSame([], $research->themes('en')->data['items'] ?? []);
        $this->get('/en/research/projects/earthquake-resistant-concrete-syria')->assertNotFound();
        $this->get('/en/research/themes/ai-ml')->assertNotFound();
    }

    public function test_research_admin_loads_and_serializes_project_and_theme_catalogs(): void
    {
        $this->actingAs(User::query()->where('role_slug', 'super_admin')->firstOrFail());
        $component = Livewire::test(ManageResearch::class)
            ->call('loadTarget', 'research.projects')
            ->assertSet('data.target_key', 'research.projects')
            ->assertSet('data.en_projects.items', fn (mixed $items): bool => is_array($items) && count($items) === 5)
            ->call('save')
            ->assertHasNoErrors();

        $projectDraft = CmsDraft::query()->where('target_key', 'research.projects')->latest('id')->firstOrFail();
        $this->assertSame('building-construction-engineering', $projectDraft->payload_json['translations']['en']['items'][0]['facultySlug'] ?? null);
        $this->assertSame('artificial-intelligence', $projectDraft->payload_json['translations']['en']['items'][2]['facultySlug'] ?? null);

        $component->call('loadTarget', 'research.themes')
            ->assertSet('data.target_key', 'research.themes')
            ->assertSet('data.en_themes.items', fn (mixed $items): bool => is_array($items) && count($items) === 12)
            ->call('save')
            ->assertHasNoErrors();

        $themeDraft = CmsDraft::query()->where('target_key', 'research.themes')->latest('id')->firstOrFail();
        $this->assertSame('theme-001', $themeDraft->payload_json['translations']['en']['items'][0]['id'] ?? null);
        $this->assertArrayNotHasKey('filters', $themeDraft->payload_json['translations']['en'] ?? []);
    }

    public function test_project_and_theme_unknown_slugs_return_not_found(): void
    {
        $this->get('/en/research/projects/not-a-real-project')->assertNotFound();
        $this->get('/en/research/themes/not-a-real-theme')->assertNotFound();
    }

    public function test_project_and_theme_sitemap_entries_are_published_only(): void
    {
        $user = User::factory()->create(['role_slug' => 'editor']);
        $workflow = app(CmsWorkflowServiceInterface::class);
        $research = app(ResearchPageServiceInterface::class);

        $workflow->unpublish('research.projects', (int) $user->getKey());
        $workflow->unpublish('research.themes', (int) $user->getKey());

        foreach (['research.projects', 'research.themes'] as $targetKey) {
            $workflow->saveDraft($targetKey, $research->getEditablePayload($targetKey), (int) $user->getKey());
        }

        $this->get('/sitemaps/sitemap-research.xml')
            ->assertOk()
            ->assertDontSee('/en/research/projects/earthquake-resistant-concrete-syria', false)
            ->assertDontSee('/en/research/themes/ai-ml', false);

        $workflow->publish('research.projects', (int) $user->getKey());
        $workflow->publish('research.themes', (int) $user->getKey());
        $this->get('/sitemaps/sitemap-research.xml')
            ->assertOk()
            ->assertSee('/en/research/projects</loc>', false)
            ->assertSee('/ar/research/projects/earthquake-resistant-concrete-syria', false)
            ->assertSee('/en/research/themes</loc>', false)
            ->assertSee('/ar/research/themes/structural-engineering', false);

        $workflow->unpublish('research.projects', (int) $user->getKey());
        $workflow->unpublish('research.themes', (int) $user->getKey());
        $this->get('/sitemaps/sitemap-research.xml')
            ->assertOk()
            ->assertDontSee('/en/research/projects/earthquake-resistant-concrete-syria', false)
            ->assertDontSee('/en/research/themes/ai-ml', false);
    }

    public function test_research_landing_uses_published_cms_payload(): void
    {
        $user = User::factory()->create(['role_slug' => 'super_admin']);
        $payload = [
            'translations' => [
                'ar' => $this->cmsLandingContent('عنوان بحث CMS', 'بوابة CMS'),
                'en' => $this->cmsLandingContent('CMS Research Landing', 'CMS Research Gateway'),
            ],
        ];

        $workflow = app(CmsWorkflowServiceInterface::class);
        $workflow->saveDraft('research.index', $payload, (int) $user->getKey());
        $workflow->publish('research.index', (int) $user->getKey());

        $this->get('/en/research')
            ->assertOk()
            ->assertSee('CMS Research Landing')
            ->assertSee('CMS Research Gateway')
            ->assertSee('CMS FEATURED PUBLICATION')
            ->assertDontSee('10.1234/cms.research.1')
            ->assertDontSee('Research at SPU');
    }

    public function test_research_conferences_use_published_cms_payload(): void
    {
        $user = User::factory()->create(['role_slug' => 'super_admin']);
        $payload = [
            'translations' => [
                'ar' => $this->cmsConferenceContent('مؤتمرات CMS', 'فعالية CMS قادمة', 'مؤتمر CMS سابق'),
                'en' => $this->cmsConferenceContent('CMS Conferences', 'CMS Upcoming Research Event', 'CMS Past Conference'),
            ],
        ];

        $workflow = app(CmsWorkflowServiceInterface::class);
        $workflow->saveDraft('research.conferences', $payload, (int) $user->getKey());
        $workflow->publish('research.conferences', (int) $user->getKey());

        $this->get('/en/research/conferences')
            ->assertOk()
            ->assertSee('CMS Conferences')
            ->assertSee('CMS Upcoming Research Event')
            ->assertSee('CMS Past Conference')
            ->assertSee('CMS Proceedings')
            ->assertSee('/en/research/conferences/register?event=cms-conf-001', false)
            ->assertDontSee('href="#"', false)
            ->assertDontSee('International Conference on AI in Healthcare 2026');
    }

    public function test_research_library_uses_published_cms_payload(): void
    {
        $user = User::factory()->create(['role_slug' => 'super_admin']);
        $payload = [
            'translations' => [
                'ar' => $this->cmsLibraryContent('مكتبة CMS', 'قاعدة CMS', 'قاعدة استعارة CMS'),
                'en' => $this->cmsLibraryContent('CMS Research Library', 'CMS Database', 'CMS Borrowing Rule'),
            ],
        ];

        $workflow = app(CmsWorkflowServiceInterface::class);
        $workflow->saveDraft('research.library', $payload, (int) $user->getKey());
        $workflow->publish('research.library', (int) $user->getKey());

        $this->get('/en/research/library')
            ->assertOk()
            ->assertSee('CMS Research Library')
            ->assertSee('CMS Database')
            ->assertSee('CMS Borrowing Rule')
            ->assertSee('CMS Special Collection')
            ->assertSee('Ms. CMS Librarian')
            ->assertDontSee('PubMed');
    }

    public function test_research_office_uses_published_cms_payload(): void
    {
        $user = User::factory()->create(['role_slug' => 'super_admin']);
        $payload = [
            'translations' => [
                'ar' => $this->cmsOfficeContent('مكتب بحث CMS', 'د. قائد CMS', 'خدمة CMS'),
                'en' => $this->cmsOfficeContent('CMS Research Office', 'Dr. CMS Leader', 'CMS Grant Service'),
            ],
        ];

        $workflow = app(CmsWorkflowServiceInterface::class);
        $workflow->saveDraft('research.office', $payload, (int) $user->getKey());
        $workflow->publish('research.office', (int) $user->getKey());

        $this->get('/en/research/office')
            ->assertOk()
            ->assertSee('CMS Research Office')
            ->assertSee('Dr. CMS Leader')
            ->assertSee('CMS Grant Service')
            ->assertSee('CMS Publications')
            ->assertSee('cms.research.office@spu.edu.sy')
            ->assertDontSee('Dr. Arwa Khair');
    }

    public function test_research_policies_use_published_cms_payload(): void
    {
        $user = User::factory()->create(['role_slug' => 'super_admin']);
        $payload = [
            'translations' => [
                'ar' => $this->cmsPolicyContent('سياسات CMS', 'قسم سياسة CMS', 'وثيقة CMS'),
                'en' => $this->cmsPolicyContent('CMS Policies & Ethics', 'CMS Policy Section', 'CMS Policy Document'),
            ],
        ];

        $workflow = app(CmsWorkflowServiceInterface::class);
        $workflow->saveDraft('research.policies', $payload, (int) $user->getKey());
        $workflow->publish('research.policies', (int) $user->getKey());

        $this->get('/en/research/policies')
            ->assertOk()
            ->assertSee('CMS Policies &amp; Ethics', false)
            ->assertSee('CMS Policy Section')
            ->assertSee('CMS Policy Document')
            ->assertSee('cms.policies@spu.edu.sy')
            ->assertDontSee('href="#"', false)
            ->assertDontSee('Ethics Review Policy');
    }

    /** @return array<string, mixed> */
    private function cmsPublicationContent(string $pageTitle, string $publicationTitle, string $summary, string $lead): array
    {
        return [
            'hero' => [
                'eyebrow' => 'CMS Research',
                'title' => $pageTitle,
                'summary' => 'CMS managed research publication page.',
                'backgroundImage' => '/images/uni-main-place.JPG',
            ],
            'filters' => [
                'facultyLabel' => 'Faculty',
                'typeLabel' => 'Publication Type',
                'yearLabel' => 'Year',
                'searchPlaceholder' => 'Search CMS publications',
                'faculties' => [['value' => '', 'label' => 'Faculty'], ['value' => 'medicine', 'label' => 'Medicine']],
                'publicationTypes' => [['value' => '', 'label' => 'Publication Type'], ['value' => 'research-article', 'label' => 'Research Article']],
                'years' => [['value' => '', 'label' => 'Year'], ['value' => '2026', 'label' => '2026']],
            ],
            'items' => [[
                'id' => 'cms-pub-001',
                'slug' => 'cms-controlled-publication',
                'links' => ['local' => '/research/publications/cms-controlled-publication/'],
                'title' => $publicationTitle,
                'summary' => $summary,
                'type' => 'Research Article',
                'typeSlug' => 'research-article',
                'faculty' => 'Faculty of Medicine',
                'facultySlug' => 'medicine',
                'author' => 'Dr. CMS Author',
                'authorSlug' => 'cms-author',
                'year' => '2026',
                'doi' => '10.1234/cms.research.1',
                'isOpenAccess' => true,
                'gsIndexed' => true,
                'image' => '/images/uni-main-place.JPG',
                'lead' => $lead,
                'paragraphs' => ['CMS detail paragraph.'],
                'keyStatement' => 'CMS key statement.',
                'keywords' => ['CMS Keyword'],
                'themes' => ['clinical-medicine'],
                'resolvedThemes' => [['slug' => 'clinical-medicine', 'label' => 'Clinical Medicine']],
                'category' => 'Research Article',
                'rate' => 'Q1',
                'scholarUrl' => 'https://scholar.google.com',
                'scopusUrl' => 'https://www.scopus.com',
            ]],
        ];
    }

    private function createImportedResearchPublication(): ResearchPublication
    {
        $publication = ResearchPublication::query()->create([
            'faculty_member_id' => null,
            'category_key' => null,
            'published_at' => '2020-01-02',
            'external_url' => null,
            'file_media_id' => null,
            'sort_order' => 0,
            'is_enabled' => true,
        ]);

        ResearchPublicationTranslation::query()->create([
            'research_publication_id' => $publication->getKey(),
            'locale' => 'en',
            'title' => 'Legacy Filter Target',
            'excerpt' => null,
            'abstract' => '<p><strong>Authors</strong></p><p>Kaddar Abir and Prof. Salwa Alcheikh</p><p><strong>Published in</strong></p><p>Legacy Journal, 2020</p><p><strong>Abstract</strong></p><p>Legacy imported abstract body.</p><p><strong>Keywords</strong></p><p>legacy, migration</p>',
            'publisher' => null,
        ]);

        MigrationLog::query()->create([
            'module' => 'research',
            'batch_name' => 'test-imported-research-publications',
            'source_table' => 'jx_member_categories',
            'source_id' => 9001,
            'target_table' => 'research_publications',
            'target_id' => $publication->getKey(),
            'status' => 'success',
            'message' => 'Test imported research publication.',
            'metadata' => ['phase' => 'phase6'],
        ]);

        return $publication;
    }

    /** @return array<string, mixed> */
    private function cmsExpertContent(string $pageTitle, string $expertName, string $role, string $biography): array
    {
        return [
            'hero' => [
                'eyebrow' => 'CMS Experts',
                'title' => $pageTitle,
                'summary' => 'CMS managed expert finder.',
                'backgroundImage' => '/images/uni-main-place.JPG',
            ],
            'searchPlaceholder' => 'Search CMS experts',
            'filters' => [
                'allFaculties' => 'All Faculties',
                'allExpertise' => 'All Research Areas',
            ],
            'faculties' => [['id' => 'medicine', 'name' => 'Faculty of Medicine']],
            'expertiseAreas' => [['id' => 'cms-research', 'name' => 'CMS Research']],
            'resultsLabel' => 'results found',
            'viewProfileLabel' => 'View Profile',
            'publicationsLabel' => 'Publications',
            'citationsLabel' => 'Citations',
            'researchers' => [[
                'id' => 'cms-expert',
                'slug' => 'cms-expert',
                'name' => $expertName,
                'title' => $role,
                'role' => $role,
                'faculty' => ['id' => 'medicine', 'name' => 'Faculty of Medicine', 'slug' => 'medicine'],
                'facultyId' => 'medicine',
                'facultySlug' => 'medicine',
                'department' => 'CMS Department',
                'expertise' => ['CMS Research', 'CMS Methods'],
                'bio' => $biography,
                'description' => $biography,
                'biography' => [$biography],
                'education' => [['degree' => 'CMS Doctoral Qualification', 'institution' => 'SPU', 'year' => '2026']],
                'courses' => [['id' => 'cms-101', 'code' => 'CMS101', 'name' => 'CMS Research Methods', 'departmentId' => 'medicine-plan']],
                'office' => ['fullAddress' => 'CMS Expert Office'],
                'email' => 'cms.expert@spu.edu.sy',
                'orcidUrl' => 'https://orcid.org/0000-0000-0000-0099',
                'scholarUrl' => 'https://scholar.google.com',
                'image' => '/images/uni-main-place.JPG',
                'publications' => 1,
                'citations' => 12,
                'profilePublications' => [[
                    'id' => 'cms-profile-pub',
                    'title' => 'CMS Profile Publication',
                    'journal' => 'CMS Journal',
                    'year' => 2026,
                    'links' => ['local' => '/research/publications/cms-controlled-publication/', 'scholar' => 'https://scholar.google.com'],
                ]],
            ]],
            'items' => [],
        ];
    }

    /** @return array<string, mixed> */
    private function cmsLandingContent(string $title, string $gatewayTitle): array
    {
        return [
            'hero' => [
                'eyebrow' => 'CMS Research Eyebrow',
                'title' => $title,
                'summary' => 'CMS managed research landing summary.',
                'cta1' => 'CMS Publications',
                'cta1Url' => '/en/research/publications',
                'cta2' => 'CMS Centers',
                'cta2Url' => '/en/research/centers',
                'backgroundImage' => '/images/uni-main-place.JPG',
            ],
            'stats' => [
                ['value' => '99', 'label' => 'CMS PUBLICATIONS'],
                ['value' => '7', 'label' => 'CMS CENTERS'],
            ],
            'featuredPublication' => [
                'sectionTitle' => 'CMS FEATURED PUBLICATION',
                'eyebrow' => 'CMS FEATURED',
                'title' => 'CMS Landing Featured Research',
                'slug' => 'cms-controlled-publication',
                'links' => ['local' => '/research/publications/cms-controlled-publication/'],
                'summary' => 'Featured CMS research summary.',
                'authorLabel' => 'AUTHOR',
                'authorName' => 'Dr. CMS Author',
                'affiliationLabel' => 'AFFILIATION',
                'affiliation' => 'Faculty of Medicine',
                'publishedLabel' => 'PUBLISHED',
                'date' => '2026',
                'viewCta' => 'View Publication',
                'downloadCta' => 'Download PDF',
                'doiLabel' => 'DOI',
                'doi' => '10.1234/cms.research.1',
                'image' => '/images/uni-main-place.JPG',
            ],
            'gateway' => [
                'sectionTitle' => $gatewayTitle,
                'cards' => [[
                    'number' => '01',
                    'title' => 'CMS Gateway Card',
                    'summary' => 'CMS gateway card summary.',
                    'cta' => 'Explore',
                    'url' => '/en/research/publications',
                ]],
            ],
        ];
    }

    /** @return array<string, mixed> */
    private function cmsConferenceContent(string $title, string $upcomingTitle, string $pastTitle): array
    {
        return [
            'hero' => [
                'eyebrow' => 'CMS Scientific Events',
                'title' => $title,
                'summary' => 'CMS managed conferences page.',
                'backgroundImage' => '/images/uni-main-place.JPG',
            ],
            'upcomingSection' => [
                'title' => 'CMS Upcoming Events',
                'viewAll' => 'View All CMS Events',
            ],
            'pastSection' => [
                'title' => 'CMS Past Conferences',
                'proceedings' => 'CMS Proceedings',
            ],
            'upcoming' => [[
                'id' => 'cms-conf-001',
                'title' => $upcomingTitle,
                'date' => 'June 2026',
                'location' => 'SPU Campus',
                'description' => 'CMS upcoming event summary.',
                'image' => '/images/uni-main-place.JPG',
                'registrationUrl' => '/research/conferences/register?event=cms-conf-001',
                'formId' => 'conference-registration',
                'eventType' => 'CMS Symposium',
            ]],
            'past' => [[
                'id' => 'cms-conf-past-001',
                'title' => $pastTitle,
                'date' => 'May 2026',
                'location' => 'Damascus, Syria',
                'description' => 'CMS past conference summary.',
                'image' => '/images/uni-main-place.JPG',
                'hasProceedings' => true,
                'proceedingsUrl' => '/storage/research/cms-proceedings.pdf',
                'participants' => '120 Participants',
            ]],
        ];
    }

    /** @return array<string, mixed> */
    private function cmsLibraryContent(string $title, string $databaseName, string $ruleTitle): array
    {
        return [
            'hero' => [
                'eyebrow' => 'CMS Academic Resources',
                'title' => $title,
                'summary' => 'CMS managed research library.',
                'backgroundImage' => '/images/uni-main-place.JPG',
            ],
            'resourcesSection' => [
                'title' => 'CMS Digital Resources',
                'subtitle' => 'CMS resource subtitle.',
            ],
            'databases' => [[
                'name' => $databaseName,
                'description' => 'CMS database description.',
                'url' => 'https://example.com/cms-database',
                'accessType' => 'CMS Access',
            ]],
            'borrowingSection' => [
                'title' => 'CMS Borrowing Rules',
                'rules' => [[
                    'title' => $ruleTitle,
                    'description' => 'CMS borrowing rule description.',
                ]],
            ],
            'specialCollections' => [
                'title' => 'CMS Special Collections',
                'items' => [[
                    'title' => 'CMS Special Collection',
                    'description' => 'CMS special collection description.',
                ]],
            ],
            'librarianSection' => [
                'title' => 'CMS Ask a Librarian',
                'name' => 'Ms. CMS Librarian',
                'hours' => 'Sun-Thu: 9:00 AM - 5:00 PM',
                'email' => 'cms.library@spu.edu.sy',
                'phone' => '+963 11 0000 000',
            ],
        ];
    }

    /** @return array<string, mixed> */
    private function cmsOfficeContent(string $title, string $leaderName, string $serviceTitle): array
    {
        return [
            'hero' => [
                'eyebrow' => 'CMS Research Administration',
                'title' => $title,
                'summary' => 'CMS managed research office.',
                'backgroundImage' => '/images/uni-main-place.JPG',
            ],
            'leadership' => [
                'title' => 'CMS Research Leadership',
                'items' => [[
                    'name' => $leaderName,
                    'role' => 'CMS Research Director',
                    'email' => 'cms.leader@spu.edu.sy',
                    'image' => '/images/uni-main-place.JPG',
                ]],
            ],
            'services' => [
                'title' => 'CMS Office Services',
                'subtitle' => 'CMS office service subtitle.',
                'items' => [[
                    'title' => $serviceTitle,
                    'description' => 'CMS service description.',
                ]],
            ],
            'statistics' => [
                'title' => 'CMS Research at a Glance',
                'items' => [[
                    'value' => '88',
                    'label' => 'CMS Publications',
                ]],
            ],
            'contact' => [
                'title' => 'CMS Contact Research Office',
                'address' => 'CMS SPU Campus',
                'addressDetail' => 'CMS research office address.',
                'email' => 'cms.research.office@spu.edu.sy',
                'phone' => '+963 11 1111 111',
                'hours' => 'Sun-Thu: 9:00 AM - 3:00 PM',
            ],
        ];
    }

    /** @return array<string, mixed> */
    private function cmsPolicyContent(string $title, string $sectionTitle, string $documentTitle): array
    {
        return [
            'hero' => [
                'eyebrow' => 'CMS Research Guidelines',
                'title' => $title,
                'summary' => 'CMS managed policies page.',
                'backgroundImage' => '/images/uni-main-place.JPG',
            ],
            'sections' => [[
                'id' => 'cms-policy-section',
                'title' => $sectionTitle,
                'description' => 'CMS policy section description.',
                'documents' => [[
                    'title' => $documentTitle,
                    'fileType' => 'PDF',
                    'url' => '/storage/research/cms-policy.pdf',
                ]],
            ]],
            'contactSection' => [
                'title' => 'CMS Policy Inquiries',
                'description' => 'CMS policy contact description.',
                'email' => 'cms.policies@spu.edu.sy',
                'phone' => '+963 11 2222 222',
                'location' => 'CMS Policy Office',
            ],
        ];
    }

    private function mainContent(string $html): string
    {
        return explode('</main>', explode('<main', $html, 2)[1] ?? '', 2)[0] ?? '';
    }

    private function publishResearchCatalogs(): void
    {
        $research = app(ResearchPageServiceInterface::class);
        $workflow = app(CmsWorkflowServiceInterface::class);
        $author = User::query()->where('role_slug', 'super_admin')->firstOrFail();

        foreach (['research.index', 'research.publications', 'research.centers', 'research.projects', 'research.themes', 'research.experts'] as $targetKey) {
            $workflow->saveDraft($targetKey, $research->getEditablePayload($targetKey), (int) $author->getKey());
            $this->assertTrue($workflow->publish($targetKey, (int) $author->getKey()));
        }
    }
}
