<?php

declare(strict_types=1);

namespace App\Contracts\Legacy;

use App\DTOs\Legacy\LegacyFacultyResearchImportResultDTO;

/**
 * The old site's research portal ("الأبحاث والمنشورات / وفقاً للكليات") listed research per faculty
 * from the jx_categories lists of services 27-77, plus "SPU students publications" (service 89).
 * The first research import only read staff-profile publications (jx_member_categories), so
 * research missing from staff pages never arrived and almost no publication had a faculty.
 */
interface LegacyFacultyResearchImportServiceInterface
{
    /**
     * Reads the faculty research lists and staff owners out of a phpMyAdmin SQL dump of the old
     * site and writes them to a JSON file the importer reads. Returns the number of entries.
     */
    public function export(string $dumpPath, string $jsonPath): int;

    /**
     * Dry run unless $write. Writing requires the approval token. Each visible faculty research
     * entry either gives its faculty to the matching publication already imported (same title) or
     * becomes a new public publication of that faculty; publications still without a faculty get
     * their legacy owner's faculty. Running it again updates the same rows.
     */
    public function import(string $jsonPath, bool $write = false, ?string $approval = null): LegacyFacultyResearchImportResultDTO;
}
