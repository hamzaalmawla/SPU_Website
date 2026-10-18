<?php

declare(strict_types=1);

namespace App\DTOs\Legacy;

final readonly class LegacyFacultyResearchImportResultDTO
{
    /**
     * @param  array<string, int>  $entriesByFaculty  Visible portal entries per faculty slug ('' = no faculty).
     * @param  array<string, int>  $publicPublicationsByFaculty  Public publications per faculty slug after the run.
     */
    public function __construct(
        public bool $written,
        public int $scannedEntries,
        public int $visibleEntries,
        public int $linkedToExisting,
        public int $created,
        public int $updated,
        public int $duplicatesInLists,
        public int $facultyFromOwner,
        public int $withoutFaculty,
        public int $imagesRepaired,
        public int $publicPublications,
        public array $entriesByFaculty,
        public array $publicPublicationsByFaculty,
    ) {}
}
