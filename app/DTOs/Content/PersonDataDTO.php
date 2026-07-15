<?php

declare(strict_types=1);

namespace App\DTOs\Content;

final readonly class PersonDataDTO
{
    /**
     * @param  array<int, PersonTranslationDataDTO>  $translations
     * @param  array<int, EducationDataDTO>  $educations
     * @param  array<string, string>|null  $socialLinks
     */
    public function __construct(
        public ?int $id,
        public string $slug,
        public string $category,
        public ?string $title,
        public ?string $position,
        public ?string $facultyScopeSlug,
        public ?string $image,
        public ?string $email,
        public ?string $phone,
        public ?string $officeLocation,
        public ?string $profileUrl,
        public ?array $socialLinks,
        public int $sortOrder,
        public bool $isEnabled,
        public array $translations,
        public array $educations,
    ) {}
}
