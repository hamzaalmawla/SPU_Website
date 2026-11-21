<?php

declare(strict_types=1);

namespace Tests\Feature\HomepageBlade;

use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\HomepageBladeTestHelpers;
use Tests\TestCase;

class SeoMetaPropertyTest extends TestCase
{
    use HomepageBladeTestHelpers;

    #[DataProvider('seoFieldCombinations')]
    public function test_seo_tags_present_iff_field_non_null(array $seoOverrides, array $expectedTags, array $absentTags): void
    {
        $data = self::makeLayoutData([
            'seo' => self::makeSeo($seoOverrides),
        ]);

        $html = view('layouts.public', $data)->render();

        foreach ($expectedTags as $tag) {
            $this->assertStringContainsString($tag, $html, "Expected tag containing '{$tag}'");
        }

        foreach ($absentTags as $tag) {
            $this->assertStringNotContainsString($tag, $html, "Did not expect tag containing '{$tag}'");
        }

        // Never duplicate title
        $this->assertSame(1, substr_count($html, '<title>'), 'Should have exactly one <title> tag');
    }

    public static function seoFieldCombinations(): array
    {
        return [
            'all populated' => [
                ['metaDescription' => 'Desc', 'ogImage' => 'https://img.com/og.jpg', 'canonicalUrl' => 'https://example.com'],
                ['name="description"', 'og:image', 'rel="canonical"'],
                [],
            ],
            'no description' => [
                ['metaDescription' => null],
                [],
                ['name="description"'],
            ],
            'no og image' => [
                ['ogImage' => null],
                [],
                ['og:image'],
            ],
            'no canonical' => [
                ['canonicalUrl' => null],
                [],
                ['rel="canonical"'],
            ],
            'no hreflang' => [
                ['hreflang' => []],
                [],
                ['hreflang'],
            ],
            'all optional null' => [
                ['metaDescription' => null, 'ogDescription' => null, 'ogImage' => null, 'canonicalUrl' => null, 'hreflang' => []],
                [],
                ['name="description"', 'og:image', 'rel="canonical"', 'hreflang'],
            ],
        ];
    }
}
