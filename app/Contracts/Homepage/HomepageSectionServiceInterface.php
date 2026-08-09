<?php

declare(strict_types=1);

namespace App\Contracts\Homepage;

use App\DTOs\Homepage\HomepageDTO;
use App\DTOs\Homepage\HomepageSectionDataDTO;
use App\DTOs\Homepage\HomepageSectionDTO;
use App\DTOs\Shared\ValidationResultDTO;
use Illuminate\Support\Collection;

/**
 * Defines management operations for the fixed 11-section homepage CMS page.
 */
interface HomepageSectionServiceInterface
{
    public const SECTION_KEYS = [
        'hero',
        'hero_stats',
        'achievements_highlights',
        'academic_faculties',
        'choose_your_path',
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
