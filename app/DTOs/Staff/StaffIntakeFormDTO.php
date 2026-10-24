<?php

declare(strict_types=1);

namespace App\DTOs\Staff;

/**
 * What the intake form shows: the profile being edited (or a blank one), with
 * the faculty's departments to pick from.
 */
final readonly class StaffIntakeFormDTO
{
    /**
     * @param  array<string, mixed>  $fields  Flat form values, see StaffIntakeService::FIELDS
     * @param  list<array<string, mixed>>  $educations
     * @param  array<int, string>  $departments
     */
    public function __construct(
        public string $ref,
        public string $mode,
        public string $facultyName,
        public array $fields,
        public array $educations,
        public array $departments,
        public ?string $photoUrl,
        public ?string $cvUrl,
        public ?string $relatedFacultyName,
    ) {}
}
