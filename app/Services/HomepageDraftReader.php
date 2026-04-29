<?php

declare(strict_types=1);

namespace App\Services;

use App\DTOs\HomepageSectionDataDTO;
use App\DTOs\HomepageSectionDTO;
use App\DTOs\HomepageSectionTranslationDTO;
use App\Models\HomepageDraft;
use App\Models\HomepageSection;
use App\Models\HomepageSectionTranslation;
use App\Support\HomepagePayloadMapper;
use Illuminate\Support\Collection;

/**
 * Handles draft merging logic: reading drafts, merging with published sections,
 * and building section DTOs from draft arrays.
 *
 * Extracted from HomepageSectionService to keep each class focused on a single responsibility.
 */
final class HomepageDraftReader
{
    private const EDITABLE_STATUSES = ['draft', 'scheduled'];

    /**
     * @param  array<int, string>  $sectionKeys
     */
    public function latestEditableDraft(): ?HomepageDraft
    {
        return HomepageDraft::query()
            ->where('target_type', 'homepage')
            ->whereIn('status', self::EDITABLE_STATUSES)
            ->latest('updated_at')
            ->first();
    }

    /**
     * @param  array<int, string>  $sectionKeys
     * @return Collection<int, HomepageSectionDTO>
     */
    public function publishedSections(array $sectionKeys): Collection
    {
        return HomepageSection::query()
            ->with('translations')
            ->whereIn('key', $sectionKeys)
            ->orderBy('sort_order')
            ->get()
            ->map(fn (HomepageSection $section): HomepageSectionDTO => $this->mapSection($section, 'ar'))
            ->sortBy(fn (HomepageSectionDTO $section): int => $section->sortOrder)
            ->values();
    }

    /**
     * @param  array<int, string>  $sectionKeys
     * @return Collection<int, HomepageSectionDTO>
     */
    public function sectionsFromDraft(HomepageDraft $draft, array $sectionKeys): Collection
    {
        $publishedSections = $this->publishedSections($sectionKeys)->keyBy('key');
        $draftSections = [];

        foreach ($this->draftSectionPayloads($draft) as $sectionPayload) {
            $key = is_string($sectionPayload['key'] ?? null) ? $sectionPayload['key'] : null;

            if ($key !== null && in_array($key, $sectionKeys, true)) {
                $draftSections[$key] = $sectionPayload;
            }
        }

        $sections = collect();

        foreach ($sectionKeys as $defaultIndex => $key) {
            $fallback = $publishedSections->get($key) ?? $this->emptySection($key, $defaultIndex + 1);
            $sections->push(
                isset($draftSections[$key])
                    ? $this->sectionFromDraftArray($draftSections[$key], $fallback, 'ar')
                    : $fallback,
            );
        }

        return $sections
            ->sortBy(fn (HomepageSectionDTO $section): int => $section->sortOrder)
            ->values();
    }

    public function mapSection(HomepageSection $section, string $payloadLocale): HomepageSectionDTO
    {
        $arabicTranslation = $this->findTranslation($section, 'ar');
        $englishTranslation = $this->findTranslation($section, 'en');
        $arabicPayload = $this->mapPayload($arabicTranslation);
        $englishPayload = $this->mapPayload($englishTranslation);

        return new HomepageSectionDTO(
            id: (int) $section->getKey(),
            key: (string) $section->key,
            sortOrder: (int) $section->sort_order,
            isEnabled: (bool) $section->is_enabled,
            payload: $payloadLocale === 'en' ? $englishPayload : $arabicPayload,
            arabicTranslation: $this->translationFromPayload($this->translationPayloadArray($arabicTranslation, $arabicPayload), 'ar'),
            englishTranslation: $this->translationFromPayload($this->translationPayloadArray($englishTranslation, $englishPayload), 'en'),
            arabicPayload: $arabicPayload,
            englishPayload: $englishPayload,
        );
    }

    public function hasRenderablePayloadForLocale(HomepageSectionDTO $section, string $locale): bool
    {
        $payload = $locale === 'en' ? ($section->englishPayload ?? $section->payload) : ($section->arabicPayload ?? $section->payload);
        $data = HomepagePayloadMapper::sectionDataToArray($payload);

        return $data !== [];
    }

    public function emptySection(string $key, int $sortOrder): HomepageSectionDTO
    {
        $payload = new HomepageSectionDataDTO();

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

    /**
     * @param  array<string, mixed>  $payload
     */
    public function translationFromPayload(array $payload, string $locale): HomepageSectionTranslationDTO
    {
        $title = $this->stringValue($payload, 'headline') ?? $this->stringValue($payload, 'title');
        $body = $this->stringValue($payload, 'summary') ?? $this->stringValue($payload, 'body');
        $cta = is_array($payload['primaryAction'] ?? null)
            ? $this->stringValue($payload['primaryAction'], 'label')
            : $this->stringValue($payload, 'ctaLabel') ?? $this->stringValue($payload, 'cta_label');

        return new HomepageSectionTranslationDTO(
            locale: $locale,
            headline: $title,
            body: $body,
            ctaLabel: $cta,
            imageAlt: $this->contentString($payload, 'imageAlt') ?? $this->contentString($payload, 'image_alt'),
        );
    }

    // ──────────────────────────────────────────────
    //  Internal helpers
    // ──────────────────────────────────────────────

    /**
     * @return array<int, array<string, mixed>>
     */
    private function draftSectionPayloads(HomepageDraft $draft): array
    {
        $draftHomepage = is_array($draft->payload_json['homepage'] ?? null)
            ? $draft->payload_json['homepage']
            : $draft->payload_json;
        $sections = is_array($draftHomepage['sections'] ?? null) ? $draftHomepage['sections'] : [];

        return array_values(array_filter($sections, static fn (mixed $section): bool => is_array($section)));
    }

    private function sectionFromDraftArray(array $payload, HomepageSectionDTO $fallback, string $payloadLocale): HomepageSectionDTO
    {
        $genericPayload = is_array($payload['payload'] ?? null) ? $payload['payload'] : [];
        $fallbackArabicPayload = HomepagePayloadMapper::sectionDataToArray($fallback->arabicPayload ?? $fallback->payload);
        $fallbackEnglishPayload = HomepagePayloadMapper::sectionDataToArray($fallback->englishPayload ?? $fallback->payload);
        $arabicPayloadArray = is_array($payload['arabicPayload'] ?? null)
            ? $payload['arabicPayload']
            : ($payloadLocale === 'ar' && $genericPayload !== [] ? $genericPayload : $fallbackArabicPayload);
        $englishPayloadArray = is_array($payload['englishPayload'] ?? null)
            ? $payload['englishPayload']
            : ($payloadLocale === 'en' && $genericPayload !== [] ? $genericPayload : $fallbackEnglishPayload);

        $arabicPayload = HomepagePayloadMapper::sectionDataFromArray($arabicPayloadArray);
        $englishPayload = HomepagePayloadMapper::sectionDataFromArray($englishPayloadArray);

        return new HomepageSectionDTO(
            id: is_int($payload['id'] ?? null) ? $payload['id'] : $fallback->id,
            key: is_string($payload['key'] ?? null) && $payload['key'] !== '' ? $payload['key'] : $fallback->key,
            sortOrder: is_int($payload['sortOrder'] ?? null)
                ? $payload['sortOrder']
                : (is_int($payload['sort_order'] ?? null) ? $payload['sort_order'] : $fallback->sortOrder),
            isEnabled: is_bool($payload['isEnabled'] ?? null)
                ? $payload['isEnabled']
                : (is_bool($payload['is_enabled'] ?? null) ? $payload['is_enabled'] : $fallback->isEnabled),
            payload: $payloadLocale === 'en' ? $englishPayload : $arabicPayload,
            arabicTranslation: $this->translationFromPayload(
                is_array($payload['arabicTranslation'] ?? null) ? $payload['arabicTranslation'] : $arabicPayloadArray,
                'ar',
            ),
            englishTranslation: $this->translationFromPayload(
                is_array($payload['englishTranslation'] ?? null) ? $payload['englishTranslation'] : $englishPayloadArray,
                'en',
            ),
            arabicPayload: $arabicPayload,
            englishPayload: $englishPayload,
        );
    }

    private function mapPayload(?HomepageSectionTranslation $translation): HomepageSectionDataDTO
    {
        return HomepagePayloadMapper::sectionDataFromArray(is_array($translation?->payload_json) ? $translation->payload_json : []);
    }

    private function findTranslation(HomepageSection $section, string $locale): ?HomepageSectionTranslation
    {
        return $section->translations->firstWhere('locale', $locale);
    }

    /**
     * @param  array<string, mixed>|null  $payload
     */
    private function translationPayloadArray(?HomepageSectionTranslation $translation, HomepageSectionDataDTO $fallbackPayload): array
    {
        return is_array($translation?->payload_json)
            ? $translation->payload_json
            : HomepagePayloadMapper::sectionDataToArray($fallbackPayload);
    }

    /**
     * @param  array<string, mixed>|null  $payload
     */
    private function stringValue(?array $payload, string $key): ?string
    {
        $value = $payload[$key] ?? null;

        return is_string($value) && $value !== '' ? $value : null;
    }

    /**
     * @param  array<string, mixed>|null  $payload
     */
    private function contentString(?array $payload, string $key): ?string
    {
        $content = is_array($payload['content'] ?? null) ? $payload['content'] : [];
        $value = $content[$key] ?? null;

        return is_string($value) && $value !== '' ? $value : null;
    }
}
