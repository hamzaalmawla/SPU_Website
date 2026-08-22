<?php

declare(strict_types=1);

namespace Tests\Feature\PX05;

use App\Models\Page\Page;
use App\Models\Page\PageTranslation;
use App\Models\Research\ResearchPublication;
use App\Models\Research\ResearchPublicationTranslation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Feature tests for sitemap.xml endpoint.
 *
 * Requirements: 16.1, 16.2
 */
class SitemapTest extends TestCase
{
    use RefreshDatabase;

    public function test_sitemap_returns_valid_xml_with_correct_content_type(): void
    {
        $this->seedPublishedPage('test-page');

        $response = $this->get('/sitemap.xml');

        $response->assertOk();
        $response->assertHeader('Content-Type', 'application/xml');

        $xml = simplexml_load_string($response->getContent());
        $this->assertNotFalse($xml, 'Response must be valid XML');
        $this->assertSame('urlset', $xml->getName());
    }

    public function test_sitemap_contains_only_published_enabled_pages(): void
    {
        $published = $this->seedPublishedPage('published-page');
        $this->seedDraftPage('draft-page');
        $this->seedDisabledPage('disabled-page');

        $response = $this->get('/sitemap.xml');

        $response->assertOk();
        $content = $response->getContent();

        $this->assertStringContainsString('published-page', $content);
        $this->assertStringNotContainsString('draft-page', $content);
        $this->assertStringNotContainsString('disabled-page', $content);
    }

    public function test_sitemap_includes_locale_alternates(): void
    {
        $page = $this->seedPublishedPage('bilingual-page', bothLocales: true);

        $response = $this->get('/sitemap.xml');

        $response->assertOk();
        $content = $response->getContent();

        $this->assertStringContainsString('hreflang="ar"', $content);
        $this->assertStringContainsString('hreflang="en"', $content);
        $this->assertStringContainsString('hreflang="x-default"', $content);
    }

    public function test_sitemap_uses_w3c_lastmod_values(): void
    {
        $page = $this->seedPublishedPage('w3c-page');
        $page->forceFill(['updated_at' => '2026-08-21 14:30:00'])->save();

        $content = $this->get('/sitemap.xml')->assertOk()->getContent();

        $this->assertMatchesRegularExpression(
            '/<lastmod>2026-08-21T14:30:00\+00:00<\/lastmod>/',
            $content,
        );
    }

    public function test_real_published_publications_do_not_require_cms_archive_payload(): void
    {
        $publication = ResearchPublication::create([
            'published_at' => now()->subDay(),
            'is_enabled' => true,
        ]);

        foreach (['ar' => 'منشور حقيقي', 'en' => 'Real Publication'] as $locale => $title) {
            ResearchPublicationTranslation::create([
                'research_publication_id' => $publication->id,
                'locale' => $locale,
                'title' => $title,
            ]);
        }

        $content = $this->get('/sitemap.xml')->assertOk()->getContent();

        $this->assertStringContainsString('/ar/research/publications/real-publication-'.$publication->id, $content);
        $this->assertStringContainsString('/en/research/publications/real-publication-'.$publication->id, $content);
    }

    public function test_sitemap_excludes_admin_and_preview_urls(): void
    {
        $this->seedPublishedPage('normal-page');

        $response = $this->get('/sitemap.xml');

        $content = $response->getContent();

        $this->assertStringNotContainsString('/admin', $content);
        $this->assertStringNotContainsString('/preview', $content);
    }

    private function seedPublishedPage(string $slug, bool $bothLocales = false): Page
    {
        $page = Page::create([
            'slug' => $slug,
            'status' => 'published',
            'is_enabled' => true,
            'published_at' => now(),
            'template' => 'default',
            'type' => 'landing',
        ]);

        PageTranslation::create([
            'page_id' => $page->id,
            'locale' => 'ar',
            'title' => 'صفحة '.$slug,
        ]);

        if ($bothLocales) {
            PageTranslation::create([
                'page_id' => $page->id,
                'locale' => 'en',
                'title' => 'Page '.$slug,
            ]);
        }

        return $page;
    }

    private function seedDraftPage(string $slug): Page
    {
        $page = Page::create([
            'slug' => $slug,
            'status' => 'draft',
            'is_enabled' => true,
            'template' => 'default',
            'type' => 'landing',
        ]);

        PageTranslation::create([
            'page_id' => $page->id,
            'locale' => 'ar',
            'title' => 'مسودة '.$slug,
        ]);

        return $page;
    }

    private function seedDisabledPage(string $slug): Page
    {
        $page = Page::create([
            'slug' => $slug,
            'status' => 'published',
            'is_enabled' => false,
            'published_at' => now(),
            'template' => 'default',
            'type' => 'landing',
        ]);

        PageTranslation::create([
            'page_id' => $page->id,
            'locale' => 'ar',
            'title' => 'معطلة '.$slug,
        ]);

        return $page;
    }
}
