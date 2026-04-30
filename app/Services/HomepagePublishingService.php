<?php

declare(strict_types=1);

namespace App\Services;

use App\Contracts\AuditServiceInterface;
use App\Contracts\CacheServiceInterface;
use App\Contracts\HomepagePublishingServiceInterface;
use App\Contracts\HomepageSectionServiceInterface;
use App\DTOs\DraftPayloadDTO;
use App\DTOs\HomepageDraftDataDTO;
use App\DTOs\HomepageDraftDTO;
use App\DTOs\HomepageSectionDataDTO;
use App\DTOs\HomepageSectionDTO;
use App\DTOs\HomepageSectionTranslationDTO;
use App\Events\DraftConflictDetected;
use App\Exceptions\ConflictException;
use App\Models\HomepageDraft;
use App\Models\HomepageSection;
use App\Models\HomepageSectionTranslation;
use App\Support\HomepagePayloadMapper;
use DateTimeInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

final class HomepagePublishingService implements HomepagePublishingServiceInterface
{
    public function __construct(
        private readonly HomepageSectionServiceInterface $homepageSectionService,
        private readonly CacheServiceInterface $cacheService,
        private readonly AuditServiceInterface $auditService,
    ) {}

    public function saveDraft(HomepageDraftDataDTO $payload, int $userId, ?int $expectedVersion = null): HomepageDraftDTO
    {
        // Optimistic locking: check version if expectedVersion is provided
        if ($expectedVersion !== null) {
            $currentDraft = HomepageDraft::query()
                ->where('target_type', 'homepage')
                ->whereIn('status', ['draft', 'scheduled'])
                ->latest()
                ->first();

            if ($currentDraft instanceof HomepageDraft && (int) $currentDraft->version !== $expectedVersion) {
                $this->auditService->log(
                    action: 'draft.conflict',
                    userId: $userId,
                    entityType: HomepageDraft::class,
                    entityId: (int) $currentDraft->getKey(),
                    metadata: [
                        'expected_version' => $expectedVersion,
                        'actual_version' => (int) $currentDraft->version,
                        'entity_type' => 'homepage',
                    ],
                );

                DraftConflictDetected::dispatch(
                    HomepageDraft::class,
                    (int) $currentDraft->getKey(),
                    $expectedVersion,
                    (int) $currentDraft->version,
                    $userId,
                );

                throw new ConflictException(
                    'Draft has been modified by another editor.',
                    (int) $currentDraft->version,
                );
            }
        }

        // Determine the next version number
        $latestDraft = HomepageDraft::query()
            ->where('target_type', 'homepage')
            ->whereIn('status', ['draft', 'scheduled'])
            ->latest()
            ->first();
        $nextVersion = $latestDraft instanceof HomepageDraft ? ((int) $latestDraft->version) + 1 : 1;

        $sections = $this->normalizeSections($payload->sections);
        $draft = HomepageDraft::query()->create([
            'target_type' => 'homepage',
            'target_id' => null,
            'payload_json' => [
                'homepage' => [
                    'sections' => $this->serializeSections($sections),
                ],
            ],
            'status' => 'draft',
            'draft_notes' => 'Homepage draft snapshot',
            'created_by' => $userId,
            'updated_by' => $userId,
            'approved_by' => null,
            'scheduled_at' => null,
            'published_at' => null,
            'version' => $nextVersion,
        ]);

        $this->auditService->log(
            action: 'homepage.draft_saved',
            userId: $userId,
            entityType: HomepageDraft::class,
            entityId: (int) $draft->getKey(),
            metadata: [
                'draft_id' => (int) $draft->getKey(),
                'section_keys' => array_values(array_map(static fn (HomepageSectionDTO $section): string => $section->key, $sections)),
            ],
        );

        return $this->mapDraftDto($draft, $sections);
    }

    public function publish(int $draftId, int $userId): bool
    {
        $draft = HomepageDraft::query()->find($draftId);

        if (! $draft instanceof HomepageDraft || $draft->target_type !== 'homepage') {
            return false;
        }

        $sections = $this->sectionsFromDraft($draft);

        if (! $this->draftIsPublishable($sections)) {
            return false;
        }

        DB::transaction(function () use ($draft, $sections, $userId): void {
            foreach ($sections as $section) {
                $sectionModel = HomepageSection::query()->updateOrCreate(
                    ['key' => $section->key],
                    [
                        'type' => $this->sectionType($section->key),
                        'sort_order' => $section->sortOrder,
                        'is_enabled' => $section->isEnabled,
                        'schema_version' => 1,
                        'config_json' => [
                            'approved_key' => $section->key,
                            'supports_preview' => true,
                        ],
                    ],
                );

                HomepageSectionTranslation::query()->updateOrCreate(
                    [
                        'section_id' => (int) $sectionModel->getKey(),
                        'locale' => 'ar',
                    ],
                    [
                        'payload_json' => $this->sectionPayloadToArray($section->arabicPayload ?? $section->payload),
                    ],
                );

                HomepageSectionTranslation::query()->updateOrCreate(
                    [
                        'section_id' => (int) $sectionModel->getKey(),
                        'locale' => 'en',
                    ],
                    [
                        'payload_json' => $this->sectionPayloadToArray($section->englishPayload ?? $section->payload),
                    ],
                );
            }

            $draft->forceFill([
                'status' => 'published',
                'updated_by' => $userId,
                'approved_by' => $userId,
                'scheduled_at' => null,
                'published_at' => now(),
            ])->save();
        });

        $this->invalidateHomepageCache();

        $this->auditService->log(
            action: 'homepage.publish',
            userId: $userId,
            entityType: HomepageDraft::class,
            entityId: $draftId,
            metadata: [
                'draft_id' => $draftId,
                'published_at' => now()->toIso8601String(),
            ],
        );

        return true;
    }

    public function unpublish(string $targetType, ?int $targetId, int $userId): bool
    {
        if ($targetType !== 'homepage' || $targetId !== null) {
            return false;
        }

        HomepageSection::query()
            ->whereIn('key', HomepageSectionServiceInterface::SECTION_KEYS)
            ->update(['is_enabled' => false]);

        $this->invalidateHomepageCache();

        $this->auditService->log(
            action: 'homepage.unpublish',
            userId: $userId,
            entityType: HomepageSection::class,
            metadata: [
                'target_type' => $targetType,
            ],
        );

        return true;
    }

    public function schedulePublish(int $draftId, DateTimeInterface $publishAt, int $userId): bool
    {
        $draft = HomepageDraft::query()->find($draftId);

        if (! $draft instanceof HomepageDraft || $draft->target_type !== 'homepage') {
            return false;
        }

        $scheduledAt = Carbon::parse($publishAt->format(DateTimeInterface::ATOM));

        if ($scheduledAt->lessThanOrEqualTo(now())) {
            return false;
        }

        $sections = $this->sectionsFromDraft($draft);

        if (! $this->draftIsPublishable($sections)) {
            return false;
        }

        $draft->forceFill([
            'status' => 'scheduled',
            'updated_by' => $userId,
            'approved_by' => $userId,
            'scheduled_at' => $scheduledAt,
            'published_at' => null,
        ])->save();

        $this->auditService->log(
            action: 'homepage.schedule',
            userId: $userId,
            entityType: HomepageDraft::class,
            entityId: $draftId,
            metadata: [
                'draft_id' => $draftId,
                'scheduled_at' => $draft->scheduled_at?->toIso8601String(),
            ],
        );

        return true;
    }

    /**
     * @param  array<int, HomepageSectionDTO>  $sections
     */
    private function mapDraftDto(HomepageDraft $draft, array $sections): HomepageDraftDTO
    {
        return new HomepageDraftDTO(
            id: (int) $draft->getKey(),
            targetType: (string) $draft->target_type,
            targetId: $draft->target_id !== null ? (int) $draft->target_id : null,
            status: (string) $draft->status,
            payload: new DraftPayloadDTO(homepage: new HomepageDraftDataDTO(sections: $sections)),
            createdBy: (int) $draft->created_by,
            publishAt: $draft->scheduled_at?->toIso8601String(),
            createdAt: $draft->created_at?->toIso8601String() ?? now()->toIso8601String(),
            updatedAt: $draft->updated_at?->toIso8601String() ?? now()->toIso8601String(),
        );
    }

    /**
     * @param  array<int, HomepageSectionDTO>  $providedSections
     * @return array<int, HomepageSectionDTO>
     */
    private function normalizeSections(array $providedSections): array
    {
        $currentSections = $this->homepageSectionService->getSections()->keyBy('key');
        $providedByKey = collect($providedSections)->keyBy('key');
        $normalized = [];

        foreach (HomepageSectionServiceInterface::SECTION_KEYS as $index => $key) {
            $current = $currentSections->get($key);

            if (! $current instanceof HomepageSectionDTO) {
                continue;
            }

            $provided = $providedByKey->get($key);

            $normalized[] = $provided instanceof HomepageSectionDTO
                ? $this->mergeSection($provided, $current, $index + 1)
                : $current;
        }

        return array_values($normalized);
    }

    /**
     * @return array<int, HomepageSectionDTO>
     */
    private function sectionsFromDraft(HomepageDraft $draft): array
    {
        $draftHomepage = is_array($draft->payload_json['homepage'] ?? null)
            ? $draft->payload_json['homepage']
            : $draft->payload_json;
        $sections = is_array($draftHomepage['sections'] ?? null) ? $draftHomepage['sections'] : [];
        $currentSections = $this->homepageSectionService->getSections()->keyBy('key');
        $normalized = [];

        foreach (HomepageSectionServiceInterface::SECTION_KEYS as $index => $key) {
            $current = $currentSections->get($key);

            if (! $current instanceof HomepageSectionDTO) {
                continue;
            }

            $sectionPayload = collect($sections)->first(
                static fn (mixed $section): bool => is_array($section) && ($section['key'] ?? null) === $key,
            );

            $normalized[] = is_array($sectionPayload)
                ? $this->sectionFromArray($sectionPayload, $current, $index + 1)
                : $current;
        }

        return array_values($normalized);
    }

    private function draftIsPublishable(array $sections): bool
    {
        if (count($sections) !== count(HomepageSectionServiceInterface::SECTION_KEYS)) {
            return false;
        }

        $keys = array_values(array_map(static fn (HomepageSectionDTO $section): string => $section->key, $sections));
        sort($keys);
        $approvedKeys = HomepageSectionServiceInterface::SECTION_KEYS;
        sort($approvedKeys);

        if ($keys !== $approvedKeys) {
            return false;
        }

        foreach ($sections as $section) {
            $arabicPayload = $section->arabicPayload ?? $section->payload;
            $englishPayload = $section->englishPayload ?? $section->payload;

            if (! $this->homepageSectionService->validateSectionPayload($section->key, $arabicPayload, 'ar')->isValid) {
                return false;
            }

            if (! $this->homepageSectionService->validateSectionPayload($section->key, $englishPayload, 'en')->isValid) {
                return false;
            }
        }

        return true;
    }

    private function mergeSection(HomepageSectionDTO $provided, HomepageSectionDTO $fallback, int $defaultSortOrder): HomepageSectionDTO
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

    private function sectionFromArray(array $payload, HomepageSectionDTO $fallback, int $defaultSortOrder): HomepageSectionDTO
    {
        $arabicPayload = is_array($payload['arabicPayload'] ?? null)
            ? $this->sectionPayloadFromArray($payload['arabicPayload'])
            : ($fallback->arabicPayload ?? $fallback->payload);
        $englishPayload = is_array($payload['englishPayload'] ?? null)
            ? $this->sectionPayloadFromArray($payload['englishPayload'])
            : ($fallback->englishPayload ?? $fallback->payload);

        return new HomepageSectionDTO(
            id: is_int($payload['id'] ?? null) ? $payload['id'] : $fallback->id,
            key: is_string($payload['key'] ?? null) ? $payload['key'] : $fallback->key,
            sortOrder: is_int($payload['sortOrder'] ?? null) ? $payload['sortOrder'] : $defaultSortOrder,
            isEnabled: is_bool($payload['isEnabled'] ?? null) ? $payload['isEnabled'] : $fallback->isEnabled,
            payload: $arabicPayload,
            arabicTranslation: $this->translationFromArray(is_array($payload['arabicTranslation'] ?? null) ? $payload['arabicTranslation'] : [], 'ar', $arabicPayload),
            englishTranslation: $this->translationFromArray(is_array($payload['englishTranslation'] ?? null) ? $payload['englishTranslation'] : [], 'en', $englishPayload),
            arabicPayload: $arabicPayload,
            englishPayload: $englishPayload,
        );
    }

    private function sectionPayloadFromArray(array $payload): HomepageSectionDataDTO
    {
        return HomepagePayloadMapper::sectionDataFromArray($payload);
    }

    private function translationFromArray(array $payload, string $locale, HomepageSectionDataDTO $fallback): HomepageSectionTranslationDTO
    {
        return new HomepageSectionTranslationDTO(
            locale: $locale,
            headline: $this->stringValue($payload, 'headline') ?? $fallback->title,
            body: $this->stringValue($payload, 'body') ?? $fallback->summary ?? $fallback->body,
            ctaLabel: $this->stringValue($payload, 'ctaLabel') ?? $fallback->primaryAction?->label ?? $fallback->sectionAction?->label,
            imageAlt: $this->stringValue($payload, 'imageAlt') ?? $this->stringValue($payload, 'image_alt'),
        );
    }

    /**
     * @param  array<int, HomepageSectionDTO>  $sections
     * @return array<int, array<string, mixed>>
     */
    private function serializeSections(array $sections): array
    {
        return array_values(array_map(fn (HomepageSectionDTO $section): array => [
            'id' => $section->id,
            'key' => $section->key,
            'sortOrder' => $section->sortOrder,
            'isEnabled' => $section->isEnabled,
            'payload' => $this->sectionPayloadToArray($section->payload),
            'arabicPayload' => $this->sectionPayloadToArray($section->arabicPayload ?? $section->payload),
            'englishPayload' => $this->sectionPayloadToArray($section->englishPayload ?? $section->payload),
            'arabicTranslation' => $this->translationToArray($section->arabicTranslation),
            'englishTranslation' => $this->translationToArray($section->englishTranslation),
        ], $sections));
    }

    /**
     * @return array<string, mixed>
     */
    private function sectionPayloadToArray(HomepageSectionDataDTO $payload): array
    {
        return HomepagePayloadMapper::sectionDataToArray($payload);
    }

    /**
     * @return array<string, mixed>
     */
    private function translationToArray(HomepageSectionTranslationDTO $translation): array
    {
        return array_filter([
            'headline' => $translation->headline,
            'body' => $translation->body,
            'ctaLabel' => $translation->ctaLabel,
            'imageAlt' => $translation->imageAlt,
        ], static fn (mixed $value): bool => $value !== null && $value !== '');
    }

    private function sectionType(string $key): string
    {
        return match ($key) {
            'hero' => 'hero',
            'hero_stats', 'bottom_stats' => 'stats',
            'footer' => 'footer',
            default => 'listing',
        };
    }

    private function invalidateHomepageCache(): void
    {
        foreach (['ar', 'en'] as $locale) {
            $this->cacheService->forget('public_pages:'.sha1($locale.'|'.$locale.'|'));
            $this->cacheService->flushTags(['public-pages', 'public-shell', 'public-shell:'.$locale]);
        }
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
     * Check whether an editable (draft or scheduled) homepage draft exists.
     */
    public function hasEditableDraft(): bool
    {
        return HomepageDraft::query()
            ->where('target_type', 'homepage')
            ->whereIn('status', ['draft', 'scheduled'])
            ->exists();
    }

    /**
     * Discard all editable (draft or scheduled) homepage drafts.
     *
     * @return int Number of drafts deleted.
     */
    public function discardEditableDraft(): int
    {
        return HomepageDraft::query()
            ->where('target_type', 'homepage')
            ->whereIn('status', ['draft', 'scheduled'])
            ->delete();
    }

    /**
     * Return the status string of the latest homepage draft, or null if none exists.
     */
    public function latestHomepageState(): ?string
    {
        $latestDraft = HomepageDraft::query()
            ->where('target_type', 'homepage')
            ->latest()
            ->first();

        return $latestDraft?->status;
    }
}
