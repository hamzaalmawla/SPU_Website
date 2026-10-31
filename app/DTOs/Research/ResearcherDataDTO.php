<?php

declare(strict_types=1);

namespace App\DTOs\Research;

final readonly class ResearcherDataDTO
{
    /** @param list<string> $expertise */
    public function __construct(
        public string $fullName,
        public ?string $email = null,
        public ?string $affiliation = null,
        public ?int $facultyId = null,
        public ?int $facultyMemberId = null,
        public ?string $orcidUrl = null,
        public ?string $scholarUrl = null,
        public ?string $scopusUrl = null,
        public array $expertise = [],
        public bool $isEnabled = true,
        public ?string $notes = null,
    ) {}
}
