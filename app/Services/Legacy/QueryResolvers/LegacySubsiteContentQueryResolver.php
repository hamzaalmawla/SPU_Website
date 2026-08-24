<?php

declare(strict_types=1);

namespace App\Services\Legacy\QueryResolvers;

use App\Contracts\Legacy\LegacyQueryModuleResolverInterface;
use App\DTOs\Legacy\LegacyQueryResolutionDTO;
use App\DTOs\Legacy\NormalizedLegacyUrlDTO;

/**
 * Resolves legacy faculty/subsite detail URLs to the equivalent current section.
 *
 * The old site encoded both the subsite and the content kind in one number:
 *
 *     service_type = subsite_index * 10 + kind
 *
 * so /pharm/index.php?dir=items&page=show&service=42&cat_id=6058 is subsite 4
 * (Pharmacy), kind 2 (course material). That is verified against the old
 * database, not inferred: every service value observed in the old sitemap falls
 * inside its subsite's decade, and the jx_categories rows carrying each
 * service_type were sampled to confirm what the kind actually holds.
 *
 * These are documented equivalent redirects, not content matches: the old items
 * themselves are not imported, so each one resolves to the current section that
 * owns that material. That satisfies "closest proven public equivalent" without
 * inventing a per-item destination that does not exist.
 *
 * Deliberately not covered here:
 *  - subsite "root", which keeps its precise per-record resolvers;
 *  - "members", which stays a private archive;
 *  - unknown alumni URLs and alumni record/detail URLs, which must keep
 *    returning a real 404. The dedicated alumni list resolver handles only
 *    reviewed graduated-students list signatures.
 */
final class LegacySubsiteContentQueryResolver implements LegacyQueryModuleResolverInterface
{
    /**
     * Subsite key => faculty slug on the new site.
     *
     * @var array<string, string>
     */
    private const FACULTIES = [
        'med' => ['index' => 2, 'slug' => 'medicine'],
        'dent' => ['index' => 3, 'slug' => 'dentistry'],
        'pharm' => ['index' => 4, 'slug' => 'pharmacy'],
        'info' => ['index' => 5, 'slug' => 'artificial-intelligence'],
        'petrol' => ['index' => 6, 'slug' => 'petroleum'],
        'admin' => ['index' => 7, 'slug' => 'business-administration'],
    ];

    /**
     * Content kind (the units digit of service_type) => faculty subpath.
     *
     * 1  faculty identity pages   — "الرؤية والأهداف", "كلمة عميد الكلية"
     * 2  course material          — "توصيف المقرر", "المحاضرات", exam programmes
     * 3  faculty news and events  — seminars, invitations, ceremonies
     * 4  research and projects    — graduation projects, studies
     * 5  research announcements   — "مناقشة مشروع تخرج", published-paper notices
     * 6  timetables              — "الجداول الدراسية", "برامج الإمتحانات"
     * 7  scientific publications  — journal papers
     *
     * @var array<int, string>
     */
    private const KIND_PATHS = [
        1 => '',
        2 => '/study-plan',
        3 => '',
        4 => '/research',
        5 => '/research',
        6 => '/study-plan',
        7 => '/research',
    ];

    /**
     * Non-faculty subsites resolve to a single section regardless of kind.
     *
     * @var array<string, string>
     */
    private const SECTIONS = [
        // The research landing is retired until SPU publishes it, so the old
        // research subsite resolves to the archive that actually holds its
        // material - the migrated legacy publications.
        'research' => ['index' => 8, 'path' => '/research/publications'],
        'hospital' => ['index' => 9, 'path' => '/campus-life/hospital'],
        'dent_clinic' => ['index' => 10, 'path' => '/campus-life/dental'],
        'clubs' => ['index' => 12, 'path' => '/campus-life/clubs-activities'],
    ];

    /**
     * Legacy "dir" values that address people rather than content.
     *
     * @var array<string, string>
     */
    private const PEOPLE_DIRS = [
        'councils' => '/members',
        'member_items' => '/research',
    ];

    /**
     * Root-subsite legacy directories that address people or media rather than
     * content records. These are safe to place at section level because the
     * destination is a directory/gallery index, not a stand-in for a specific
     * article. Root "items" is deliberately absent — see targetPath().
     *
     * @var array<string, string>
     */
    private const ROOT_DIRS = [
        'councils' => '/about/directorates/staff',
        'photos' => '/news/gallery',
    ];

    public function canResolve(NormalizedLegacyUrlDTO $url): bool
    {
        return $url->requestType === 'legacy_router'
            && in_array($url->dir, ['items', 'councils', 'member_items', 'photos'], true)
            // "show" is a detail URL and "list" is the section index; both land on
            // the same current section, which is the index page either way.
            && in_array($url->page, ['show', 'list'], true)
            && $this->targetPath($url) !== null;
    }

    public function resolve(NormalizedLegacyUrlDTO $url): ?LegacyQueryResolutionDTO
    {
        $path = $this->targetPath($url);

        if ($path === null) {
            return null;
        }

        return new LegacyQueryResolutionDTO(
            module: 'legacy_subsite_content',
            sourceTable: 'jx_categories',
            sourceId: $this->sourceId($url) ?? $url->subsite->siteId,
            targetUrl: '/'.$url->language->locale.$path,
            statusCode: 301,
            confidence: 'medium',
            notes: 'Resolved a legacy faculty/subsite detail URL to the current section that owns that material, keyed on the subsite and the service_type kind digit.',
        );
    }

    private function targetPath(NormalizedLegacyUrlDTO $url): ?string
    {
        $subsite = $url->subsite->key;

        // The old /members/ area held staff records and their publications. The
        // records themselves stay a private archive and are never resolved to an
        // imported row; the URL still reaches the public section that replaced
        // it, which exposes nothing that is not already public.
        if ($subsite === 'members') {
            return match ($url->dir) {
                'councils' => '/about/directorates/staff',
                'member_items' => '/research/publications',
                default => null,
            };
        }

        // "root" keeps its precise per-record resolvers (news, reviewed category,
        // functional route). No section-level catch-all is offered there on
        // purpose: a root URL that those resolvers cannot place was hidden, empty
        // or unreviewed on the old site, and must stay a 404 so it is logged for
        // triage rather than absorbed by a generic page.
        if ($subsite === 'root') {
            return self::ROOT_DIRS[$url->dir] ?? null;
        }

        if (isset(self::SECTIONS[$subsite])) {
            if ($url->dir === 'photos') {
                return self::SECTIONS[$subsite]['path'];
            }

            return $url->dir === 'items' && $this->serviceMatchesSubsite($url, self::SECTIONS[$subsite]['index'])
                ? self::SECTIONS[$subsite]['path']
                : null;
        }

        if (! isset(self::FACULTIES[$subsite])) {
            return null;
        }

        $faculty = '/facilities/'.self::FACULTIES[$subsite]['slug'];

        // Faculty staff, council and gallery listings are addressed by dir, not
        // by service.
        if ($url->dir === 'photos') {
            return $faculty;
        }

        if ($url->dir !== 'items') {
            $peoplePath = self::PEOPLE_DIRS[$url->dir] ?? null;

            return $peoplePath !== null ? $faculty.$peoplePath : null;
        }

        if (! $this->serviceMatchesSubsite($url, self::FACULTIES[$subsite]['index'])) {
            return null;
        }

        $subpath = self::KIND_PATHS[((int) $url->service) % 10] ?? null;

        return $subpath !== null ? $faculty.$subpath : null;
    }

    /**
     * A legacy service number belongs to exactly one subsite: its tens digit is
     * the subsite index. /med/index.php?service=3 is therefore a mismatch —
     * service 3 is a root service — and must not resolve just because its units
     * digit happens to name a valid kind.
     */
    private function serviceMatchesSubsite(NormalizedLegacyUrlDTO $url, int $subsiteIndex): bool
    {
        $service = $url->service ?? null;

        return is_numeric($service) && intdiv((int) $service, 10) === $subsiteIndex;
    }

    private function sourceId(NormalizedLegacyUrlDTO $url): ?int
    {
        $value = $url->params['cat_id'] ?? $url->params['id'] ?? null;

        return is_numeric($value) && (int) $value > 0 ? (int) $value : null;
    }
}
