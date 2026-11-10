<?php

declare(strict_types=1);

namespace App\DTOs\CampusLife;

final readonly class CampusLifeJobDTO
{
    public function __construct(
        public string $id,
        public string $slug,
        public string $title,
        public string $status,
        public string $postedDate,
        public ?string $closeDate,
        public bool $applicationEligible,
    ) {}
}
