<?php

declare(strict_types=1);

namespace App\DTOs;

final readonly class PartnershipDTO
{
    public function __construct(
        public int $id,
        public string $name,
        public string $category,
        public string $status,
        public string $establishedLabel,
        public string $description,
        public ?string $logo,
        public ?string $websiteUrl,
        public ?string $scope,
        public ?string $signedAt,
    ) {}
}
