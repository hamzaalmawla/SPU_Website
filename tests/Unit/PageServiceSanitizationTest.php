<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Contracts\AuditServiceInterface;
use App\Contracts\CacheServiceInterface;
use App\Contracts\SeoMetadataServiceInterface;
use App\DTOs\PageTranslationDTO;
use App\Models\Page;
use App\Models\PageTranslation;
use App\Services\PageService;
use App\Support\HtmlSanitizer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Tests that PageService sanitizes HTML content at CMS storage time.
 *
 * Validates Requirements 1.1, 1.2, 1.7 — defense-in-depth sanitization.
 */
final class PageServiceSanitizationTest extends TestCase
{
    use RefreshDatabase;

    private PageService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = new PageService(
            app(AuditServiceInterface::class),
            app(CacheServiceInterface::class),
            app(SeoMetadataServiceInterface::class),
            app(HtmlSanitizer::class),
        );
    }

    #[Test]
    public function it_sanitizes_body_field_on_translation_update(): void
    {
        $page = Page::factory()->create();

        $payload = new PageTranslationDTO(
            title: 'Test Page',
            body: '<p>Safe content</p><script>alert("xss")</script>',
        );

        $this->service->updateArabicTranslation($page->id, $payload);

        $translation = PageTranslation::where('page_id', $page->id)
            ->where('locale', 'ar')
            ->first();

        $this->assertNotNull($translation);
        $this->assertStringContainsString('Safe content', $translation->body);
        $this->assertStringNotContainsString('<script>', $translation->body);
        $this->assertStringNotContainsString('alert', $translation->body);
    }

    #[Test]
    public function it_sanitizes_legacy_html_blocks_in_body_payload_on_translation_update(): void
    {
        $page = Page::factory()->create();

        $payload = new PageTranslationDTO(
            title: 'Test Page',
            bodyPayload: [
                'blocks' => [
                    [
                        'type' => 'legacy_html',
                        'content' => '<p>Good content</p><img onerror="alert(1)" src="x">',
                    ],
                    [
                        'type' => 'text',
                        'content' => 'Plain text block — not sanitized',
                    ],
                ],
            ],
        );

        $this->service->updateEnglishTranslation($page->id, $payload);

        $translation = PageTranslation::where('page_id', $page->id)
            ->where('locale', 'en')
            ->first();

        $this->assertNotNull($translation);

        $blocks = $translation->body_payload['blocks'];

        // legacy_html block should be sanitized
        $this->assertStringContainsString('Good content', $blocks[0]['content']);
        $this->assertStringNotContainsString('onerror', $blocks[0]['content']);

        // text block should remain untouched
        $this->assertSame('Plain text block — not sanitized', $blocks[1]['content']);
    }

    #[Test]
    public function it_handles_null_body_payload_gracefully(): void
    {
        $page = Page::factory()->create();

        $payload = new PageTranslationDTO(
            title: 'Test Page',
            bodyPayload: null,
            body: null,
        );

        $this->service->updateArabicTranslation($page->id, $payload);

        $translation = PageTranslation::where('page_id', $page->id)
            ->where('locale', 'ar')
            ->first();

        $this->assertNotNull($translation);
        $this->assertSame('', $translation->body);
        $this->assertNull($translation->body_payload);
    }

    #[Test]
    public function it_sanitizes_javascript_urls_in_body_content(): void
    {
        $page = Page::factory()->create();

        $payload = new PageTranslationDTO(
            title: 'Test Page',
            bodyPayload: [
                'blocks' => [
                    [
                        'type' => 'legacy_html',
                        'content' => '<a href="javascript:alert(1)">Click me</a>',
                    ],
                ],
            ],
        );

        $this->service->updateArabicTranslation($page->id, $payload);

        $translation = PageTranslation::where('page_id', $page->id)
            ->where('locale', 'ar')
            ->first();

        $this->assertNotNull($translation);
        $block = $translation->body_payload['blocks'][0];
        $this->assertStringNotContainsString('javascript:', $block['content']);
        $this->assertStringContainsString('Click me', $block['content']);
    }
}
