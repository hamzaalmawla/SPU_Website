<?php

declare(strict_types=1);

namespace App\Services\Legacy\QueryResolvers;

use App\Contracts\Legacy\LegacyQueryModuleResolverInterface;
use App\DTOs\Legacy\LegacyQueryResolutionDTO;
use App\DTOs\Legacy\NormalizedLegacyUrlDTO;

final class LegacySubsiteHomeQueryResolver implements LegacyQueryModuleResolverInterface
{
    /** @var array<string, string> */
    private const TARGETS = [
        'root' => '',
        'med' => '/faculties/medicine',
        'dent' => '/faculties/dentistry',
        'pharm' => '/faculties/pharmacy',
        'info' => '/faculties/artificial-intelligence',
        'petrol' => '/faculties/petroleum',
        'admin' => '/faculties/business-administration',
        'research' => '/research',
        'hospital' => '/campus-life/hospital',
        'dent_clinic' => '/campus-life/dental',
        'clubs' => '/campus-life/clubs-activities',
    ];

    public function canResolve(NormalizedLegacyUrlDTO $url): bool
    {
        return $url->requestType === 'legacy_router'
            && $url->dir === null
            && ($url->page === null || $url->page === 'home')
            && array_key_exists($url->subsite->key, self::TARGETS);
    }

    public function resolve(NormalizedLegacyUrlDTO $url): ?LegacyQueryResolutionDTO
    {
        if (! $this->canResolve($url)) {
            return null;
        }

        return new LegacyQueryResolutionDTO(
            module: 'legacy_subsite_home',
            sourceTable: 'legacy_router',
            sourceId: $url->subsite->siteId,
            targetUrl: '/'.$url->language->locale.self::TARGETS[$url->subsite->key],
            statusCode: 301,
            confidence: 'high',
            notes: 'Resolved audited legacy subsite home to its canonical Laravel landing page.',
        );
    }
}
