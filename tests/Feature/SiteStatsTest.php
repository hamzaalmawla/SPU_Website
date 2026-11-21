<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Contracts\SiteStats\SiteStatsRecorderInterface;
use App\Contracts\SiteStats\SiteStatsReportInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Support\SiteStatsCollector;
use Tests\TestCase;

/**
 * The dashboard's own visitor statistics: each public page posts one view to
 * public/stats-hit.php, which queues it without booting Laravel;
 * `site-stats:collect` writes the queue every minute. No cookie, no stored IP
 * address (a visitor is a hash keyed with a per-day random key), and bots,
 * other sites and staff intake links are left out.
 */
final class SiteStatsTest extends TestCase
{
    use RefreshDatabase;

    private string $directory;

    protected function setUp(): void
    {
        parent::setUp();

        $this->directory = SiteStatsCollector::boot();
    }

    protected function tearDown(): void
    {
        SiteStatsCollector::cleanUp($this->directory);

        parent::tearDown();
    }

    public function test_a_visitor_counts_once_a_day_and_a_second_page_makes_them_engaged(): void
    {
        $this->assertTrue(SiteStatsCollector::view('/ar'));
        SiteStatsCollector::view('/ar/about');
        $this->collect();
        // A later batch: the same visitor keeps counting once.
        SiteStatsCollector::view('/ar/about/');
        SiteStatsCollector::view('/en/faculties', userAgent: SiteStatsCollector::PHONE, ip: '10.0.0.2', referrer: 'https://www.google.com/');
        $this->collect();

        $traffic = app(SiteStatsReportInterface::class)->traffic(7);
        $this->assertSame(2, $traffic->visitorsToday);
        $this->assertSame(4, $traffic->pageViewsToday);
        $this->assertSame(1, $traffic->periodEngagedVisitors);
        $this->assertSame(['desktop' => 1, 'tablet' => 0, 'mobile' => 1], $traffic->devices);
        $this->assertSame(['direct' => 1, 'search' => 1, 'social' => 0, 'referral' => 0], $traffic->sources);
        $this->assertSame(['ar' => 1, 'en' => 1], $traffic->languages);
        $this->assertCount(7, $traffic->days);

        $pages = app(SiteStatsReportInterface::class)->topPages(7, 10);
        $this->assertSame(['/ar/about', '/ar', '/en/faculties'], $pages->pluck('hint')->all());
        $this->assertSame(2, $pages->first()?->value);
        $this->assertSame('الصفحة الرئيسية', $pages->firstWhere('hint', '/ar')?->label);

        // No IP address is stored anywhere, only hashes keyed with the day's random key.
        $this->assertStringNotContainsString('10.0.0', (string) json_encode(DB::table('site_visitor_days')->get()->all()));
        $this->assertSame([], glob($this->directory.'/queue/*') ?: [], 'Collected queue files are deleted.');
        $this->assertCount(1, glob($this->directory.'/salt-*') ?: []);
    }

    public function test_the_collector_queues_without_storing_the_address_and_refuses_other_requests(): void
    {
        SiteStatsCollector::view('/ar/news', ip: '203.0.113.77');
        $queued = (string) file_get_contents((string) (glob($this->directory.'/queue/*.log') ?: [''])[0]);
        $this->assertStringContainsString('news', $queued);
        $this->assertStringNotContainsString('203.0.113.77', $queued);

        $this->assertFalse(SiteStatsCollector::view('/ar', method: 'GET'));
        $this->assertFalse(SiteStatsCollector::view(str_repeat('/ar', 1000)), 'An oversized body is refused.');

        // A file of the current minute may still be written to; it waits for the next run.
        $directory = $this->directory;
        $now = new \DateTimeImmutable('now', new \DateTimeZone('Asia/Damascus'));
        $line = \spu_stats_queue_line(['REQUEST_METHOD' => 'POST', 'REMOTE_ADDR' => '10.0.0.8', 'HTTP_USER_AGENT' => SiteStatsCollector::BROWSER, 'HTTP_ORIGIN' => url('/'), 'HTTP_HOST' => (string) parse_url(url('/'), PHP_URL_HOST)], '{"p":"/ar","r":""}', $directory, $now);
        $this->assertIsString($line);
        \spu_stats_enqueue($directory, $line, $now);

        $this->collect();
        $this->assertSame(1, (int) DB::table('site_traffic_days')->sum('page_views'));
        $this->assertCount(1, glob($directory.'/queue/*.log') ?: []);
    }

    public function test_bots_other_sites_and_secret_links_are_not_counted(): void
    {
        SiteStatsCollector::view('/ar', userAgent: 'Mozilla/5.0 (compatible; Googlebot/2.1; +http://www.google.com/bot.html)');
        SiteStatsCollector::view('/ar', userAgent: '');
        SiteStatsCollector::view('/ar', origin: 'https://example.com');
        SiteStatsCollector::view('/ar/staff-intake/'.str_repeat('a', 40));
        SiteStatsCollector::view('/wp-login.php');
        SiteStatsCollector::view('/admin');
        $this->collect();

        $this->assertSame(0, (int) DB::table('site_traffic_days')->sum('page_views'));
        $this->assertSame(0, DB::table('site_page_days')->count());
    }

    public function test_switched_off_statistics_send_nothing_and_discard_the_queue(): void
    {
        SiteStatsCollector::view('/ar');
        config(['site_stats.enabled' => false]);

        $this->collect();
        $this->assertSame(0, DB::table('site_traffic_days')->count());
        $this->assertSame([], glob($this->directory.'/queue/*') ?: []);
        $this->get('/en/search?q=x')->assertOk()->assertSee('<meta name="spu-stats" content="off">', false);
    }

    public function test_referring_sites_and_social_visits_are_recorded(): void
    {
        SiteStatsCollector::view('/ar', referrer: 'https://l.facebook.com/l.php?u=x');
        SiteStatsCollector::view('/en', ip: '10.0.0.3', referrer: 'https://www.moe.gov.sy/news');
        SiteStatsCollector::view('/en/about', ip: '10.0.0.3', referrer: url('/en'));
        $this->collect();

        $traffic = app(SiteStatsReportInterface::class)->traffic(1);
        $this->assertSame(['direct' => 0, 'search' => 0, 'social' => 1, 'referral' => 1], $traffic->sources);
        $this->assertEqualsCanonicalizing(['facebook.com', 'moe.gov.sy'], app(SiteStatsReportInterface::class)->topReferrers(7)->pluck('label')->all());
    }

    public function test_site_searches_and_searches_that_find_nothing_are_listed(): void
    {
        SiteStatsCollector::view('/ar/search?q=%D8%A7%D9%84%D9%82%D8%A8%D9%88%D9%84', results: 12);
        SiteStatsCollector::view('/ar/search?q=%D8%A7%D9%84%D9%82%D8%A8%D9%88%D9%84', ip: '10.0.0.4', results: 12);
        SiteStatsCollector::view('/en/search?q=Scholarship%20%20Grants', results: 0);
        // Later pages of the same results are not another search.
        SiteStatsCollector::view('/en/search?q=scholarship+grants&page=2', ip: '10.0.0.5', results: 0);
        $this->collect();

        $report = app(SiteStatsReportInterface::class);
        $top = $report->topSearches(30, 8);
        $this->assertSame(['القبول', 'scholarship grants'], $top->pluck('label')->all());
        $this->assertSame([2, 1], $top->pluck('value')->all());
        $this->assertSame(['scholarship grants'], $report->topSearches(30, 8, withoutResults: true)->pluck('label')->all());

        // The search page tells the script how many results it showed.
        $this->get('/en/search?q=admission')->assertOk()->assertSee('data-search-total=', false);
    }

    public function test_people_who_asked_the_assistant_count_once_a_day(): void
    {
        $recorder = app(SiteStatsRecorderInterface::class);
        SiteStatsCollector::view('/ar');
        $this->collect();

        $this->assertTrue($recorder->recordChatbotVisitor('10.0.0.1', SiteStatsCollector::BROWSER));
        $this->assertFalse($recorder->recordChatbotVisitor('10.0.0.1', SiteStatsCollector::BROWSER));
        $this->assertTrue($recorder->recordChatbotVisitor('10.0.0.9', SiteStatsCollector::PHONE));

        $traffic = app(SiteStatsReportInterface::class)->traffic(1);
        $this->assertSame(2, $traffic->chatbotVisitorsToday);
        // Asking the assistant is not a page view and does not change the visitor count.
        $this->assertSame(1, $traffic->visitorsToday);
    }

    public function test_prune_keeps_daily_totals_and_drops_old_visitor_hashes_and_day_keys(): void
    {
        SiteStatsCollector::view('/ar');
        $this->collect();
        DB::table('site_visitor_days')->update(['day' => now()->subDays(5)->toDateString()]);
        DB::table('site_page_days')->update(['day' => now()->subYear()->toDateString()]);
        file_put_contents($this->directory.'/salt-2020-01-01', str_repeat('a', 64));

        $this->artisan('site-stats:prune')->assertSuccessful();

        $this->assertSame(0, DB::table('site_visitor_days')->count());
        $this->assertSame(0, DB::table('site_page_days')->count());
        $this->assertSame(1, (int) DB::table('site_traffic_days')->sum('visitors'));
        $this->assertFileDoesNotExist($this->directory.'/salt-2020-01-01');
        $this->assertCount(1, glob($this->directory.'/salt-*') ?: [], 'Today\'s key stays.');
    }

    private function collect(): void
    {
        $this->artisan('site-stats:collect')->assertSuccessful();
    }
}
