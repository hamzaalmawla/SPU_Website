<?php

declare(strict_types=1);

namespace App\Contracts\SiteStats;

use App\DTOs\SiteStats\SiteStatsHitDTO;

/**
 * Records the dashboard's first-party visitor statistics (config/site_stats.php).
 * A visitor is counted once a day by a keyed hash of the day, IP address and
 * user agent; no IP address, user agent or cookie is stored. Recording never
 * fails the visitor's request: an error is reported and the hit dropped.
 */
interface SiteStatsRecorderInterface
{
    /**
     * Counts one public page view. Ignored (false) for bots, pages of another
     * site, paths outside /ar and /en, and staff intake links (their URL is a secret).
     */
    public function recordPageView(SiteStatsHitDTO $hit): bool;

    /** Counts a visitor who asked the AI assistant a question today (once a day each). */
    public function recordChatbotVisitor(string $ipAddress, string $userAgent): bool;

    /** Deletes visitor hashes and per-page detail older than the retention; returns the rows deleted. */
    public function prune(): int;
}
