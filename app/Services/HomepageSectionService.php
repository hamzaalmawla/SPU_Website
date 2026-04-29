<?php

declare(strict_types=1);

namespace App\Services;

use App\Contracts\AuditServiceInterface;
use App\Contracts\HomepageSectionServiceInterface;
use App\DTOs\HomepageDTO;
use App\DTOs\HomepageSectionDataDTO;
use App\DTOs\HomepageSectionDTO;
use App\DTOs\HomepageSectionTranslationDTO;
use App\DTOs\ValidationResultDTO;
use App\Models\HomepageDraft;
use App\Models\HomepageSection;
use App\Models\User;
use App\Support\HomepagePayloadMapper;
use Illuminate\Contracts\Auth\Factory as AuthFactory;
use Illuminate\Support\Collection;

/**
 * Orchestrates homepage section CRUD operations, delegating draft reading
 * to HomepageDraftReader and validation to HomepageSectionValidator.
 */
final class HomepageSectionService implements HomepageSectionServiceInterface
{
    public function __construct(
        private readonly AuditServiceInterface $auditService,
        private readonly AuthFactory $authFactory,
        private readonly HomepageDraftReader $draftReader,
        private readonly HomepageSectionValidator $validator,
    ) {}

    /**
     * @return Collection<int, HomepageSectionDTO>
     */
    public function getSections(): Collection
    {
        $draft = $this->draftReader->latestEditableDraft();

        return $draft instanceof HomepageDraft
            ? $this->draftReader->sectionsFromDraft($draft, self::SECTION_KEYS)
            : $this->draftReader->publishedSections(self::SECTION_KEYS);
    }

    public function getSectionByKey(string $key): ?HomepageSectionDTO
    {
        $this->validator->assertApprovedKey($key);

        return $this->getSections()->firstWhere('key', $key);
    }

    public function getPublicHomepage(string $locale): HomepageDTO
    {
        $sections = HomepageSection::query()
            ->with('translations')
            ->whereIn('key', self::SECTION_KEYS)
            ->enabled()
            ->orderBy('sort_order')
            ->get()
            ->map(fn (HomepageSection $section): HomepageSectionDTO => $this->draftReader->mapSection($section, $locale))
            ->filter(fn (HomepageSectionDTO $section): bool => $this->draftReader->hasRenderablePayloadForLocale($section, $locale))
            ->values()
            ->all();

        return new HomepageDTO(
            locale: $locale,
            direction: $locale === 'ar' ? 'rtl' : 'ltr',
            sections: $sections,
        );
    }

    public function updateSection(string $key, HomepageSectionDataDTO $payload, string $locale): bool
    {
        $this->validator->assertApprovedKey($key);

        $validation = $this->validateSectionPayload($key, $payload, $locale);

        if (! $validation->isValid) {
            return false;
        }

        $sections = $this->editableSectionsIndexed();
        $section = $sections->get($key);

        if (! $section instanceof HomepageSectionDTO) {
            return false;
        }

        $sections->put($key, $this->replaceSectionPayload($section, $payload, $locale));

        $draft = $this->persistDraftSnapshot($sections->values()->all(), $this->resolveActorId());

        $this->auditService->log(
            action: 'homepage.section_updated',
            userId: $this->currentUserId(),
            entityType: HomepageDraft::class,
            entityId: (int) $draft->getKey(),
            metadata: [
                'section_key' => $key,
                'locale' => $locale,
                'draft_id' => (int) $draft->getKey(),
            ],
        );

        return true;
    }

    public function toggleSection(string $key, bool $enabled): bool
    {
        $this->validator->assertApprovedKey($key);

        $sections = $this->editableSectionsIndexed();
        $section = $sections->get($key);

        if (! $section instanceof HomepageSectionDTO) {
            return false;
        }

        $sections->put($key, $this->withEnabledState($section, $enabled));

        $draft = $this->persistDraftSnapshot($sections->values()->all(), $this->resolveActorId());

        $this->auditService->log(
            action: 'homepage.section_updated',
            userId: $this->currentUserId(),
            entityType: HomepageDraft::class,
            entityId: (int) $draft->getKey(),
            metadata: [
                'section_key' => $key,
                'is_enabled' => $enabled,
                'draft_id' => (int) $draft->getKey(),
            ],
        );

        return true;
    }

    public function reorderSections(array $orderedKeys): bool
    {
        $normalizedKeys = array_values(array_filter($orderedKeys, static fn (mixed $key): bool => is_string($key) && $key !== ''));

        if (! $this->validator->hasExactApprovedKeySet($normalizedKeys)) {
            return false;
        }

        $sections = $this->editableSectionsIndexed();

        foreach ($normalizedKeys as $index => $key) {
            $section = $sections->get($key);

            if ($section instanceof HomepageSectionDTO) {
                $sections->put($key, $this->withSortOrder($section, $index + 1));
            }
        }

        $draft = $this->persistDraftSnapshot(
            $sections
                ->sortBy(fn (HomepageSectionDTO $section): int => $section->sortOrder)
                ->values()
                ->all(),
            $this->resolveActorId(),
        );

        $this->auditService->log(
            action: 'homepage.section_updated',
            userId: $this->currentUserId(),
            entityType: HomepageDraft::class,
            entityId: (int) $draft->getKey(),
            metadata: [
                'ordered_keys' => $normalizedKeys,
                'draft_id' => (int) $draft->getKey(),
            ],
        );

        return true;
    }

    public function validateSectionPayload(string $key, HomepageSectionDataDTO $payload, string $locale): ValidationResultDTO
    {
        return $this->validator->validateSectionPayload($key, $payload, $locale);
    }

    // ──────────────────────────────────────────────
    //  Internal helpers
    // ──────────────────────────────────────────────

    /**
     * @return Collection<string, HomepageSectionDTO>
     */
    private function editableSectionsIndexed(): Collection
    {
        return $this->getSections()->keyBy('key');
    }

    private function replaceSectionPayload(HomepageSectionDTO $section, HomepageSectionDataDTO $payload, string $locale): HomepageSectionDTO
    {
        $arabicPayload = $locale === 'ar' ? $payload : ($section->arabicPayload ?? $section->payload);
        $englishPayload = $locale === 'en' ? $payload : ($section->englishPayload ?? $section->payload);

        return new HomepageSectionDTO(
            id: $section->id,
            key: $section->key,
            sortOrder: $section->sortOrder,
            isEnabled: $section->isEnabled,
            payload: $locale === 'en' ? $englishPayload : $arabicPayload,
            arabicTranslation: $this->draftReader->translationFromPayload(HomepagePayloadMapper::sectionDataToArray($arabicPayload), 'ar'),
            englishTranslation: $this->draftReader->translationFromPayload(HomepagePayloadMapper::sectionDataToArray($englishPayload), 'en'),
            arabicPayload: $arabicPayload,
            englishPayload: $englishPayload,
        );
    }

    private function withEnabledState(HomepageSectionDTO $section, bool $enabled): HomepageSectionDTO
    {
        return new HomepageSectionDTO(
            id: $section->id,
            key: $section->key,
            sortOrder: $section->sortOrder,
            isEnabled: $enabled,
            payload: $section->payload,
            arabicTranslation: $section->arabicTranslation,
            englishTranslation: $section->englishTranslation,
            arabicPayload: $section->arabicPayload,
            englishPayload: $section->englishPayload,
        );
    }

    private function withSortOrder(HomepageSectionDTO $section, int $sortOrder): HomepageSectionDTO
    {
        return new HomepageSectionDTO(
            id: $section->id,
            key: $section->key,
            sortOrder: $sortOrder,
            isEnabled: $section->isEnabled,
            payload: $section->payload,
            arabicTranslation: $section->arabicTranslation,
            englishTranslation: $section->englishTranslation,
            arabicPayload: $section->arabicPayload,
            englishPayload: $section->englishPayload,
        );
    }

    /**
     * @param  array<int, HomepageSectionDTO>  $sections
     */
    private function persistDraftSnapshot(array $sections, int $userId): HomepageDraft
    {
        return HomepageDraft::query()->create([
            'target_type' => 'homepage',
            'target_id' => null,
            'payload_json' => [
                'homepage' => [
                    'sections' => $this->serializeSections($sections),
                ],
            ],
            'status' => 'draft',
            'draft_notes' => 'Homepage editor snapshot',
            'created_by' => $userId,
            'updated_by' => $userId,
            'approved_by' => null,
            'scheduled_at' => null,
            'published_at' => null,
        ]);
    }

    /**
     * @param  array<int, HomepageSectionDTO>  $sections
     * @return array<int, array<string, mixed>>
     */
    private function serializeSections(array $sections): array
    {
        return array_values(array_map(
            fn (HomepageSectionDTO $section): array => [
                'id' => $section->id,
                'key' => $section->key,
                'sortOrder' => $section->sortOrder,
                'isEnabled' => $section->isEnabled,
                'payload' => HomepagePayloadMapper::sectionDataToArray($section->payload),
                'arabicPayload' => HomepagePayloadMapper::sectionDataToArray($section->arabicPayload ?? $section->payload),
                'englishPayload' => HomepagePayloadMapper::sectionDataToArray($section->englishPayload ?? $section->payload),
                'arabicTranslation' => $this->translationToArray($section->arabicTranslation),
                'englishTranslation' => $this->translationToArray($section->englishTranslation),
            ],
            $sections,
        ));
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

    private function resolveActorId(?int $preferred = null): int
    {
        if ($preferred !== null) {
            return $preferred;
        }

        $currentUserId = $this->currentUserId();

        if ($currentUserId !== null) {
            return $currentUserId;
        }

        $fallbackId = User::query()->orderBy('id')->value('id');

        if (is_int($fallbackId)) {
            return $fallbackId;
        }

        throw new \RuntimeException('A user record is required before homepage drafts can be saved.');
    }

    private function currentUserId(): ?int
    {
        $user = $this->authFactory->guard((string) config('auth.admin_guard', 'web'))->user();

        return $user !== null && is_numeric($user->getAuthIdentifier()) ? (int) $user->getAuthIdentifier() : null;
    }
}
