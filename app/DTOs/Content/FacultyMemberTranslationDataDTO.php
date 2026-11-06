<?php

declare(strict_types=1);

namespace App\DTOs\Content;

final readonly class FacultyMemberTranslationDataDTO
{
    /** @param array<int, string>|null $specializations */
    public function __construct(
        public string $locale,
        public string $fullName,
        public ?string $title,
        public ?string $position,
        public ?string $bio,
        public ?array $specializations,
    ) {}
}
