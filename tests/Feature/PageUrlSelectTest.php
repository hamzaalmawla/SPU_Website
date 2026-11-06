<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Contracts\Navigation\MenuServiceInterface;
use App\Contracts\Seo\SitemapServiceInterface;
use App\DTOs\Seo\SitemapEntryDTO;
use App\Filament\Components\PageUrlSelect;
use App\Models\Cms\CmsTargetContent;
use App\Models\Navigation\MenuItem;
use App\Models\Page\Page;
use App\Models\Page\PageTranslation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

final class PageUrlSelectTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_third_level_page_is_offered_with_its_full_trail_in_both_locales(): void
    {
        $hub = $this->publishedPage('student-hub', null, ['ar' => 'مركز الطلاب', 'en' => 'Student Hub']);
        $careers = $this->publishedPage('careers', $hub, ['ar' => 'المهن', 'en' => 'Careers']);
        $this->publishedPage('openings', $careers, ['ar' => 'الشواغر', 'en' => 'Openings']);

        $this->assertSame(
            ['/en/student-hub/careers/openings' => 'Student Hub › Careers › Openings'],
            PageUrlSelect::searchOptions('openings', 'en'),
        );
        $this->assertSame(
            ['/ar/student-hub/careers/openings' => 'مركز الطلاب › المهن › الشواغر'],
            PageUrlSelect::searchOptions('الشواغر', 'ar'),
        );
    }

    public function test_the_job_board_is_offered_once_its_content_is_published(): void
    {
        CmsTargetContent::query()->create([
            'target_key' => 'campus_life.jobs',
            'status' => 'published',
            'payload_json' => ['translations' => [
                'ar' => ['hero' => ['title' => 'لوحة الوظائف']],
                'en' => ['hero' => ['title' => 'Job Board']],
            ]],
            'published_at' => now(),
        ]);
        $campusLife = $this->menuItem('Campus Life', '/en/campus-life', null);
        $this->menuItem('Job Board', '/en/campus-life/career-development/jobs', $campusLife);

        // The menu names the page in English; Arabic has no menu link, so its
        // trail falls back to the CMS target names.
        $this->assertSame(
            'Campus Life › Career Development › Job Board',
            PageUrlSelect::options('en')['/en/campus-life/career-development/jobs'] ?? null,
        );
        $this->assertSame(
            'الحياة الجامعية › التطوير المهني › وظائف التطوير المهني',
            PageUrlSelect::options('ar')['/ar/campus-life/career-development/jobs'] ?? null,
        );
        $this->assertArrayHasKey('/en/campus-life/career-development/jobs', PageUrlSelect::searchOptions('job board', 'en'));
    }

    public function test_draft_target_content_is_not_offered(): void
    {
        CmsTargetContent::query()->create([
            'target_key' => 'campus_life.jobs',
            'status' => 'draft',
            'payload_json' => ['translations' => ['en' => ['hero' => ['title' => 'Job Board']]]],
        ]);

        $this->assertArrayNotHasKey('/en/campus-life/career-development/jobs', PageUrlSelect::options('en'));
    }

    public function test_the_menu_builder_page_list_shows_the_trail_of_deep_pages(): void
    {
        $hub = $this->publishedPage('student-hub', null, ['ar' => 'مركز الطلاب', 'en' => 'Student Hub']);
        $careers = $this->publishedPage('careers', $hub, ['ar' => 'المهن', 'en' => 'Careers']);
        $openings = $this->publishedPage('openings', $careers, ['ar' => 'الشواغر', 'en' => 'Openings']);

        $options = app(MenuServiceInterface::class)->getPageTargetOptions('en');

        $this->assertSame('Student Hub › Careers › Openings (openings)', $options[$openings->id] ?? null);
        $this->assertSame(
            [$hub->id, $careers->id, $openings->id],
            array_values(array_intersect(array_keys($options), [$hub->id, $careers->id, $openings->id])),
        );
    }

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
        $this->app->forgetScopedInstances();

        $englishNews = PageUrlSelect::searchOptions('news', 'en');
        $englishAbout = PageUrlSelect::searchOptions('about', 'en');
        $arabicNews = PageUrlSelect::searchOptions('news', 'ar');

        $this->assertArrayHasKey('/en/news/articles', $englishNews);
        $this->assertArrayHasKey('/en/about', $englishAbout);
        $this->assertArrayHasKey('/ar/news/articles', $arabicNews);
    }

    /** @param  array<string, string>  $titles */
    private function publishedPage(string $slug, ?Page $parent, array $titles): Page
    {
        $page = Page::query()->create([
            'slug' => $slug,
            'parent_id' => $parent?->id,
            'status' => 'published',
            'is_enabled' => true,
            'published_at' => now()->subMinute(),
            'template' => 'default',
            'type' => 'landing',
        ]);

        foreach ($titles as $locale => $title) {
            PageTranslation::query()->create(['page_id' => $page->id, 'locale' => $locale, 'title' => $title]);
        }

        return $page;
    }

    private function menuItem(string $label, string $url, ?MenuItem $parent): MenuItem
    {
        return MenuItem::query()->create([
            'type' => 'header',
            'group_key' => 'header',
            'parent_id' => $parent?->id,
            'label' => $label,
            'locale' => 'en',
            'target_kind' => 'url',
            'url' => $url,
            'is_enabled' => true,
            'open_in_new_tab' => false,
            'sort_order' => 1,
            'depth' => $parent === null ? 0 : 1,
        ]);
    }
}
