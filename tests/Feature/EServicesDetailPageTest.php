<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Contracts\Cms\CmsWorkflowServiceInterface;
use App\Contracts\Page\EServicesPageServiceInterface;
use App\Contracts\Seo\SitemapServiceInterface;
use App\DTOs\EServices\EServicesDetailPageDTO;
use App\Filament\Pages\ManageEServicesPage;
use App\Models\Settings\Setting;
use App\Models\User\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

final class EServicesDetailPageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);
    }

    public function test_all_detail_pages_render_in_arabic_and_english_with_localized_metadata(): void
    {
        foreach (['ar', 'en'] as $locale) {
            foreach (['library', 'staff-email', 'it-support'] as $slug) {
                $this->get("/{$locale}/e-services/{$slug}")
                    ->assertOk()
                    ->assertHeader('Content-Security-Policy')
                    ->assertSee('/images/slider-3.webp', false)
                    ->assertSee("/ar/e-services/{$slug}", false)
                    ->assertSee("/en/e-services/{$slug}", false)
                    ->assertSee('WebPage')
                    ->assertSee('BreadcrumbList');
            }
        }

        $this->get('/en/e-services/library')
            ->assertSee('Verified open resources')
            ->assertSee('https://www.doabooks.org', false)
            ->assertSee('https://doaj.org', false)
            ->assertSee('https://archive.org', false)
            ->assertSee('https://www.loc.gov/collections/world-digital-library/about-this-collection/', false)
            ->assertDontSee('ScienceDirect')
            ->assertDontSee('Scopus')
            ->assertDontSee('licensed databases');

        $this->get('/en/e-services/staff-email')
            ->assertSee('/en/e-services/it-support', false)
            ->assertDontSee('staff.spu.edu.sy')
            ->assertDontSee('mail.spu.edu.sy')
            ->assertDontSee('Exchange');

        $this->get('/en/e-services/it-support')
            ->assertSee('/en/contact?topic=it-support#contact-form', false)
            ->assertDontSee('support ticket')
            ->assertDontSee('VPN')
            ->assertDontSee('multi-factor');

        $this->get('/en/e-services')
            ->assertOk()
            ->assertSee('/en/e-services/library', false)
            ->assertSee('/en/e-services/staff-email', false)
            ->assertSee('/en/e-services/it-support', false)
            ->assertDontSee('students.spu.edu.sy')
            ->assertDontSee('my.spu.edu.sy')
            ->assertDontSee('according to the requirements');
    }

    public function test_it_support_contact_topic_prefills_the_subject(): void
    {
        $this->get('/en/contact?topic=it-support#contact-form')
            ->assertOk()
            ->assertSee('value="IT support request"', false);

        $this->get('/ar/contact?topic=it-support#contact-form')
            ->assertOk()
            ->assertSee('value="طلب مساعدة تقنية"', false);
    }

    public function test_detail_html_aliases_land_on_functional_pages(): void
    {
        foreach (['library', 'staff-email', 'it-support'] as $slug) {
            $this->followingRedirects()
                ->get("/en/e-services/{$slug}.html")
                ->assertOk();
        }
    }

    public function test_detail_target_workflow_keeps_draft_private_then_previews_and_publishes(): void
    {
        $pages = app(EServicesPageServiceInterface::class);
        $workflow = app(CmsWorkflowServiceInterface::class);
        $author = User::query()->where('role_slug', 'super_admin')->firstOrFail();
        $payload = [
            'translations' => [
                'ar' => $this->detailPayload($pages->getDetailPage('ar', 'staff-email'), 'معاينة بريد الموظفين'),
                'en' => $this->detailPayload($pages->getDetailPage('en', 'staff-email'), 'Managed Staff Email Guidance'),
            ],
        ];

        $workflow->saveDraft('e_services.staff-email', $payload, (int) $author->id);

        $this->get('/en/e-services/staff-email')->assertOk()->assertDontSee('Managed Staff Email Guidance');

        $preview = $workflow->preview('e_services.staff-email', 'en', (int) $author->id);
        $this->get($preview->previewUrl)
            ->assertOk()
            ->assertSee('Managed Staff Email Guidance')
            ->assertSee('Preview mode');

        $this->assertTrue($workflow->publish('e_services.staff-email', (int) $author->id));
        $this->get('/en/e-services/staff-email')->assertOk()->assertSee('Managed Staff Email Guidance');

        $this->assertTrue($workflow->unpublish('e_services.staff-email', (int) $author->id));
        $this->get('/en/e-services/staff-email')->assertOk()->assertDontSee('Managed Staff Email Guidance');
    }

    public function test_detail_readiness_rejects_mismatched_structure_and_unsafe_library_urls(): void
    {
        $pages = app(EServicesPageServiceInterface::class);
        $workflow = app(CmsWorkflowServiceInterface::class);
        $arabic = $this->detailPayload($pages->getDetailPage('ar', 'library'));
        $english = $this->detailPayload($pages->getDetailPage('en', 'library'));
        $english['sections'][0]['id'] = 'different-id';
        $english['resources']['links'][0]['url'] = 'http://www.doabooks.org';
        $english['cta']['url'] = 'javascript:alert(1)';
        $english['relatedLinks'][0]['url'] = 'https://example.com';

        $readiness = $workflow->readiness('e_services.library', [
            'translations' => ['ar' => $arabic, 'en' => $english],
        ]);

        $this->assertFalse($readiness->isReady);
        $this->assertArrayHasKey('en', $readiness->errors);
        $this->assertArrayHasKey('translations', $readiness->errors);
        $this->assertStringContainsString('safe public HTTPS URL', implode(' ', $readiness->errors['en']));
        $this->assertStringContainsString('localized internal', implode(' ', $readiness->errors['en']));

        $preview = $pages->buildDetailPreviewPage('en', 'library', $english);
        $this->assertSame('', $preview->ctaUrl);
        $this->assertCount(2, $preview->relatedLinks);
    }

    public function test_detail_target_can_be_scheduled_and_the_schedule_can_be_unpublished(): void
    {
        $pages = app(EServicesPageServiceInterface::class);
        $workflow = app(CmsWorkflowServiceInterface::class);
        $author = User::query()->where('role_slug', 'super_admin')->firstOrFail();
        $workflow->saveDraft('e_services.it-support', [
            'translations' => [
                'ar' => $this->detailPayload($pages->getDetailPage('ar', 'it-support')),
                'en' => $this->detailPayload($pages->getDetailPage('en', 'it-support')),
            ],
        ], (int) $author->id);

        $this->assertTrue($workflow->schedule('e_services.it-support', now()->addHour(), (int) $author->id));
        $this->assertDatabaseHas('cms_drafts', ['target_key' => 'e_services.it-support', 'status' => 'scheduled']);
        $this->assertTrue($workflow->unpublish('e_services.it-support', (int) $author->id));
        $this->assertDatabaseHas('cms_drafts', ['target_key' => 'e_services.it-support', 'status' => 'draft']);
    }

    public function test_admin_editor_selects_each_independent_detail_target(): void
    {
        $this->actingAs(User::query()->where('role_slug', 'super_admin')->firstOrFail());

        $component = Livewire::test(ManageEServicesPage::class)
            ->assertSet('data.target_key', 'e_services');

        foreach (['library' => 'E-Library', 'staff-email' => 'Staff Email', 'it-support' => 'IT Support'] as $slug => $title) {
            $component
                ->call('loadTarget', 'e_services.'.$slug)
                ->assertSet('data.target_key', 'e_services.'.$slug)
                ->assertSet('data.en_detail.hero_title', $title);
        }
    }

    public function test_sitemap_contains_the_landing_and_all_detail_routes(): void
    {
        $locations = app(SitemapServiceInterface::class)->generateEntries()->pluck('loc')->all();

        foreach (['/e-services', '/e-services/library', '/e-services/staff-email', '/e-services/it-support'] as $path) {
            $this->assertContains(config('app.url').'/ar'.$path, $locations);
            $this->assertContains(config('app.url').'/en'.$path, $locations);
        }
    }

    public function test_sitemap_uses_published_cms_details_without_seeded_settings(): void
    {
        $pages = app(EServicesPageServiceInterface::class);
        $workflow = app(CmsWorkflowServiceInterface::class);
        $author = User::query()->where('role_slug', 'super_admin')->firstOrFail();
        $workflow->saveDraft('e_services.library', [
            'translations' => [
                'ar' => $this->detailPayload($pages->getDetailPage('ar', 'library')),
                'en' => $this->detailPayload($pages->getDetailPage('en', 'library')),
            ],
        ], (int) $author->id);
        $this->assertTrue($workflow->publish('e_services.library', (int) $author->id));
        Setting::query()->where('group_key', 'like', 'e_services%')->delete();

        $locations = app(SitemapServiceInterface::class)->generateEntries()->pluck('loc')->all();
        $this->assertContains(config('app.url').'/ar/e-services/library', $locations);
        $this->assertContains(config('app.url').'/en/e-services/library', $locations);
        $this->assertNotContains(config('app.url').'/en/e-services/staff-email', $locations);
    }

    /** @return array<string, mixed> */
    private function detailPayload(EServicesDetailPageDTO $page, ?string $heroTitle = null): array
    {
        return [
            'hero' => [
                'eyebrow' => $page->heroEyebrow,
                'title' => $heroTitle ?? $page->heroTitle,
                'summary' => $page->heroSummary,
                'image' => $page->heroImage,
            ],
            'intro' => ['title' => $page->introTitle, 'body' => $page->introBody],
            'sections' => $page->sections,
            'resources' => ['title' => $page->resourceLinksTitle, 'links' => $page->resourceLinks],
            'cta' => ['title' => $page->ctaTitle, 'body' => $page->ctaBody, 'label' => $page->ctaLabel, 'url' => $page->ctaUrl],
            'relatedLinks' => $page->relatedLinks,
            'seo' => ['title' => $page->seoTitle, 'description' => $page->seoDescription, 'image' => $page->seoImage],
        ];
    }
}
