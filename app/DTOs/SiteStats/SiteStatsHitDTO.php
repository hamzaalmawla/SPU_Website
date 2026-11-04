<?php

declare(strict_types=1);

namespace App\DTOs\SiteStats;

/**
 * One page view as the browser reported it. `pagePath` is the page's path and
 * query string, `originHost` the host of the request's Origin (else Referer)
 * header, `referrer` the page's own document.referrer (where the visitor came
 * from) and `searchResults` the result count a search page showed. The IP
 * address and user agent are never stored: they only key the visitor's daily hash.
 */
final readonly class SiteStatsHitDTO
{
    public function __construct(
        public string $pagePath,
        public string $originHost,
        public string $siteHost,
        public string $referrer,
        public string $ipAddress,
        public string $userAgent,
        public ?int $searchResults = null,
    ) {}
}
