<?php

declare(strict_types=1);

namespace Tests\Feature\HomepageBlade;

use Tests\Support\HomepageBladeTestHelpers;
use Tests\TestCase;

class LayoutMetaTagsTest extends TestCase
{
    use HomepageBladeTestHelpers;

    public function test_fully_populated_seo_renders_all_meta_tags(): void
    {
        $data = self::makeLayoutData([
            'seo' => self::makeSeo([
                'title' => 'SPU Test Title',
                'metaDescription' => 'Test description',
                'ogTitle' => 'OG Test Title',
                'ogDescription' => 'OG Test Desc',
                'ogImage' => 'https://example.com/og.jpg',
                'canonicalUrl' => 'https://example.com/en',
                'hreflang' => [
                    ['locale' => 'ar', 'url' => 'https://example.com/ar'],
                    ['locale' => 'en', 'url' => 'https://example.com/en'],
                ],
            ]),
        ]);

        $html = view('layouts.public', $data)->render();

        $this->assertStringContainsString('<title>SPU Test Title</title>', $html);
        $this->assertStringContainsString('content="Test description"', $html);
        $this->assertStringContainsString('href="https://example.com/en"', $html);
        $this->assertStringContainsString('content="https://example.com/og.jpg"', $html);
        $this->assertStringContainsString('hreflang="ar"', $html);
        $this->assertStringContainsString('hreflang="en"', $html);
        $this->assertStringContainsString('content="OG Test Title"', $html);
        $this->assertStringContainsString('content="OG Test Desc"', $html);

        // No duplicate title tags
        $this->assertSame(1, substr_count($html, '<title>'));
    }

    public function test_null_optional_seo_fields_omit_tags(): void
    {
        $data = self::makeLayoutData([
            'seo' => self::makeSeo([
                'metaDescription' => null,
                'ogDescription' => null,
                'ogImage' => null,
                'canonicalUrl' => null,
                'hreflang' => [],
            ]),
        ]);

        $html = view('layouts.public', $data)->render();

        $this->assertStringContainsString('<title>', $html);
        $this->assertStringNotContainsString('name="description"', $html);
        $this->assertStringNotContainsString('rel="canonical"', $html);
        $this->assertStringNotContainsString('og:image', $html);
        $this->assertStringNotContainsString('hreflang', $html);
    }

    public function test_robots_defaults_to_index_follow_when_null(): void
    {
        $data = self::makeLayoutData([
            'seo' => self::makeSeo(['robots' => null]),
        ]);

        $html = view('layouts.public', $data)->render();

        $this->assertStringContainsString('content="index,follow"', $html);
    }

    public function test_og_locale_matches_page_locale(): void
    {
        $data = self::makeLayoutData(['locale' => 'ar', 'seo' => self::makeSeo(['locale' => 'ar'])]);
        $html = view('layouts.public', $data)->render();

        $this->assertStringContainsString('content="ar"', $html);
    }
}
