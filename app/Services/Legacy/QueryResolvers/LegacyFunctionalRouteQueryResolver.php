<?php

declare(strict_types=1);

namespace App\Services\Legacy\QueryResolvers;

use App\Contracts\Legacy\LegacyQueryModuleResolverInterface;
use App\DTOs\Legacy\LegacyQueryResolutionDTO;
use App\DTOs\Legacy\NormalizedLegacyUrlDTO;

final class LegacyFunctionalRouteQueryResolver implements LegacyQueryModuleResolverInterface
{
    /** @var array<string, array{source_id: int, path: string}> */
    private const ROUTES = [
        'dir=html&ex=1&lang=1&page=contactus' => ['source_id' => 1, 'path' => '/contact'],
        'dir=html&ex=1&lang=2&page=contactus' => ['source_id' => 1, 'path' => '/contact'],
        'dir=html&ex=1&lang=1&page=complaint' => ['source_id' => 2, 'path' => '/e-services/suggestions-complaints'],
        'dir=jobs&ex=2&lang=1&page=list&service=49' => ['source_id' => 49, 'path' => '/campus-life/career-development/jobs'],
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
