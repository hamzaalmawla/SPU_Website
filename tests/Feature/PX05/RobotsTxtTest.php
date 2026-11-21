<?php

declare(strict_types=1);

namespace Tests\Feature\PX05;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Feature tests for robots.txt endpoint.
 *
 * Requirements: 16.3, 16.4
 */
class RobotsTxtTest extends TestCase
{
    use RefreshDatabase;

    public function test_robots_txt_returns_text_plain_with_sitemap_reference(): void
    {
        $response = $this->get('/robots.txt');

        $response->assertOk();
        $this->assertStringStartsWith('text/plain', $response->headers->get('Content-Type'));

        $content = $response->getContent();
        $this->assertStringContainsString('Sitemap:', $content);
        $this->assertStringContainsString('sitemap.xml', $content);
    }

    public function test_non_production_environment_returns_disallow_directive(): void
    {
        // Testing environment is non-production by default
        $response = $this->get('/robots.txt');

        $response->assertOk();
        $content = $response->getContent();

        $this->assertStringContainsString('Disallow: /', $content);
    }
}
