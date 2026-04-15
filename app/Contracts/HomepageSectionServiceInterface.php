<?php

declare(strict_types=1);

namespace App\Contracts;

use App\DTOs\HomepageDTO;
use App\DTOs\HomepageSectionDataDTO;
use App\DTOs\HomepageSectionDTO;
use App\DTOs\ValidationResultDTO;
use Illuminate\Support\Collection;

/**
 * Defines management operations for the fixed 10-section homepage CMS page.
 */
interface HomepageSectionServiceInterface
{
    public const SECTION_KEYS = [
        'hero',
        'hero_stats',
        'academic_faculties',
        'achievements_highlights',
        'university_news',
        'research_studies',
        'events_activities',
        'medical_facilities_services',
        'bottom_stats',
        'footer',
    ];

    /**
     * Retrieve editable homepage sections.
     *
     * @return Collection<int, HomepageSectionDTO>
     */
    public function getSections(): Collection;

    /**
     * Retrieve a single homepage section by approved key from self::SECTION_KEYS.
     */
    public function getSectionByKey(string $key): ?HomepageSectionDTO;

    /**
     * Retrieve the public homepage composite view-model.
     */
    public function getPublicHomepage(string $locale): HomepageDTO;

    /**
     * Update one homepage section using an approved key from self::SECTION_KEYS.
     */
    public function updateSection(string $key, HomepageSectionDataDTO $payload, string $locale): bool;

    /**
     * Enable or disable a homepage section without deleting it.
     */
    public function toggleSection(string $key, bool $enabled): bool;

    /**
     * Reorder homepage sections by key.
     *
     * @param  array<int, string>  $orderedKeys
     */
    public function reorderSections(array $orderedKeys): bool;

    /**
     * Validate a section payload before draft save or publish.
     */
    public function validateSectionPayload(string $key, HomepageSectionDataDTO $payload, string $locale): ValidationResultDTO;
}
