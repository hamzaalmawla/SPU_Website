<?php

declare(strict_types=1);

namespace App\DTOs\Faculty;

final readonly class FacultyStudyPlanImportResultDTO
{
    /**
     * @param  array<string, int>  $coursesByDepartment
     * @param  array<string, list<string>>  $errors
     */
    public function __construct(
        public string $targetKey,
        public int $departmentCount,
        public int $courseCount,
        public array $coursesByDepartment,
        public bool $written,
        public ?int $draftVersion,
        public array $errors,
        public bool $valid,
        public bool $skipped = false,
        public ?string $skipReason = null,
    ) {}
}
