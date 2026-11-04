<?php

declare(strict_types=1);

namespace App\Services\SiteStats;

use App\Contracts\SiteStats\SiteStatsRecorderInterface;
use App\DTOs\SiteStats\SiteStatsHitDTO;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Counts page views into daily totals. A visitor is `HMAC(day|kind|ip|user agent)`
 * keyed with the app key: the same browser counts once a day, a new day gives it a
 * new hash, and the hash is deleted after two days. Each increment is a single
 * UPDATE after an insert-or-ignore of its row, so concurrent hits never lose a count.
 */
final class SiteStatsRecorder implements SiteStatsRecorderInterface
{
    /** Crawlers, monitors and tools; real browsers that run the page's script rarely match. */
    private const BOT_PATTERN = '/bot\b|bot\/|crawl|spider|slurp|archiver|headless|phantom|lighthouse|pagespeed|gtmetrix|python|curl|wget|scrapy|httpclient|go-http|okhttp|java\//i';

    private const SEARCH_ENGINES = ['google', 'bing.com', 'yahoo', 'yandex', 'duckduckgo.com', 'baidu.com', 'ecosia.org', 'search.brave.com', 'qwant.com', 'startpage.com', 'naver.com', 'seznam.cz'];

    private const SOCIAL_SITES = ['facebook.com', 'fb.com', 'fb.me', 'messenger.com', 'instagram.com', 't.co', 'twitter.com', 'x.com', 'linkedin.com', 'lnkd.in', 'youtube.com', 'youtu.be', 't.me', 'telegram.org', 'web.telegram.org', 'whatsapp.com', 'wa.me', 'tiktok.com', 'reddit.com', 'snapchat.com', 'pinterest.com', 'threads.net'];

    public function recordPageView(SiteStatsHitDTO $hit): bool
    {
        if (! (bool) config('site_stats.enabled', true) || $this->isBot($hit->userAgent)) {
            return false;
        }

        if ($hit->siteHost === '' || strcasecmp($hit->originHost, $hit->siteHost) !== 0) {
            return false;
        }

        $path = $this->path($hit->pagePath);

        if ($path === null) {
            return false;
        }

        try {
            $day = $this->today();
            $now = now();
            $visitor = $this->visitorHash($day, 'page', $hit->ipAddress, $hit->userAgent);
            [$source, $referrerHost] = $this->source($hit->referrer, $hit->siteHost);
            $totals = ['page_views' => 1];

            $isNew = DB::table('site_visitor_days')->insertOrIgnore([
                'day' => $day,
                'visitor' => $visitor,
                'page_views' => 1,
                'last_seen_at' => $now,
            ]) > 0;

            if ($isNew) {
                $totals['visitors'] = 1;
                $totals[$this->device($hit->userAgent).'_visitors'] = 1;
                $totals[(str_starts_with($path, '/ar') ? 'arabic' : 'english').'_visitors'] = 1;
                $totals[$source.'_visitors'] = 1;
            } else {
                $visitorRow = DB::table('site_visitor_days')->where('day', $day)->where('visitor', $visitor);

                // The second page of the day makes the visitor engaged (they did not leave after one page).
                if ((clone $visitorRow)->where('page_views', 1)->update(['page_views' => 2, 'last_seen_at' => $now]) > 0) {
                    $totals['engaged_visitors'] = 1;
                } else {
                    $visitorRow->update(['page_views' => DB::raw($this->wrap('page_views').' + 1'), 'last_seen_at' => $now]);
                }
            }

            $this->incrementDay($day, $totals);
            $this->increment('site_page_days', ['day' => $day, 'path' => $path], 'views');

            if ($referrerHost !== null) {
                $this->increment('site_referrer_days', ['day' => $day, 'host' => $referrerHost], 'visits');
            }

            $this->recordSearch($day, $path, $hit);

            return true;
        } catch (Throwable $exception) {
            report($exception);

            return false;
        }
    }

    public function recordChatbotVisitor(string $ipAddress, string $userAgent): bool
    {
        if (! (bool) config('site_stats.enabled', true) || $this->isBot($userAgent)) {
            return false;
        }

        try {
            $day = $this->today();
            // A visit kind of its own: a page view's row is not touched, so the visitor and engaged counts stay right.
            $isNew = DB::table('site_visitor_days')->insertOrIgnore([
                'day' => $day,
                'visitor' => $this->visitorHash($day, 'chat', $ipAddress, $userAgent),
                'page_views' => 0,
                'last_seen_at' => now(),
            ]) > 0;

            if ($isNew) {
                $this->incrementDay($day, ['chatbot_visitors' => 1]);
            }

            return $isNew;
        } catch (Throwable $exception) {
            report($exception);

            return false;
        }
    }

    public function prune(): int
    {
        $today = Carbon::parse($this->today());
        $visitorsBefore = $today->copy()->subDays(max(1, (int) config('site_stats.retention.visitor_days', 2)))->toDateString();
        $detailBefore = $today->copy()->subDays(max(31, (int) config('site_stats.retention.detail_days', 180)))->toDateString();
        $deleted = DB::table('site_visitor_days')->where('day', '<', $visitorsBefore)->delete();

        foreach (['site_page_days', 'site_referrer_days', 'site_search_days'] as $table) {
            $deleted += DB::table($table)->where('day', '<', $detailBefore)->delete();
        }

        return $deleted;
    }

    /** A search page's term (first page of results only), with the count it showed. */
    private function recordSearch(string $day, string $path, SiteStatsHitDTO $hit): void
    {
        if (preg_match('#^/(ar|en)/search$#', $path, $matches) !== 1) {
            return;
        }

        parse_str((string) parse_url($hit->pagePath, PHP_URL_QUERY), $query);
        $term = is_string($query['q'] ?? null) ? $this->searchTerm($query['q']) : '';
        $page = is_string($query['page'] ?? null) ? (int) $query['page'] : 1;

        if ($term === '' || $page > 1) {
            return;
        }

        $this->increment(
            'site_search_days',
            ['day' => $day, 'locale' => $matches[1], 'query' => $term],
            'searches',
            ['results' => max(0, (int) ($hit->searchResults ?? 0))],
        );
    }

    /**
     * The public path to count: decoded, without the query string or a trailing slash, under
     * /ar or /en. Staff intake links are never recorded (the link is a secret).
     */
    private function path(string $pagePath): ?string
    {
        $path = rawurldecode((string) parse_url('/'.ltrim($pagePath, '/'), PHP_URL_PATH));

        if (! mb_check_encoding($path, 'UTF-8') || preg_match('/[\x00-\x1F\x7F]/', $path) === 1) {
            return null;
        }

        $path = rtrim($path, '/');

        if (preg_match('#^/(ar|en)(/|$)#', $path) !== 1 || preg_match('#^/(ar|en)/staff-intake(/|$)#', $path) === 1) {
            return null;
        }

        return mb_substr($path, 0, 255);
    }

    /**
     * Where the visitor came from: `direct` (typed, bookmarked or from this site), `search`,
     * `social` or `referral`, and the other site's host (null for direct).
     *
     * @return array{0: string, 1: ?string}
     */
    private function source(string $referrer, string $siteHost): array
    {
        $host = strtolower((string) parse_url(trim($referrer), PHP_URL_HOST));

        if ($host === '' || $host === strtolower($siteHost)) {
            return ['direct', null];
        }

        $host = (string) preg_replace('/^(www\d?|m|l|lm|mobile)\./', '', $host);
        $host = mb_substr($host, 0, 191);

        foreach (self::SEARCH_ENGINES as $engine) {
            if ($host === $engine || str_starts_with($host, $engine.'.') || str_ends_with($host, '.'.$engine) || (! str_contains($engine, '.') && preg_match('/(^|\.)'.preg_quote($engine, '/').'\.[a-z.]+$/', $host) === 1)) {
                return ['search', $host];
            }
        }

        foreach (self::SOCIAL_SITES as $site) {
            if ($host === $site || str_ends_with($host, '.'.$site)) {
                return ['social', $host];
            }
        }

        return ['referral', $host];
    }

    private function device(string $userAgent): string
    {
        return match (true) {
            preg_match('/iPad|Tablet|Kindle|Silk|PlayBook/i', $userAgent) === 1,
            preg_match('/Android/i', $userAgent) === 1 && preg_match('/Mobile/i', $userAgent) !== 1 => 'tablet',
            preg_match('/Mobi|iPhone|iPod|Android|Opera Mini|IEMobile/i', $userAgent) === 1 => 'mobile',
            default => 'desktop',
        };
    }

    private function isBot(string $userAgent): bool
    {
        $userAgent = trim($userAgent);

        return $userAgent === '' || preg_match(self::BOT_PATTERN, $userAgent) === 1;
    }

    private function searchTerm(string $term): string
    {
        $term = trim((string) preg_replace('/\s+/u', ' ', $term));

        return mb_check_encoding($term, 'UTF-8') ? mb_substr(mb_strtolower($term), 0, 120) : '';
    }

    private function visitorHash(string $day, string $kind, string $ipAddress, string $userAgent): string
    {
        return hash_hmac('sha256', $day.'|'.$kind.'|'.$ipAddress.'|'.$userAgent, (string) config('app.key'));
    }

    /** @param  array<string, int>  $amounts */
    private function incrementDay(string $day, array $amounts): void
    {
        $now = now();
        DB::table('site_traffic_days')->insertOrIgnore(['day' => $day, 'created_at' => $now, 'updated_at' => $now]);

        $updates = ['updated_at' => $now];
        foreach ($amounts as $column => $amount) {
            $updates[$column] = DB::raw($this->wrap($column).' + '.(int) $amount);
        }

        DB::table('site_traffic_days')->where('day', $day)->update($updates);
    }

    /**
     * @param  array<string, string>  $keys
     * @param  array<string, int>  $set  columns written as they are (the latest value wins)
     */
    private function increment(string $table, array $keys, string $column, array $set = []): void
    {
        DB::table($table)->insertOrIgnore([...$keys, $column => 0, ...$set]);
        DB::table($table)->where($keys)->update([$column => DB::raw($this->wrap($column).' + 1'), ...$set]);
    }

    private function wrap(string $column): string
    {
        return DB::connection()->getQueryGrammar()->wrap($column);
    }

    private function today(): string
    {
        return now((string) config('site_stats.timezone', 'Asia/Damascus'))->toDateString();
    }
}
