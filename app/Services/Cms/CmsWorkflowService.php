<?php

declare(strict_types=1);

namespace App\Services\Cms;

use App\Contracts\Cms\AboutEntityCmsServiceInterface;
use App\Contracts\Cms\CmsTargetRegistryInterface;
use App\Contracts\Cms\CmsWorkflowServiceInterface;
use App\Contracts\Faculty\FacultyStudyPlanLinkServiceInterface;
use App\Contracts\Media\MediaServiceInterface;
use App\Contracts\News\NewsArticleCmsServiceInterface;
use App\Contracts\Shared\AuditServiceInterface;
use App\Contracts\Shared\CacheServiceInterface;
use App\DTOs\Cms\CmsDraftDTO;
use App\DTOs\Cms\CmsPreviewTokenDTO;
use App\DTOs\Cms\CmsPublishReadinessDTO;
use App\DTOs\Cms\CmsTargetDTO;
use App\Enums\PublicationStatus;
use App\Exceptions\ConflictException;
use App\Jobs\InvalidateCmsCache;
use App\Models\Cms\CmsDraft;
use App\Models\Cms\CmsTargetContent;
use App\Models\Shared\PreviewToken;
use App\Models\User\User;
use App\Services\Preview\PreviewTokenStore;
use DateTimeInterface;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Throwable;

final class CmsWorkflowService implements CmsWorkflowServiceInterface
{
    /** @var list<string> */
    private const RESEARCH_FACULTY_SLUGS = ['medicine', 'dentistry', 'pharmacy', 'artificial-intelligence', 'building-construction-engineering', 'petroleum', 'business-administration'];

    /** @var list<string> */
    private const RESEARCH_THEME_SLUGS = ['ai-ml', 'pharmaceutical-sciences', 'clinical-medicine', 'dental-sciences', 'petroleum-engineering', 'construction-engineering', 'business-administration', 'medical-education', 'biomedical-engineering', 'energy-systems', 'data-science', 'structural-engineering'];

    public function __construct(
        private readonly CmsTargetRegistryInterface $targetRegistry,
        private readonly AuditServiceInterface $auditService,
        private readonly CacheServiceInterface $cacheService,
        private readonly PreviewTokenStore $previewTokenStore,
        private readonly MediaServiceInterface $mediaService,
        private readonly AboutEntityCmsServiceInterface $aboutEntityCmsService,
        private readonly NewsArticleCmsServiceInterface $newsArticleCmsService,
        private readonly FacultyStudyPlanLinkServiceInterface $studyPlanLinkService,
    ) {}

    /** @param array<string, mixed> $payload */
    public function saveDraft(string $targetKey, array $payload, int $userId, ?int $expectedVersion = null): CmsDraftDTO
    {
        $target = $this->requireTarget($targetKey);
        $this->authorizeTargetWrite($target, $userId, $payload);

        return DB::transaction(function () use ($target, $payload, $userId, $expectedVersion): CmsDraftDTO {
            $latestDraft = $this->latestEditableDraft($target->key);
            $currentVersion = $latestDraft instanceof CmsDraft ? (int) $latestDraft->version : null;

            if ($expectedVersion !== null && $currentVersion !== null && $expectedVersion !== $currentVersion) {
                throw new ConflictException('CMS draft has been modified by another editor.', $currentVersion);
            }

            $hasScheduledRelease = CmsDraft::query()
                ->where('target_key', $target->key)
                ->where('status', PublicationStatus::Scheduled->value)
                ->exists();
            $nextVersion = ((int) CmsDraft::query()
                ->where('target_key', $target->key)
                ->max('version')) + 1;

            CmsDraft::query()
                ->where('target_key', $target->key)
                ->where('status', PublicationStatus::Draft->value)
                ->update(['status' => PublicationStatus::Superseded->value]);

            $draft = CmsDraft::query()->create([
                'target_key' => $target->key,
                'payload_json' => $payload,
                'status' => PublicationStatus::Draft->value,
                'created_by' => $userId,
                'updated_by' => $userId,
                'version' => $nextVersion,
            ]);

            if (! $hasScheduledRelease) {
                $this->aboutEntityCmsService->markDraft($target->key);
                $this->newsArticleCmsService->markDraft($target->key);
            }

            $this->auditService->log('cms.draft.saved', $userId, CmsDraft::class, (int) $draft->getKey(), [
                'target_key' => $target->key,
                'version' => (int) $draft->version,
            ]);

            return $this->draftDto($draft);
        });
    }

    public function preview(string $targetKey, string $locale, int $userId, ?string $device = null): CmsPreviewTokenDTO
    {
        $target = $this->requireTarget($targetKey);
        $this->authorizePreview($target, $userId);

        $draft = $this->latestEditableDraft($target->key);
        $payload = $draft instanceof CmsDraft && is_array($draft->payload_json) ? $draft->payload_json : [];

        $result = $this->previewTokenStore->createWithPayload('cms', null, $locale, $userId, [
            'target_key' => $target->key,
            'payload' => $payload,
            'draft_id' => $draft instanceof CmsDraft ? (int) $draft->getKey() : null,
            'draft_version' => $draft instanceof CmsDraft ? (int) $draft->version : null,
        ], $device);

        $previewToken = $result['model'];

        $this->auditService->log('cms.preview.created', $userId, PreviewToken::class, (int) $previewToken->getKey(), [
            'target_key' => $target->key,
            'locale' => $locale,
            'expires_at' => $previewToken->expires_at?->toIso8601String(),
        ]);

        return new CmsPreviewTokenDTO(
            token: $result['raw_token'],
            targetKey: $target->key,
            locale: $locale,
            previewUrl: '/'.$locale.'/preview?token='.$result['raw_token'],
            expiresAt: $previewToken->expires_at?->toIso8601String(),
            device: $device,
        );
    }

    public function publish(string $targetKey, int $userId): bool
    {
        $target = $this->requireTarget($targetKey);
        $this->authorizePublish($target, $userId);

        $draft = $this->requireLatestEditableDraft($target->key);
        $this->assertReadyForPublish($target->key, is_array($draft->payload_json) ? $draft->payload_json : []);

        $published = DB::transaction(fn (): bool => $this->publishDraft($draft, $userId));

        if ($published) {
            $this->invalidatePublishedTarget($target->key, $userId, 'cms.published');
        }

        return $published;
    }

    public function schedule(string $targetKey, DateTimeInterface $publishAt, int $userId): bool
    {
        $target = $this->requireTarget($targetKey);
        $this->authorizePublish($target, $userId);

        if ($publishAt <= now()) {
            throw new \InvalidArgumentException('Scheduled publish time must be in the future.');
        }

        $draft = $this->requireLatestEditableDraft($target->key);
        $this->assertReadyForPublish($target->key, is_array($draft->payload_json) ? $draft->payload_json : []);

        DB::transaction(function () use ($draft, $publishAt, $userId, $target): void {
            CmsDraft::query()
                ->where('target_key', $target->key)
                ->whereKeyNot((int) $draft->getKey())
                ->where('status', PublicationStatus::Scheduled->value)
                ->update(['status' => PublicationStatus::Superseded->value]);

            $draft->forceFill([
                'status' => PublicationStatus::Scheduled->value,
                'scheduled_at' => $publishAt,
                'approved_by' => $userId,
                'updated_by' => $userId,
            ])->save();
            $this->aboutEntityCmsService->markScheduled($target->key);
            $this->newsArticleCmsService->markScheduled($target->key);
        });

        $this->auditService->log('cms.publish.scheduled', $userId, CmsDraft::class, (int) $draft->getKey(), [
            'target_key' => $target->key,
            'scheduled_at' => $draft->scheduled_at?->toIso8601String(),
        ]);

        return true;
    }

    public function unpublish(string $targetKey, int $userId): bool
    {
        $target = $this->requireTarget($targetKey);
        $this->authorizePublish($target, $userId);

        $unpublished = DB::transaction(function () use ($target, $userId): bool {
            $content = CmsTargetContent::query()->where('target_key', $target->key)->first();
            $entityUnpublished = $this->aboutEntityCmsService->unpublishTarget($target->key);
            $newsArticleUnpublished = $this->newsArticleCmsService->unpublishTarget($target->key);
            $hasWorkingDraft = CmsDraft::query()
                ->where('target_key', $target->key)
                ->where('status', PublicationStatus::Draft->value)
                ->exists();
            $cancelledSchedules = CmsDraft::query()
                ->where('target_key', $target->key)
                ->where('status', PublicationStatus::Scheduled->value)
                ->update([
                    'status' => $hasWorkingDraft ? PublicationStatus::Superseded->value : PublicationStatus::Draft->value,
                    'scheduled_at' => null,
                    'approved_by' => null,
                    'updated_by' => $userId,
                ]);

            if ($content instanceof CmsTargetContent) {
                $content->forceFill([
                    'status' => PublicationStatus::Draft->value,
                    'updated_by' => $userId,
                ])->save();
            }

            return $content instanceof CmsTargetContent || $entityUnpublished || $newsArticleUnpublished || $cancelledSchedules > 0;
        });

        if (! $unpublished) {
            return false;
        }

        $this->invalidatePublishedTarget($target->key, $userId, 'cms.unpublished');

        return true;
    }

    /** @param array<string, mixed>|null $payload */
    public function readiness(string $targetKey, ?array $payload = null): CmsPublishReadinessDTO
    {
        $target = $this->requireTarget($targetKey);
        $payload ??= $this->latestEditablePayload($target->key);

        if ($payload === null || $payload === []) {
            return new CmsPublishReadinessDTO(false, [
                'content' => ['A draft payload is required before publishing.'],
            ]);
        }

        $errors = [];

        foreach ($target->locales as $locale) {
            $translation = $this->localePayload($payload, $locale);

            if (! $this->hasTitleLikeContent($translation)) {
                $errors[$locale][] = 'A localized title or headline is required.';
            }

            if (! $this->hasBodyLikeContent($translation)) {
                $errors[$locale][] = 'Localized body content or content blocks are required.';
            }
        }

        if ($target->key === 'news.gallery') {
            $this->appendGalleryReadinessErrors($payload, $target->locales, $errors);
        }

        if ($target->key === 'news.articles') {
            $this->appendNewsArticlesReadinessErrors($payload, $target->locales, $errors);
        }

        if ($target->key === 'about.vision-mission') {
            $this->appendVisionMissionReadinessErrors($payload, $target->locales, $errors);
        }

        if ($target->key === 'research.centers') {
            $this->appendResearchCentersReadinessErrors($payload, $target->locales, $errors);
        }

        if ($target->key === 'research.projects') {
            $this->appendResearchProjectsReadinessErrors($payload, $target->locales, $errors);
        }

        if ($target->key === 'research.themes') {
            $this->appendResearchThemesReadinessErrors($payload, $target->locales, $errors);
        }

        if (in_array($target->key, ['research.publications', 'research.experts', 'research.conferences', 'research.policies'], true)) {
            $this->appendResearchCatalogReadinessErrors($target->key, $payload, $target->locales, $errors);
        }

        if ($target->key === 'campus_life.jobs') {
            $this->appendCampusLifeJobsReadinessErrors($payload, $target->locales, $errors);
        }

        if ($target->key === 'campus_life.landing') {
            $this->appendCampusLifeLandingReadinessErrors($payload, $target->locales, $errors);
        }

        if ($target->key === 'campus_life.virtual_tour') {
            $this->appendVirtualTourReadinessErrors($payload, $target->locales, $errors);
        }

        if ($target->key === 'e_services.suggestions-complaints') {
            $this->appendSuggestionsComplaintsReadinessErrors($payload, $target->locales, $errors);
        }

        if (str_starts_with($target->key, 'e_services.') && $target->key !== 'e_services.suggestions-complaints') {
            $this->appendEServicesDetailReadinessErrors($target->key, $payload, $target->locales, $errors);
        }

        if (str_starts_with($target->key, 'admissions.')) {
            $this->appendAdmissionsReadinessErrors($target->key, $payload, $target->locales, $errors);
        }

        if (str_ends_with($target->key, '.departments') && str_starts_with($target->key, 'facilities.')) {
            foreach ($this->studyPlanLinkService->validationErrors($target->key, $payload) as $field => $messages) {
                $errors[$field] = array_values(array_unique([
                    ...($errors[$field] ?? []),
                    ...$messages,
                ]));
            }
        }

        if (str_ends_with($target->key, '.research') && str_starts_with($target->key, 'facilities.')) {
            foreach ($target->locales as $locale) {
                $translation = $this->localePayload($payload, $locale);

                foreach (['seoTitle', 'seoDescription', 'seoImage'] as $field) {
                    if (! $this->filledString($translation[$field] ?? null)) {
                        $errors[$locale][] = "The research {$field} field is required.";
                    }
                }
            }
        }

        if ($target->key === 'facilities.pharmacy.training') {
            $this->appendPharmacyTrainingReadinessErrors($payload, $target->locales, $errors);
        }

        if (str_ends_with($target->key, '.study_plan') && str_starts_with($target->key, 'facilities.')) {
            foreach ($this->studyPlanLinkService->studyPlanValidationErrors($target->key, $payload) as $field => $messages) {
                $errors[$field] = array_values(array_unique([
                    ...($errors[$field] ?? []),
                    ...$messages,
                ]));
            }
        }

        foreach ($this->aboutEntityCmsService->publishErrors($target->key, $payload) as $field => $messages) {
            $errors[$field] = array_values(array_unique([
                ...($errors[$field] ?? []),
                ...$messages,
            ]));
        }

        foreach ($this->newsArticleCmsService->publishErrors($target->key, $payload) as $field => $messages) {
            $errors[$field] = array_values(array_unique([
                ...($errors[$field] ?? []),
                ...$messages,
            ]));
        }

        return new CmsPublishReadinessDTO($errors === [], $errors);
    }

    public function latestEditableDraftVersion(string $targetKey, int $userId): ?int
    {
        $target = $this->requireTarget($targetKey);
        $this->authorizeTargetWrite($target, $userId);

        $draft = $this->latestEditableDraft($targetKey);

        return $draft instanceof CmsDraft ? (int) $draft->version : null;
    }

    /** @return array<string, mixed>|null */
    public function latestEditableDraftPayload(string $targetKey, int $userId): ?array
    {
        $target = $this->requireTarget($targetKey);
        $this->authorizeTargetWrite($target, $userId);

        return $this->latestEditablePayload($targetKey);
    }

    /** @return array<string, mixed>|null */
    /**
     * Published payloads are read constantly and written rarely. Rendering one
     * public page asked this question 23 times for 10 distinct keys, because the
     * research availability check consults it for every menu item across the
     * header, footer and utility trees — roughly a third of the query cost of a
     * cold render, all of it re-reading rows that had not changed.
     *
     * Caching is safe rather than convenient here: every publish path already
     * flushes the 'cms' and 'cms:<key>' tags through InvalidateCmsCache, and it
     * does so inline inside DB::afterCommit, so freshness does not depend on a
     * queue worker running. No publish path reads through this method, so a
     * cached value can never feed back into a write.
     *
     * The absence of a payload is cached too, wrapped in an array. A bare null
     * reads as a cache miss, so unpublished targets — the ones the availability
     * check asks about most — would otherwise re-query on every single call and
     * gain nothing at all.
     */
    public function getPublishedPayload(string $targetKey): ?array
    {
        // Stays outside the cached closure so an unknown key still throws
        // rather than being answered from cache.
        $this->requireTarget($targetKey);

        $cached = $this->cacheService
            ->tags(['cms', 'cms:'.$targetKey])
            ->remember(
                'cms.published-payload.'.$targetKey,
                function () use ($targetKey): array {
                    $content = CmsTargetContent::query()
                        ->where('target_key', $targetKey)
                        ->where('status', PublicationStatus::Published->value)
                        ->first();

                    return ['payload' => $content instanceof CmsTargetContent && is_array($content->payload_json)
                        ? $content->payload_json
                        : null];
                },
                (int) config('cache.public_page_ttl', 3600),
            );

        return is_array($cached) && is_array($cached['payload'] ?? null)
            ? $cached['payload']
            : null;
    }

    public function publishDueScheduled(): int
    {
        $drafts = CmsDraft::query()
            ->where('status', PublicationStatus::Scheduled->value)
            ->where('scheduled_at', '<=', now())
            ->orderBy('scheduled_at')
            ->get();

        $published = 0;

        foreach ($drafts as $draft) {
            if (! $draft instanceof CmsDraft) {
                continue;
            }

            $target = $this->requireTarget($draft->target_key);
            $userId = $draft->approved_by !== null ? (int) $draft->approved_by : (int) $draft->created_by;

            try {
                $this->authorizePublish($target, $userId);
            } catch (AuthorizationException) {
                continue;
            }

            $readiness = $this->readiness($draft->target_key, is_array($draft->payload_json) ? $draft->payload_json : []);

            if (! $readiness->isReady) {
                continue;
            }

            if (DB::transaction(fn (): bool => $this->publishDraft($draft, $userId))) {
                $this->invalidatePublishedTarget($draft->target_key, $userId, 'cms.published');
                $published++;
            }
        }

        return $published;
    }

    private function publishDraft(CmsDraft $draft, int $userId): bool
    {
        $wasScheduled = $draft->status === PublicationStatus::Scheduled->value;
        $publishedAt = now();
        $payload = is_array($draft->payload_json) ? $draft->payload_json : [];
        $this->aboutEntityCmsService->publishTarget($draft->target_key, $payload, $publishedAt);
        $this->newsArticleCmsService->publishTarget($draft->target_key, $payload, $publishedAt, $userId);

        CmsTargetContent::query()->updateOrCreate(
            ['target_key' => $draft->target_key],
            [
                'payload_json' => $payload,
                'status' => PublicationStatus::Published->value,
                'updated_by' => $userId,
                'published_at' => $publishedAt,
            ],
        );

        CmsDraft::query()
            ->where('target_key', $draft->target_key)
            ->whereKeyNot((int) $draft->getKey())
            ->whereIn('status', $wasScheduled
                ? [PublicationStatus::Scheduled->value]
                : PublicationStatus::editableValues())
            ->update(['status' => PublicationStatus::Superseded->value]);

        $draft->forceFill([
            'status' => PublicationStatus::Published->value,
            'scheduled_at' => null,
            'published_at' => $publishedAt,
            'approved_by' => $userId,
            'updated_by' => $userId,
        ])->save();

        return true;
    }

    private function invalidatePublishedTarget(string $targetKey, int $userId, string $auditAction): void
    {
        $this->previewTokenStore->invalidateCmsTarget($targetKey);

        $this->scheduleCacheInvalidationAfterCommit($targetKey);

        $this->auditService->log($auditAction, $userId, CmsTargetContent::class, null, [
            'target_key' => $targetKey,
        ]);
    }

    private function scheduleCacheInvalidationAfterCommit(string $targetKey): void
    {
        DB::afterCommit(function () use ($targetKey): void {
            try {
                InvalidateCmsCache::invalidate($this->cacheService, $targetKey);
            } catch (Throwable $exception) {
                Log::error('CMS cache invalidation failed after commit; queueing a retry.', [
                    'target_key' => $targetKey,
                    'exception' => $exception::class,
                    'message' => $exception->getMessage(),
                ]);

                try {
                    InvalidateCmsCache::dispatch($targetKey);
                } catch (Throwable $queueException) {
                    Log::error('CMS cache invalidation retry could not be queued.', [
                        'target_key' => $targetKey,
                        'exception' => $queueException::class,
                        'message' => $queueException->getMessage(),
                    ]);
                }
            }
        });
    }

    private function assertReadyForPublish(string $targetKey, array $payload): void
    {
        $readiness = $this->readiness($targetKey, $payload);

        if (! $readiness->isReady) {
            throw ValidationException::withMessages($readiness->errors);
        }
    }

    private function requireTarget(string $targetKey): CmsTargetDTO
    {
        $target = $this->targetRegistry->find($targetKey);

        if (! $target instanceof CmsTargetDTO || ! $target->supportsDraftWorkflow) {
            throw new \InvalidArgumentException('Unknown or unsupported CMS target.');
        }

        return $target;
    }

    private function requireLatestEditableDraft(string $targetKey): CmsDraft
    {
        $draft = $this->latestEditableDraft($targetKey);

        if (! $draft instanceof CmsDraft) {
            throw ValidationException::withMessages([
                'content' => ['A draft is required before publishing.'],
            ]);
        }

        return $draft;
    }

    private function latestEditableDraft(string $targetKey): ?CmsDraft
    {
        $draft = CmsDraft::query()
            ->where('target_key', $targetKey)
            ->where('status', PublicationStatus::Draft->value)
            ->latest('updated_at')
            ->latest('id')
            ->first();

        if (! $draft instanceof CmsDraft) {
            $draft = CmsDraft::query()
                ->where('target_key', $targetKey)
                ->where('status', PublicationStatus::Scheduled->value)
                ->latest('updated_at')
                ->latest('id')
                ->first();
        }

        return $draft instanceof CmsDraft ? $draft : null;
    }

    /** @return array<string, mixed>|null */
    private function latestEditablePayload(string $targetKey): ?array
    {
        $draft = $this->latestEditableDraft($targetKey);

        return $draft instanceof CmsDraft && is_array($draft->payload_json) ? $draft->payload_json : null;
    }

    private function authorizePreview(CmsTargetDTO $target, int $userId): void
    {
        $user = User::query()->find($userId);

        if (! $user instanceof User || Gate::forUser($user)->denies($this->previewAbilityForTarget($target))) {
            throw new AuthorizationException('This user is not authorized to preview CMS content.');
        }

        $this->authorizeTargetWrite($target, $userId);
    }

    private function authorizePublish(CmsTargetDTO $target, int $userId): void
    {
        $user = User::query()->find($userId);

        if (! $user instanceof User || Gate::forUser($user)->denies($this->publishAbilityForTarget($target))) {
            throw new AuthorizationException('This user is not authorized to publish CMS content.');
        }

        $this->authorizeTargetWrite($target, $userId);
    }

    /** @param array<string, mixed>|null $payload */
    private function authorizeTargetWrite(CmsTargetDTO $target, int $userId, ?array $payload = null): void
    {
        $user = User::query()->find($userId);
        $ability = $this->manageAbilityForTarget($target);

        if (! $user instanceof User || (bool) $user->is_locked || Gate::forUser($user)->denies($ability)) {
            throw new AuthorizationException('This user is not authorized to manage this CMS target.');
        }

        if ($user->role_slug === 'faculty_editor' && $target->facultyScopeSlug === null) {
            throw new AuthorizationException('Faculty editors cannot manage global CMS targets.');
        }

        if ($target->facultyScopeSlug !== null && $user->role_slug === 'faculty_editor') {
            $userScope = $this->canonicalFacultyScope((string) $user->faculty_scope_slug);

            if ($userScope === '' || $userScope !== $this->canonicalFacultyScope($target->facultyScopeSlug)) {
                throw new AuthorizationException('This faculty editor is not authorized to manage this faculty target.');
            }
        }

        $this->aboutEntityCmsService->authorizeTarget($target->key, $userId, $payload);
        $this->newsArticleCmsService->authorizeTarget($target->key, $userId, $payload);
    }

    private function canonicalFacultyScope(string $scope): string
    {
        return match ($scope) {
            'ai', 'ai-engineering' => 'artificial-intelligence',
            'construction' => 'building-construction-engineering',
            'business' => 'business-administration',
            default => $scope,
        };
    }

    private function manageAbilityForArea(string $area): string
    {
        return match ($area) {
            'homepage' => 'manage-homepage',
            'faculty', 'facilities', 'campus_life' => 'manage-faculties',
            'news' => 'manage-news',
            default => 'manage-pages',
        };
    }

    private function manageAbilityForTarget(CmsTargetDTO $target): string
    {
        return $target->key === 'campus_life.jobs'
            ? 'manage-jobs'
            : $this->manageAbilityForArea($target->area);
    }

    private function previewAbilityForTarget(CmsTargetDTO $target): string
    {
        return $target->key === 'campus_life.jobs'
            ? 'preview-jobs'
            : 'preview-content';
    }

    private function publishAbilityForTarget(CmsTargetDTO $target): string
    {
        return $target->key === 'campus_life.jobs'
            ? 'publish-jobs'
            : 'publish-content';
    }

    /** @param array<string, mixed> $payload */
    private function localePayload(array $payload, string $locale): array
    {
        $candidates = [
            $payload['translations'][$locale] ?? null,
            $payload[$locale] ?? null,
            $payload[$locale.'_translation'] ?? null,
        ];

        foreach ($candidates as $candidate) {
            if (is_array($candidate)) {
                return $candidate;
            }
        }

        $prefixed = [];

        foreach ($payload as $key => $value) {
            if (is_string($key) && str_starts_with($key, $locale.'_')) {
                $prefixed[substr($key, strlen($locale) + 1)] = $value;
            }
        }

        return $prefixed;
    }

    /** @param array<string, mixed> $payload */
    private function hasTitleLikeContent(array $payload): bool
    {
        foreach (['title', 'headline', 'name', 'full_name', 'label', 'navigation_label', 'navigationLabel'] as $key) {
            if ($this->filledString($payload[$key] ?? null)) {
                return true;
            }
        }

        return is_array($payload['hero'] ?? null) && $this->filledString($payload['hero']['title'] ?? null);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  array<int, string>  $locales
     * @param  array<string, array<int, string>>  $errors
     */
    private function appendAdmissionsReadinessErrors(string $targetKey, array $payload, array $locales, array &$errors): void
    {
        foreach ($locales as $locale) {
            $translation = $this->localePayload($payload, $locale);

            if ($this->containsAdmissionsPlaceholder($translation)) {
                $errors[$locale][] = 'Admissions content contains a known placeholder, fabricated reference value, or inert link.';
            }

            if ($targetKey === 'admissions.landing') {
                $hero = is_array($translation['hero'] ?? null) ? $translation['hero'] : [];
                $images = is_array($hero['images'] ?? null) ? $hero['images'] : [];
                $timeline = is_array($translation['timeline'] ?? null) ? $translation['timeline'] : [];
                $resources = is_array($translation['resources']['cards'] ?? null) ? $translation['resources']['cards'] : [];

                foreach (['campus', 'campusAlt', 'students', 'studentsAlt'] as $field) {
                    if (! $this->filledString($images[$field] ?? null)) {
                        $errors[$locale][] = "The Admissions hero {$field} field is required.";
                    }
                }
                if (! $this->filledString($timeline['imageAlt'] ?? null)) {
                    $errors[$locale][] = 'The Admissions timeline image alt text is required.';
                }
                foreach ($resources as $resource) {
                    $slug = is_array($resource) ? ($resource['slug'] ?? null) : null;
                    if (! is_string($slug) || ! in_array($slug, ['requirements', 'tuition', 'how-to-apply', 'faq', 'calendar', 'documents', 'transfer', 'filling-vacancies', 'graduation-exams'], true)) {
                        $errors[$locale][] = 'Every Admissions resource must link to a supported Admissions route.';
                    }
                }
                $resourceSlugs = collect($resources)
                    ->filter(static fn (mixed $resource): bool => is_array($resource) && is_string($resource['slug'] ?? null))
                    ->pluck('slug')
                    ->all();
                $requiredResourceSlugs = ['requirements', 'tuition', 'how-to-apply', 'faq', 'calendar', 'documents', 'transfer', 'filling-vacancies'];
                if (array_diff($requiredResourceSlugs, $resourceSlugs) !== [] || count($resourceSlugs) !== count(array_unique($resourceSlugs))) {
                    $errors[$locale][] = 'The Admissions landing must include each approved resource route exactly once.';
                }
            }

            if ($targetKey === 'admissions.tuition') {
                $rows = is_array($translation['feeRows'] ?? null) ? $translation['feeRows'] : [];
                $methods = is_array($translation['methods'] ?? null) ? $translation['methods'] : [];

                if ($rows === [] && ! $this->filledString($translation['availabilityGuidance'] ?? null)) {
                    $errors[$locale][] = 'Transparent tuition availability guidance is required when no verified fee rows are published.';
                }
                foreach ($rows as $row) {
                    if (! is_array($row) || ! $this->admissionsFieldsAreFilled($row, ['faculty', 'type', 'tuitionFee', 'registrationFee', 'additionalFees'])) {
                        $errors[$locale][] = 'Every tuition row requires complete verified fee data.';
                    }
                }
                if ($methods === [] && ! $this->filledString($translation['paymentGuidance'] ?? null)) {
                    $errors[$locale][] = 'Transparent payment guidance is required when no verified payment method is published.';
                }
                foreach ($methods as $method) {
                    if (! is_array($method) || ! $this->admissionsFieldsAreFilled($method, ['title', 'desc'])) {
                        $errors[$locale][] = 'Every payment method requires a title and description.';

                        continue;
                    }
                    $ctaUrl = $method['ctaUrl'] ?? null;
                    if ($this->filledString($ctaUrl) && ! $this->isSafeAdmissionsPaymentUrl($ctaUrl)) {
                        $errors[$locale][] = 'Payment actions must use a non-placeholder HTTPS URL.';
                    }
                    if ($this->filledString($method['cta'] ?? null) !== $this->filledString($ctaUrl)) {
                        $errors[$locale][] = 'Payment action labels and URLs must be supplied together.';
                    }
                }
            }

            if ($targetKey === 'admissions.how-to-apply') {
                if (! $this->admissionsFieldsAreFilled($translation, ['applicationTitle', 'applicationGuidance'])) {
                    $errors[$locale][] = 'The admissions application title and non-guarantee guidance are required.';
                }
                $steps = is_array($translation['steps'] ?? null) ? $translation['steps'] : [];
                $hasApplicationAction = collect($steps)->contains(function (mixed $step): bool {
                    if (! is_array($step) || ! is_string($step['href'] ?? null)) {
                        return false;
                    }
                    $url = rtrim($step['href'], '/');

                    return str_ends_with($url, '/admissions/how-to-apply#application');
                });
                if (! $hasApplicationAction) {
                    $errors[$locale][] = 'At least one How to Apply step must link to the real #application form.';
                }
            }

            if ($targetKey === 'admissions.calendar') {
                $deadlines = is_array($translation['deadlines'] ?? null) ? $translation['deadlines'] : [];
                $semesters = is_array($translation['semesters'] ?? null) ? $translation['semesters'] : [];
                if ($deadlines === [] && $semesters === [] && ! $this->filledString($translation['scheduleGuidance'] ?? null)) {
                    $errors[$locale][] = 'Transparent calendar guidance is required when no approved dates are published.';
                }
                $this->appendAdmissionsDownloadError($translation['download'] ?? null, $locale, 'calendar', $errors);
            }

            if ($targetKey === 'admissions.documents') {
                if (! $this->filledString($translation['downloadGuidance'] ?? null)) {
                    $errors[$locale][] = 'Transparent document-download guidance is required.';
                }
                foreach ((array) ($translation['tabs'] ?? []) as $tab) {
                    if (! is_array($tab)) {
                        continue;
                    }
                    foreach ((array) ($tab['subTabs'] ?? []) as $subTab) {
                        if (is_array($subTab)) {
                            $this->appendAdmissionsDownloadError($subTab['download'] ?? null, $locale, 'checklist', $errors);
                        }
                    }
                }
            }
        }
    }

    /** @param array<string, array<int, string>> $errors */
    private function appendAdmissionsDownloadError(mixed $download, string $locale, string $label, array &$errors): void
    {
        if (! is_array($download) || $download === []) {
            return;
        }

        $href = $download['href'] ?? null;
        $mediaId = is_numeric($download['mediaId'] ?? null) ? (int) $download['mediaId'] : 0;
        $hasHref = $this->filledString($href);

        if (! $hasHref && $mediaId === 0) {
            return;
        }

        if (! $hasHref || $href === '#' || $mediaId <= 0 || ! $this->mediaService->publicDocumentsArePublishable([$mediaId])) {
            $errors[$locale][] = "The {$label} download must reference a reviewed main Media Library document.";
        }
    }

    /** @param array<string, mixed> $payload
     * @param  list<string>  $fields
     */
    private function admissionsFieldsAreFilled(array $payload, array $fields): bool
    {
        foreach ($fields as $field) {
            if (! $this->filledString($payload[$field] ?? null)) {
                return false;
            }
        }

        return true;
    }

    private function isSafeAdmissionsPaymentUrl(mixed $url): bool
    {
        return is_string($url)
            && filter_var($url, FILTER_VALIDATE_URL) !== false
            && parse_url($url, PHP_URL_SCHEME) === 'https'
            && ! str_contains(mb_strtolower($url), 'example.');
    }

    private function containsAdmissionsPlaceholder(mixed $value): bool
    {
        if (is_array($value)) {
            foreach ($value as $item) {
                if ($this->containsAdmissionsPlaceholder($item)) {
                    return true;
                }
            }

            return false;
        }

        if (! is_string($value)) {
            return false;
        }

        $normalized = mb_strtolower(trim($value));
        $known = [
            '#', 'applications open', 'التقديم مفتوح', 'main national bank', 'المصرف الوطني الرئيسي',
            'sy12345678901234567890', '$15,000', '$13,500', '$500', '$300', '$250 (lab)', '$350 (materials)',
            '15 aug 2026', '15 آب 2026', '01 jan 2026', '01 كانون الثاني 2026', '2026/2027',
            'sept 15, 2026', 'sept 1, 2026', 'jan 10, 2027', 'fall 2026', 'spring 2027',
            'pdf, 2.4 mb', 'pdf, 1.2 mb', 'pdf, 280 kb', 'pdf, 310 kb', 'pdf, 295 kb', 'pdf, 340 kb',
        ];

        return in_array($normalized, $known, true)
            || str_contains($normalized, 'lorem ipsum')
            || str_contains($normalized, 'placeholder')
            || str_contains($normalized, 'example.com');
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  array<int, string>  $locales
     * @param  array<string, array<int, string>>  $errors
     */
    private function appendGalleryReadinessErrors(array $payload, array $locales, array &$errors): void
    {
        $localeIds = [];

        foreach ($locales as $locale) {
            $translation = $this->localePayload($payload, $locale);
            $items = is_array($translation['items'] ?? null) ? $translation['items'] : [];
            $ids = [];

            foreach ($items as $item) {
                if (! is_array($item) || ! is_int($item['mediaId'] ?? null) || $item['mediaId'] <= 0) {
                    $errors[$locale][] = 'Every gallery item must reference a Media Library image.';

                    continue;
                }

                $ids[] = $item['mediaId'];
            }

            if ($items === []) {
                $errors[$locale][] = 'At least one gallery image is required.';
            } elseif (count(array_unique($ids)) !== count($ids)) {
                $errors[$locale][] = 'Gallery image references must be unique.';
            } elseif (count($ids) === count($items) && ! $this->mediaService->publicImagesArePublishable($ids)) {
                $errors[$locale][] = 'Gallery images must be reviewed main-library images with AR and EN titles and alt text.';
            }

            $localeIds[$locale] = $ids;
        }

        $expectedIds = null;
        foreach ($localeIds as $locale => $ids) {
            if ($expectedIds === null) {
                $expectedIds = $ids;

                continue;
            }

            if ($ids !== $expectedIds) {
                $errors[$locale][] = 'Gallery image order must match across locales.';
            }
        }
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  array<int, string>  $locales
     * @param  array<string, array<int, string>>  $errors
     */
    private function appendVisionMissionReadinessErrors(array $payload, array $locales, array &$errors): void
    {
        $cardCounts = [];
        $pillarCounts = [];

        foreach ($locales as $locale) {
            $translation = $this->localePayload($payload, $locale);
            $sections = is_array($translation['sections'] ?? null) ? $translation['sections'] : [];
            $cards = is_array($sections['cards'] ?? null) ? $sections['cards'] : [];
            $pillars = is_array($sections['pillars'] ?? null) ? $sections['pillars'] : [];

            foreach (['heroImage', 'seoTitle', 'seoDescription', 'seoImage'] as $field) {
                if (! $this->filledString($translation[$field] ?? null)) {
                    $errors[$locale][] = "The {$field} field is required.";
                }
            }

            foreach (['cardsTitle', 'pillarsTitle'] as $field) {
                if (! $this->filledString($sections[$field] ?? null)) {
                    $errors[$locale][] = "The {$field} field is required.";
                }
            }

            if ($cards === []) {
                $errors[$locale][] = 'At least one Vision, Mission, or Values card is required.';
            }

            foreach ($cards as $card) {
                if (! is_array($card) || ! $this->filledString($card['icon'] ?? null) || ! $this->filledString($card['title'] ?? null) || ! $this->filledString($card['body'] ?? null)) {
                    $errors[$locale][] = 'Every Vision, Mission, and Values card requires an icon, title, and body.';
                }
            }

            if ($pillars === []) {
                $errors[$locale][] = 'At least one strategic pillar is required.';
            }

            foreach ($pillars as $pillar) {
                if (! is_array($pillar) || ! $this->filledString($pillar['title'] ?? null) || ! $this->filledString($pillar['summary'] ?? null)) {
                    $errors[$locale][] = 'Every strategic pillar requires a title and summary.';
                }
            }

            $cardCounts[$locale] = count($cards);
            $pillarCounts[$locale] = count($pillars);
        }

        if (count(array_unique($cardCounts)) > 1 || count(array_unique($pillarCounts)) > 1) {
            $errors['translations'][] = 'Vision and Mission card and pillar counts must match across locales.';
        }
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  array<int, string>  $locales
     * @param  array<string, array<int, string>>  $errors
     */
    private function appendEServicesDetailReadinessErrors(string $targetKey, array $payload, array $locales, array &$errors): void
    {
        $orderedIds = [];

        foreach ($locales as $locale) {
            $translation = $this->localePayload($payload, $locale);
            $hero = is_array($translation['hero'] ?? null) ? $translation['hero'] : [];
            $intro = is_array($translation['intro'] ?? null) ? $translation['intro'] : [];
            $resources = is_array($translation['resources'] ?? null) ? $translation['resources'] : [];
            $cta = is_array($translation['cta'] ?? null) ? $translation['cta'] : [];
            $seo = is_array($translation['seo'] ?? null) ? $translation['seo'] : [];

            foreach (['eyebrow', 'title', 'summary', 'image'] as $field) {
                if (! $this->filledString($hero[$field] ?? null)) {
                    $errors[$locale][] = "The hero {$field} field is required.";
                }
            }

            foreach (['title', 'body'] as $field) {
                if (! $this->filledString($intro[$field] ?? null)) {
                    $errors[$locale][] = "The intro {$field} field is required.";
                }
            }

            foreach (['title', 'body', 'label', 'url'] as $field) {
                if (! $this->filledString($cta[$field] ?? null)) {
                    $errors[$locale][] = "The call-to-action {$field} field is required.";
                }
            }
            if (! $this->isSafeLocalizedEServicesUrl($cta['url'] ?? null, $locale)) {
                $errors[$locale][] = 'The call-to-action must use a localized internal E-Services or contact URL.';
            }

            foreach (['title', 'description', 'image'] as $field) {
                if (! $this->filledString($seo[$field] ?? null)) {
                    $errors[$locale][] = "The SEO {$field} field is required.";
                }
            }

            $sections = is_array($translation['sections'] ?? null) ? $translation['sections'] : [];
            $relatedLinks = is_array($translation['relatedLinks'] ?? null) ? $translation['relatedLinks'] : [];
            $resourceLinks = is_array($resources['links'] ?? null) ? $resources['links'] : [];

            $orderedIds[$locale] = [
                'sections' => $this->validatedEServicesItems($sections, ['id', 'title', 'body'], 'guidance section', $locale, $errors),
                'relatedLinks' => $this->validatedEServicesItems($relatedLinks, ['id', 'title', 'url'], 'related link', $locale, $errors),
                'resourceLinks' => $this->validatedEServicesItems($resourceLinks, ['id', 'title', 'url'], 'resource link', $locale, $errors),
            ];

            if ($sections === []) {
                $errors[$locale][] = 'At least one guidance section is required.';
            }

            if ($relatedLinks === []) {
                $errors[$locale][] = 'At least one related E-Services link is required.';
            }
            foreach ($relatedLinks as $link) {
                $url = is_array($link) ? ($link['url'] ?? null) : null;
                if (! $this->isSafeLocalizedEServicesUrl($url, $locale)) {
                    $errors[$locale][] = 'Every related link must use a localized internal E-Services or contact URL.';
                }
            }

            if ($targetKey === 'e_services.library') {
                if (! $this->filledString($resources['title'] ?? null) || $resourceLinks === []) {
                    $errors[$locale][] = 'The library requires a resource title and at least one resource link.';
                }

                foreach ($resourceLinks as $link) {
                    $url = is_array($link) ? ($link['url'] ?? null) : null;

                    if (! $this->isSafeHttpsUrl($url)) {
                        $errors[$locale][] = 'Every library resource must use a safe public HTTPS URL.';
                    }
                }
            } elseif ($resourceLinks !== []) {
                $errors[$locale][] = 'Resource links are only supported on the E-Library page.';
            }
        }

        $expected = $orderedIds[$locales[0]] ?? [];
        foreach (array_slice($locales, 1) as $locale) {
            if (($orderedIds[$locale] ?? []) !== $expected) {
                $errors['translations'][] = 'E-Services section and link IDs must match in the same order across locales.';
            }
        }
    }

    /**
     * @param  array<int, mixed>  $items
     * @param  array<int, string>  $requiredFields
     * @param  array<string, array<int, string>>  $errors
     * @return array<int, string>
     */
    private function validatedEServicesItems(array $items, array $requiredFields, string $itemLabel, string $locale, array &$errors): array
    {
        $ids = [];

        foreach ($items as $item) {
            if (! is_array($item)) {
                $errors[$locale][] = "Every {$itemLabel} must be structured content.";

                continue;
            }

            foreach ($requiredFields as $field) {
                if (! $this->filledString($item[$field] ?? null)) {
                    $errors[$locale][] = "Every {$itemLabel} requires {$field}.";
                }
            }

            if ($this->filledString($item['id'] ?? null)) {
                $ids[] = (string) $item['id'];
            }
        }

        if (count(array_unique($ids)) !== count($ids)) {
            $errors[$locale][] = ucfirst($itemLabel).' IDs must be unique.';
        }

        return $ids;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  array<int, string>  $locales
     * @param  array<string, array<int, string>>  $errors
     */
    private function appendResearchCentersReadinessErrors(array $payload, array $locales, array &$errors): void
    {
        $localeCenters = [];
        $localeLaboratories = [];

        foreach ($locales as $locale) {
            $translation = $this->localePayload($payload, $locale);
            $hero = is_array($translation['hero'] ?? null) ? $translation['hero'] : [];
            $intro = is_array($translation['intro'] ?? null) ? $translation['intro'] : [];
            $laboratories = is_array($translation['laboratories'] ?? null) ? $translation['laboratories'] : [];

            foreach (['title', 'summary', 'primaryCta', 'secondaryCta', 'secondaryCtaUrl', 'backgroundImage'] as $field) {
                if (! $this->filledString($hero[$field] ?? null)) {
                    $errors[$locale][] = "The centers hero {$field} field is required.";
                }
            }

            if (! $this->isSafeResearchPath($hero['secondaryCtaUrl'] ?? null, $locale)) {
                $errors[$locale][] = 'The centers secondary call-to-action must use an internal Research URL.';
            }

            foreach (['title', 'summary'] as $field) {
                if (! $this->filledString($intro[$field] ?? null)) {
                    $errors[$locale][] = "The centers introduction {$field} field is required.";
                }
            }

            $highlights = is_array($intro['highlights'] ?? null) ? $intro['highlights'] : [];
            if ($highlights === []) {
                $errors[$locale][] = 'At least one center highlight is required.';
            }
            foreach ($highlights as $highlight) {
                if (! is_array($highlight) || ! $this->filledString($highlight['title'] ?? null) || ! $this->filledString($highlight['summary'] ?? null) || ! $this->filledString($highlight['icon'] ?? null)) {
                    $errors[$locale][] = 'Every center highlight requires a title, summary, and icon.';
                }
            }

            $items = is_array($translation['items'] ?? null) ? $translation['items'] : [];
            $centerSignatures = [];
            $centerIds = [];
            $centerSlugs = [];
            if ($items === []) {
                $errors[$locale][] = 'At least one research center is required.';
            }

            foreach ($items as $item) {
                if (! is_array($item)) {
                    $errors[$locale][] = 'Every research center must be structured content.';

                    continue;
                }

                foreach (['id', 'slug', 'name', 'mission', 'faculty', 'facultySlug', 'directorName', 'contactEmail', 'image'] as $field) {
                    if (! $this->filledString($item[$field] ?? null)) {
                        $errors[$locale][] = "Every research center requires {$field}.";
                    }
                }

                $id = trim((string) ($item['id'] ?? ''));
                $slug = trim((string) ($item['slug'] ?? ''));
                $facultySlug = trim((string) ($item['facultySlug'] ?? ''));
                $centerIds[] = $id;
                $centerSlugs[] = $slug;
                $centerSignatures[] = [
                    'id' => $id,
                    'slug' => $slug,
                    'facultySlug' => $facultySlug,
                    'publicationSlugs' => $this->readinessStringList($item['publicationSlugs'] ?? []),
                    'projectSlugs' => $this->readinessStringList($item['projectSlugs'] ?? []),
                    'researcherSlugs' => $this->readinessStringList($item['researcherSlugs'] ?? []),
                ];

                if ($slug !== '' && preg_match('~^[a-z0-9]+(?:-[a-z0-9]+)*$~', $slug) !== 1) {
                    $errors[$locale][] = 'Research center slugs must use lowercase letters, numbers, and hyphens only.';
                }
                if (! in_array($facultySlug, ['medicine', 'dentistry', 'pharmacy', 'artificial-intelligence', 'building-construction-engineering', 'petroleum', 'business-administration'], true)) {
                    $errors[$locale][] = 'Every research center requires a valid faculty slug.';
                }
                if (filter_var($item['contactEmail'] ?? null, FILTER_VALIDATE_EMAIL) === false) {
                    $errors[$locale][] = 'Every research center requires a valid contact email.';
                }
                if ($this->filledString($item['externalWebsite'] ?? null) && ! $this->isSafeHttpsUrl($item['externalWebsite'])) {
                    $errors[$locale][] = 'Research center websites must use safe public HTTPS URLs.';
                }
                foreach (['labs', 'researchers', 'projects', 'publications'] as $field) {
                    if (! is_numeric($item[$field] ?? null) || (int) $item[$field] < 0) {
                        $errors[$locale][] = "Research center {$field} must be a non-negative number.";
                    }
                }
            }

            if (count(array_unique($centerIds)) !== count($centerIds) || count(array_unique($centerSlugs)) !== count($centerSlugs)) {
                $errors[$locale][] = 'Research center IDs and slugs must be unique.';
            }

            if (! $this->filledString($laboratories['title'] ?? null)) {
                $errors[$locale][] = 'The research laboratories title is required.';
            }
            $laboratoryItems = is_array($laboratories['items'] ?? null) ? $laboratories['items'] : [];
            $laboratorySignatures = [];
            $laboratoryIds = [];
            foreach ($laboratoryItems as $laboratory) {
                if (! is_array($laboratory)) {
                    $errors[$locale][] = 'Every research laboratory must be structured content.';

                    continue;
                }
                foreach (['id', 'slug', 'title', 'faculty', 'summary', 'director', 'projects', 'publications', 'contact', 'cta', 'image'] as $field) {
                    if (! $this->filledString($laboratory[$field] ?? null)) {
                        $errors[$locale][] = "Every research laboratory requires {$field}.";
                    }
                }

                $laboratoryId = trim((string) ($laboratory['id'] ?? ''));
                $laboratorySlug = trim((string) ($laboratory['slug'] ?? ''));
                $laboratoryIds[] = $laboratoryId;
                $laboratorySignatures[] = ['id' => $laboratoryId, 'slug' => $laboratorySlug];
                if ($laboratorySlug !== '' && ! in_array($laboratorySlug, $centerSlugs, true)) {
                    $errors[$locale][] = 'Every research laboratory must link to a published center slug.';
                }
            }
            if ($laboratoryItems === []) {
                $errors[$locale][] = 'At least one research laboratory is required.';
            } elseif (count(array_unique($laboratoryIds)) !== count($laboratoryIds)) {
                $errors[$locale][] = 'Research laboratory IDs must be unique.';
            }

            $localeCenters[$locale] = $centerSignatures;
            $localeLaboratories[$locale] = $laboratorySignatures;
        }

        if (count($localeCenters) === count($locales) && count(array_unique(array_map('serialize', $localeCenters))) !== 1) {
            $errors['centers'][] = 'Center IDs, slugs, faculty assignments, and relationships must match across locales.';
        }
        if (count($localeLaboratories) === count($locales) && count(array_unique(array_map('serialize', $localeLaboratories))) !== 1) {
            $errors['centers'][] = 'Laboratory IDs and linked center slugs must match across locales.';
        }
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  array<int, string>  $locales
     * @param  array<string, array<int, string>>  $errors
     */
    private function appendResearchProjectsReadinessErrors(array $payload, array $locales, array &$errors): void
    {
        $localeSignatures = [];

        foreach ($locales as $locale) {
            $translation = $this->localePayload($payload, $locale);
            $hero = is_array($translation['hero'] ?? null) ? $translation['hero'] : [];
            $filters = is_array($translation['filters'] ?? null) ? $translation['filters'] : [];
            $cardLabels = is_array($translation['cardLabels'] ?? null) ? $translation['cardLabels'] : [];

            foreach (['eyebrow', 'title', 'summary', 'backgroundImage'] as $field) {
                if (! $this->filledString($hero[$field] ?? null)) {
                    $errors[$locale][] = "The projects hero {$field} field is required.";
                }
            }
            if ($this->filledString($hero['backgroundImage'] ?? null) && ! $this->isSafePublicAsset($hero['backgroundImage'])) {
                $errors[$locale][] = 'The projects hero image must use a safe internal or HTTPS URL.';
            }
            $this->appendResearchBreadcrumbErrors($hero, 'projects', $locale, $errors);

            foreach (['statusLabel', 'facultyLabel', 'themeLabel', 'searchPlaceholder'] as $field) {
                if (! $this->filledString($filters[$field] ?? null)) {
                    $errors[$locale][] = "The project filters {$field} field is required.";
                }
            }
            foreach (['viewProject', 'since'] as $field) {
                if (! $this->filledString($cardLabels[$field] ?? null)) {
                    $errors[$locale][] = "The project card {$field} label is required.";
                }
            }

            $filterValues = [];
            foreach (['statuses', 'faculties', 'themes'] as $group) {
                $options = is_array($filters[$group] ?? null) ? $filters[$group] : [];
                if ($options === []) {
                    $errors[$locale][] = "At least one project {$group} filter is required.";
                }
                $filterValues[$group] = [];
                foreach ($options as $option) {
                    if (! is_array($option) || ! $this->filledString($option['label'] ?? null)) {
                        $errors[$locale][] = "Every project {$group} filter requires a label.";

                        continue;
                    }
                    $value = trim((string) ($option['value'] ?? ''));
                    $filterValues[$group][] = $value;
                    if ($value !== '' && ! $this->isValidResearchRelation($group, $value)) {
                        $errors[$locale][] = "The project {$group} filter contains an invalid value.";
                    }
                }
                if (count(array_unique($filterValues[$group])) !== count($filterValues[$group])) {
                    $errors[$locale][] = "Project {$group} filter values must be unique.";
                }
            }

            $items = is_array($translation['items'] ?? null) ? $translation['items'] : [];
            if ($items === []) {
                $errors[$locale][] = 'At least one research project is required.';
            }
            $ids = [];
            $slugs = [];
            $signatures = [];
            foreach ($items as $item) {
                if (! is_array($item)) {
                    $errors[$locale][] = 'Every research project must be structured content.';

                    continue;
                }
                foreach (['id', 'slug', 'title', 'summary', 'faculty', 'facultySlug', 'theme', 'themeSlug', 'status', 'startYear', 'funding', 'image'] as $field) {
                    if (! $this->filledString($item[$field] ?? null)) {
                        $errors[$locale][] = "Every research project requires {$field}.";
                    }
                }

                $id = trim((string) ($item['id'] ?? ''));
                $slug = trim((string) ($item['slug'] ?? ''));
                $facultySlug = trim((string) ($item['facultySlug'] ?? ''));
                $themeSlug = trim((string) ($item['themeSlug'] ?? ''));
                $status = trim((string) ($item['status'] ?? ''));
                $startYear = trim((string) ($item['startYear'] ?? ''));
                $endYear = trim((string) ($item['endYear'] ?? ''));
                $ids[] = $id;
                $slugs[] = $slug;
                $signatures[] = compact('id', 'slug', 'facultySlug', 'themeSlug', 'status', 'startYear', 'endYear');

                foreach (['ID' => $id, 'slug' => $slug] as $label => $value) {
                    if ($value !== '' && preg_match('~^[a-z0-9]+(?:-[a-z0-9]+)*$~', $value) !== 1) {
                        $errors[$locale][] = "Research project {$label}s must use lowercase letters, numbers, and hyphens only.";
                    }
                }
                if (! in_array($facultySlug, self::RESEARCH_FACULTY_SLUGS, true)) {
                    $errors[$locale][] = 'Every research project requires a valid faculty slug.';
                }
                if (! in_array($themeSlug, self::RESEARCH_THEME_SLUGS, true)) {
                    $errors[$locale][] = 'Every research project must reference an approved research theme.';
                }
                if (! in_array($status, ['ongoing', 'completed', 'paused'], true)) {
                    $errors[$locale][] = 'Every research project requires a valid status.';
                }
                if (preg_match('~^(?:19|20|21)\d{2}$~', $startYear) !== 1 || ($endYear !== '' && (preg_match('~^(?:19|20|21)\d{2}$~', $endYear) !== 1 || (int) $endYear < (int) $startYear))) {
                    $errors[$locale][] = 'Research project years must be valid and the end year cannot precede the start year.';
                }
                if ($this->filledString($item['image'] ?? null) && ! $this->isSafePublicAsset($item['image'])) {
                    $errors[$locale][] = 'Research project images must use safe internal or HTTPS URLs.';
                }
            }
            if (count(array_unique($ids)) !== count($ids) || count(array_unique($slugs)) !== count($slugs)) {
                $errors[$locale][] = 'Research project IDs and slugs must be unique.';
            }

            $localeSignatures[$locale] = ['filters' => $filterValues, 'items' => $signatures];
        }

        if (count($localeSignatures) === count($locales) && count(array_unique(array_map('serialize', $localeSignatures))) !== 1) {
            $errors['projects'][] = 'Project IDs, slugs, filters, statuses, faculty assignments, themes, and years must match across locales.';
        }
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  array<int, string>  $locales
     * @param  array<string, array<int, string>>  $errors
     */
    private function appendResearchThemesReadinessErrors(array $payload, array $locales, array &$errors): void
    {
        $localeSignatures = [];

        foreach ($locales as $locale) {
            $translation = $this->localePayload($payload, $locale);
            $hero = is_array($translation['hero'] ?? null) ? $translation['hero'] : [];
            foreach (['eyebrow', 'title', 'summary', 'backgroundImage'] as $field) {
                if (! $this->filledString($hero[$field] ?? null)) {
                    $errors[$locale][] = "The themes hero {$field} field is required.";
                }
            }
            if ($this->filledString($hero['backgroundImage'] ?? null) && ! $this->isSafePublicAsset($hero['backgroundImage'])) {
                $errors[$locale][] = 'The themes hero image must use a safe internal or HTTPS URL.';
            }
            $this->appendResearchBreadcrumbErrors($hero, 'themes', $locale, $errors);

            $items = is_array($translation['items'] ?? null) ? $translation['items'] : [];
            if ($items === []) {
                $errors[$locale][] = 'At least one research theme is required.';
            }
            $ids = [];
            $slugs = [];
            $signatures = [];
            foreach ($items as $item) {
                if (! is_array($item)) {
                    $errors[$locale][] = 'Every research theme must be structured content.';

                    continue;
                }
                foreach (['id', 'slug', 'name', 'description', 'icon'] as $field) {
                    if (! $this->filledString($item[$field] ?? null)) {
                        $errors[$locale][] = "Every research theme requires {$field}.";
                    }
                }
                $id = trim((string) ($item['id'] ?? ''));
                $slug = trim((string) ($item['slug'] ?? ''));
                $publicationCount = $item['publicationCount'] ?? null;
                $projectCount = $item['projectCount'] ?? null;
                $ids[] = $id;
                $slugs[] = $slug;
                $signatures[] = compact('id', 'slug', 'publicationCount', 'projectCount');

                foreach (['ID' => $id, 'slug' => $slug] as $label => $value) {
                    if ($value !== '' && preg_match('~^[a-z0-9]+(?:-[a-z0-9]+)*$~', $value) !== 1) {
                        $errors[$locale][] = "Research theme {$label}s must use lowercase letters, numbers, and hyphens only.";
                    }
                }
                if ($slug !== '' && ! in_array($slug, self::RESEARCH_THEME_SLUGS, true)) {
                    $errors[$locale][] = 'Research theme slugs must identify an approved theme.';
                }
                if (! is_numeric($publicationCount) || (int) $publicationCount < 0 || ! is_numeric($projectCount) || (int) $projectCount < 0) {
                    $errors[$locale][] = 'Research theme publication and project counts must be non-negative numbers.';
                }
                if ($this->filledString($item['icon'] ?? null) && ! $this->isSafePublicAsset($item['icon'])) {
                    $errors[$locale][] = 'Research theme icons must use safe internal or HTTPS URLs.';
                }
            }
            if (count(array_unique($ids)) !== count($ids) || count(array_unique($slugs)) !== count($slugs)) {
                $errors[$locale][] = 'Research theme IDs and slugs must be unique.';
            }

            $localeSignatures[$locale] = $signatures;
        }

        if (count($localeSignatures) === count($locales) && count(array_unique(array_map('serialize', $localeSignatures))) !== 1) {
            $errors['themes'][] = 'Theme IDs, slugs, and catalog counts must match across locales.';
        }
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  array<int, string>  $locales
     * @param  array<string, array<int, string>>  $errors
     */
    private function appendResearchCatalogReadinessErrors(string $targetKey, array $payload, array $locales, array &$errors): void
    {
        $paths = match ($targetKey) {
            'research.publications' => ['items'],
            'research.experts' => ['researchers'],
            'research.conferences' => ['upcoming', 'past'],
            'research.policies' => ['sections'],
            default => [],
        };
        $localeSignatures = [];

        foreach ($locales as $locale) {
            $translation = $this->localePayload($payload, $locale);
            $localeSignatures[$locale] = [];

            foreach ($paths as $path) {
                $items = is_array($translation[$path] ?? null) ? $translation[$path] : [];
                $ids = [];

                foreach ($items as $item) {
                    if (! is_array($item)) {
                        $errors['research'][] = 'Every catalog entry must contain structured content.';

                        continue;
                    }

                    $id = trim((string) ($item['id'] ?? ''));
                    if ($id === '') {
                        $errors['research'][] = 'Every catalog entry requires a stable internal identity.';
                    } else {
                        $ids[] = $id;
                    }

                    if ($targetKey === 'research.publications') {
                        foreach (['scholarUrl', 'scopusUrl'] as $field) {
                            if ($this->filledString($item[$field] ?? null) && ! $this->isSafeHttpsUrl($item[$field])) {
                                $errors['research'][] = 'Publication index links must use safe public HTTPS URLs.';
                            }
                        }
                        foreach (is_array($item['downloads'] ?? null) ? $item['downloads'] : [] as $download) {
                            if (! is_array($download) || ! $this->isSafeResearchResourceUrl($download['url'] ?? null)) {
                                $errors['research'][] = 'Every publication download must use a safe file URL.';
                            }
                        }
                    }

                    if ($targetKey === 'research.experts') {
                        foreach (['orcidUrl', 'scholarUrl'] as $field) {
                            if ($this->filledString($item[$field] ?? null) && ! $this->isSafeHttpsUrl($item[$field])) {
                                $errors['research'][] = 'Expert profile links must use safe public HTTPS URLs.';
                            }
                        }
                    }

                    if ($targetKey === 'research.conferences' && $path === 'upcoming') {
                        $formId = trim((string) ($item['formId'] ?? ''));
                        if ($formId !== '' && ! in_array($formId, ['conference-registration', 'symposium-registration'], true)) {
                            $errors['research'][] = 'Choose an approved conference registration form.';
                        }
                        if (is_string($item['registrationUrl'] ?? null) && str_contains($item['registrationUrl'], '/research/conferences/register') && $formId === '') {
                            $errors['research'][] = 'Choose an approved form for each internal conference registration link.';
                        }
                        if ($this->filledString($item['registrationUrl'] ?? null) && ! $this->isSafeResearchResourceUrl($item['registrationUrl'])) {
                            $errors['research'][] = 'Conference registration links must use a safe internal or HTTPS URL.';
                        }
                    }

                    if ($targetKey === 'research.conferences' && $path === 'past' && (bool) ($item['hasProceedings'] ?? false) && ! (bool) ($item['proceedingsUnavailable'] ?? false) && ! $this->isSafeResearchResourceUrl($item['proceedingsUrl'] ?? null)) {
                        $errors['research'][] = 'Upload a valid proceedings file before marking proceedings as available.';
                    }

                    if ($targetKey === 'research.policies') {
                        $documents = is_array($item['documents'] ?? null) ? $item['documents'] : [];
                        if ($documents === [] && ! (bool) ($item['documentsUnavailable'] ?? false)) {
                            $errors['research'][] = 'Every policy section requires at least one document.';
                        }
                        foreach ($documents as $document) {
                            if (! (bool) ($item['documentsUnavailable'] ?? false) && (! is_array($document) || ! $this->isSafeResearchResourceUrl($document['url'] ?? null))) {
                                $errors['research'][] = 'Every policy document requires a valid file.';
                            }
                        }
                    }
                }

                if (count(array_unique($ids)) !== count($ids)) {
                    $errors['research'][] = 'Catalog entry identities must be unique within each language.';
                }

                $localeSignatures[$locale][$path] = $ids;
            }
        }

        if (count($localeSignatures) === count($locales) && count(array_unique(array_map('serialize', $localeSignatures))) !== 1) {
            $errors['research'][] = 'Arabic and English catalog entries must have matching identities and order.';
        }

        if (isset($errors['research'])) {
            $errors['research'] = array_values(array_unique($errors['research']));
        }
    }

    private function isValidResearchRelation(string $group, string $value): bool
    {
        return match ($group) {
            'statuses' => in_array($value, ['ongoing', 'completed', 'paused'], true),
            'faculties' => in_array($value, self::RESEARCH_FACULTY_SLUGS, true),
            'themes' => in_array($value, self::RESEARCH_THEME_SLUGS, true),
            default => false,
        };
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  array<int, string>  $locales
     * @param  array<string, array<int, string>>  $errors
     */
    private function appendCampusLifeJobsReadinessErrors(array $payload, array $locales, array &$errors): void
    {
        $localeSignatures = [];

        foreach ($locales as $locale) {
            $translation = $this->localePayload($payload, $locale);
            $hero = is_array($translation['hero'] ?? null) ? $translation['hero'] : [];

            foreach (['title', 'summary', 'image'] as $field) {
                if (! $this->filledString($hero[$field] ?? null)) {
                    $errors[$locale][] = "The jobs hero {$field} field is required.";
                }
            }

            if ($this->filledString($hero['image'] ?? null) && ! $this->isSafePublicAsset($hero['image'])) {
                $errors[$locale][] = 'The jobs hero image must use a safe internal or HTTPS URL.';
            }

            $filterIds = [];
            foreach (['categories', 'types'] as $group) {
                $options = is_array($translation[$group] ?? null) ? $translation[$group] : [];
                if ($options === []) {
                    $errors[$locale][] = "At least one jobs {$group} option is required.";
                }

                $filterIds[$group] = [];
                foreach ($options as $option) {
                    if (! is_array($option) || ! $this->filledString($option['id'] ?? null) || ! $this->filledString($option['label'] ?? null)) {
                        $errors[$locale][] = "Every jobs {$group} option requires an ID and label.";

                        continue;
                    }

                    $optionId = trim((string) $option['id']);
                    $filterIds[$group][] = $optionId;
                    if (preg_match('~^[a-z0-9]+(?:-[a-z0-9]+)*$~', $optionId) !== 1) {
                        $errors[$locale][] = "Jobs {$group} IDs must use lowercase letters, numbers, and hyphens only.";
                    }
                }

                if (count(array_unique($filterIds[$group])) !== count($filterIds[$group])) {
                    $errors[$locale][] = "Jobs {$group} IDs must be unique.";
                }
            }

            $labels = is_array($translation['labels'] ?? null) ? $translation['labels'] : [];
            foreach (['category', 'type', 'search', 'searchAction', 'showing', 'positions', 'of', 'previous', 'next', 'reset', 'noResults', 'learnMore', 'apply', 'applicationsClosed', 'postedOn', 'closesOn', 'status', 'openStatus', 'closedStatus', 'share', 'copyLink', 'copied', 'related', 'overview', 'responsibilities', 'requirements', 'benefits', 'back'] as $label) {
                if (! $this->filledString($labels[$label] ?? null)) {
                    $errors[$locale][] = "The jobs {$label} label is required.";
                }
            }

            $items = is_array($translation['jobs'] ?? null) ? $translation['jobs'] : [];
            if ($items === []) {
                $errors[$locale][] = 'At least one job is required.';
            }

            $ids = [];
            $slugs = [];
            $signatures = [];

            foreach ($items as $item) {
                if (! is_array($item)) {
                    $errors[$locale][] = 'Every job must be structured content.';

                    continue;
                }

                foreach (['id', 'slug', 'category', 'type', 'status', 'title', 'department', 'location', 'shortDescription', 'postedDate', 'closeDate', 'image'] as $field) {
                    if (! $this->filledString($item[$field] ?? null)) {
                        $errors[$locale][] = "Every job requires {$field}.";
                    }
                }

                foreach (['overview', 'responsibilities', 'requirements', 'benefits'] as $field) {
                    if ($this->readinessStringList($item[$field] ?? []) === []) {
                        $errors[$locale][] = "Every job requires localized {$field}.";
                    }
                }

                $id = trim((string) ($item['id'] ?? ''));
                $slug = trim((string) ($item['slug'] ?? ''));
                $category = trim((string) ($item['category'] ?? ''));
                $type = trim((string) ($item['type'] ?? ''));
                $status = trim((string) ($item['status'] ?? ''));
                $postedDate = trim((string) ($item['postedDate'] ?? ''));
                $closeDate = trim((string) ($item['closeDate'] ?? ''));
                $applicationEligible = $item['applicationEligible'] ?? null;
                $image = trim((string) ($item['image'] ?? ''));
                $ids[] = $id;
                $slugs[] = $slug;
                $signatures[] = compact('id', 'slug', 'category', 'type', 'status', 'postedDate', 'closeDate', 'applicationEligible', 'image');

                foreach (['ID' => $id, 'slug' => $slug] as $label => $value) {
                    if ($value !== '' && preg_match('~^[a-z0-9]+(?:-[a-z0-9]+)*$~', $value) !== 1) {
                        $errors[$locale][] = "Job {$label}s must use lowercase letters, numbers, and hyphens only.";
                    }
                }

                if (! in_array($status, ['open', 'closed'], true)) {
                    $errors[$locale][] = 'Every job requires an open or closed status.';
                }

                if ($category !== '' && ! in_array($category, $filterIds['categories'] ?? [], true)) {
                    $errors[$locale][] = 'Every job category must reference a configured category filter.';
                }

                if ($type !== '' && ! in_array($type, $filterIds['types'] ?? [], true)) {
                    $errors[$locale][] = 'Every job type must reference a configured type filter.';
                }

                if (! is_bool($applicationEligible)) {
                    $errors[$locale][] = 'Every job application eligibility value must be true or false.';
                }

                $postedTimestamp = \DateTimeImmutable::createFromFormat('!Y-m-d', $postedDate);
                $closeTimestamp = \DateTimeImmutable::createFromFormat('!Y-m-d', $closeDate);
                if (! $postedTimestamp instanceof \DateTimeImmutable || $postedTimestamp->format('Y-m-d') !== $postedDate
                    || ! $closeTimestamp instanceof \DateTimeImmutable || $closeTimestamp->format('Y-m-d') !== $closeDate
                    || $closeTimestamp < $postedTimestamp) {
                    $errors[$locale][] = 'Job dates must be valid and the close date cannot precede the posted date.';
                }

                if ($image !== '' && ! $this->isSafePublicAsset($image)) {
                    $errors[$locale][] = 'Job images must use safe internal or HTTPS URLs.';
                }
            }

            if (count(array_unique($ids)) !== count($ids) || count(array_unique($slugs)) !== count($slugs)) {
                $errors[$locale][] = 'Job IDs and slugs must be unique.';
            }

            $localeSignatures[$locale] = ['filters' => $filterIds, 'jobs' => $signatures];
        }

        if (count($localeSignatures) === count($locales) && count(array_unique(array_map('serialize', $localeSignatures))) !== 1) {
            $errors['jobs'][] = 'Job IDs, slugs, categories, types, statuses, dates, images, and application eligibility must match across locales.';
        }
    }

    /** @param array<int, string> $locales @param array<string, array<int, string>> $errors */
    private function appendCampusLifeLandingReadinessErrors(array $payload, array $locales, array &$errors): void
    {
        foreach ($locales as $locale) {
            $translation = $this->localePayload($payload, $locale);
            $portals = is_array($translation['portals'] ?? null) ? $translation['portals'] : [];

            if ($portals === [] && ! $this->filledString($translation['portalGuidance'] ?? null)) {
                $errors[$locale][] = 'Transparent portal availability guidance is required when no verified destinations are published.';
            }

            $controlGroups = [
                ['items' => $translation['hero']['quickLinks'] ?? [], 'url' => 'href'],
                ['items' => $translation['services'] ?? [], 'url' => 'href'],
                ['items' => $portals, 'url' => 'url'],
            ];
            foreach ($controlGroups as $group) {
                foreach (is_array($group['items']) ? $group['items'] : [] as $item) {
                    $url = is_array($item) ? ($item[$group['url']] ?? null) : null;
                    if (! $this->isSafeCampusDestination($url, $locale)) {
                        $errors[$locale][] = 'Campus Life controls must use a verified localized public destination and cannot use inert URLs.';
                    }
                }
            }

            foreach (is_array($translation['stats'] ?? null) ? $translation['stats'] : [] as $stat) {
                if (! is_array($stat) || ! ($stat['verified'] ?? false)) {
                    $errors[$locale][] = 'Campus Life figures must be explicitly marked as verified or removed.';
                }
            }
        }
    }

    /** @param array<int, string> $locales @param array<string, array<int, string>> $errors */
    private function appendSuggestionsComplaintsReadinessErrors(array $payload, array $locales, array &$errors): void
    {
        $expectedTypes = ['complaint', 'inquiry', 'suggestion'];

        foreach ($locales as $locale) {
            $translation = $this->localePayload($payload, $locale);
            $hero = is_array($translation['hero'] ?? null) ? $translation['hero'] : [];
            $form = is_array($translation['form'] ?? null) ? $translation['form'] : [];
            $seo = is_array($translation['seo'] ?? null) ? $translation['seo'] : [];

            foreach (['eyebrow', 'title', 'summary', 'image'] as $field) {
                if (! $this->filledString($hero[$field] ?? null)) {
                    $errors[$locale][] = "The suggestions hero {$field} field is required.";
                }
            }
            foreach (['title', 'infoTitle', 'infoBody', 'consentLabel', 'attachmentHelp'] as $field) {
                if (! $this->filledString($form[$field] ?? null)) {
                    $errors[$locale][] = "The suggestions form {$field} field is required.";
                }
            }
            foreach (['title', 'description', 'image'] as $field) {
                if (! $this->filledString($seo[$field] ?? null)) {
                    $errors[$locale][] = "The suggestions SEO {$field} field is required.";
                }
            }

            $types = collect(is_array($form['requestTypes'] ?? null) ? $form['requestTypes'] : [])
                ->filter(fn (mixed $item): bool => is_array($item))->pluck('value')->sort()->values()->all();
            if ($types !== $expectedTypes) {
                $errors[$locale][] = 'Suggestion, complaint, and inquiry request types must each be configured exactly once.';
            }
            if (! $this->isSafePublicAsset($hero['image'] ?? null) || ! $this->isSafePublicAsset($seo['image'] ?? null)) {
                $errors[$locale][] = 'Suggestions page images must use safe public assets.';
            }
        }
    }

    private function isSafeCampusDestination(mixed $url, string $locale): bool
    {
        return is_string($url)
            && $url !== ''
            && $url !== '#'
            && ! str_starts_with($url, '//')
            && preg_match('~^/'.preg_quote($locale, '~').'/(?:campus-life|e-services|admissions|contact|facilities|virtual-tour)(?:[/?#]|$)~', $url) === 1;
    }

    /** @param array<int, string> $locales @param array<string, array<int, string>> $errors */
    private function appendVirtualTourReadinessErrors(array $payload, array $locales, array &$errors): void
    {
        $localeSceneIds = [];

        foreach ($locales as $locale) {
            $translation = $this->localePayload($payload, $locale);
            $hero = is_array($translation['hero'] ?? null) ? $translation['hero'] : [];
            $tour = is_array($translation['tour'] ?? null) ? $translation['tour'] : [];
            $seo = is_array($translation['seo'] ?? null) ? $translation['seo'] : [];
            $scenes = is_array($tour['scenes'] ?? null) ? $tour['scenes'] : [];

            foreach (['eyebrow', 'title', 'summary', 'image', 'imageAlt', 'primaryLabel', 'primaryUrl', 'secondaryLabel', 'secondaryUrl'] as $field) {
                if (! $this->filledString($hero[$field] ?? null)) {
                    $errors[$locale][] = "The virtual tour hero {$field} field is required.";
                }
            }
            foreach (['primaryUrl', 'secondaryUrl'] as $field) {
                if (! is_string($hero[$field] ?? null) || preg_match('~^/'.preg_quote($locale, '~').'/virtual-tour(?:[?#]|$)~', $hero[$field]) !== 1) {
                    $errors[$locale][] = 'Virtual tour hero actions must target a real section on the localized tour page.';
                }
            }
            foreach (['eyebrow', 'title', 'summary', 'experienceLabel', 'controlLabel', 'fullscreenLabel', 'exitFullscreenLabel', 'playLabel', 'pauseLabel', 'zoomInLabel', 'zoomOutLabel', 'resetLabel', 'previousLabel', 'nextLabel'] as $field) {
                if (! $this->filledString($tour[$field] ?? null)) {
                    $errors[$locale][] = "The virtual tour {$field} field is required.";
                }
            }
            if (! is_numeric($tour['autoplayInterval'] ?? null) || (int) $tour['autoplayInterval'] < 3000 || (int) $tour['autoplayInterval'] > 20000) {
                $errors[$locale][] = 'Virtual tour autoplay must be between 3 and 20 seconds.';
            }
            if ($scenes === []) {
                $errors[$locale][] = 'At least one virtual tour photo scene is required.';
            }

            $sceneIds = [];
            foreach ($scenes as $scene) {
                if (! is_array($scene)) {
                    $errors[$locale][] = 'Every virtual tour scene must be structured content.';

                    continue;
                }
                foreach (['id', 'title', 'summary', 'image', 'imageAlt'] as $field) {
                    if (! $this->filledString($scene[$field] ?? null)) {
                        $errors[$locale][] = "Every virtual tour scene requires {$field}.";
                    }
                }
                $sceneId = trim((string) ($scene['id'] ?? ''));
                $sceneIds[] = $sceneId;
                if (preg_match('~^[a-z0-9]+(?:-[a-z0-9]+)*$~', $sceneId) !== 1) {
                    $errors[$locale][] = 'Virtual tour scene IDs must use lowercase letters, numbers, and hyphens.';
                }
                if (! $this->isReviewedVirtualTourImage($scene)) {
                    $errors[$locale][] = 'Every virtual tour scene must use an existing approved image or reviewed Media Library image.';
                }
                foreach (is_array($scene['hotspots'] ?? null) ? $scene['hotspots'] : [] as $hotspot) {
                    if (! is_array($hotspot) || ! $this->filledString($hotspot['id'] ?? null) || ! $this->filledString($hotspot['label'] ?? null) || ! $this->filledString($hotspot['description'] ?? null)
                        || ! is_numeric($hotspot['x'] ?? null) || (float) $hotspot['x'] < 0 || (float) $hotspot['x'] > 100
                        || ! is_numeric($hotspot['y'] ?? null) || (float) $hotspot['y'] < 0 || (float) $hotspot['y'] > 100) {
                        $errors[$locale][] = 'Every hotspot requires an ID, label, description, and coordinates from 0 to 100.';
                    }
                }
            }
            if (count(array_unique($sceneIds)) !== count($sceneIds)) {
                $errors[$locale][] = 'Virtual tour scene IDs must be unique.';
            }
            foreach ($scenes as $scene) {
                foreach (is_array($scene['hotspots'] ?? null) ? $scene['hotspots'] : [] as $hotspot) {
                    $targetSceneId = is_array($hotspot) ? trim((string) ($hotspot['targetSceneId'] ?? '')) : '';
                    if ($targetSceneId !== '' && ! in_array($targetSceneId, $sceneIds, true)) {
                        $errors[$locale][] = 'Hotspot target scene IDs must reference a configured scene.';
                    }
                }
            }
            $localeSceneIds[$locale] = $sceneIds;

            $serialized = mb_strtolower((string) json_encode($translation, JSON_UNESCAPED_UNICODE));
            if (str_contains($serialized, '360') || str_contains($serialized, 'بانوراما')) {
                $errors[$locale][] = 'Flat campus photographs cannot be described as 360-degree panoramas.';
            }
            foreach (['title', 'description', 'image'] as $field) {
                if (! $this->filledString($seo[$field] ?? null)) {
                    $errors[$locale][] = "The virtual tour SEO {$field} field is required.";
                }
            }
            foreach (['highlights', 'facilities'] as $group) {
                foreach (is_array($translation[$group]['items'] ?? null) ? $translation[$group]['items'] : [] as $item) {
                    if (! is_array($item) || ! $this->isSafeCampusDestination($item['href'] ?? null, $locale)) {
                        $errors[$locale][] = 'Virtual tour cards must use verified localized public destinations.';
                    }
                }
            }
        }

        if (count($localeSceneIds) === count($locales) && count(array_unique(array_map('serialize', $localeSceneIds))) !== 1) {
            $errors['scenes'][] = 'Virtual tour scene IDs and order must match across locales.';
        }
    }

    /** @param array<string, mixed> $scene */
    private function isReviewedVirtualTourImage(array $scene): bool
    {
        $image = $scene['image'] ?? null;
        $mediaId = is_numeric($scene['imageMediaId'] ?? null) ? (int) $scene['imageMediaId'] : 0;

        if ($mediaId > 0) {
            return $this->mediaService->publicImagesArePublishable([$mediaId]);
        }

        return is_string($image)
            && str_starts_with($image, '/images/')
            && is_file(public_path(ltrim($image, '/')));
    }

    /** @param array<int, string> $locales @param array<string, array<int, string>> $errors */
    private function appendNewsArticlesReadinessErrors(array $payload, array $locales, array &$errors): void
    {
        foreach ($locales as $locale) {
            $translation = $this->localePayload($payload, $locale);
            foreach (['title', 'summary', 'heroImage', 'allLabel', 'searchLabel', 'searchPlaceholder', 'searchAction', 'readMoreLabel', 'emptyLabel', 'previousLabel', 'nextLabel', 'seoTitle', 'seoDescription', 'seoImage'] as $field) {
                if (! $this->filledString($translation[$field] ?? null)) {
                    $errors[$locale][] = "The News Articles shell {$field} field is required.";
                }
            }
            if (! $this->isSafePublicAsset($translation['heroImage'] ?? null) || ! $this->isSafePublicAsset($translation['seoImage'] ?? null)) {
                $errors[$locale][] = 'News Articles shell images must use safe public assets.';
            }
        }
    }

    /** @param array<int, string> $locales @param array<string, array<int, string>> $errors */
    private function appendPharmacyTrainingReadinessErrors(array $payload, array $locales, array &$errors): void
    {
        foreach ($locales as $locale) {
            $translation = $this->localePayload($payload, $locale);
            $training = is_array($translation['payload'] ?? null) ? $translation['payload'] : [];
            $hero = is_array($training['hero'] ?? null) ? $training['hero'] : [];
            $programme = is_array($training['programme'] ?? null) ? $training['programme'] : [];
            $partners = is_array($training['partners'] ?? null) ? $training['partners'] : [];

            foreach (['eyebrow', 'title', 'summary', 'image'] as $field) {
                if (! $this->filledString($hero[$field] ?? null)) {
                    $errors[$locale][] = "The training hero {$field} field is required.";
                }
            }
            if (! $this->isSafePublicAsset($hero['image'] ?? null)) {
                $errors[$locale][] = 'The training hero must use a safe public image.';
            }
            if (! $this->filledString($programme['title'] ?? null) || ! is_array($programme['steps'] ?? null) || $programme['steps'] === []) {
                $errors[$locale][] = 'The training programme requires a title and at least one structured step.';
            }
            if (! is_array($training['introCards'] ?? null) || $training['introCards'] === []) {
                $errors[$locale][] = 'At least one training introduction card is required.';
            }
            foreach (is_array($partners['items'] ?? null) ? $partners['items'] : [] as $item) {
                $href = is_array($item) ? ($item['href'] ?? null) : null;
                if (! is_string($href) || preg_match('~^/(?:'.preg_quote($locale, '~').'/)?(?:facilities/pharmacy|campus-life)(?:[/?#]|$)~', $href) !== 1) {
                    $errors[$locale][] = 'Training destinations must use an existing Pharmacy or Campus Life route.';
                }
            }
            foreach (is_array($training['facts'] ?? null) ? $training['facts'] : [] as $fact) {
                if (! is_array($fact) || ! ($fact['verified'] ?? false)) {
                    $errors[$locale][] = 'Training facts must be explicitly verified or removed.';
                }
            }
            foreach (['seoTitle', 'seoDescription', 'seoImage'] as $field) {
                if (! $this->filledString($translation[$field] ?? null)) {
                    $errors[$locale][] = "The training {$field} field is required.";
                }
            }
        }
    }

    private function isSafePublicAsset(mixed $url): bool
    {
        if (! is_string($url) || trim($url) === '' || str_starts_with($url, '//')) {
            return false;
        }

        return str_starts_with($url, '/') || $this->isSafeHttpsUrl($url);
    }

    /**
     * @param  array<string, mixed>  $hero
     * @param  array<string, array<int, string>>  $errors
     */
    private function appendResearchBreadcrumbErrors(array $hero, string $catalog, string $locale, array &$errors): void
    {
        $breadcrumbs = is_array($hero['breadcrumbs'] ?? null) ? $hero['breadcrumbs'] : [];
        if ($breadcrumbs === []) {
            $errors[$locale][] = "At least one {$catalog} breadcrumb is required.";
        }

        foreach ($breadcrumbs as $breadcrumb) {
            $url = is_array($breadcrumb) ? ($breadcrumb['url'] ?? null) : null;
            if (! is_array($breadcrumb) || ! $this->filledString($breadcrumb['label'] ?? null) || ! $this->isSafeResearchCatalogPath($url)) {
                $errors[$locale][] = "Every {$catalog} breadcrumb requires a label and safe internal Research URL.";
            }
        }
    }

    private function isSafeResearchCatalogPath(mixed $url): bool
    {
        return is_string($url)
            && ! str_starts_with($url, '//')
            && preg_match('~^/(?:$|(?:ar|en)/?$|(?:(?:ar|en)/)?research(?:[/?#].*)?$)~', $url) === 1;
    }

    /** @return array<int, string> */
    private function readinessStringList(mixed $items): array
    {
        return array_values(array_map(
            static fn (mixed $item): string => trim((string) $item),
            array_filter(is_array($items) ? $items : [], static fn (mixed $item): bool => is_scalar($item) && trim((string) $item) !== ''),
        ));
    }

    private function isSafeResearchPath(mixed $url, string $locale): bool
    {
        return is_string($url)
            && ! str_starts_with($url, '//')
            && preg_match('~^/(?:'.preg_quote($locale, '~').'/)?research(?:[/?#]|$)~', $url) === 1;
    }

    private function isSafeHttpsUrl(mixed $url): bool
    {
        if (! is_string($url) || filter_var($url, FILTER_VALIDATE_URL) === false) {
            return false;
        }

        $parts = parse_url($url);

        return is_array($parts)
            && ($parts['scheme'] ?? null) === 'https'
            && is_string($parts['host'] ?? null)
            && $parts['host'] !== ''
            && ! isset($parts['user'], $parts['pass']);
    }

    private function isSafeResearchResourceUrl(mixed $url): bool
    {
        return is_string($url)
            && trim($url) !== ''
            && $url !== '#'
            && ((str_starts_with($url, '/') && ! str_starts_with($url, '//')) || $this->isSafeHttpsUrl($url));
    }

    private function isSafeLocalizedEServicesUrl(mixed $url, string $locale): bool
    {
        return is_string($url)
            && ! str_starts_with($url, '//')
            && preg_match('~^/'.preg_quote($locale, '~').'/(?:e-services|contact)(?:[/?#]|$)~', $url) === 1;
    }

    /** @param array<string, mixed> $payload */
    private function hasBodyLikeContent(array $payload): bool
    {
        foreach (['body', 'content', 'description', 'summary', 'excerpt', 'subheadline', 'role', 'position', 'bio', 'intro', 'availabilityGuidance', 'paymentGuidance', 'scheduleGuidance', 'downloadGuidance', 'applicationGuidance'] as $key) {
            if ($this->filledString($payload[$key] ?? null)) {
                return true;
            }
        }

        foreach (['sections', 'blocks', 'items', 'cards', 'body_blocks', 'bodyBlocks'] as $key) {
            if (is_array($payload[$key] ?? null) && $payload[$key] !== []) {
                return true;
            }
        }

        if (is_array($payload['hero'] ?? null) && $this->filledString($payload['hero']['summary'] ?? ($payload['hero']['subtitle'] ?? null))) {
            return true;
        }

        foreach (['info', 'form', 'location', 'digitalServices', 'supportCards', 'trustBar', 'journey', 'timeline', 'resources', 'tabs', 'sections', 'services', 'departments', 'support', 'clubs', 'activities', 'facts', 'model', 'feeRows', 'steps', 'featureCards', 'statCards', 'deadlines'] as $key) {
            if (is_array($payload[$key] ?? null) && $payload[$key] !== []) {
                return true;
            }
        }

        return false;
    }

    private function filledString(mixed $value): bool
    {
        return is_string($value) && trim($value) !== '';
    }

    private function draftDto(CmsDraft $draft): CmsDraftDTO
    {
        return new CmsDraftDTO(
            id: (int) $draft->getKey(),
            targetKey: (string) $draft->target_key,
            status: (string) $draft->status,
            payload: is_array($draft->payload_json) ? $draft->payload_json : [],
            createdBy: (int) $draft->created_by,
            publishAt: $draft->scheduled_at?->toIso8601String(),
            createdAt: $draft->created_at?->toIso8601String() ?? now()->toIso8601String(),
            updatedAt: $draft->updated_at?->toIso8601String() ?? now()->toIso8601String(),
            version: (int) $draft->version,
        );
    }
}
