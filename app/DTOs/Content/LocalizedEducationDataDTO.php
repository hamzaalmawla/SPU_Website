<?php

declare(strict_types=1);

namespace App\DTOs\Content;

final readonly class LocalizedEducationDataDTO
{
    public function __construct(
        public string $locale,
        public string $degree,
        public ?string $institution,
        public ?string $fieldOfStudy,
        public ?int $yearStart,
        public ?int $yearEnd,
        public ?string $description,
    ) {}
}
