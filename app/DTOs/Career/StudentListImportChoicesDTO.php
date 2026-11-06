<?php

declare(strict_types=1);

namespace App\DTOs\Career;

/** What the editor confirmed in the import preview. */
final readonly class StudentListImportChoicesDTO
{
    /**
     * @param  array<string, ?int>  $departments  specialization text => department id, null for none
     */
    public function __construct(
        public string $academicYear,
        public string $semester,
        public array $departments = [],
    ) {}
}
