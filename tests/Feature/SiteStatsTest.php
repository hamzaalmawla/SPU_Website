<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Contracts\SiteStats\SiteStatsRecorderInterface;
use App\Contracts\SiteStats\SiteStatsReportInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * The dashboard's own visitor statistics: each public page reports one view to
 * /stats/hit, counted without cookies or stored IP addresses (a visitor is a
 * daily hash), with bots, other sites and staff intake links left out.
 */
final class SiteStatsTest extends TestCase
{
    use RefreshDatabase;

    private const BROWSER = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0 Safari/537.36';

    private const PHONE = 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_0 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/18.0 Mobile/15E148 Safari/604.1';

    public function test_a_visitor_counts_once_a_day_and_a_second_page_makes_them_engaged(): void
    {
        $this->hit('/ar')->assertNoContent();
        $this->hit('/ar/about');
        $this->hit('/ar/about/');
        $this->hit('/en/faculties', userAgent: self::PHONE, ip: '10.0.0.2', referrer: 'https://www.google.com/');

        $traffic = app(SiteStatsReportInterface::class)->traffic(7);
        $this->assertSame(2, $traffic->visitorsToday);
        $this->assertSame(4, $traffic->pageViewsToday);
        $this->assertSame(2, $traffic->onlineNow);
        $this->assertSame(1, $traffic->periodEngagedVisitors);
        $this->assertSame(['desktop' => 1, 'tablet' => 0, 'mobile' => 1], $traffic->devices);
        $this->assertSame(['direct' => 1, 'search' => 1, 'social' => 0, 'referral' => 0], $traffic->sources);
        $this->assertSame(['ar' => 1, 'en' => 1], $traffic->languages);
        $this->assertCount(7, $traffic->days);

        $pages = app(SiteStatsReportInterface::class)->topPages(7, 10);
        $this->assertSame(['/ar/about', '/ar', '/en/faculties'], $pages->pluck('hint')->all());
        $this->assertSame(2, $pages->first()?->value);
        $this->assertSame('الصفحة الرئيسية', $pages->firstWhere('hint', '/ar')?->label);

        // No IP address or user agent is stored, only the hash.
        $stored = json_encode(DB::table('site_visitor_days')->get()->all());
        $this->assertStringNotContainsString('10.0.0', (string) $stored);
        $this->assertStringNotContainsString('Chrome', (string) $stored);
    }

    public function test_bots_other_sites_and_secret_links_are_not_counted(): void
    {
        $this->hit('/ar', userAgent: 'Mozilla/5.0 (compatible; Googlebot/2.1; +http://www.google.com/bot.html)')->assertNoContent();
        $this->hit('/ar', userAgent: '');
        $this->hit('/ar', origin: 'https://example.com');
        $this->hit('/ar/staff-intake/'.str_repeat('a', 40));
        $this->hit('/wp-login.php');
        $this->hit('/admin');

        $this->assertSame(0, (int) DB::table('site_traffic_days')->sum('page_views'));
        $this->assertSame(0, DB::table('site_page_days')->count());
    }

    public function test_referring_sites_and_social_visits_are_recorded(): void
    {
        $this->hit('/ar', referrer: 'https://l.facebook.com/l.php?u=x');
        $this->hit('/en', ip: '10.0.0.3', referrer: 'https://www.moe.gov.sy/news');
        $this->hit('/en/about', ip: '10.0.0.3', referrer: url('/en'));

        $traffic = app(SiteStatsReportInterface::class)->traffic(1);
        $this->assertSame(['direct' => 0, 'search' => 0, 'social' => 1, 'referral' => 1], $traffic->sources);
        $this->assertEqualsCanonicalizing(['facebook.com', 'moe.gov.sy'], app(SiteStatsReportInterface::class)->topReferrers(7)->pluck('label')->all());
    }

    public function test_site_searches_and_searches_that_find_nothing_are_listed(): void
    {
        $this->hit('/ar/search?q=%D8%A7%D9%84%D9%82%D8%A8%D9%88%D9%84', results: 12);
        $this->hit('/ar/search?q=%D8%A7%D9%84%D9%82%D8%A8%D9%88%D9%84', ip: '10.0.0.4', results: 12);
        $this->hit('/en/search?q=Scholarship%20%20Grants', results: 0);
        // Later pages of the same results are not another search.
        $this->hit('/en/search?q=scholarship+grants&page=2', ip: '10.0.0.5', results: 0);

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
        $this->hit('/ar');

        $this->assertTrue($recorder->recordChatbotVisitor('10.0.0.1', self::BROWSER));
        $this->assertFalse($recorder->recordChatbotVisitor('10.0.0.1', self::BROWSER));
        $this->assertTrue($recorder->recordChatbotVisitor('10.0.0.9', self::PHONE));

        $traffic = app(SiteStatsReportInterface::class)->traffic(1);
        $this->assertSame(2, $traffic->chatbotVisitorsToday);
        // Asking the assistant is not a page view and does not change the visitor count.
        $this->assertSame(1, $traffic->visitorsToday);
        $this->assertSame(1, $traffic->onlineNow);
    }

    public function test_prune_keeps_daily_totals_and_drops_old_visitor_hashes(): void
    {
        $this->hit('/ar');
        DB::table('site_visitor_days')->update(['day' => now()->subDays(5)->toDateString()]);
        DB::table('site_page_days')->update(['day' => now()->subYear()->toDateString()]);

        $this->artisan('site-stats:prune')->assertSuccessful();

        $this->assertSame(0, DB::table('site_visitor_days')->count());
        $this->assertSame(0, DB::table('site_page_days')->count());
        $this->assertSame(1, (int) DB::table('site_traffic_days')->sum('visitors'));
    }

    /** Posts one page view the way resources/js/public/siteStats.js does. */
    private function hit(
        string $page,
        string $userAgent = self::BROWSER,
        string $ip = '10.0.0.1',
        string $referrer = '',
        ?string $origin = null,
        ?int $results = null,
    ): TestResponse {
        $body = ['p' => $page, 'r' => $referrer];

        if ($results !== null) {
            $body['n'] = $results;
        }

        return $this->call('POST', '/stats/hit', [], [], [], [
            'HTTP_ORIGIN' => $origin ?? rtrim(url('/'), '/'),
            'HTTP_USER_AGENT' => $userAgent,
            'REMOTE_ADDR' => $ip,
            'CONTENT_TYPE' => 'text/plain',
        ], (string) json_encode($body));
    }
}
