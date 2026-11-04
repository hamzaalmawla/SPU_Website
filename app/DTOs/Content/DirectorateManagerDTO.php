<?php

declare(strict_types=1);

namespace App\DTOs\Content;

/**
 * A directorate's manager, read from their public faculty profile.
 */
final readonly class DirectorateManagerDTO
{
    /** @param array<int, EducationDTO> $educations */
    public function __construct(
        public string $name,
        public ?string $title,
        public ?string $image,
        public ?string $email,
        public ?string $phone,
        public ?string $officeLocation,
        public array $educations,
        public string $profileUrl,
    ) {}
}
