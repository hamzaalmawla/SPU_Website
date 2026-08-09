<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Contracts\Seo\SitemapServiceInterface;
use App\DTOs\Seo\SitemapEntryDTO;
use App\Filament\Components\PageUrlSelect;
use Mockery;
use Tests\TestCase;

final class PageUrlSelectTest extends TestCase
{
    public function test_repeated_selectors_generate_the_sitemap_only_once_per_request(): void
    {
        $sitemap = Mockery::mock(SitemapServiceInterface::class);
        $sitemap->shouldReceive('generateEntries')
            ->once()
            ->andReturn(collect([
                new SitemapEntryDTO('https://spu.test/en/news/articles', '2026-08-08', null, null, []),
                new SitemapEntryDTO('https://spu.test/en/about', '2026-08-08', null, null, []),
                new SitemapEntryDTO('https://spu.test/ar/news/articles', '2026-08-08', null, null, []),
            ]));
        $this->app->instance(SitemapServiceInterface::class, $sitemap);
        request()->attributes->remove('filament.page-url-options.entries');
        request()->attributes->remove('filament.page-url-options.en');
        request()->attributes->remove('filament.page-url-options.ar');

        $englishNews = PageUrlSelect::searchOptions('news', 'en');
        $englishAbout = PageUrlSelect::searchOptions('about', 'en');
        $arabicNews = PageUrlSelect::searchOptions('news', 'ar');

        $this->assertArrayHasKey('/en/news/articles', $englishNews);
        $this->assertArrayHasKey('/en/about', $englishAbout);
        $this->assertArrayHasKey('/ar/news/articles', $arabicNews);
    }
}
