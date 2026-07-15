<?php

declare(strict_types=1);

namespace App\DTOs\Content;

/** @return array<string, mixed> */
final readonly class ProfilePageDTO
{
    public function __construct(
        public string $locale,
        public string $direction,
        public string $sourceType,
        public string $slug,
        public string $name,
        public ?string $title,
        public ?string $position,
        public ?string $category,
        public ?string $facultyName,
        public ?string $departmentName,
        public ?string $email,
        public ?string $phone,
        public ?string $image,
        public ?string $bio,
        public ?string $quote,
        /** @var array<int, string>|null */
        public ?array $specializations,
        public ?string $officeLocation,
        public ?array $socialLinks,
        /** @var array<int, EducationDTO> */
        public array $educations,
        /** @var array<int, array<string, mixed>> */
        public array $publications,
        /** @var array<int, array<string, mixed>> */
        public array $councilMemberships,
        public ?string $cvUrl,
        public ?string $profileUrl,
        public string $seoTitle,
        public string $seoDescription,
        public ?string $seoImage,
        public string $path,
    ) {}
}
