<?php

declare(strict_types=1);

namespace App\Contracts\SiteStats;

use App\DTOs\Dashboard\DashboardRowDTO;
use App\DTOs\SiteStats\SiteTrafficReportDTO;
use Illuminate\Support\Collection;

/**
 * Reads the visitor statistics for the admin dashboard.
 */
interface SiteStatsReportInterface
{
    public function traffic(int $days = 30): SiteTrafficReportDTO;

    /**
     * Most viewed pages in the last `$days` days: label the page title (else its path), value the
     * views, hint the path, url the public page.
     *
     * @return Collection<int, DashboardRowDTO>
     */
    public function topPages(int $days = 7, int $limit = 10): Collection;

    /**
     * Sites that sent visitors in the last `$days` days: label the host, value the visits.
     *
     * @return Collection<int, DashboardRowDTO>
     */
    public function topReferrers(int $days = 7, int $limit = 8): Collection;

    /**
     * Site search terms of the last `$days` days, most searched first: label the term, value the
     * searches, hint the language, url the search page. `$withoutResults` keeps only terms whose
     * latest search found nothing (content visitors look for and do not find).
     *
     * @return Collection<int, DashboardRowDTO>
     */
    public function topSearches(int $days = 30, int $limit = 8, bool $withoutResults = false): Collection;
}
