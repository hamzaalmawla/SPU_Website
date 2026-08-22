<?php

declare(strict_types=1);

namespace App\Services\Legacy\QueryResolvers;

use App\Contracts\Legacy\LegacyQueryModuleResolverInterface;
use App\DTOs\Legacy\LegacyQueryResolutionDTO;
use App\DTOs\Legacy\NormalizedLegacyUrlDTO;

/**
 * Resolves only the reviewed global alumni list signatures.
 *
 * The legacy `d` value is a verified faculty code, not an alumni record ID.
 * It may therefore narrow the directory to a faculty, but it must never be
 * used to manufacture a per-record destination.
 */
final class LegacyAlumniQueryResolver implements LegacyQueryModuleResolverInterface
{
    /** @var array<int, string> */
    private const FACULTY_SLUGS = [
        2 => 'medicine',
        3 => 'dentistry',
        4 => 'pharmacy',
        5 => 'artificial-intelligence',
        6 => 'petroleum',
        7 => 'business-administration',
    ];

    public function canResolve(NormalizedLegacyUrlDTO $url): bool
    {
        if ($url->requestType !== 'legacy_router'
            || $url->subsite->key !== 'alumni'
            || mb_strtolower($url->path) !== '/alumni/index.php'
            || $url->dir !== 'graduated_students'
            || $url->page !== 'list'
            || ($url->params['ex'] ?? null) !== '2'
            || ! isset($url->params['d'])
            || preg_match('/^[2-7]$/', (string) $url->params['d']) !== 1
            || ! isset(self::FACULTY_SLUGS[(int) $url->params['d']])
            || ! $this->hasReviewedParameters($url)) {
            return false;
        }

        return array_diff(array_keys($url->params), ['page', 'ex', 'dir', 'lang', 'd']) === [];
    }

    private function hasReviewedParameters(NormalizedLegacyUrlDTO $url): bool
    {
        parse_str((string) $url->queryString, $parameters);

        foreach ($parameters as $value) {
            if (is_array($value)) {
                return false;
            }
        }

        return array_diff(array_keys($parameters), ['page', 'ex', 'dir', 'lang', 'd']) === []
            && isset($parameters['page'], $parameters['ex'], $parameters['dir'], $parameters['d'])
            && preg_match('/^[2-7]$/', (string) $parameters['d']) === 1
            && (! isset($parameters['lang']) || in_array((string) $parameters['lang'], ['1', '2'], true));
    }

    public function resolve(NormalizedLegacyUrlDTO $url): ?LegacyQueryResolutionDTO
    {
        if (! $this->canResolve($url)) {
            return null;
        }

        $facultySlug = self::FACULTY_SLUGS[(int) $url->params['d']];

        return new LegacyQueryResolutionDTO(
            module: 'legacy_alumni_directory',
            sourceTable: 'legacy_router',
            sourceId: (int) $url->params['d'],
            targetUrl: '/'.$url->language->locale.'/alumni?faculty='.rawurlencode($facultySlug),
            statusCode: 301,
            confidence: 'high',
            notes: 'Resolved a reviewed global graduated-students list signature. The d value is a faculty code; no alumni record URL is inferred.',
        );
    }
}
