<?php

declare(strict_types=1);

namespace App\DTOs\Research;

final readonly class ResearcherDataDTO
{
    public function __construct(
        public string $fullName,
        public ?string $email = null,
        public ?string $affiliation = null,
        public ?int $facultyId = null,
        public ?int $facultyMemberId = null,
    ) {}
}
