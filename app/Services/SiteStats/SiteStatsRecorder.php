<?php

declare(strict_types=1);

namespace App\Services\SiteStats;

use App\Contracts\SiteStats\SiteStatsRecorderInterface;
use App\DTOs\SiteStats\SiteStatsHitDTO;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Writes the page views public/stats-hit.php queued (one file per minute) into
 * daily totals, once a minute from the scheduler, so a page view costs the web
 * server no framework boot. A visitor is `HMAC(day|kind|ip|user agent)` keyed with
 * that day's random key (`salt-<day>`, shared with the collector and deleted after
 * two days): the same browser counts once a day and cannot be followed across days.
 */
final class SiteStatsRecorder implements SiteStatsRecorderInterface
{
    /** Crawlers, monitors and tools; real browsers that run the page's script rarely match. */
    private const BOT_PATTERN = '/bot\b|bot\/|crawl|spider|slurp|archiver|headless|phantom|lighthouse|pagespeed|gtmetrix|python|curl|wget|scrapy|httpclient|go-http|okhttp|java\//i';

    private const SEARCH_ENGINES = ['google', 'bing.com', 'yahoo', 'yandex', 'duckduckgo.com', 'baidu.com', 'ecosia.org', 'search.brave.com', 'qwant.com', 'startpage.com', 'naver.com', 'seznam.cz'];

    private const SOCIAL_SITES = ['facebook.com', 'fb.com', 'fb.me', 'messenger.com', 'instagram.com', 't.co', 'twitter.com', 'x.com', 'linkedin.com', 'lnkd.in', 'youtube.com', 'youtu.be', 't.me', 'telegram.org', 'web.telegram.org', 'whatsapp.com', 'wa.me', 'tiktok.com', 'reddit.com', 'snapchat.com', 'pinterest.com', 'threads.net'];

    public function collectQueued(): int
    {
        $queue = $this->directory().'/queue';

        if (! is_dir($queue)) {
            return 0;
        }

        // A minute's file is complete once that minute has passed (a few seconds' margin
        // for a request that named its file just before the minute turned).
        $open = $this->now()->subSeconds(5)->format('Ymd-Hi');
        $files = [];

        foreach (glob($queue.'/*.log') ?: [] as $file) {
            $claimed = $file.'.'.getmypid().'.collecting';

            if (basename($file, '.log') < $open && @rename($file, $claimed)) {
                $files[] = $claimed;
            }
        }

        // A run that stopped part-way leaves its claimed files; they are taken again later.
        foreach (glob($queue.'/*.collecting') ?: [] as $file) {
            if (! in_array($file, $files, true) && (int) @filemtime($file) < time() - 600) {
                $files[] = $file;
            }
        }

        $recorded = 0;

        foreach ($files as $file) {
            try {
                if ((bool) config('site_stats.enabled', true)) {
                    $recorded += $this->recordHits($this->readQueue($file));
                }

                @unlink($file);
            } catch (Throwable $exception) {
                report($exception);
            }
        }

        return $recorded;
    }

    public function recordChatbotVisitor(string $ipAddress, string $userAgent): bool
    {
        if (! (bool) config('site_stats.enabled', true) || $this->isBot($userAgent)) {
            return false;
        }

        try {
            $day = $this->now()->toDateString();
            $salt = $this->salt($day);

            if ($salt === null) {
                return false;
            }

            // A visit kind of its own: a page view's row is not touched, so the visitor and engaged counts stay right.
            $isNew = DB::table('site_visitor_days')->insertOrIgnore([
                'day' => $day,
                'visitor' => hash_hmac('sha256', $day.'|chat|'.$ipAddress.'|'.$userAgent, $salt),
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
        $today = $this->now()->startOfDay();
        $visitorsBefore = $today->copy()->subDays(max(1, (int) config('site_stats.retention.visitor_days', 2)))->toDateString();
        $detailBefore = $today->copy()->subDays(max(31, (int) config('site_stats.retention.detail_days', 180)))->toDateString();
        $deleted = DB::table('site_visitor_days')->where('day', '<', $visitorsBefore)->delete();

        foreach (['site_page_days', 'site_referrer_days', 'site_search_days'] as $table) {
            $deleted += DB::table($table)->where('day', '<', $detailBefore)->delete();
        }

        // Without yesterday's key no hash of that day can be made again.
        $yesterday = $today->copy()->subDay()->toDateString();
        foreach (glob($this->directory().'/salt-*') ?: [] as $file) {
            if (preg_match('/^salt-(\d{4}-\d{2}-\d{2})$/', basename($file), $matches) === 1 && $matches[1] < $yesterday) {
                @unlink($file);
            }
        }

        return $deleted;
    }

    /**
     * The page views in one queue file. A line that is not one is skipped.
     *
     * @return list<SiteStatsHitDTO>
     */
    private function readQueue(string $file): array
    {
        $handle = @fopen($file, 'rb');

        if ($handle === false) {
            return [];
        }

        $hits = [];

        while (($line = fgets($handle)) !== false) {
            $row = json_decode($line, true);
            $body = is_array($row) && is_string($row['b'] ?? null) ? json_decode($row['b'], true) : null;

            if (! is_array($row) || ! is_array($body)
                || ! is_string($row['d'] ?? null) || preg_match('/^\d{4}-\d{2}-\d{2}$/', $row['d']) !== 1
                || ! is_string($row['v'] ?? null) || preg_match('/^[0-9a-f]{64}$/', $row['v']) !== 1
                || ! is_int($row['t'] ?? null)) {
                continue;
            }

            $hits[] = new SiteStatsHitDTO(
                day: $row['d'],
                visitor: $row['v'],
                time: $row['t'],
                pagePath: is_string($body['p'] ?? null) ? mb_substr($body['p'], 0, 2048) : '',
                originHost: is_string($row['o'] ?? null) ? $row['o'] : '',
                siteHost: is_string($row['h'] ?? null) ? $row['h'] : '',
                referrer: is_string($body['r'] ?? null) ? mb_substr($body['r'], 0, 2048) : '',
                userAgent: is_string($row['ua'] ?? null) ? $row['ua'] : '',
                searchResults: is_numeric($body['n'] ?? null) ? (int) $body['n'] : null,
            );
        }

        fclose($handle);

        return $hits;
    }

    /**
     * Writes a batch of page views: each visitor once with their page count, and every total
     * incremented once by its sum.
     *
     * @param  list<SiteStatsHitDTO>  $hits
     */
    private function recordHits(array $hits): int
    {
        usort($hits, static fn (SiteStatsHitDTO $a, SiteStatsHitDTO $b): int => $a->time <=> $b->time);
        $visitors = [];
        $days = [];
        $pages = [];
        $referrers = [];
        $searches = [];
        $recorded = 0;

        foreach ($hits as $hit) {
            $path = $this->path($hit->pagePath);

            if ($path === null || $this->isBot($hit->userAgent) || $hit->siteHost === '' || strcasecmp($hit->originHost, $hit->siteHost) !== 0) {
                continue;
            }

            [$source, $referrerHost] = $this->source($hit->referrer, $hit->siteHost);
            $key = $hit->day.'|'.$hit->visitor;
            $visitors[$key] ??= ['day' => $hit->day, 'visitor' => $hit->visitor, 'views' => 0, 'last' => 0, 'first' => $hit, 'path' => $path, 'source' => $source];
            $visitors[$key]['views']++;
            $visitors[$key]['last'] = max($visitors[$key]['last'], $hit->time);
            $days[$hit->day]['page_views'] = ($days[$hit->day]['page_views'] ?? 0) + 1;
            $pages[$hit->day][$path] = ($pages[$hit->day][$path] ?? 0) + 1;

            if ($referrerHost !== null) {
                $referrers[$hit->day][$referrerHost] = ($referrers[$hit->day][$referrerHost] ?? 0) + 1;
            }

            $search = $this->search($path, $hit);
            if ($search !== null) {
                $searchKey = $search['locale'].'|'.$search['query'];
                $searches[$hit->day][$searchKey] = [...$search, 'count' => ($searches[$hit->day][$searchKey]['count'] ?? 0) + 1];
            }

            $recorded++;
        }

        if ($recorded === 0) {
            return 0;
        }

        DB::transaction(function () use ($visitors, $days, $pages, $referrers, $searches): void {
            foreach ($visitors as $visitor) {
                $day = $visitor['day'];
                $lastSeen = Carbon::createFromTimestamp($visitor['last']);
                $isNew = DB::table('site_visitor_days')->insertOrIgnore([
                    'day' => $day,
                    'visitor' => $visitor['visitor'],
                    'page_views' => $visitor['views'],
                    'last_seen_at' => $lastSeen,
                ]) > 0;

                if ($isNew) {
                    $first = $visitor['first'];
                    foreach (['visitors', $this->device($first->userAgent).'_visitors', (str_starts_with($visitor['path'], '/ar') ? 'arabic' : 'english').'_visitors', $visitor['source'].'_visitors'] as $column) {
                        $days[$day][$column] = ($days[$day][$column] ?? 0) + 1;
                    }
                    $before = 0;
                } else {
                    $row = DB::table('site_visitor_days')->where('day', $day)->where('visitor', $visitor['visitor']);
                    $before = (int) (clone $row)->value('page_views');
                    $row->update(['page_views' => DB::raw($this->wrap('page_views').' + '.(int) $visitor['views']), 'last_seen_at' => $lastSeen]);
                }

                // The second page of the day makes the visitor engaged (they did not leave after one page).
                if ($before <= 1 && $before + $visitor['views'] >= 2) {
                    $days[$day]['engaged_visitors'] = ($days[$day]['engaged_visitors'] ?? 0) + 1;
                }
            }

            foreach ($days as $day => $amounts) {
                $this->incrementDay((string) $day, $amounts);
            }

            foreach ($pages as $day => $paths) {
                foreach ($paths as $path => $views) {
                    $this->increment('site_page_days', ['day' => (string) $day, 'path' => (string) $path], 'views', $views);
                }
            }

            foreach ($referrers as $day => $hosts) {
                foreach ($hosts as $host => $visits) {
                    $this->increment('site_referrer_days', ['day' => (string) $day, 'host' => (string) $host], 'visits', $visits);
                }
            }

            foreach ($searches as $day => $terms) {
                foreach ($terms as $term) {
                    $this->increment(
                        'site_search_days',
                        ['day' => (string) $day, 'locale' => $term['locale'], 'query' => $term['query']],
                        'searches',
                        $term['count'],
                        ['results' => $term['results']],
                    );
                }
            }
        }, 3);

        return $recorded;
    }

    /**
     * A search page's term (first page of results only), with the count it showed.
     *
     * @return array{locale: string, query: string, results: int}|null
     */
    private function search(string $path, SiteStatsHitDTO $hit): ?array
    {
        if (preg_match('#^/(ar|en)/search$#', $path, $matches) !== 1) {
            return null;
        }

        parse_str((string) parse_url($hit->pagePath, PHP_URL_QUERY), $query);
        $term = is_string($query['q'] ?? null) ? $this->searchTerm($query['q']) : '';
        $page = is_string($query['page'] ?? null) ? (int) $query['page'] : 1;

        return $term === '' || $page > 1 ? null : ['locale' => $matches[1], 'query' => $term, 'results' => max(0, (int) ($hit->searchResults ?? 0))];
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

    /**
     * The day's random key, created atomically by whichever side needs it first. The same
     * rule as spu_stats_salt() in public/stats-hit.php.
     */
    private function salt(string $day): ?string
    {
        $directory = $this->directory();
        $path = $directory.'/salt-'.$day;
        $salt = is_file($path) ? @file_get_contents($path) : false;

        if (is_string($salt) && strlen($salt) === 64) {
            return $salt;
        }

        if (! is_dir($directory)) {
            @mkdir($directory, 0775, true);
        }

        $temporary = $path.'.'.bin2hex(random_bytes(6));

        if (@file_put_contents($temporary, bin2hex(random_bytes(32))) !== 64) {
            return null;
        }

        @link($temporary, $path);
        @unlink($temporary);
        $salt = @file_get_contents($path);

        return is_string($salt) && strlen($salt) === 64 ? $salt : null;
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
    private function increment(string $table, array $keys, string $column, int $amount, array $set = []): void
    {
        DB::table($table)->insertOrIgnore([...$keys, $column => 0, ...$set]);
        DB::table($table)->where($keys)->update([$column => DB::raw($this->wrap($column).' + '.$amount), ...$set]);
    }

    private function wrap(string $column): string
    {
        return DB::connection()->getQueryGrammar()->wrap($column);
    }

    private function directory(): string
    {
        return (string) config('site_stats.directory', storage_path('app/site-stats'));
    }

    /** Now in the statistics timezone (public/stats-hit.php names days and queue files in it). */
    private function now(): Carbon
    {
        return now('Asia/Damascus');
    }
}
