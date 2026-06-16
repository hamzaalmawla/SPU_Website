<?php

declare(strict_types=1);

namespace App\Support;

use App\Contracts\Homepage\HomepageSectionServiceInterface;
use App\DTOs\Homepage\HomepageSectionDataDTO;
use App\DTOs\Homepage\HomepageSectionDTO;
use App\DTOs\Homepage\HomepageSectionTranslationDTO;

/**
 * Deterministic mapper for homepage draft section snapshots.
 *
 * This helper is intentionally persistence-free: services still own
 * authorization, publish decisions, cache invalidation, and audit logging.
 */
final class HomepageDraftSectionMapper
{
    /**
     * @param  array<int, HomepageSectionDTO>  $providedSections
     * @param  iterable<int, HomepageSectionDTO>  $currentSections
     * @return array<int, HomepageSectionDTO>
     */
    public static function normalizeForEditableDraft(array $providedSections, iterable $currentSections): array
    {
        $currentByKey = self::sectionsByKey($currentSections);
        $providedByKey = self::sectionsByKey($providedSections);
        $normalized = [];

        foreach (HomepageSectionServiceInterface::SECTION_KEYS as $index => $key) {
            $fallback = $currentByKey[$key] ?? self::emptySection($key, $index + 1);
            $provided = $providedByKey[$key] ?? null;

            $normalized[] = $provided instanceof HomepageSectionDTO
                ? self::mergeSection($provided, $fallback, $index + 1)
                : $fallback;
        }

        return array_values($normalized);
    }

    /**
     * @param  array<string, mixed>  $draftPayload
     * @param  iterable<int, HomepageSectionDTO>  $currentSections
     * @return array<int, HomepageSectionDTO>
     */
    public static function sectionsFromStoredDraft(array $draftPayload, iterable $currentSections): array
    {
        $draftHomepage = is_array($draftPayload['homepage'] ?? null)
            ? $draftPayload['homepage']
            : $draftPayload;
        $sections = is_array($draftHomepage['sections'] ?? null) ? $draftHomepage['sections'] : [];
        $currentByKey = self::sectionsByKey($currentSections);
        $normalized = [];

        foreach (HomepageSectionServiceInterface::SECTION_KEYS as $index => $key) {
            $current = $currentByKey[$key] ?? null;

            if (! $current instanceof HomepageSectionDTO) {
                continue;
            }

            $sectionPayload = self::firstSectionPayloadByKey($sections, $key);

            $normalized[] = is_array($sectionPayload)
                ? self::sectionFromStoredArray($sectionPayload, $current, $index + 1)
                : $current;
        }

        return array_values($normalized);
    }

    /**
     * @param  array<int, mixed>  $sections
     * @param  iterable<int, HomepageSectionDTO>  $fallbackSections
     * @return array<int, HomepageSectionDTO>
     */
    public static function previewSectionsFromDraft(array $sections, string $locale, iterable $fallbackSections): array
    {
        $fallbackByKey = self::sectionsByKey($fallbackSections);
        $approvedSections = [];

        foreach ($sections as $section) {
            if (! is_array($section)) {
                continue;
            }

            $key = is_string($section['key'] ?? null) ? $section['key'] : null;

            if ($key === null || ! in_array($key, HomepageSectionServiceInterface::SECTION_KEYS, true)) {
                continue;
            }

            $fallback = $fallbackByKey[$key] ?? null;

            $approvedSections[$key] = self::previewSectionFromDraft(
                $section,
                $locale,
                $fallback instanceof HomepageSectionDTO ? $fallback : null,
            );
        }

        return array_values(array_filter(array_map(
            static fn (string $key): ?HomepageSectionDTO => $approvedSections[$key] ?? null,
            HomepageSectionServiceInterface::SECTION_KEYS,
        )));
    }

    /**
     * @param  iterable<int, HomepageSectionDTO>  $sections
     * @return array<string, HomepageSectionDTO>
     */
    private static function sectionsByKey(iterable $sections): array
    {
        $byKey = [];

        foreach ($sections as $section) {
            if ($section instanceof HomepageSectionDTO) {
                $byKey[$section->key] = $section;
            }
        }

        return $byKey;
    }

    /**
     * @param  array<int, mixed>  $sections
     * @return array<string, mixed>|null
     */
    private static function firstSectionPayloadByKey(array $sections, string $key): ?array
    {
        foreach ($sections as $section) {
            if (is_array($section) && ($section['key'] ?? null) === $key) {
                return $section;
            }
        }

        return null;
    }

    private static function mergeSection(HomepageSectionDTO $provided, HomepageSectionDTO $fallback, int $defaultSortOrder): HomepageSectionDTO
    {
        return new HomepageSectionDTO(
            id: $provided->id > 0 ? $provided->id : $fallback->id,
            key: $fallback->key,
            sortOrder: $provided->sortOrder > 0 ? $provided->sortOrder : ($fallback->sortOrder > 0 ? $fallback->sortOrder : $defaultSortOrder),
            isEnabled: $provided->isEnabled,
            payload: $provided->payload,
            arabicTranslation: $provided->arabicTranslation,
            englishTranslation: $provided->englishTranslation,
            arabicPayload: $provided->arabicPayload ?? $fallback->arabicPayload ?? $fallback->payload,
            englishPayload: $provided->englishPayload ?? $fallback->englishPayload ?? $fallback->payload,
        );
    }

    private static function emptySection(string $key, int $sortOrder): HomepageSectionDTO
    {
        $payload = new HomepageSectionDataDTO;

        return new HomepageSectionDTO(
            id: 0,
            key: $key,
            sortOrder: $sortOrder,
            isEnabled: true,
            payload: $payload,
            arabicTranslation: new HomepageSectionTranslationDTO(locale: 'ar'),
            englishTranslation: new HomepageSectionTranslationDTO(locale: 'en'),
            arabicPayload: $payload,
            englishPayload: $payload,
        );
    }

    /** @param array<string, mixed> $payload */
    private static function sectionFromStoredArray(array $payload, HomepageSectionDTO $fallback, int $defaultSortOrder): HomepageSectionDTO
    {
        $arabicPayload = is_array($payload['arabicPayload'] ?? null)
            ? self::sectionPayloadFromArray($payload['arabicPayload'])
            : ($fallback->arabicPayload ?? $fallback->payload);
        $englishPayload = is_array($payload['englishPayload'] ?? null)
            ? self::sectionPayloadFromArray($payload['englishPayload'])
            : ($fallback->englishPayload ?? $fallback->payload);

        return new HomepageSectionDTO(
            id: is_int($payload['id'] ?? null) ? $payload['id'] : $fallback->id,
            key: is_string($payload['key'] ?? null) ? $payload['key'] : $fallback->key,
            sortOrder: is_int($payload['sortOrder'] ?? null) ? $payload['sortOrder'] : $defaultSortOrder,
            isEnabled: is_bool($payload['isEnabled'] ?? null) ? $payload['isEnabled'] : $fallback->isEnabled,
            payload: $arabicPayload,
            arabicTranslation: self::translationFromStoredArray(is_array($payload['arabicTranslation'] ?? null) ? $payload['arabicTranslation'] : [], 'ar', $arabicPayload),
            englishTranslation: self::translationFromStoredArray(is_array($payload['englishTranslation'] ?? null) ? $payload['englishTranslation'] : [], 'en', $englishPayload),
            arabicPayload: $arabicPayload,
            englishPayload: $englishPayload,
        );
    }

    /** @param array<string, mixed> $payload */
    private static function sectionPayloadFromArray(array $payload): HomepageSectionDataDTO
    {
        return HomepagePayloadMapper::sectionDataFromArray($payload);
    }

    /** @param array<string, mixed> $payload */
    private static function translationFromStoredArray(array $payload, string $locale, HomepageSectionDataDTO $fallback): HomepageSectionTranslationDTO
    {
        return new HomepageSectionTranslationDTO(
            locale: $locale,
            headline: self::stringValue($payload, 'headline') ?? $fallback->title,
            body: self::stringValue($payload, 'body') ?? $fallback->summary ?? $fallback->body,
            ctaLabel: self::stringValue($payload, 'ctaLabel') ?? $fallback->primaryAction?->label ?? $fallback->sectionAction?->label,
            imageAlt: self::stringValue($payload, 'imageAlt') ?? self::stringValue($payload, 'image_alt'),
        );
    }

    /** @param array<string, mixed> $payload */
    private static function previewSectionFromDraft(array $payload, string $locale, ?HomepageSectionDTO $fallback = null): HomepageSectionDTO
    {
        $fallbackPayload = $fallback instanceof HomepageSectionDTO
            ? HomepagePayloadMapper::sectionDataToArray($fallback->payload)
            : [];
        $fallbackArabicPayload = $fallback instanceof HomepageSectionDTO
            ? HomepagePayloadMapper::sectionDataToArray($fallback->arabicPayload ?? $fallback->payload)
            : [];
        $fallbackEnglishPayload = $fallback instanceof HomepageSectionDTO
            ? HomepagePayloadMapper::sectionDataToArray($fallback->englishPayload ?? $fallback->payload)
            : [];
        $genericPayload = self::mergeDraftPayload(
            $fallbackPayload,
            is_array($payload['payload'] ?? null) ? $payload['payload'] : [],
        );
        $arabicPayload = self::sectionDataFromDraft(
            is_array($payload['arabicPayload'] ?? null)
                ? self::mergeDraftPayload($fallbackArabicPayload, $payload['arabicPayload'])
                : ($locale === 'ar' ? $genericPayload : $fallbackArabicPayload),
        );
        $englishPayload = self::sectionDataFromDraft(
            is_array($payload['englishPayload'] ?? null)
                ? self::mergeDraftPayload($fallbackEnglishPayload, $payload['englishPayload'])
                : ($locale === 'en' ? $genericPayload : $fallbackEnglishPayload),
        );

        return new HomepageSectionDTO(
            id: (int) ($payload['id'] ?? $fallback?->id ?? 0),
            key: (string) ($payload['key'] ?? ''),
            sortOrder: (int) ($payload['sortOrder'] ?? ($payload['sort_order'] ?? $fallback?->sortOrder ?? 0)),
            isEnabled: (bool) ($payload['isEnabled'] ?? ($payload['is_enabled'] ?? $fallback?->isEnabled ?? true)),
            payload: $locale === 'en'
                ? (self::isEmptySectionPayload($englishPayload) ? self::sectionDataFromDraft($genericPayload) : $englishPayload)
                : (self::isEmptySectionPayload($arabicPayload) ? self::sectionDataFromDraft($genericPayload) : $arabicPayload),
            arabicTranslation: self::translationFromDraft((array) ($payload['arabicTranslation'] ?? []), 'ar', $arabicPayload),
            englishTranslation: self::translationFromDraft((array) ($payload['englishTranslation'] ?? []), 'en', $englishPayload),
            arabicPayload: $arabicPayload,
            englishPayload: $englishPayload,
        );
    }

    /**
     * Drafts may only contain editor-exposed fields. Merge them over the
     * published database payload so preview keeps non-editable presentation data.
     *
     * @param  array<string, mixed>  $fallback
     * @param  array<string, mixed>  $draft
     * @return array<string, mixed>
     */
    private static function mergeDraftPayload(array $fallback, array $draft): array
    {
        return array_replace_recursive($fallback, $draft);
    }

    /** @param array<string, mixed> $payload */
    private static function sectionDataFromDraft(array $payload): HomepageSectionDataDTO
    {
        return HomepagePayloadMapper::sectionDataFromArray($payload);
    }

    /** @param array<string, mixed> $payload */
    private static function translationFromDraft(array $payload, string $locale, HomepageSectionDataDTO $fallback): HomepageSectionTranslationDTO
    {
        return new HomepageSectionTranslationDTO(
            locale: $locale,
            headline: self::stringFromDraft($payload, 'headline')
                ?? self::stringFromDraft($payload, 'title')
                ?? $fallback->title,
            body: self::stringFromDraft($payload, 'body')
                ?? self::stringFromDraft($payload, 'summary')
                ?? $fallback->summary
                ?? $fallback->body,
            ctaLabel: self::stringFromDraft($payload, 'ctaLabel')
                ?? self::stringFromDraft($payload, 'cta_label')
                ?? $fallback->primaryAction?->label
                ?? $fallback->sectionAction?->label,
            imageAlt: self::stringFromDraft($payload, 'imageAlt') ?? self::stringFromDraft($payload, 'image_alt'),
        );
    }

    private static function isEmptySectionPayload(HomepageSectionDataDTO $payload): bool
    {
        return $payload->eyebrow === null
            && $payload->subtitle === null
            && $payload->badge === null
            && $payload->title === null
            && $payload->summary === null
            && $payload->body === null
            && $payload->videoUrl === null
            && $payload->imageUrl === null
            && $payload->backgroundImageUrl === null
            && $payload->primaryAction === null
            && $payload->secondaryAction === null
            && $payload->sectionAction === null
            && $payload->stats === []
            && $payload->featuredItems === []
            && $payload->articles === []
            && $payload->researchItems === []
            && $payload->events === []
            && $payload->footerColumns === []
            && $payload->contactLinks === []
            && $payload->socialLinks === []
            && $payload->items === []
            && $payload->content === [];
    }

    /** @param array<string, mixed>|null $payload */
    private static function stringValue(?array $payload, string $key): ?string
    {
        $value = $payload[$key] ?? null;

        return is_string($value) && $value !== '' ? $value : null;
    }

    /** @param array<string, mixed> $payload */
    private static function stringFromDraft(array $payload, string $key): ?string
    {
        $value = $payload[$key] ?? $payload[strtolower(preg_replace('/(?<!^)[A-Z]/', '_$0', $key) ?? $key)] ?? null;

        return is_string($value) && $value !== '' ? $value : null;
    }
}
