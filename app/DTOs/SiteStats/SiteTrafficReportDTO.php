<?php

declare(strict_types=1);

namespace App\DTOs\SiteStats;

/**
 * Visitor totals for the admin dashboard. "Today" is the current day in the
 * statistics timezone; the period totals cover the last `periodDays` days,
 * today included.
 */
final readonly class SiteTrafficReportDTO
{
    /**
     * @param  list<array{day: string, visitors: int, pageViews: int}>  $days  oldest first, one per day of the period
     * @param  array{desktop: int, tablet: int, mobile: int}  $devices  visitors in the period
     * @param  array{direct: int, search: int, social: int, referral: int}  $sources  visitors in the period, by where they came from
     * @param  array{ar: int, en: int}  $languages  visitors in the period, by the language of their first page
     */
    public function __construct(
        public int $visitorsToday,
        public int $visitorsYesterday,
        public int $pageViewsToday,
        public int $pageViewsYesterday,
        public int $chatbotVisitorsToday,
        public int $onlineNow,
        public int $periodDays,
        public int $periodVisitors,
        public int $periodPageViews,
        public int $periodEngagedVisitors,
        public int $periodChatbotVisitors,
        public array $days,
        public array $devices,
        public array $sources,
        public array $languages,
    ) {}
}
