<?php

declare(strict_types=1);

namespace Tests\Feature\Seo;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * robots.txt is emitted from the environment name alone.
 *
 * This is a one-way door at cutover: if APP_ENV is anything but "production"
 * when the new site takes over the main domain, every crawler is told
 * "Disallow: /" and the university de-indexes itself. Pin both directions.
 */
final class RobotsIndexingPolicyTest extends TestCase
{
    use RefreshDatabase;

    public function test_non_production_environments_disallow_all_crawling(): void
    {
        foreach (['testing', 'local', 'staging'] as $environment) {
            $this->app->detectEnvironment(static fn (): string => $environment);

            $content = (string) $this->get('/robots.txt')->assertOk()->getContent();

            $this->assertStringContainsString('Disallow: /', $content, "Environment {$environment} must not be indexable.");
            $this->assertStringNotContainsString('Allow: /', $content);
        }
    }

    public function test_production_allows_crawling(): void
    {
        $this->app->detectEnvironment(static fn (): string => 'production');

        $content = (string) $this->get('/robots.txt')->assertOk()->getContent();

        $this->assertStringContainsString('Allow: /', $content);
        $this->assertStringNotContainsString('Disallow: /', $content);
    }

    public function test_robots_always_points_at_the_canonical_sitemap_entry_point(): void
    {
        $expected = 'Sitemap: '.rtrim((string) config('edge.canonical_url'), '/').'/sitemap.xml';

        foreach (['production', 'staging'] as $environment) {
            $this->app->detectEnvironment(static fn (): string => $environment);

            $this->get('/robots.txt')
                ->assertOk()
                ->assertHeader('Content-Type', 'text/plain; charset=utf-8')
                ->assertSee($expected, false);
        }
    }

    /**
     * The sitemap URL advertised in robots.txt must be the index that actually
     * answers, otherwise the split silently orphans every child sitemap.
     */
    public function test_the_advertised_sitemap_url_resolves_to_the_index(): void
    {
        $content = (string) $this->get('/robots.txt')->assertOk()->getContent();

        $this->assertMatchesRegularExpression('#^Sitemap: \S+/sitemap\.xml$#m', $content);

        $xml = simplexml_load_string((string) $this->get('/sitemap.xml')->assertOk()->getContent());
        $this->assertNotFalse($xml);
        $this->assertSame('sitemapindex', $xml->getName());
    }
}
