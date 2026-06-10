<?php

declare(strict_types=1);

namespace App\Services;

use App\Contracts\AuditServiceInterface;
use App\Contracts\HomepageSectionServiceInterface;
use App\Contracts\NavigationServiceInterface;
use App\Contracts\PageServiceInterface;
use App\Contracts\PreviewServiceInterface;
use App\DTOs\HomepageDTO;
use App\DTOs\HomepageSectionDataDTO;
use App\DTOs\HomepageSectionDTO;
use App\DTOs\HomepageSectionTranslationDTO;
use App\DTOs\PreviewDTO;
use App\DTOs\PreviewPayloadDTO;
use App\Models\HomepageDraft;
use App\Models\Page;
use App\Models\PreviewToken;
use App\Models\User;
use App\Support\HomepagePayloadMapper;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Gate;

/**
 * Preview assembly orchestrator.
 *
 * Delegates token lifecycle (create, resolve, validate, invalidate, hash)
 * to PreviewTokenStore and focuses on building preview DTOs from draft content.
 */
final class PreviewService implements PreviewServiceInterface
{
    private const EDITABLE_STATUSES = ['draft', 'scheduled'];

    public function __construct(
        private readonly AuditServiceInterface $auditService,
        private readonly PageServiceInterface $pageService,
        private readonly HomepageSectionServiceInterface $homepageSectionService,
        private readonly NavigationServiceInterface $navigationService,
        private readonly PreviewTokenStore $tokenStore,
    ) {}

    public function createToken(string $targetType, ?int $targetId, string $locale, int $userId, ?string $device = null): PreviewDTO
    {
        $this->authorizePreview($targetType, $targetId, $userId);

        $result = $this->tokenStore->create($targetType, $targetId, $locale, $userId, $device);

        $this->auditService->log('preview.created', $userId, PreviewToken::class, (int) $result['model']->getKey(), [
            'target_type' => $targetType,
            'target_id' => $targetId,
            'locale' => $locale,
            'expires_at' => $result['model']->expires_at?->toIso8601String(),
        ]);

        return $this->buildPreviewDto($result['model'], rawToken: $result['raw_token']);
    }

    public function resolveToken(string $token, ?string $locale = null): ?PreviewDTO
    {
        $previewToken = $this->tokenStore->resolve($token);

        if (! $previewToken instanceof PreviewToken) {
            return null;
        }

        $this->auditService->log('preview.resolved', null, PreviewToken::class, (int) $previewToken->getKey(), [
            'target_type' => $previewToken->target_type,
            'target_id' => $previewToken->target_id,
            'locale' => $locale ?? $previewToken->locale,
        ]);

        return $this->buildPreviewDto($previewToken, $locale, $token);
    }

    public function validateToken(string $token): bool
    {
        return $this->tokenStore->validate($token);
    }

    public function invalidateToken(string $token): bool
    {
        $previewToken = $this->tokenStore->resolve($token);
        $invalidated = $this->tokenStore->invalidate($token);

        if ($invalidated && $previewToken instanceof PreviewToken) {
            $this->auditService->log('preview.invalidated', null, PreviewToken::class, (int) $previewToken->getKey(), [
                'target_type' => $previewToken->target_type,
                'target_id' => $previewToken->target_id,
            ]);
        }

        return $invalidated;
    }

    private function authorizePreview(string $targetType, ?int $targetId, int $userId): void
    {
        $user = User::query()->find($userId);

        if (! $user instanceof User || Gate::forUser($user)->denies('preview-content')) {
            throw new AuthorizationException('This user is not authorized to create preview tokens.');
        }

        if ($targetType === 'homepage') {
            if ($targetId !== null || Gate::forUser($user)->denies('manage-homepage')) {
                throw new AuthorizationException('This user is not authorized to preview the homepage.');
            }

            return;
        }

        if ($targetType === 'page' && $targetId !== null) {
            $page = Page::query()->find($targetId);

            if (! $page instanceof Page || Gate::forUser($user)->denies('update', $page)) {
                throw new AuthorizationException('This user is not authorized to preview this page.');
            }

            return;
        }

        throw new AuthorizationException('This preview target is not supported.');
    }

    // ------------------------------------------------------------------
    // Preview DTO assembly
    // ------------------------------------------------------------------

    private function buildPreviewDto(PreviewToken $token, ?string $requestedLocale = null, ?string $rawToken = null): PreviewDTO
    {
        $locale = $this->resolveSupportedLocale($requestedLocale, is_string($token->locale) ? $token->locale : null);
        $payload = $this->buildPayload(
            $token->target_type,
            $token->target_id,
            $locale,
            is_array($token->payload_json) ? $token->payload_json : null,
        );
        $navigationPath = $token->target_type === 'homepage'
            ? $locale
            : $this->pagePreviewPath($payload, $locale);

        return new PreviewDTO(
            token: $rawToken ?? '',
            targetType: (string) $token->target_type,
            targetId: $token->target_id !== null ? (int) $token->target_id : null,
            locale: $locale,
            previewUrl: '/'.$locale.'/preview?token='.($rawToken ?? ''),
            payload: new PreviewPayloadDTO(
                page: $payload->page,
                homepage: $payload->homepage,
                navigation: $this->navigationService->getFullNavigationPayload($locale, $navigationPath),
            ),
            expiresAt: $token->expires_at?->toIso8601String(),
            device: is_string($token->device) && $token->device !== '' ? $token->device : null,
        );
    }

    /**
     * @param  array<string, mixed>|null  $snapshot
     */
    private function buildPayload(string $targetType, ?int $targetId, string $locale, ?array $snapshot = null): PreviewPayloadDTO
    {
        if ($targetType === 'page' && $targetId !== null) {
            $preview = $snapshot !== null
                ? $this->pageService->buildPreviewPayloadFromSnapshot($targetId, $snapshot, $locale)
                : $this->pageService->buildPreviewPayload($targetId, $locale);

            return $preview->payload;
        }

        return new PreviewPayloadDTO(homepage: $this->buildHomepagePreview($locale, $snapshot));
    }

    /**
     * @param  array<string, mixed>|null  $snapshot
     */
    private function buildHomepagePreview(string $locale, ?array $snapshot = null): HomepageDTO
    {
        $draftHomepage = is_array($snapshot['homepage'] ?? null)
            ? $snapshot['homepage']
            : $snapshot;

        if (! is_array($draftHomepage)) {
            $draft = HomepageDraft::query()
                ->where('target_type', 'homepage')
                ->whereIn('status', self::EDITABLE_STATUSES)
                ->latest('updated_at')
                ->first();

            if (! $draft instanceof HomepageDraft || ! is_array($draft->payload_json)) {
                return $this->homepageSectionService->getPublicHomepage($locale);
            }

            $draftHomepage = is_array($draft->payload_json['homepage'] ?? null)
                ? $draft->payload_json['homepage']
                : $draft->payload_json;
        }

        $sections = is_array($draftHomepage['sections'] ?? null) ? $draftHomepage['sections'] : [];

        if ($sections === []) {
            return $this->homepageSectionService->getPublicHomepage($locale);
        }

        $fallbackSections = collect($this->homepageSectionService->getPublicHomepage($locale)->sections)->keyBy('key');
        $approvedSections = [];

        foreach ($sections as $section) {
            if (! is_array($section)) {
                continue;
            }

            $key = is_string($section['key'] ?? null) ? $section['key'] : null;

            if ($key === null || ! in_array($key, HomepageSectionServiceInterface::SECTION_KEYS, true)) {
                continue;
            }

            $fallback = $fallbackSections->get($key);

            $approvedSections[$key] = $this->sectionFromDraft(
                $section,
                $locale,
                $fallback instanceof HomepageSectionDTO ? $fallback : null,
            );
        }

        if ($approvedSections === []) {
            return $this->homepageSectionService->getPublicHomepage($locale);
        }

        return new HomepageDTO(
            locale: $locale,
            direction: $locale === 'ar' ? 'rtl' : 'ltr',
            sections: array_values(array_filter(array_map(
                static fn (string $key): ?HomepageSectionDTO => $approvedSections[$key] ?? null,
                HomepageSectionServiceInterface::SECTION_KEYS,
            ))),
        );
    }

    // ------------------------------------------------------------------
    // Section / translation mapping helpers
    // ------------------------------------------------------------------

    private function sectionFromDraft(array $payload, string $locale, ?HomepageSectionDTO $fallback = null): HomepageSectionDTO
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
        $genericPayload = $this->mergeDraftPayload(
            $fallbackPayload,
            is_array($payload['payload'] ?? null) ? $payload['payload'] : [],
        );
        $arabicPayload = $this->sectionDataFromDraft(
            is_array($payload['arabicPayload'] ?? null)
                ? $this->mergeDraftPayload($fallbackArabicPayload, $payload['arabicPayload'])
                : ($locale === 'ar' ? $genericPayload : $fallbackArabicPayload),
        );
        $englishPayload = $this->sectionDataFromDraft(
            is_array($payload['englishPayload'] ?? null)
                ? $this->mergeDraftPayload($fallbackEnglishPayload, $payload['englishPayload'])
                : ($locale === 'en' ? $genericPayload : $fallbackEnglishPayload),
        );

        return new HomepageSectionDTO(
            id: (int) ($payload['id'] ?? $fallback?->id ?? 0),
            key: (string) ($payload['key'] ?? ''),
            sortOrder: (int) ($payload['sortOrder'] ?? ($payload['sort_order'] ?? $fallback?->sortOrder ?? 0)),
            isEnabled: (bool) ($payload['isEnabled'] ?? ($payload['is_enabled'] ?? $fallback?->isEnabled ?? true)),
            payload: $locale === 'en'
                ? ($this->isEmptySectionPayload($englishPayload) ? $this->sectionDataFromDraft($genericPayload) : $englishPayload)
                : ($this->isEmptySectionPayload($arabicPayload) ? $this->sectionDataFromDraft($genericPayload) : $arabicPayload),
            arabicTranslation: $this->translationFromDraft((array) ($payload['arabicTranslation'] ?? []), 'ar', $arabicPayload),
            englishTranslation: $this->translationFromDraft((array) ($payload['englishTranslation'] ?? []), 'en', $englishPayload),
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
    private function mergeDraftPayload(array $fallback, array $draft): array
    {
        return array_replace_recursive($fallback, $draft);
    }

    private function sectionDataFromDraft(array $payload): HomepageSectionDataDTO
    {
        return HomepagePayloadMapper::sectionDataFromArray($payload);
    }

    private function translationFromDraft(array $payload, string $locale, HomepageSectionDataDTO $fallback): HomepageSectionTranslationDTO
    {
        return new HomepageSectionTranslationDTO(
            locale: $locale,
            headline: $this->stringFromDraft($payload, 'headline')
                ?? $this->stringFromDraft($payload, 'title')
                ?? $fallback->title,
            body: $this->stringFromDraft($payload, 'body')
                ?? $this->stringFromDraft($payload, 'summary')
                ?? $fallback->summary
                ?? $fallback->body,
            ctaLabel: $this->stringFromDraft($payload, 'ctaLabel')
                ?? $this->stringFromDraft($payload, 'cta_label')
                ?? $fallback->primaryAction?->label
                ?? $fallback->sectionAction?->label,
            imageAlt: $this->stringFromDraft($payload, 'imageAlt') ?? $this->stringFromDraft($payload, 'image_alt'),
        );
    }

    // ------------------------------------------------------------------
    // Utility helpers
    // ------------------------------------------------------------------

    private function resolveSupportedLocale(?string $preferredLocale, ?string $fallbackLocale = null): string
    {
        foreach ([$preferredLocale, $fallbackLocale, app()->getLocale(), 'ar'] as $candidate) {
            if (is_string($candidate) && in_array($candidate, ['ar', 'en'], true)) {
                return $candidate;
            }
        }

        return 'ar';
    }

    private function pagePreviewPath(PreviewPayloadDTO $payload, string $locale): ?string
    {
        if ($payload->page === null || $payload->page->metadata->isHomepageShell) {
            return null;
        }

        return trim($this->pageService->resolveLanguageSwitchTargetUrl($payload->page->id, $locale) ?? '', '/');
    }

    private function isEmptySectionPayload(HomepageSectionDataDTO $payload): bool
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

    private function stringFromDraft(array $payload, string $key): ?string
    {
        $value = $payload[$key] ?? $payload[strtolower(preg_replace('/(?<!^)[A-Z]/', '_$0', $key) ?? $key)] ?? null;

        return is_string($value) && $value !== '' ? $value : null;
    }
}
