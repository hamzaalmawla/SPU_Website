<?php

declare(strict_types=1);

namespace Tests\Feature;

use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Contracts\Cms\CmsWorkflowServiceInterface;
use App\Models\Research\ResearchPublication;
use App\Models\Research\ResearchPublicationTranslation;
use App\Models\Shared\MigrationLog;
use App\Models\User\User;
use Tests\TestCase;

final class ResearchPublicPagesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);
    }

    public function test_english_research_landing_returns_ok_with_frontend_content(): void
    {
        $this->get('/en/research')
            ->assertOk()
            ->assertSee('Research at SPU')
            ->assertSee('Expert Finder')
            ->assertSee('Conferences &amp; Seminars', false)
            ->assertSee('FEATURED PUBLICATION')
            ->assertSee('Research Gateway')
            ->assertSee('/en/research/publications/ai-dental-diagnostics', false);
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
        foreach ([
            '/en/research/repository',
            '/en/research/publications',
            '/en/research/centers',
            '/en/research/projects',
            '/en/research/themes',
            '/en/research/researchers',
            '/en/research/expert-finder',
            '/en/research/conferences',
            '/en/research/library',
            '/en/research/office',
            '/en/research/policies',
        ] as $uri) {
            $this->get($uri)->assertOk();
        }
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
            ->assertSee('View all on Google Scholar')
            ->assertSee('Faculty of Medicine Dean Office')
            ->assertSee('Education')
            ->assertSee('Courses Taught')
            ->assertSee('Clinical Medicine');
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
            ->assertSee('/en/research/themes/dental-sciences', false);
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

    public function test_legacy_query_detail_redirects_to_canonical_publication_route(): void
    {
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
            ->assertSee('10.1234/cms.research.1');
    }

    public function test_research_experts_use_published_cms_payload_for_finder_and_profile(): void
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

        $this->get('/en/research/expert-finder')
            ->assertOk()
            ->assertSee('CMS Expert Finder')
            ->assertSee('Dr. CMS Expert')
            ->assertDontSee('Dr. Ayman Ali');

        $this->get('/en/research/researchers/cms-expert')
            ->assertOk()
            ->assertSee('Dr. CMS Expert')
            ->assertSee('CMS Research Professor')
            ->assertSee('CMS professional biography.')
            ->assertSee('CMS Expert Office')
            ->assertSee('CMS Research Methods')
            ->assertSee('CMS Profile Publication');
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

    private function createImportedResearchPublication(): void
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
                'registrationUrl' => '#',
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
                'proceedingsUrl' => '#',
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
                    'url' => '#',
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
}
