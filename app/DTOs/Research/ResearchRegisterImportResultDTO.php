<?php

declare(strict_types=1);

namespace App\DTOs\Research;

final readonly class ResearchRegisterImportResultDTO
{
    /** @param array<string, int> $papersByFaculty Faculty public slug ('' for none) to new papers */
    public function __construct(
        public bool $written,
        public int $papersInFile,
        public int $alreadyOnSite,
        public int $alreadyImported,
        public int $created,
        public int $hiddenWithoutDate,
        public int $researchersInFile,
        public int $researchersCreated,
        public int $researchersLinkedToStaff,
        public array $papersByFaculty,
        public int $updated = 0,
        public int $linksAdded = 0,
        public int $facultiesAdded = 0,
        public int $existingPapersGivenResearchers = 0,
        public int $authorLinksAdded = 0,
    ) {}
}
