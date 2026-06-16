<?php

declare(strict_types=1);

namespace App\Services\Homepage;

use App\Contracts\Shared\AuditServiceInterface;
use App\Contracts\Shared\CacheServiceInterface;
use App\Contracts\Homepage\HomepagePublishingServiceInterface;
use App\Contracts\Homepage\HomepageSectionServiceInterface;
use App\DTOs\Page\DraftPayloadDTO;
use App\DTOs\Homepage\HomepageDraftDataDTO;
use App\DTOs\Homepage\HomepageDraftDTO;
use App\DTOs\Homepage\HomepageSectionDataDTO;
use App\DTOs\Homepage\HomepageSectionDTO;
use App\Events\DraftConflictDetected;
use App\Exceptions\ConflictException;
use App\Models\Homepage\HomepageDraft;
use App\Models\Homepage\HomepageSection;
use App\Models\Homepage\HomepageSectionTranslation;
use App\Models\User\User;
use App\Support\HomepageDraftSectionMapper;
use App\Support\HomepagePayloadMapper;
use App\Support\HtmlSanitizer;
use App\Support\UrlSanitizer;
use DateTimeInterface;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

final class HomepagePublishingService implements HomepagePublishingServiceInterface
{
    public function __construct(
        private readonly HomepageSectionServiceInterface $homepageSectionService,
        private readonly CacheServiceInterface $cacheService,
        private readonly AuditServiceInterface $auditService,
        private readonly HtmlSanitizer $htmlSanitizer,
        private readonly PreviewTokenStore $previewTokenStore,
    ) {}

    public function saveDraft(HomepageDraftDataDTO $payload, int $userId, ?int $expectedVersion = null): HomepageDraftDTO
    {
        $this->authorizeHomepage($userId, 'update');

        // Optimistic locking: check version if expectedVersion is provided
        if ($expectedVersion !== null) {
            $currentDraft = HomepageDraft::query()
                ->where('target_type', 'homepage')
                ->whereIn('status', ['draft', 'scheduled'])
                ->latest('version')
                ->first();

            if ($currentDraft instanceof HomepageDraft
                && (int) $currentDraft->version !== $expectedVersion
                && ! $this->isOlderEditableDraftResidue((int) $currentDraft->version, $expectedVersion)
            ) {
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

        $nextVersion = $this->nextDraftVersion();

        $sections = HomepageDraftSectionMapper::normalizeForEditableDraft(
            $payload->sections,
            $this->homepageSectionService->getSections(),
        );
        $draft = HomepageDraft::forceCreate([
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

        $this->supersedeOtherEditableDrafts((int) $draft->getKey(), $userId);
        $this->invalidatePreviewTokens('homepage.draft_saved', $userId);

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
        $this->authorizeHomepage($userId, 'publish');

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
                        'is_enabled' => true,
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
                        'payload_json' => $this->sanitizeSectionPayload(
                            $this->sectionPayloadToArray($section->arabicPayload ?? $section->payload),
                        ),
                    ],
                );

                HomepageSectionTranslation::query()->updateOrCreate(
                    [
                        'section_id' => (int) $sectionModel->getKey(),
                        'locale' => 'en',
                    ],
                    [
                        'payload_json' => $this->sanitizeSectionPayload(
                            $this->sectionPayloadToArray($section->englishPayload ?? $section->payload),
                        ),
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

            $this->supersedeOtherEditableDrafts((int) $draft->getKey(), $userId);
        });

        $this->invalidateHomepageCache();
        $this->invalidatePreviewTokens('homepage.publish', $userId);

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
        $this->authorizeHomepage($userId, 'publish');

        if ($targetType !== 'homepage' || $targetId !== null) {
            return false;
        }

        HomepageSection::query()
            ->whereIn('key', HomepageSectionServiceInterface::SECTION_KEYS)
            ->update(['is_enabled' => false]);

        $this->invalidateHomepageCache();
        $this->invalidatePreviewTokens('homepage.unpublish', $userId);

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
        $this->authorizeHomepage($userId, 'publish');

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

    public function publishDueScheduled(): int
    {
        $published = 0;

        HomepageDraft::query()
            ->where('target_type', 'homepage')
            ->where('status', 'scheduled')
            ->whereNotNull('scheduled_at')
            ->where('scheduled_at', '<=', now())
            ->orderBy('scheduled_at')
            ->get()
            ->each(function (HomepageDraft $draft) use (&$published): void {
                $actorId = $this->scheduledPublishActorId($draft);

                if ($actorId !== null && $this->publish((int) $draft->getKey(), $actorId)) {
                    $published++;
                }
            });

        return $published;
    }

    public function latestEditableDraftVersion(): ?int
    {
        $draft = HomepageDraft::query()
            ->where('target_type', 'homepage')
            ->whereIn('status', ['draft', 'scheduled'])
            ->latest()
            ->first();

        return $draft instanceof HomepageDraft ? (int) $draft->version : null;
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
            version: (int) ($draft->version ?? 1),
        );
    }

    /**
     * @return array<int, HomepageSectionDTO>
     */
    private function sectionsFromDraft(HomepageDraft $draft): array
    {
        return HomepageDraftSectionMapper::sectionsFromStoredDraft(
            is_array($draft->payload_json) ? $draft->payload_json : [],
            $this->homepageSectionService->getSections(),
        );
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

    /**
     * @param  array<int, HomepageSectionDTO>  $sections
     * @return array<int, array<string, mixed>>
     */
    private function serializeSections(array $sections): array
    {
        return HomepagePayloadMapper::serializeSections($sections);
    }

    /**
     * @return array<string, mixed>
     */
    private function sectionPayloadToArray(HomepageSectionDataDTO $payload): array
    {
        return HomepagePayloadMapper::sectionDataToArray($payload);
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

    /**
     * Sanitize all string content in a section payload array before persistence.
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function sanitizeSectionPayload(array $payload): array
    {
        return $this->sanitizePayloadValue($payload);
    }

    /**
     * @param  array<string, mixed>|list<mixed>|string|mixed  $value
     * @return array<string, mixed>|list<mixed>|string|mixed
     */
    private function sanitizePayloadValue(mixed $value, ?string $key = null): mixed
    {
        if (is_array($value)) {
            foreach ($value as $childKey => $childValue) {
                $value[$childKey] = $this->sanitizePayloadValue($childValue, is_string($childKey) ? $childKey : null);
            }

            return $value;
        }

        if (! is_string($value) || $value === '') {
            return $value;
        }

        if ($this->isUrlPayloadKey($key)) {
            return UrlSanitizer::sanitize($value);
        }

        return $this->htmlSanitizer->sanitize($value);
    }

    private function isUrlPayloadKey(?string $key): bool
    {
        if ($key === null) {
            return false;
        }

        $normalized = strtolower($key);

        return str_contains($normalized, 'url')
            || str_contains($normalized, 'href')
            || str_contains($normalized, 'link');
    }

    private function invalidateHomepageCache(): void
    {
        foreach (['ar', 'en'] as $locale) {
            $this->cacheService->forget('public_pages:'.sha1($locale.'|'.$locale.'|'));

            if (! $this->cacheService->flushTags(['public-pages', 'public-shell', 'public-shell:'.$locale])) {
                $this->cacheService->flushAll();
            }
        }
    }

    private function scheduledPublishActorId(HomepageDraft $draft): ?int
    {
        foreach ([$draft->approved_by, $draft->updated_by, $draft->created_by] as $actorId) {
            if (is_numeric($actorId) && User::query()->whereKey((int) $actorId)->exists()) {
                return (int) $actorId;
            }
        }

        return null;
    }

    private function nextDraftVersion(): int
    {
        $latestVersion = HomepageDraft::query()
            ->where('target_type', 'homepage')
            ->max('version');

        return is_numeric($latestVersion) ? ((int) $latestVersion) + 1 : 1;
    }

    private function isOlderEditableDraftResidue(int $currentEditableVersion, int $expectedVersion): bool
    {
        if ($currentEditableVersion >= $expectedVersion) {
            return false;
        }

        return HomepageDraft::query()
            ->where('target_type', 'homepage')
            ->where('version', $expectedVersion)
            ->exists();
    }

    private function supersedeOtherEditableDrafts(int $currentDraftId, int $userId): void
    {
        HomepageDraft::query()
            ->where('target_type', 'homepage')
            ->whereKeyNot($currentDraftId)
            ->whereIn('status', ['draft', 'scheduled'])
            ->update([
                'status' => 'superseded',
                'updated_by' => $userId,
                'scheduled_at' => null,
            ]);
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
    public function discardEditableDraft(int $userId): int
    {
        $this->authorizeHomepage($userId, 'update');

        $deleted = HomepageDraft::query()
            ->where('target_type', 'homepage')
            ->whereIn('status', ['draft', 'scheduled'])
            ->delete();

        if ($deleted > 0) {
            $this->invalidatePreviewTokens('homepage.draft_discarded', $userId);
            $this->auditService->log('homepage.draft_discarded', $userId, HomepageDraft::class, metadata: [
                'deleted_count' => $deleted,
            ]);
        }

        return $deleted;
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

    private function authorizeHomepage(int $userId, string $ability): void
    {
        $user = User::query()->find($userId);

        $gateAbility = $ability === 'publish' ? 'publish-content' : 'manage-homepage';

        if (! $user instanceof User || Gate::forUser($user)->denies($gateAbility)) {
            throw new AuthorizationException('This user is not authorized to manage the homepage.');
        }
    }

    private function invalidatePreviewTokens(string $reason, int $userId): void
    {
        $deleted = $this->previewTokenStore->invalidateTarget('homepage');

        if ($deleted > 0) {
            $this->auditService->log('preview.invalidated', $userId, \App\Models\PreviewToken::class, metadata: [
                'target_type' => 'homepage',
                'target_id' => null,
                'deleted_count' => $deleted,
                'reason' => $reason,
            ]);
        }
    }
}
