<?php

declare(strict_types=1);

namespace App\Services;

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
use App\Models\PreviewToken;
use App\Support\HomepagePayloadMapper;

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
        private readonly PageServiceInterface $pageService,
        private readonly HomepageSectionServiceInterface $homepageSectionService,
        private readonly NavigationServiceInterface $navigationService,
        private readonly PreviewTokenStore $tokenStore,
    ) {}

    public function createToken(string $targetType, ?int $targetId, string $locale, int $userId, ?string $device = null): PreviewDTO
    {
        $result = $this->tokenStore->create($targetType, $targetId, $locale, $userId, $device);

        return $this->buildPreviewDto($result['model'], rawToken: $result['raw_token']);
    }

    public function resolveToken(string $token, ?string $locale = null): ?PreviewDTO
    {
        $previewToken = $this->tokenStore->resolve($token);

        return $previewToken instanceof PreviewToken
            ? $this->buildPreviewDto($previewToken, $locale, $token)
            : null;
    }

    public function validateToken(string $token): bool
    {
        return $this->tokenStore->validate($token);
    }

    public function invalidateToken(string $token): bool
    {
        return $this->tokenStore->invalidate($token);
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

        $approvedSections = [];

        foreach ($sections as $section) {
            if (! is_array($section)) {
                continue;
            }

            $key = is_string($section['key'] ?? null) ? $section['key'] : null;

            if ($key === null || ! in_array($key, HomepageSectionServiceInterface::SECTION_KEYS, true)) {
                continue;
            }

            $approvedSections[$key] = $this->sectionFromDraft($section, $locale);
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

    private function sectionFromDraft(array $payload, string $locale): HomepageSectionDTO
    {
        $arabicPayload = $this->sectionDataFromDraft(
            is_array($payload['arabicPayload'] ?? null)
                ? $payload['arabicPayload']
                : (is_array($payload['payload'] ?? null) && $locale === 'ar' ? $payload['payload'] : []),
        );
        $englishPayload = $this->sectionDataFromDraft(
            is_array($payload['englishPayload'] ?? null)
                ? $payload['englishPayload']
                : (is_array($payload['payload'] ?? null) && $locale === 'en' ? $payload['payload'] : []),
        );

        return new HomepageSectionDTO(
            id: (int) ($payload['id'] ?? 0),
            key: (string) ($payload['key'] ?? ''),
            sortOrder: (int) ($payload['sortOrder'] ?? ($payload['sort_order'] ?? 0)),
            isEnabled: (bool) ($payload['isEnabled'] ?? ($payload['is_enabled'] ?? true)),
            payload: $locale === 'en'
                ? ($this->isEmptySectionPayload($englishPayload) ? $this->sectionDataFromDraft((array) ($payload['payload'] ?? [])) : $englishPayload)
                : ($this->isEmptySectionPayload($arabicPayload) ? $this->sectionDataFromDraft((array) ($payload['payload'] ?? [])) : $arabicPayload),
            arabicTranslation: $this->translationFromDraft((array) ($payload['arabicTranslation'] ?? []), 'ar', $arabicPayload),
            englishTranslation: $this->translationFromDraft((array) ($payload['englishTranslation'] ?? []), 'en', $englishPayload),
            arabicPayload: $arabicPayload,
            englishPayload: $englishPayload,
        );
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
