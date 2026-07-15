<?php

declare(strict_types=1);

namespace App\DTOs\Content;

final readonly class FacultyMemberDataDTO
{
    /**
     * @param  array<int, FacultyMemberTranslationDataDTO>  $translations
     * @param  array<int, EducationDataDTO>  $educations
     * @param  array<string, string>|null  $socialLinks
     */
    public function __construct(
        public ?int $id,
        public string $slug,
        public ?int $facultyId,
        public ?int $departmentId,
        public ?string $email,
        public ?string $phone,
        public ?string $officeLocation,
        public ?int $photoMediaId,
        public ?int $cvMediaId,
        public ?array $socialLinks,
        public int $sortOrder,
        public bool $isEnabled,
        public array $translations,
        public array $educations,
    ) {}
}
