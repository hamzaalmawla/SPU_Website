<?php

declare(strict_types=1);

namespace App\Http\Controllers\Public;

use App\Contracts\SiteStats\SiteStatsRecorderInterface;
use App\DTOs\SiteStats\SiteStatsHitDTO;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * Receives the page view a public page reports after it loads
 * (resources/js/siteStats.js). Always answers 204: the visitor's page never
 * waits on, or learns anything from, the statistics.
 */
final class SiteStatsController extends Controller
{
    public function __invoke(Request $request, SiteStatsRecorderInterface $recorder): Response
    {
        $body = json_decode(mb_substr($request->getContent(), 0, 4096), true);
        $body = is_array($body) ? $body : [];
        $origin = (string) ($request->headers->get('Origin') ?: $request->headers->get('Referer') ?: '');

        $recorder->recordPageView(new SiteStatsHitDTO(
            pagePath: is_string($body['p'] ?? null) ? mb_substr($body['p'], 0, 2048) : '',
            originHost: (string) parse_url($origin, PHP_URL_HOST),
            siteHost: $request->getHost(),
            referrer: is_string($body['r'] ?? null) ? mb_substr($body['r'], 0, 2048) : '',
            ipAddress: (string) $request->ip(),
            userAgent: mb_substr((string) $request->userAgent(), 0, 512),
            searchResults: is_numeric($body['n'] ?? null) ? (int) $body['n'] : null,
        ));

        return response()->noContent()->header('Cache-Control', 'no-store');
    }
}
