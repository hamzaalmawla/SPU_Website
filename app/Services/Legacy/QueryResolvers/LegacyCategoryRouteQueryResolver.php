<?php

declare(strict_types=1);

namespace App\Services\Legacy\QueryResolvers;

use App\Contracts\Legacy\LegacyQueryModuleResolverInterface;
use App\DTOs\Legacy\LegacyQueryResolutionDTO;
use App\DTOs\Legacy\NormalizedLegacyUrlDTO;

final class LegacyCategoryRouteQueryResolver implements LegacyQueryModuleResolverInterface
{
    /** @var array<int, array{service: int, path: string}> */
    private const ROUTES = [
        12 => ['service' => 1, 'path' => ''],
        2029 => ['service' => 2, 'path' => ''],
        16 => ['service' => 1, 'path' => '/about'],
        28 => ['service' => 1, 'path' => '/about/accreditation'],
        1237 => ['service' => 1, 'path' => '/admissions/academic-warnings'],
        60 => ['service' => 2, 'path' => '/e-services/suggestions-complaints'],
        299 => ['service' => 1, 'path' => '/facilities/medicine'],
        1525 => ['service' => 1, 'path' => '/facilities/medicine'],
        1641 => ['service' => 1, 'path' => '/facilities/medicine'],
        1261 => ['service' => 2, 'path' => '/facilities/medicine'],
        1270 => ['service' => 2, 'path' => '/facilities/medicine'],
        298 => ['service' => 1, 'path' => '/facilities/dentistry'],
        1526 => ['service' => 1, 'path' => '/facilities/dentistry'],
        1647 => ['service' => 1, 'path' => '/facilities/dentistry'],
        1263 => ['service' => 2, 'path' => '/facilities/dentistry'],
        1271 => ['service' => 2, 'path' => '/facilities/dentistry'],
        297 => ['service' => 1, 'path' => '/facilities/pharmacy'],
        1527 => ['service' => 1, 'path' => '/facilities/pharmacy'],
        1646 => ['service' => 1, 'path' => '/facilities/pharmacy'],
        1272 => ['service' => 2, 'path' => '/facilities/pharmacy'],
        1640 => ['service' => 2, 'path' => '/facilities/pharmacy'],
        296 => ['service' => 1, 'path' => '/facilities/artificial-intelligence'],
        1528 => ['service' => 1, 'path' => '/facilities/artificial-intelligence'],
        1645 => ['service' => 1, 'path' => '/facilities/artificial-intelligence'],
        1265 => ['service' => 2, 'path' => '/facilities/artificial-intelligence'],
        1273 => ['service' => 2, 'path' => '/facilities/artificial-intelligence'],
        295 => ['service' => 1, 'path' => '/facilities/petroleum'],
        1529 => ['service' => 1, 'path' => '/facilities/petroleum'],
        1644 => ['service' => 1, 'path' => '/facilities/petroleum'],
        1266 => ['service' => 2, 'path' => '/facilities/petroleum'],
        1274 => ['service' => 2, 'path' => '/facilities/petroleum'],
        294 => ['service' => 1, 'path' => '/facilities/business-administration'],
        1530 => ['service' => 1, 'path' => '/facilities/business-administration'],
        1643 => ['service' => 1, 'path' => '/facilities/business-administration'],
        1264 => ['service' => 2, 'path' => '/facilities/business-administration'],
        1275 => ['service' => 2, 'path' => '/facilities/business-administration'],
    ];

    public function canResolve(NormalizedLegacyUrlDTO $url): bool
    {
        $sourceId = $this->sourceId($url);
        $mapping = $sourceId !== null ? (self::ROUTES[$sourceId] ?? null) : null;

        return $url->requestType === 'legacy_router'
            && $url->subsite->key === 'root'
            && $url->dir === 'items'
            && $url->page === 'show'
            && $mapping !== null
            && (int) ($url->service ?? 0) === $mapping['service'];
    }

    public function resolve(NormalizedLegacyUrlDTO $url): ?LegacyQueryResolutionDTO
    {
        if (! $this->canResolve($url)) {
            return null;
        }

        $sourceId = $this->sourceId($url);
        if ($sourceId === null) {
            return null;
        }

        return new LegacyQueryResolutionDTO(
            module: 'legacy_category_route',
            sourceTable: 'jx_categories',
            sourceId: $sourceId,
            targetUrl: '/'.$url->language->locale.self::ROUTES[$sourceId]['path'],
            statusCode: 301,
            confidence: 'high',
            notes: 'Resolved reviewed root navigation category by exact source ID, service, bilingual title, and legacy destination evidence.',
        );
    }

    private function sourceId(NormalizedLegacyUrlDTO $url): ?int
    {
        $value = $url->params['cat_id'] ?? $url->params['id'] ?? null;

        return is_numeric($value) ? (int) $value : null;
    }
}
