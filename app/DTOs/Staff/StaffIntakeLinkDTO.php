<?php

declare(strict_types=1);

namespace App\DTOs\Staff;

final readonly class StaffIntakeLinkDTO
{
    public function __construct(
        public int $id,
        public int $facultyId,
        public string $facultyNameAr,
        public string $facultyNameEn,
        public ?string $label,
        public ?string $expiresAt,
        public ?string $revokedAt,
        public ?int $maxSubmissions,
        public int $submissionsCount,
        public bool $active,
        public ?int $createdBy,
    ) {}
}
