<?php

declare(strict_types=1);

namespace App\Services\SiteStats;

use App\Contracts\SiteStats\SiteStatsReportInterface;
use App\DTOs\Dashboard\DashboardRowDTO;
use App\DTOs\SiteStats\SiteTrafficReportDTO;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

final class SiteStatsReportService implements SiteStatsReportInterface
{
    public function traffic(int $days = 30): SiteTrafficReportDTO
    {
        $days = max(1, $days);
        $today = $this->today();
        $from = $today->copy()->subDays($days - 1)->toDateString();
        $rows = DB::table('site_traffic_days')->where('day', '>=', $from)->get()
            ->keyBy(static fn (object $row): string => substr((string) $row->day, 0, 10));
        $todayRow = $rows->get($today->toDateString());
        $yesterday = DB::table('site_traffic_days')->where('day', $today->copy()->subDay()->toDateString())->first();
        $sum = static fn (string $column): int => (int) $rows->sum($column);

        $series = [];
        for ($offset = $days - 1; $offset >= 0; $offset--) {
            $day = $today->copy()->subDays($offset)->toDateString();
            $row = $rows->get($day);
            $series[] = ['day' => $day, 'visitors' => (int) ($row->visitors ?? 0), 'pageViews' => (int) ($row->page_views ?? 0)];
        }

        $onlineSince = now()->subMinutes(max(1, (int) config('site_stats.online_minutes', 5)));

        return new SiteTrafficReportDTO(
            visitorsToday: (int) ($todayRow->visitors ?? 0),
            visitorsYesterday: (int) ($yesterday->visitors ?? 0),
            pageViewsToday: (int) ($todayRow->page_views ?? 0),
            pageViewsYesterday: (int) ($yesterday->page_views ?? 0),
            chatbotVisitorsToday: (int) ($todayRow->chatbot_visitors ?? 0),
            onlineNow: DB::table('site_visitor_days')
                ->where('day', $today->toDateString())
                ->where('page_views', '>', 0)
                ->where('last_seen_at', '>=', $onlineSince)
                ->count(),
            periodDays: $days,
            periodVisitors: $sum('visitors'),
            periodPageViews: $sum('page_views'),
            periodEngagedVisitors: $sum('engaged_visitors'),
            periodChatbotVisitors: $sum('chatbot_visitors'),
            days: $series,
            devices: ['desktop' => $sum('desktop_visitors'), 'tablet' => $sum('tablet_visitors'), 'mobile' => $sum('mobile_visitors')],
            sources: ['direct' => $sum('direct_visitors'), 'search' => $sum('search_visitors'), 'social' => $sum('social_visitors'), 'referral' => $sum('referral_visitors')],
            languages: ['ar' => $sum('arabic_visitors'), 'en' => $sum('english_visitors')],
        );
    }

    public function topPages(int $days = 7, int $limit = 10): Collection
    {
        $rows = DB::table('site_page_days')
            ->where('day', '>=', $this->from($days))
            ->select('path', DB::raw('SUM(views) as total'))
            ->groupBy('path')
            ->orderByDesc('total')
            ->orderBy('path')
            ->limit(max(1, $limit))
            ->get();
        $titles = $this->pageTitles($rows->pluck('path')->map(static fn (mixed $path): string => (string) $path)->all());

        return $rows->map(static fn (object $row): DashboardRowDTO => new DashboardRowDTO(
            label: $titles[(string) $row->path] ?? (string) $row->path,
            value: (int) $row->total,
            hint: (string) $row->path,
            url: (string) $row->path,
        ))->values();
    }

    public function topReferrers(int $days = 7, int $limit = 8): Collection
    {
        return DB::table('site_referrer_days')
            ->where('day', '>=', $this->from($days))
            ->select('host', DB::raw('SUM(visits) as total'))
            ->groupBy('host')
            ->orderByDesc('total')
            ->orderBy('host')
            ->limit(max(1, $limit))
            ->get()
            ->map(static fn (object $row): DashboardRowDTO => new DashboardRowDTO(label: (string) $row->host, value: (int) $row->total))
            ->values();
    }

    public function topSearches(int $days = 30, int $limit = 8, bool $withoutResults = false): Collection
    {
        $from = $this->from($days);
        $rows = DB::table('site_search_days')
            ->where('day', '>=', $from)
            ->select('locale', 'query', DB::raw('SUM(searches) as total'), DB::raw('MAX(day) as last_day'))
            ->groupBy('locale', 'query')
            ->orderByDesc('total')
            ->orderBy('query')
            ->limit($withoutResults ? 500 : max(1, $limit))
            ->get();

        if ($withoutResults) {
            // The latest day each term was searched decides: content added since then may answer it.
            $latest = DB::table('site_search_days')
                ->where('day', '>=', $from)
                ->whereIn('query', $rows->pluck('query')->all())
                ->orderByDesc('day')
                ->get(['locale', 'query', 'results', 'day'])
                ->unique(static fn (object $row): string => $row->locale.'|'.$row->query)
                ->keyBy(static fn (object $row): string => $row->locale.'|'.$row->query);
            $rows = $rows
                ->filter(static fn (object $row): bool => (int) ($latest->get($row->locale.'|'.$row->query)->results ?? 1) === 0)
                ->take(max(1, $limit));
        }

        return $rows->map(static fn (object $row): DashboardRowDTO => new DashboardRowDTO(
            label: (string) $row->query,
            value: (int) $row->total,
            hint: strtoupper((string) $row->locale),
            url: '/'.$row->locale.'/search?q='.rawurlencode((string) $row->query),
        ))->values();
    }

    /**
     * Page titles from the site search index, by the path counted (decoded).
     *
     * @param  list<string>  $paths
     * @return array<string, string>
     */
    private function pageTitles(array $paths): array
    {
        if ($paths === []) {
            return [];
        }

        // The index stores some paths with their segments encoded.
        $byUrl = [];
        foreach ($paths as $path) {
            $byUrl[$path] = $path;
            $byUrl[implode('/', array_map('rawurlencode', explode('/', $path)))] = $path;
        }

        $titles = [];
        foreach (DB::table('search_documents')->whereIn('url', array_keys($byUrl))->get(['url', 'title']) as $document) {
            $path = $byUrl[(string) $document->url] ?? null;
            $title = trim((string) $document->title);

            if ($path !== null && $title !== '') {
                $titles[$path] ??= $title;
            }
        }

        foreach ($paths as $path) {
            if (preg_match('#^/(ar|en)$#', $path, $matches) === 1) {
                $titles[$path] ??= $matches[1] === 'ar' ? 'الصفحة الرئيسية' : 'Homepage';
            }
        }

        return $titles;
    }

    private function from(int $days): string
    {
        return $this->today()->subDays(max(1, $days) - 1)->toDateString();
    }

    private function today(): Carbon
    {
        return now((string) config('site_stats.timezone', 'Asia/Damascus'))->startOfDay();
    }
}
