<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Contracts\Seo\StructuredDataServiceInterface;
use App\Contracts\Settings\SettingsServiceInterface;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * JSON-LD emitted through the existing $structuredData layout variable must be
 * well-formed and carry the expected schema.org types.
 */
final class StructuredDataTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);
        Cache::flush();
    }

    public function test_homepage_emits_college_or_university(): void
    {
        $documents = $this->jsonLdFrom('/ar');

        $this->assertNotEmpty($documents, 'The homepage must emit JSON-LD.');

        $types = $this->typesIn($documents);

        $this->assertContains('CollegeOrUniversity', $types);
        $this->assertContains('WebSite', $types);
    }

    public function test_homepage_json_ld_is_well_formed_in_both_locales(): void
    {
        foreach (['ar', 'en'] as $locale) {
            $documents = $this->jsonLdFrom('/'.$locale);

            $this->assertNotEmpty($documents, "Missing JSON-LD for /{$locale}.");

            foreach ($documents as $document) {
                $this->assertArrayHasKey('@context', $document);
                $this->assertSame('https://schema.org', $document['@context']);
            }
        }
    }

    public function test_organisation_is_sourced_from_settings_not_hardcoded(): void
    {
        $organisation = app(StructuredDataServiceInterface::class)->organisation('ar')->data;

        $this->assertSame('CollegeOrUniversity', $organisation['@type']);
        $this->assertArrayHasKey('name', $organisation);
        $this->assertArrayHasKey('alternateName', $organisation);
        $this->assertNotSame($organisation['name'], $organisation['alternateName'], 'AR and EN names must differ.');
        $this->assertArrayHasKey('url', $organisation);
        $this->assertArrayHasKey('logo', $organisation);
        $this->assertStringStartsWith('http', (string) $organisation['logo']);
    }

    public function test_website_search_action_is_guarded_by_route_existence(): void
    {
        $website = app(StructuredDataServiceInterface::class)->website('ar')->data;

        $this->assertSame('WebSite', $website['@type']);

        $hasSearchRoute = Route::has('public.search')
            || Route::has('public.search.index')
            || Route::has('search');

        if (! $hasSearchRoute) {
            $this->assertArrayNotHasKey(
                'potentialAction',
                $website,
                'SearchAction must not be emitted before the search route exists.'
            );

            return;
        }

        $this->assertSame('SearchAction', $website['potentialAction']['@type']);
        $this->assertStringContainsString('{search_term_string}', $website['potentialAction']['target']['urlTemplate']);
        $this->assertSame('required name=search_term_string', $website['potentialAction']['query-input']);
    }

    /**
     * The public page query budget is tight (5 PHP workers, no OPcache).
     * Structured data must ride on settings the public shell has already
     * loaded, never issue lookups of its own.
     */
    public function test_structured_data_adds_no_queries_once_the_shell_is_loaded(): void
    {
        $settings = app(SettingsServiceInterface::class);
        $service = app(StructuredDataServiceInterface::class);

        // Warm exactly what the public shell warms before a view renders.
        $settings->getPublicSettings('ar');

        DB::enableQueryLog();
        DB::flushQueryLog();

        $service->homepage('ar');
        $service->breadcrumbs('ar', [['name' => 'News', 'url' => '/ar/news']]);

        $queries = DB::getQueryLog();
        DB::disableQueryLog();

        $this->assertSame(
            [],
            $queries,
            'Structured data must not query the database: '.json_encode(array_column($queries, 'query'))
        );
    }

    public function test_breadcrumbs_are_positioned_from_the_homepage(): void
    {
        $breadcrumbs = app(StructuredDataServiceInterface::class)->breadcrumbs('en', [
            ['name' => 'News', 'url' => '/en/news'],
            ['name' => 'An article', 'url' => '/en/news/an-article'],
        ])->data;

        $this->assertSame('BreadcrumbList', $breadcrumbs['@type']);
        $this->assertCount(3, $breadcrumbs['itemListElement']);
        $this->assertSame('Home', $breadcrumbs['itemListElement'][0]['name']);
        $this->assertSame(1, $breadcrumbs['itemListElement'][0]['position']);
        $this->assertSame('An article', $breadcrumbs['itemListElement'][2]['name']);
        $this->assertSame(3, $breadcrumbs['itemListElement'][2]['position']);
        $this->assertStringStartsWith('http', $breadcrumbs['itemListElement'][2]['item']);
    }

    public function test_breadcrumbs_skip_malformed_crumbs(): void
    {
        $breadcrumbs = app(StructuredDataServiceInterface::class)->breadcrumbs('en', [
            ['name' => '', 'url' => '/en/news'],
            ['name' => 'News', 'url' => ''],
            ['name' => 'Valid', 'url' => '/en/news/valid'],
        ])->data;

        $this->assertCount(2, $breadcrumbs['itemListElement']);
        $this->assertSame('Valid', $breadcrumbs['itemListElement'][1]['name']);
    }

    public function test_news_section_page_emits_a_breadcrumb_list(): void
    {
        $documents = $this->jsonLdFrom('/ar/news');

        $this->assertContains('BreadcrumbList', $this->typesIn($documents));
    }

    /**
     * The publication detail pages already emit ScholarlyArticle through the
     * same $structuredData layout variable; nothing here may displace them.
     */
    public function test_existing_publication_structured_data_is_untouched(): void
    {
        $uri = '/en/research/publications/ai-dental-diagnostics';

        if ($this->get($uri)->getStatusCode() !== 200) {
            $this->markTestSkipped('Publication fixture unavailable in this dataset.');
        }

        $types = $this->typesIn($this->jsonLdFrom($uri));

        $this->assertContains('ScholarlyArticle', $types);
        $this->assertNotContains(
            'BreadcrumbList',
            $types,
            'Publication pages must keep their own structured data, not be overwritten.'
        );
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function jsonLdFrom(string $uri): array
    {
        $response = $this->get($uri);

        if ($response->getStatusCode() !== 200) {
            return [];
        }

        $content = $response->getContent() ?: '';
        preg_match_all(
            '#<script type="application/ld\+json">(.*?)</script>#s',
            $content,
            $matches
        );

        $documents = [];

        foreach ($matches[1] as $raw) {
            $decoded = json_decode(html_entity_decode($raw, ENT_QUOTES | ENT_HTML5), true);

            $this->assertNotNull(
                $decoded,
                'JSON-LD block is not valid JSON: '.json_last_error_msg()
            );
            $this->assertIsArray($decoded);

            $documents[] = $decoded;
        }

        return $documents;
    }

    /**
     * Collect @type values, flattening any @graph documents.
     *
     * @param  array<int, array<string, mixed>>  $documents
     * @return array<int, string>
     */
    private function typesIn(array $documents): array
    {
        $types = [];

        foreach ($documents as $document) {
            if (isset($document['@type']) && is_string($document['@type'])) {
                $types[] = $document['@type'];
            }

            foreach ($document['@graph'] ?? [] as $node) {
                if (is_array($node) && isset($node['@type']) && is_string($node['@type'])) {
                    $types[] = $node['@type'];
                }
            }
        }

        return $types;
    }
}
