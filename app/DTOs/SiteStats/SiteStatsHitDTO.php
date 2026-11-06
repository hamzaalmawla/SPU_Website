<?php

declare(strict_types=1);

namespace App\DTOs\SiteStats;

/**
 * One page view as public/stats-hit.php queued it. `visitor` is already the
 * day's hash (the IP address never leaves the collector), `pagePath` the page's
 * path and query string, `originHost` the host of the request's Origin (else
 * Referer) header and `siteHost` its Host header, `referrer` the page's own
 * document.referrer and `searchResults` the result count a search page showed.
 */
final readonly class SiteStatsHitDTO
{
    public function __construct(
        public string $day,
        public string $visitor,
        public int $time,
        public string $pagePath,
        public string $originHost,
        public string $siteHost,
        public string $referrer,
        public string $userAgent,
        public ?int $searchResults = null,
    ) {}
}
