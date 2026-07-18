<?php

declare(strict_types=1);

namespace App\Services\Cms;

use App\Contracts\Cms\AboutEntityCmsServiceInterface;
use App\Contracts\Cms\CmsTargetRegistryInterface;
use App\Contracts\Cms\CmsWorkflowServiceInterface;
use App\Contracts\Media\MediaServiceInterface;
use App\Contracts\Shared\AuditServiceInterface;
use App\Contracts\Shared\CacheServiceInterface;
use App\DTOs\Cms\CmsDraftDTO;
use App\DTOs\Cms\CmsPreviewTokenDTO;
use App\DTOs\Cms\CmsPublishReadinessDTO;
use App\DTOs\Cms\CmsTargetDTO;
use App\Enums\PublicationStatus;
use App\Exceptions\ConflictException;
use App\Models\Cms\CmsDraft;
use App\Models\Cms\CmsTargetContent;
use App\Models\Shared\PreviewToken;
use App\Models\User\User;
use App\Services\Preview\PreviewTokenStore;
use DateTimeInterface;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

final class CmsWorkflowService implements CmsWorkflowServiceInterface
{
    public function __construct(
        private readonly CmsTargetRegistryInterface $targetRegistry,
        private readonly AuditServiceInterface $auditService,
        private readonly CacheServiceInterface $cacheService,
        private readonly PreviewTokenStore $previewTokenStore,
        private readonly MediaServiceInterface $mediaService,
        private readonly AboutEntityCmsServiceInterface $aboutEntityCmsService,
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

            CmsDraft::query()
                ->where('target_key', $target->key)
                ->whereIn('status', PublicationStatus::editableValues())
                ->update(['status' => PublicationStatus::Superseded->value]);

            $draft = CmsDraft::query()->create([
                'target_key' => $target->key,
                'payload_json' => $payload,
                'status' => PublicationStatus::Draft->value,
                'created_by' => $userId,
                'updated_by' => $userId,
                'version' => ($currentVersion ?? 0) + 1,
            ]);

            $this->aboutEntityCmsService->markDraft($target->key);

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

        return DB::transaction(fn (): bool => $this->publishDraft($draft, $userId));
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
            $draft->forceFill([
                'status' => PublicationStatus::Scheduled->value,
                'scheduled_at' => $publishAt,
                'approved_by' => $userId,
                'updated_by' => $userId,
            ])->save();
            $this->aboutEntityCmsService->markScheduled($target->key);
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
            $cancelledSchedules = CmsDraft::query()
                ->where('target_key', $target->key)
                ->where('status', PublicationStatus::Scheduled->value)
                ->update([
                    'status' => PublicationStatus::Draft->value,
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

            return $content instanceof CmsTargetContent || $entityUnpublished || $cancelledSchedules > 0;
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

        if ($target->key === 'about.vision-mission') {
            $this->appendVisionMissionReadinessErrors($payload, $target->locales, $errors);
        }

        if (str_starts_with($target->key, 'e_services.')) {
            $this->appendEServicesDetailReadinessErrors($target->key, $payload, $target->locales, $errors);
        }

        foreach ($this->aboutEntityCmsService->publishErrors($target->key, $payload) as $field => $messages) {
            $errors[$field] = array_values(array_unique([
                ...($errors[$field] ?? []),
                ...$messages,
            ]));
        }

        return new CmsPublishReadinessDTO($errors === [], $errors);
    }

    public function latestEditableDraftVersion(string $targetKey): ?int
    {
        $this->requireTarget($targetKey);

        $draft = $this->latestEditableDraft($targetKey);

        return $draft instanceof CmsDraft ? (int) $draft->version : null;
    }

    /** @return array<string, mixed>|null */
    public function latestEditableDraftPayload(string $targetKey): ?array
    {
        $this->requireTarget($targetKey);

        return $this->latestEditablePayload($targetKey);
    }

    /** @return array<string, mixed>|null */
    public function getPublishedPayload(string $targetKey): ?array
    {
        $this->requireTarget($targetKey);

        $content = CmsTargetContent::query()
            ->where('target_key', $targetKey)
            ->where('status', PublicationStatus::Published->value)
            ->first();

        return $content instanceof CmsTargetContent && is_array($content->payload_json)
            ? $content->payload_json
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

            $readiness = $this->readiness($draft->target_key, is_array($draft->payload_json) ? $draft->payload_json : []);

            if (! $readiness->isReady) {
                continue;
            }

            $userId = $draft->approved_by !== null ? (int) $draft->approved_by : (int) $draft->created_by;

            if (DB::transaction(fn (): bool => $this->publishDraft($draft, $userId))) {
                $published++;
            }
        }

        return $published;
    }

    private function publishDraft(CmsDraft $draft, int $userId): bool
    {
        $publishedAt = now();
        $payload = is_array($draft->payload_json) ? $draft->payload_json : [];
        $this->aboutEntityCmsService->publishTarget($draft->target_key, $payload, $publishedAt);

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
            ->whereIn('status', PublicationStatus::editableValues())
            ->update(['status' => PublicationStatus::Superseded->value]);

        $draft->forceFill([
            'status' => PublicationStatus::Published->value,
            'scheduled_at' => null,
            'published_at' => $publishedAt,
            'approved_by' => $userId,
            'updated_by' => $userId,
        ])->save();

        $this->invalidatePublishedTarget($draft->target_key, $userId, 'cms.published');

        return true;
    }

    private function invalidatePublishedTarget(string $targetKey, int $userId, string $auditAction): void
    {
        $this->previewTokenStore->invalidateCmsTarget($targetKey);

        if (! $this->cacheService->flushTags(['public-pages', 'public-shell', 'seo', 'sitemap', 'cms', 'cms:'.$targetKey])) {
            $this->cacheService->flushAll();
        }

        $this->auditService->log($auditAction, $userId, CmsTargetContent::class, null, [
            'target_key' => $targetKey,
        ]);
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
        $draftId = CmsDraft::query()
            ->where('target_key', $targetKey)
            ->whereIn('status', PublicationStatus::editableValues())
            ->latest('updated_at')
            ->latest('id')
            ->value('id');

        $id = $draftId !== null ? (int) $draftId : null;

        if ($id === null) {
            return null;
        }

        $draft = CmsDraft::query()->find($id);

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

        if (! $user instanceof User || Gate::forUser($user)->denies('preview-content')) {
            throw new AuthorizationException('This user is not authorized to preview CMS content.');
        }

        $this->authorizeTargetWrite($target, $userId);
    }

    private function authorizePublish(CmsTargetDTO $target, int $userId): void
    {
        $user = User::query()->find($userId);

        if (! $user instanceof User || Gate::forUser($user)->denies('publish-content')) {
            throw new AuthorizationException('This user is not authorized to publish CMS content.');
        }

        $this->authorizeTargetWrite($target, $userId);
    }

    /** @param array<string, mixed>|null $payload */
    private function authorizeTargetWrite(CmsTargetDTO $target, int $userId, ?array $payload = null): void
    {
        $user = User::query()->find($userId);
        $ability = $this->manageAbilityForArea($target->area);

        if (! $user instanceof User || Gate::forUser($user)->denies($ability)) {
            throw new AuthorizationException('This user is not authorized to manage this CMS target.');
        }

        $this->aboutEntityCmsService->authorizeTarget($target->key, $userId, $payload);
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

    private function isSafeLocalizedEServicesUrl(mixed $url, string $locale): bool
    {
        return is_string($url)
            && ! str_starts_with($url, '//')
            && preg_match('~^/'.preg_quote($locale, '~').'/(?:e-services|contact)(?:[/?#]|$)~', $url) === 1;
    }

    /** @param array<string, mixed> $payload */
    private function hasBodyLikeContent(array $payload): bool
    {
        foreach (['body', 'content', 'description', 'summary', 'excerpt', 'subheadline', 'role', 'position'] as $key) {
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
