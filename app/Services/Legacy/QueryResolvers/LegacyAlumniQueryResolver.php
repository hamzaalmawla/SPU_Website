<?php

declare(strict_types=1);

namespace App\Services\Legacy\QueryResolvers;

use App\Contracts\Legacy\LegacyQueryModuleResolverInterface;
use App\DTOs\Legacy\LegacyQueryResolutionDTO;
use App\DTOs\Legacy\NormalizedLegacyUrlDTO;

/**
 * Resolves the old graduate lists - the alumni subsite's global list and each
 * faculty subsite's own - to the alumni directory filtered by faculty.
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

    /**
     * Faculty subsites published their own graduate lists under the same
     * router, and their subsite id is the faculty code the alumni subsite
     * carried in `d`. A faculty list may omit `d`; when present it must agree.
     *
     * @var array<string, int>
     */
    private const FACULTY_SUBSITES = [
        'med' => 2,
        'dent' => 3,
        'pharm' => 4,
        'info' => 5,
        'petrol' => 6,
        'admin' => 7,
    ];

    /**
     * Paging and display parameters the old list printed into every link.
     * They change which slice of the same list was shown, never what it was.
     */
    private const LIST_STATE_PARAMETERS = ['section', 'dyear', 'act', 'First', 'Last', 'CurrentPage', 'nt', 'src'];

    public function canResolve(NormalizedLegacyUrlDTO $url): bool
    {
        return $url->requestType === 'legacy_router'
            && $url->dir === 'graduated_students'
            && $url->page === 'list'
            && in_array($url->params['ex'] ?? '2', ['2'], true)
            && $this->facultyCode($url) !== null
            && $this->hasReviewedParameters($url);
    }

    private function facultyCode(NormalizedLegacyUrlDTO $url): ?int
    {
        $path = mb_strtolower($url->path);
        $d = $url->params['d'] ?? null;
        $code = $d !== null && preg_match('/^[2-7]$/', (string) $d) === 1 ? (int) $d : null;

        if ($path === '/alumni/index.php' && $url->subsite->key === 'alumni') {
            return $code;
        }

        $subsiteCode = self::FACULTY_SUBSITES[$url->subsite->key] ?? null;

        if ($subsiteCode === null || $path !== '/'.$url->subsite->key.'/index.php') {
            return null;
        }

        if ($d !== null && $code !== $subsiteCode) {
            return null;
        }

        return $subsiteCode;
    }

    private function hasReviewedParameters(NormalizedLegacyUrlDTO $url): bool
    {
        parse_str((string) $url->queryString, $parameters);

        foreach ($parameters as $value) {
            if (is_array($value)) {
                return false;
            }
        }

        $allowed = ['page', 'ex', 'dir', 'lang', 'd', ...self::LIST_STATE_PARAMETERS];

        return array_diff(array_keys($parameters), $allowed) === []
            && isset($parameters['page'], $parameters['dir'])
            && (! isset($parameters['lang']) || in_array((string) $parameters['lang'], ['1', '2'], true));
    }

    public function resolve(NormalizedLegacyUrlDTO $url): ?LegacyQueryResolutionDTO
    {
        if (! $this->canResolve($url)) {
            return null;
        }

        $code = (int) $this->facultyCode($url);
        $facultySlug = self::FACULTY_SLUGS[$code];
        $year = (string) ($url->params['dyear'] ?? '');

        // The directory ignores a year it holds no graduates for, so an old
        // year filter can narrow the list but never empty it.
        $query = 'faculty='.rawurlencode($facultySlug)
            .(preg_match('/^(19|20)\d{2}$/', $year) === 1 ? '&year='.$year : '');

        return new LegacyQueryResolutionDTO(
            module: 'legacy_alumni_directory',
            sourceTable: 'legacy_router',
            sourceId: $code,
            targetUrl: '/'.$url->language->locale.'/alumni?'.$query,
            statusCode: 301,
            confidence: 'high',
            notes: 'Resolved a reviewed global graduated-students list signature. The d value is a faculty code; no alumni record URL is inferred.',
        );
    }
}
