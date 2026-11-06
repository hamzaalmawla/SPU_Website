<?php

declare(strict_types=1);

namespace App\DTOs\Faculty;

final readonly class FacultyStructureImportResultDTO
{
    /**
     * @param  array<string, array{departments: int, departmentsCreated: int, departmentsDisabled: int, labs: int|null, labsCreated: int, labsDisabled: int, labsCardHidden: bool, pages: list<string>}>  $faculties
     * @param  list<string>  $warnings
     */
    public function __construct(
        public bool $written,
        public array $faculties,
        public array $warnings,
    ) {}
}
