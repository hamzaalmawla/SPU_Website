<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Support\Facades\Blade;
use Tests\TestCase;

final class PublicPaginationTest extends TestCase
{
    public function test_shared_pagination_is_compact_accessible_and_preserves_page_urls(): void
    {
        $html = Blade::render(
            '<x-public.pagination :current-page="5" :total-pages="10" :page-url="$pageUrl" locale="en" />',
            ['pageUrl' => static fn (int $page): string => '/en/catalog?filter=active'.($page > 1 ? '&page='.$page : '')],
        );

        $this->assertStringContainsString('aria-current="page"', $html);
        $this->assertStringContainsString('Current page 5', $html);
        $this->assertStringContainsString('Page 5 of 10', $html);
        $this->assertStringContainsString('rel="prev"', $html);
        $this->assertStringContainsString('rel="next"', $html);
        $this->assertStringContainsString('/en/catalog?filter=active&amp;page=4', $html);
        $this->assertStringContainsString('aria-label="Page 1"', $html);
        $this->assertStringContainsString('aria-label="Page 10"', $html);
        $this->assertSame(2, substr_count($html, '>...</span>'));
        $this->assertStringNotContainsString('aria-label="Page 2"', $html);
    }
}
