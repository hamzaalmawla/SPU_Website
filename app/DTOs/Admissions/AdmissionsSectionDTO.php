<?php

declare(strict_types=1);

namespace App\DTOs\Admissions;

final readonly class AdmissionsSectionDTO
{
    /** @param array<string, mixed> $section */
    public function __construct(
        public string $locale,
        public string $direction,
        public string $sectionSlug,
        public array $section,
        public string $seoTitle,
        public string $seoDescription,
        public string $seoImage,
    ) {}
}
