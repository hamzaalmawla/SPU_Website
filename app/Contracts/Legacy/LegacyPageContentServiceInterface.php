<?php

declare(strict_types=1);

namespace App\Contracts\Legacy;

use App\DTOs\Legacy\LegacyPageCoverageDTO;

/**
 * Every static page of the old site and its subsites (main site, the six faculty sites, the
 * research portal, hospital, dental clinics, alumni and activities sites), the new page that
 * should carry its text, and how much of that text the new page actually shows.
 */
interface LegacyPageContentServiceInterface
{
    /** Reads the pages out of the old site's SQL dump and writes them, cleaned and mapped, to JSON. */
    public function export(string $dumpPath, string $jsonPath): int;

    /**
     * Renders each mapped new page in Arabic and English and measures how much of the old page's
     * text it contains. Read-only.
     *
     * @return list<LegacyPageCoverageDTO>
     */
    public function coverage(string $jsonPath, ?string $site = null): array;

    /**
     * The old site's record lists (news, events, projects, research): visible items per list and how
     * many have a successful migration to the new site. Read-only.
     *
     * @return list<array{service: int, list: string, old_visible: int, migrated: int, into: list<string>}>
     */
    public function recordLists(string $jsonPath): array;
}
