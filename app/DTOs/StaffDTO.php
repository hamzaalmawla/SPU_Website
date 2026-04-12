<?php

declare(strict_types=1);

namespace App\DTOs;

/**
 * Data transfer object for staff entities.
 */
final readonly class StaffDTO
{
    /**
     * Create a new staff data transfer object.
     */
    public function __construct(
        public int $id,
        public string $slug,
        public string $nameAr,
        public string $nameEn,
        public string $titleAr,
        public string $titleEn,
        public string $email,
        public int $facultyId,
        public int $departmentId,
        public ?string $orcidId,
        public ?string $googleScholarUrl,
        public ?string $scopusId,
        public bool $isActive,
    ) {}
}
