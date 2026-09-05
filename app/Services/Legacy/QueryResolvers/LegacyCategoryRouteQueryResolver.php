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
        299 => ['service' => 1, 'path' => '/faculties/medicine'],
        1525 => ['service' => 1, 'path' => '/faculties/medicine'],
        1641 => ['service' => 1, 'path' => '/faculties/medicine'],
        1261 => ['service' => 2, 'path' => '/faculties/medicine'],
        1270 => ['service' => 2, 'path' => '/faculties/medicine'],
        298 => ['service' => 1, 'path' => '/faculties/dentistry'],
        1526 => ['service' => 1, 'path' => '/faculties/dentistry'],
        1647 => ['service' => 1, 'path' => '/faculties/dentistry'],
        1263 => ['service' => 2, 'path' => '/faculties/dentistry'],
        1271 => ['service' => 2, 'path' => '/faculties/dentistry'],
        297 => ['service' => 1, 'path' => '/faculties/pharmacy'],
        1527 => ['service' => 1, 'path' => '/faculties/pharmacy'],
        1646 => ['service' => 1, 'path' => '/faculties/pharmacy'],
        1272 => ['service' => 2, 'path' => '/faculties/pharmacy'],
        1640 => ['service' => 2, 'path' => '/faculties/pharmacy'],
        296 => ['service' => 1, 'path' => '/faculties/artificial-intelligence'],
        1528 => ['service' => 1, 'path' => '/faculties/artificial-intelligence'],
        1645 => ['service' => 1, 'path' => '/faculties/artificial-intelligence'],
        1265 => ['service' => 2, 'path' => '/faculties/artificial-intelligence'],
        1273 => ['service' => 2, 'path' => '/faculties/artificial-intelligence'],
        295 => ['service' => 1, 'path' => '/faculties/petroleum'],
        1529 => ['service' => 1, 'path' => '/faculties/petroleum'],
        1644 => ['service' => 1, 'path' => '/faculties/petroleum'],
        1266 => ['service' => 2, 'path' => '/faculties/petroleum'],
        1274 => ['service' => 2, 'path' => '/faculties/petroleum'],
        294 => ['service' => 1, 'path' => '/faculties/business-administration'],
        1530 => ['service' => 1, 'path' => '/faculties/business-administration'],
        1643 => ['service' => 1, 'path' => '/faculties/business-administration'],
        1264 => ['service' => 2, 'path' => '/faculties/business-administration'],
        1275 => ['service' => 2, 'path' => '/faculties/business-administration'],
    ];

    public function canResolve(NormalizedLegacyUrlDTO $url): bool
    {
        $mapping = $this->mapping($url);

        return $url->requestType === 'legacy_router'
            && $url->subsite->key === 'root'
            && $url->dir === 'items'
            && $url->page === 'show'
            && $mapping !== null
            && (int) ($url->service ?? 0) === $mapping['service'];
    }

    /**
     * Reviewed hand-mapped routes win; the generated allow-list in
     * config/legacy_category_routes.php fills in the rest.
     *
     * That file lists only ids that were visible, non-link rows with a real title
     * on the old site, so an unknown, hidden or retired cat_id resolves to
     * nothing here and still returns a real 404.
     *
     * @return array{service: int, path: string}|null
     */
    private function mapping(NormalizedLegacyUrlDTO $url): ?array
    {
        $sourceId = $this->sourceId($url);

        if ($sourceId === null) {
            return null;
        }

        if (isset(self::ROUTES[$sourceId])) {
            return self::ROUTES[$sourceId];
        }

        /** @var array<int, array{service: int, path: string}> $generated */
        $generated = config('legacy_category_routes', []);
        $entry = $generated[$sourceId] ?? null;

        return is_array($entry) && isset($entry['service'], $entry['path']) ? $entry : null;
    }

    public function resolve(NormalizedLegacyUrlDTO $url): ?LegacyQueryResolutionDTO
    {
        if (! $this->canResolve($url)) {
            return null;
        }

        $sourceId = $this->sourceId($url);
        $mapping = $this->mapping($url);

        if ($sourceId === null || $mapping === null) {
            return null;
        }

        return new LegacyQueryResolutionDTO(
            module: 'legacy_category_route',
            sourceTable: 'jx_categories',
            sourceId: $sourceId,
            targetUrl: '/'.$url->language->locale.$mapping['path'],
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
