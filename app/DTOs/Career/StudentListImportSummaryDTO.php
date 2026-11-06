<?php

declare(strict_types=1);

namespace App\DTOs\Career;

/** A past import, for the import history list. */
final readonly class StudentListImportSummaryDTO
{
    public function __construct(
        public int $id,
        public string $kind,
        public string $fileName,
        public ?string $decisionNumber,
        public string $academicYear,
        public string $semester,
        public int $created,
        public int $updated,
        public int $skipped,
        public ?string $importedBy,
        public string $importedAt,
        public ?string $undoneAt,
    ) {}
}
