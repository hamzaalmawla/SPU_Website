<?php

declare(strict_types=1);

namespace Tests\Feature;

use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * /llms.txt and /.well-known/ard.json, checked against what Lighthouse's
 * Agentic Browsing audits require (llms.txt: Markdown with an H1; ARD: the
 * ard-schema rules).
 */
final class AgentDiscoveryTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
        config(['edge.canonical_url' => 'https://v2.spu.edu.sy']);
    }

    public function test_llms_txt_is_a_markdown_guide_built_from_the_published_menus(): void
    {
        $response = $this->get('/llms.txt')->assertOk();

        $this->assertStringStartsWith('text/markdown', (string) $response->headers->get('Content-Type'));
        $text = (string) $response->getContent();

        $this->assertMatchesRegularExpression('/\A# \S.+\n/u', $text, 'Starts with one H1.');
        $this->assertSame(1, preg_match_all('/^# /m', $text));
        $this->assertStringContainsString('## '.__('agents.sections_heading', [], 'en'), $text);
        $this->assertStringContainsString('## '.__('agents.sections_heading', [], 'ar'), $text);
        $this->assertMatchesRegularExpression('#- \[[^\]]+\]\(https://v2\.spu\.edu\.sy/en/[a-z-]+#', $text);
        $this->assertMatchesRegularExpression('#- \[[^\]]+\]\(https://v2\.spu\.edu\.sy/ar/[a-z-]+#', $text);
        $this->assertStringContainsString('(https://v2.spu.edu.sy/.well-known/ard.json)', $text);
        $this->assertStringNotContainsString('](#)', $text);
        $this->assertStringNotContainsString('preview', $text);
    }

    public function test_the_ard_manifest_follows_the_schema(): void
    {
        $response = $this->get('/.well-known/ard.json')->assertOk()->assertHeader('Content-Type', 'application/json');
        $manifest = $response->json();

        $this->assertIsArray($manifest['entries'] ?? null);
        $this->assertNotEmpty($manifest['entries']);
        $this->assertArrayNotHasKey('collections', $manifest);

        foreach ($manifest['entries'] as $entry) {
            $this->assertMatchesRegularExpression('/^urn:air:[a-z0-9.-]+:[a-z0-9-]+:[a-z0-9-]+$/', $entry['identifier']);
            $this->assertIsString($entry['displayName']);
            $this->assertNotSame('', $entry['displayName']);
            $this->assertMatchesRegularExpression('#^[a-z]+/[a-z0-9.+-]+$#', $entry['type']);
            $this->assertTrue(isset($entry['url']) xor isset($entry['data']), 'Exactly one of url or data.');
            $this->assertStringStartsWith('https://v2.spu.edu.sy/', $entry['url']);
            $this->assertGreaterThanOrEqual(2, count($entry['representativeQueries']));
            $this->assertLessThanOrEqual(5, count($entry['representativeQueries']));
        }

        $this->assertSame('urn:air:spu.edu.sy:website:guide', $manifest['entries'][0]['identifier']);
        $this->get(parse_url($manifest['entries'][0]['url'], PHP_URL_PATH))->assertOk();
        $this->get(parse_url($manifest['entries'][1]['url'], PHP_URL_PATH))->assertOk();
    }
}
