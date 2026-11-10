<?php

declare(strict_types=1);

namespace App\DTOs\Content;

final readonly class EducationDataDTO
{
    /** @param array<int, LocalizedEducationDataDTO> $translations */
    public function __construct(
        public ?int $id,
        public int $sortOrder,
        public bool $isEnabled,
        public array $translations,
    ) {}
}
