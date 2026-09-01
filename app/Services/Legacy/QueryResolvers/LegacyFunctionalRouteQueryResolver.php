<?php

declare(strict_types=1);

namespace App\Services\Legacy\QueryResolvers;

use App\Contracts\Legacy\LegacyQueryModuleResolverInterface;
use App\DTOs\Legacy\LegacyQueryResolutionDTO;
use App\DTOs\Legacy\NormalizedLegacyUrlDTO;

final class LegacyFunctionalRouteQueryResolver implements LegacyQueryModuleResolverInterface
{
    /**
     * Reviewed exact query signatures => the new section that owns the material.
     *
     * The "service=N" rows are the old root content-list pages. They are the
     * section index for a service, not a record, so no per-record resolver can
     * place them: LegacyNewsQueryResolver only answers "page=show" URLs carrying
     * a legacy id, and LegacySubsiteContentQueryResolver deliberately offers no
     * section-level catch-all for the root subsite. Without a rule here they
     * returned a real 404 even though the old homepage links all six of them.
     *
     * What each service holds was read off the live old site on 2026-08-29 by
     * fetching each list page and diffing its body against the shared template:
     *
     *   3  news              "…تتصدر تصنيف Webometrics", competition results
     *   4  announcements     "إعلان عن…", course and competition notices
     *   5  partnership MoUs  the agreements page ("الاتفاقيات التي أبرمتها")
     *   6  community service community-responsibility text and photo library
     *   7  achievements      rankings, awards, published-research notices
     *  10  events            chess competition, book fair, receptions, holidays
     *
     * The destinations for 5, 6, 7 and 10 are the ones the generated allow-list
     * config/legacy_category_routes.php already uses for those same service ids,
     * so a list URL and a record URL from one service agree on where they land.
     * Services 3 and 4 have no row in that file on purpose — their records are
     * per-article and belong to LegacyNewsQueryResolver — but their list pages
     * are still section indexes, and the new site has a dedicated route for
     * each: /news for news, /news/announcements for announcements.
     *
     * Both language spellings are listed because the old site served lang=1
     * (Arabic) and lang=2 (English) at 200 for every one of them; resolve()
     * localises the destination from the URL's own language.
     *
     * @var array<string, array{source_id: int, path: string}>
     */
    private const ROUTES = [
        'dir=html&ex=1&lang=1&page=contactus' => ['source_id' => 1, 'path' => '/contact'],
        'dir=html&ex=1&lang=2&page=contactus' => ['source_id' => 1, 'path' => '/contact'],
        'dir=html&ex=1&lang=1&page=complaint' => ['source_id' => 2, 'path' => '/e-services/suggestions-complaints'],
        'dir=jobs&ex=2&lang=1&page=list&service=49' => ['source_id' => 49, 'path' => '/campus-life/career-development/jobs'],

        // Old root content-list pages, linked from the old homepage.
        'dir=items&ex=2&lang=1&page=list&service=3' => ['source_id' => 3, 'path' => '/news'],
        'dir=items&ex=2&lang=2&page=list&service=3' => ['source_id' => 3, 'path' => '/news'],
        'dir=items&ex=2&lang=1&page=list&service=4' => ['source_id' => 4, 'path' => '/news/announcements'],
        'dir=items&ex=2&lang=2&page=list&service=4' => ['source_id' => 4, 'path' => '/news/announcements'],
        'dir=items&ex=2&lang=1&page=list&service=5' => ['source_id' => 5, 'path' => '/about/partnerships'],
        'dir=items&ex=2&lang=2&page=list&service=5' => ['source_id' => 5, 'path' => '/about/partnerships'],
        'dir=items&ex=2&lang=1&page=list&service=6' => ['source_id' => 6, 'path' => '/news'],
        'dir=items&ex=2&lang=2&page=list&service=6' => ['source_id' => 6, 'path' => '/news'],
        'dir=items&ex=2&lang=1&page=list&service=7' => ['source_id' => 7, 'path' => '/news'],
        'dir=items&ex=2&lang=2&page=list&service=7' => ['source_id' => 7, 'path' => '/news'],
        'dir=items&ex=2&lang=1&page=list&service=10' => ['source_id' => 10, 'path' => '/news/events-list'],
        'dir=items&ex=2&lang=2&page=list&service=10' => ['source_id' => 10, 'path' => '/news/events-list'],

        // The old FAQ page. A continuity audit on 2026-09-01 found this is the
        // most-linked URL still returning 404 - 87 inbound links, because it sits
        // in the old site's footer on every page - while its destination was
        // already serving. Verified on the live old site the same day: lang=1
        // returns 4,946 visible characters and lang=2 returns 5,290, so both are
        // real content rather than the empty shared template.
        'dir=faqs&ex=2&lang=1&page=faqs' => ['source_id' => 0, 'path' => '/admissions/faq'],
        'dir=faqs&ex=2&lang=2&page=faqs' => ['source_id' => 0, 'path' => '/admissions/faq'],
    ];

    public function canResolve(NormalizedLegacyUrlDTO $url): bool
    {
        return $url->requestType === 'legacy_router'
            && $url->subsite->key === 'root'
            && mb_strtolower($url->path) === '/index.php'
            && isset(self::ROUTES[$this->signature($url)]);
    }

    public function resolve(NormalizedLegacyUrlDTO $url): ?LegacyQueryResolutionDTO
    {
        if (! $this->canResolve($url)) {
            return null;
        }

        $mapping = self::ROUTES[$this->signature($url)];

        return new LegacyQueryResolutionDTO(
            module: 'legacy_functional_route',
            sourceTable: 'legacy_router',
            sourceId: $mapping['source_id'],
            targetUrl: '/'.$url->language->locale.$mapping['path'],
            statusCode: 301,
            confidence: 'high',
            notes: 'Resolved an exact reviewed functional query signature to its dedicated localized public route.',
        );
    }

    private function signature(NormalizedLegacyUrlDTO $url): string
    {
        $params = $url->params;
        ksort($params);

        return http_build_query($params, '', '&', PHP_QUERY_RFC3986);
    }
}
