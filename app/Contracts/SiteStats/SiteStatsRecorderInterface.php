<?php

declare(strict_types=1);

namespace App\Contracts\SiteStats;

/**
 * Records the dashboard's first-party visitor statistics (config/site_stats.php).
 *
 * Page views are queued by public/stats-hit.php, which does not boot Laravel,
 * and written here once a minute (`site-stats:collect`). A visitor counts once
 * a day by `HMAC(day|kind|ip|user agent)` keyed with a random per-day key that
 * is deleted after two days; no IP address, user agent or cookie is stored.
 * Recording never fails a visitor's request.
 */
interface SiteStatsRecorderInterface
{
    /**
     * Writes the page views queued in every finished minute and deletes those queue files. Bots,
     * other sites, paths outside /ar and /en and staff intake links (their URL is a secret) are
     * dropped. Returns the page views recorded.
     */
    public function collectQueued(): int;

    /** Counts a visitor who asked the AI assistant a question today (once a day each). */
    public function recordChatbotVisitor(string $ipAddress, string $userAgent): bool;

    /** Deletes old visitor hashes, day keys and per-page detail; returns the rows deleted. */
    public function prune(): int;
}
