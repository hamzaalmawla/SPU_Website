<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Contracts\Shared\AuditServiceInterface;
use App\Contracts\Shared\CacheServiceInterface;
use App\DTOs\Page\PageTranslationDTO;
use App\Models\Page\Page;
use App\Models\Page\PageTranslation;
use App\Models\User\User;
use App\Services\Page\PageDraftService;
use App\Services\Page\PagePublishabilityValidator;
use App\Services\Page\PagePublicReadService;
use App\Services\Page\PageService;
use App\Services\Page\PageUrlResolver;
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
            app(PagePublicReadService::class),
            app(PageDraftService::class),
            app(PageUrlResolver::class),
            app(PagePublishabilityValidator::class),
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

        $this->service->updateArabicTranslation($page->id, $payload, $this->editor()->id);

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

        $this->service->updateEnglishTranslation($page->id, $payload, $this->editor()->id);

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
    public function it_sanitizes_nested_legacy_html_blocks_in_body_payload_on_translation_update(): void
    {
        $page = Page::factory()->create();

        $payload = new PageTranslationDTO(
            title: 'Test Page',
            bodyPayload: [
                'blocks' => [
                    [
                        'type' => 'columns',
                        'children' => [
                            [
                                'type' => 'legacy_html',
                                'content' => '<p>Nested content</p><script>alert(1)</script><img src=x onerror=alert(1)>',
                            ],
                            [
                                'type' => 'text',
                                'content' => '<script>Escaped later</script>',
                            ],
                        ],
                    ],
                ],
            ],
        );

        $this->service->updateEnglishTranslation($page->id, $payload, $this->editor()->id);

        $translation = PageTranslation::where('page_id', $page->id)
            ->where('locale', 'en')
            ->first();

        $this->assertNotNull($translation);

        $children = $translation->body_payload['blocks'][0]['children'];

        $this->assertStringContainsString('Nested content', $children[0]['content']);
        $this->assertStringNotContainsString('<script', $children[0]['content']);
        $this->assertStringNotContainsString('onerror', $children[0]['content']);
        $this->assertSame('<script>Escaped later</script>', $children[1]['content']);
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

        $this->service->updateArabicTranslation($page->id, $payload, $this->editor()->id);

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

        $this->service->updateArabicTranslation($page->id, $payload, $this->editor()->id);

        $translation = PageTranslation::where('page_id', $page->id)
            ->where('locale', 'ar')
            ->first();

        $this->assertNotNull($translation);
        $block = $translation->body_payload['blocks'][0];
        $this->assertStringNotContainsString('javascript:', $block['content']);
        $this->assertStringContainsString('Click me', $block['content']);
    }

    private function editor(): User
    {
        return User::factory()->create(['role_slug' => 'editor']);
    }
}
