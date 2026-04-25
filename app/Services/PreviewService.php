<?php

declare(strict_types=1);

namespace App\Services;

use App\Contracts\HomepageSectionServiceInterface;
use App\Contracts\NavigationServiceInterface;
use App\Contracts\PageServiceInterface;
use App\Contracts\PreviewServiceInterface;
use App\DTOs\HomepageDTO;
use App\DTOs\HomepageFeatureItemDTO;
use App\DTOs\HomepageSectionDataDTO;
use App\DTOs\HomepageSectionDTO;
use App\DTOs\HomepageSectionTranslationDTO;
use App\DTOs\HomepageStatItemDTO;
use App\DTOs\NavigationActionDTO;
use App\DTOs\PreviewDTO;
use App\DTOs\PreviewPayloadDTO;
use App\Models\HomepageDraft;
use App\Models\PreviewToken;
use Illuminate\Support\Str;

final class PreviewService implements PreviewServiceInterface
{
    public function __construct(
        private readonly PageServiceInterface $pageService,
        private readonly HomepageSectionServiceInterface $homepageSectionService,
        private readonly NavigationServiceInterface $navigationService,
    ) {}

    public function createToken(string $targetType, ?int $targetId, string $locale, int $userId): PreviewDTO
    {
        $this->assertSupportedTargetType($targetType);

        $token = PreviewToken::query()->create([
            'token' => Str::random(64),
            'target_type' => $targetType,
            'target_id' => $targetId,
            'locale' => $locale,
            'device' => null,
            'issued_to_user_id' => $userId,
            'payload_json' => null,
            'expires_at' => now()->addHours(6),
        ]);

        return $this->buildPreviewDto($token);
    }

    public function resolveToken(string $token, ?string $locale = null): ?PreviewDTO
    {
        $previewToken = PreviewToken::query()
            ->where('token', $token)
            ->where('expires_at', '>', now())
            ->first();

        return $previewToken instanceof PreviewToken ? $this->buildPreviewDto($previewToken, $locale) : null;
    }

    public function validateToken(string $token): bool
    {
        return PreviewToken::query()
            ->where('token', $token)
            ->where('expires_at', '>', now())
            ->exists();
    }

    public function invalidateToken(string $token): bool
    {
        return PreviewToken::query()->where('token', $token)->delete() > 0;
    }

    private function buildPreviewDto(PreviewToken $token, ?string $requestedLocale = null): PreviewDTO
    {
        $locale = is_string($requestedLocale) && $requestedLocale !== ''
            ? $requestedLocale
            : (is_string($token->locale) && $token->locale !== '' ? $token->locale : app()->getLocale());
        $payload = $this->buildPayload($token->target_type, $token->target_id, $locale);
        $navigationPath = $token->target_type === 'homepage'
            ? $locale
            : $this->pagePreviewPath($payload, $locale);

        return new PreviewDTO(
            token: (string) $token->token,
            targetType: (string) $token->target_type,
            targetId: $token->target_id !== null ? (int) $token->target_id : null,
            locale: $locale,
            previewUrl: '/'.$locale.'/preview?token='.(string) $token->token,
            payload: new PreviewPayloadDTO(
                page: $payload->page,
                homepage: $payload->homepage,
                navigation: $this->navigationService->getFullNavigationPayload($locale, $navigationPath),
            ),
            expiresAt: $token->expires_at?->toIso8601String(),
        );
    }

    private function buildPayload(string $targetType, ?int $targetId, string $locale): PreviewPayloadDTO
    {
        if ($targetType === 'page' && $targetId !== null) {
            $preview = $this->pageService->buildPreviewPayload($targetId, $locale);

            return $preview->payload;
        }

        return new PreviewPayloadDTO(homepage: $this->buildHomepagePreview($locale));
    }

    private function buildHomepagePreview(string $locale): HomepageDTO
    {
        $draft = HomepageDraft::query()
            ->where('target_type', 'homepage')
            ->latest('updated_at')
            ->first();

        if (! $draft instanceof HomepageDraft || ! is_array($draft->payload_json)) {
            return $this->homepageSectionService->getPublicHomepage($locale);
        }

        $draftHomepage = is_array($draft->payload_json['homepage'] ?? null)
            ? $draft->payload_json['homepage']
            : $draft->payload_json;
        $sections = is_array($draftHomepage['sections'] ?? null) ? $draftHomepage['sections'] : [];

        if ($sections === []) {
            return $this->homepageSectionService->getPublicHomepage($locale);
        }

        return new HomepageDTO(
            locale: $locale,
            direction: $locale === 'ar' ? 'rtl' : 'ltr',
            sections: array_values(array_filter(array_map(
                fn (mixed $section): ?HomepageSectionDTO => is_array($section) ? $this->sectionFromDraft($section) : null,
                $sections
            ))),
        );
    }

    private function sectionFromDraft(array $payload): HomepageSectionDTO
    {
        return new HomepageSectionDTO(
            id: (int) ($payload['id'] ?? 0),
            key: (string) ($payload['key'] ?? ''),
            sortOrder: (int) ($payload['sortOrder'] ?? ($payload['sort_order'] ?? 0)),
            isEnabled: (bool) ($payload['isEnabled'] ?? ($payload['is_enabled'] ?? true)),
            payload: $this->sectionDataFromDraft((array) ($payload['payload'] ?? [])),
            arabicTranslation: $this->translationFromDraft((array) ($payload['arabicTranslation'] ?? []), 'ar'),
            englishTranslation: $this->translationFromDraft((array) ($payload['englishTranslation'] ?? []), 'en'),
        );
    }

    private function sectionDataFromDraft(array $payload): HomepageSectionDataDTO
    {
        return new HomepageSectionDataDTO(
            eyebrow: $this->stringFromDraft($payload, 'eyebrow'),
            title: $this->stringFromDraft($payload, 'title') ?? $this->stringFromDraft($payload, 'headline'),
            summary: $this->stringFromDraft($payload, 'summary'),
            body: $this->stringFromDraft($payload, 'body'),
            imageUrl: $this->stringFromDraft($payload, 'imageUrl'),
            backgroundImageUrl: $this->stringFromDraft($payload, 'backgroundImageUrl'),
            primaryAction: $this->actionFromDraft($payload['primaryAction'] ?? $payload['primary_action'] ?? null),
            secondaryAction: $this->actionFromDraft($payload['secondaryAction'] ?? $payload['secondary_action'] ?? null),
            stats: $this->statsFromDraft($payload['stats'] ?? []),
            featuredItems: $this->featuredItemsFromDraft($payload['featuredItems'] ?? $payload['featured_items'] ?? []),
        );
    }

    private function translationFromDraft(array $payload, string $locale): HomepageSectionTranslationDTO
    {
        return new HomepageSectionTranslationDTO(
            locale: $locale,
            headline: $this->stringFromDraft($payload, 'headline'),
            body: $this->stringFromDraft($payload, 'body'),
            ctaLabel: $this->stringFromDraft($payload, 'ctaLabel'),
            imageAlt: $this->stringFromDraft($payload, 'imageAlt'),
        );
    }

    private function assertSupportedTargetType(string $targetType): void
    {
        if (! in_array($targetType, self::TARGET_TYPES, true)) {
            throw new \InvalidArgumentException('Unsupported preview target type.');
        }
    }

    private function pagePreviewPath(PreviewPayloadDTO $payload, string $locale): ?string
    {
        if ($payload->page === null || $payload->page->metadata->isHomepageShell) {
            return null;
        }

        return trim($this->pageService->resolveLanguageSwitchTargetUrl($payload->page->id, $locale) ?? '', '/');
    }

    /**
     * @param  array<string, mixed>|null  $payload
     */
    private function actionFromDraft(?array $payload): ?NavigationActionDTO
    {
        if (! is_array($payload)) {
            return null;
        }

        $label = $this->stringFromDraft($payload, 'label');
        $url = $this->stringFromDraft($payload, 'url');

        if ($label === null || $url === null) {
            return null;
        }

        return new NavigationActionDTO($label, $url, $this->stringFromDraft($payload, 'target'));
    }

    /**
     * @return array<int, HomepageStatItemDTO>
     */
    private function statsFromDraft(mixed $items): array
    {
        if (! is_array($items)) {
            return [];
        }

        return array_values(array_map(
            static fn (array $item): HomepageStatItemDTO => new HomepageStatItemDTO(
                value: (string) ($item['value'] ?? ''),
                label: (string) ($item['label'] ?? ''),
                description: is_string($item['description'] ?? null) ? $item['description'] : null,
                icon: is_string($item['icon'] ?? null) ? $item['icon'] : null,
            ),
            array_filter($items, static fn (mixed $item): bool => is_array($item))
        ));
    }

    /**
     * @return array<int, HomepageFeatureItemDTO>
     */
    private function featuredItemsFromDraft(mixed $items): array
    {
        if (! is_array($items)) {
            return [];
        }

        return array_values(array_map(
            static fn (array $item): HomepageFeatureItemDTO => new HomepageFeatureItemDTO(
                title: (string) ($item['title'] ?? ''),
                summary: is_string($item['summary'] ?? null) ? $item['summary'] : null,
                imageUrl: is_string($item['imageUrl'] ?? ($item['image_url'] ?? null)) ? (string) ($item['imageUrl'] ?? $item['image_url']) : null,
                url: is_string($item['url'] ?? null) ? $item['url'] : null,
                tags: is_array($item['tags'] ?? null) ? array_values(array_filter($item['tags'], static fn (mixed $tag): bool => is_string($tag))) : [],
            ),
            array_filter($items, static fn (mixed $item): bool => is_array($item))
        ));
    }

    private function stringFromDraft(array $payload, string $key): ?string
    {
        $value = $payload[$key] ?? $payload[strtolower(preg_replace('/(?<!^)[A-Z]/', '_$0', $key) ?? $key)] ?? null;

        return is_string($value) && $value !== '' ? $value : null;
    }
}
