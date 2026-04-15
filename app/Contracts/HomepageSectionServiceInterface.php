<?php

declare(strict_types=1);

namespace App\Contracts;

use App\DTOs\HomepageSectionDTO;
use Illuminate\Support\Collection;

/**
 * Defines management operations for the fixed 10-section homepage CMS page.
 * Shared shell concerns such as footer, navigation, and utility settings are
 * handled outside the homepage section set.
 */
interface HomepageSectionServiceInterface
{
    public const SECTION_KEYS = [
        'hero',
        'hero_stats',
        'achievements_highlights',
        'academic_faculties',
        'audience_paths',
        'university_news',
        'research_studies',
        'events_activities',
        'medical_facilities_services',
        'bottom_stats',
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
     * Retrieve the public homepage composite view payload.
     *
     * @return array<string, mixed>
     */
    public function getPublicHomepage(string $locale): array;

    /**
     * Update one homepage section using an approved key from self::SECTION_KEYS.
     *
     * @param  array<string, mixed>  $payload
     */
    public function updateSection(string $key, array $payload, string $locale): bool;

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
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, array<int, string>>
     */
    public function validateSectionPayload(string $key, array $payload, string $locale): array;
}
