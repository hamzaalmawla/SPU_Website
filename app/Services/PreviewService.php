<?php

declare(strict_types=1);

namespace App\Services;

use App\Contracts\HomepageSectionServiceInterface;
use App\Contracts\NavigationServiceInterface;
use App\Contracts\PageServiceInterface;
use App\Contracts\PreviewServiceInterface;
use App\DTOs\ArticleCardDTO;
use App\DTOs\ContactLinkDTO;
use App\DTOs\EventCardDTO;
use App\DTOs\FooterColumnDTO;
use App\DTOs\HomepageDTO;
use App\DTOs\HomepageFeatureItemDTO;
use App\DTOs\HomepageSectionDataDTO;
use App\DTOs\HomepageSectionDTO;
use App\DTOs\HomepageSectionTranslationDTO;
use App\DTOs\HomepageStatItemDTO;
use App\DTOs\NavigationActionDTO;
use App\DTOs\PreviewDTO;
use App\DTOs\PreviewPayloadDTO;
use App\DTOs\ResearchCardDTO;
use App\DTOs\SocialLinkDTO;
use App\Models\HomepageDraft;
use App\Models\PageDraft;
use App\Models\PreviewToken;
use Illuminate\Support\Str;

final class PreviewService implements PreviewServiceInterface
{
    private const EDITABLE_STATUSES = ['draft', 'scheduled'];

    public function __construct(
        private readonly PageServiceInterface $pageService,
        private readonly HomepageSectionServiceInterface $homepageSectionService,
        private readonly NavigationServiceInterface $navigationService,
    ) {}

    public function createToken(string $targetType, ?int $targetId, string $locale, int $userId, ?string $device = null): PreviewDTO
    {
        $this->assertSupportedTargetType($targetType);
        $this->assertSupportedDevice($device);
        $this->assertSupportedLocale($locale);

        $rawToken = Str::random(64);

        $token = PreviewToken::query()->create([
            'token_hash' => $this->hashToken($rawToken),
            'target_type' => $targetType,
            'target_id' => $targetId,
            'locale' => $locale,
            'device' => $device,
            'issued_to_user_id' => $userId,
            'payload_json' => $this->snapshotPayload($targetType, $targetId),
            'expires_at' => now()->addHours(6),
        ]);

        return $this->buildPreviewDto($token, rawToken: $rawToken);
    }

    public function resolveToken(string $token, ?string $locale = null): ?PreviewDTO
    {
        $previewToken = PreviewToken::query()
            ->where('token_hash', $this->hashToken($token))
            ->where('expires_at', '>', now())
            ->first();

        return $previewToken instanceof PreviewToken ? $this->buildPreviewDto($previewToken, $locale, $token) : null;
    }

    public function validateToken(string $token): bool
    {
        return PreviewToken::query()
            ->where('token_hash', $this->hashToken($token))
            ->where('expires_at', '>', now())
            ->exists();
    }

    public function invalidateToken(string $token): bool
    {
        return PreviewToken::query()->where('token_hash', $this->hashToken($token))->delete() > 0;
    }

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

    private function hashToken(string $token): string
    {
        return hash_hmac('sha256', $token, (string) config('app.key'));
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
        return new HomepageSectionDataDTO(
            eyebrow: $this->stringFromDraft($payload, 'eyebrow'),
            subtitle: $this->stringFromDraft($payload, 'subtitle') ?? $this->stringFromDraft($payload, 'subheadline'),
            badge: $this->stringFromDraft($payload, 'badge') ?? $this->stringFromDraft($payload, 'kicker'),
            title: $this->stringFromDraft($payload, 'title') ?? $this->stringFromDraft($payload, 'headline'),
            summary: $this->stringFromDraft($payload, 'summary'),
            body: $this->stringFromDraft($payload, 'body'),
            videoUrl: $this->stringFromDraft($payload, 'videoUrl'),
            imageUrl: $this->stringFromDraft($payload, 'imageUrl'),
            backgroundImageUrl: $this->stringFromDraft($payload, 'backgroundImageUrl'),
            primaryAction: $this->actionFromDraft($payload['primaryAction'] ?? $payload['primary_action'] ?? null),
            secondaryAction: $this->actionFromDraft($payload['secondaryAction'] ?? $payload['secondary_action'] ?? null),
            sectionAction: $this->actionFromDraft($payload['sectionAction'] ?? $payload['section_action'] ?? null),
            stats: $this->statsFromDraft($payload['stats'] ?? []),
            featuredItems: $this->featuredItemsFromDraft($payload['featuredItems'] ?? $payload['featured_items'] ?? []),
            articles: $this->articlesFromDraft($payload['articles'] ?? []),
            researchItems: $this->researchItemsFromDraft($payload['researchItems'] ?? $payload['research_items'] ?? []),
            events: $this->eventsFromDraft($payload['events'] ?? []),
            footerColumns: $this->footerColumnsFromDraft($payload['footerColumns'] ?? $payload['footer_columns'] ?? []),
            contactLinks: $this->contactLinksFromDraft($payload['contactLinks'] ?? $payload['contact_links'] ?? []),
            socialLinks: $this->socialLinksFromDraft($payload['socialLinks'] ?? $payload['social_links'] ?? []),
            items: is_array($payload['items'] ?? null)
                ? array_values(array_filter($payload['items'], static fn (mixed $item): bool => is_array($item)))
                : [],
            content: is_array($payload['content'] ?? null) ? $payload['content'] : [],
        );
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

    private function assertSupportedTargetType(string $targetType): void
    {
        if (! in_array($targetType, self::TARGET_TYPES, true)) {
            throw new \InvalidArgumentException('Unsupported preview target type.');
        }
    }

    private function assertSupportedDevice(?string $device): void
    {
        if ($device !== null && ! in_array($device, ['desktop', 'tablet', 'mobile'], true)) {
            throw new \InvalidArgumentException('Unsupported preview device.');
        }
    }

    private function assertSupportedLocale(string $locale): void
    {
        if (! in_array($locale, ['ar', 'en'], true)) {
            throw new \InvalidArgumentException('Unsupported preview locale.');
        }
    }

    private function resolveSupportedLocale(?string $preferredLocale, ?string $fallbackLocale = null): string
    {
        foreach ([$preferredLocale, $fallbackLocale, app()->getLocale(), 'ar'] as $candidate) {
            if (is_string($candidate) && in_array($candidate, ['ar', 'en'], true)) {
                return $candidate;
            }
        }

        return 'ar';
    }

    /**
     * @return array<string, mixed>|null
     */
    private function snapshotPayload(string $targetType, ?int $targetId): ?array
    {
        if ($targetType === 'homepage') {
            $draft = HomepageDraft::query()
                ->where('target_type', 'homepage')
                ->whereIn('status', self::EDITABLE_STATUSES)
                ->latest('updated_at')
                ->first();

            return $draft instanceof HomepageDraft && is_array($draft->payload_json)
                ? $draft->payload_json
                : null;
        }

        if ($targetType === 'page' && $targetId !== null) {
            $draft = PageDraft::query()
                ->where('page_id', $targetId)
                ->whereIn('status', self::EDITABLE_STATUSES)
                ->latest('updated_at')
                ->first();

            return $draft instanceof PageDraft && is_array($draft->payload_json)
                ? $draft->payload_json
                : null;
        }

        return null;
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
                prefix: is_string($item['prefix'] ?? null) ? $item['prefix'] : null,
                suffix: is_string($item['suffix'] ?? null) ? $item['suffix'] : null,
                helperText: is_string($item['helperText'] ?? ($item['helper_text'] ?? null)) ? (string) ($item['helperText'] ?? $item['helper_text']) : null,
                url: is_string($item['url'] ?? null) ? $item['url'] : null,
                sortOrder: is_int($item['sortOrder'] ?? null) ? $item['sortOrder'] : (is_int($item['sort_order'] ?? null) ? $item['sort_order'] : null),
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

    private function articlesFromDraft(mixed $items): array
    {
        if (! is_array($items)) {
            return [];
        }

        return array_values(array_map(
            static fn (array $item): ArticleCardDTO => new ArticleCardDTO(
                id: (int) ($item['id'] ?? 0),
                locale: (string) ($item['locale'] ?? 'ar'),
                title: (string) ($item['title'] ?? ''),
                slug: (string) ($item['slug'] ?? ''),
                excerpt: is_string($item['excerpt'] ?? null) ? $item['excerpt'] : null,
                imageUrl: is_string($item['imageUrl'] ?? ($item['image_url'] ?? null)) ? (string) ($item['imageUrl'] ?? $item['image_url']) : null,
                publishedAt: is_string($item['publishedAt'] ?? ($item['published_at'] ?? null)) ? (string) ($item['publishedAt'] ?? $item['published_at']) : null,
                url: is_string($item['url'] ?? null) ? $item['url'] : null,
                categoryLabel: is_string($item['categoryLabel'] ?? ($item['category_label'] ?? null)) ? (string) ($item['categoryLabel'] ?? $item['category_label']) : null,
                badgeTag: is_string($item['badgeTag'] ?? ($item['badge_tag'] ?? null)) ? (string) ($item['badgeTag'] ?? $item['badge_tag']) : null,
            ),
            array_filter($items, static fn (mixed $item): bool => is_array($item)),
        ));
    }

    private function researchItemsFromDraft(mixed $items): array
    {
        if (! is_array($items)) {
            return [];
        }

        return array_values(array_map(
            static fn (array $item): ResearchCardDTO => new ResearchCardDTO(
                id: (int) ($item['id'] ?? 0),
                locale: (string) ($item['locale'] ?? 'ar'),
                title: (string) ($item['title'] ?? ''),
                slug: (string) ($item['slug'] ?? ''),
                summary: is_string($item['summary'] ?? ($item['excerpt'] ?? null)) ? (string) ($item['summary'] ?? $item['excerpt']) : null,
                imageUrl: is_string($item['imageUrl'] ?? ($item['image_url'] ?? null)) ? (string) ($item['imageUrl'] ?? $item['image_url']) : null,
                publishedAt: is_string($item['publishedAt'] ?? ($item['published_at'] ?? null)) ? (string) ($item['publishedAt'] ?? $item['published_at']) : null,
                url: is_string($item['url'] ?? null) ? $item['url'] : null,
                categoryLabel: is_string($item['categoryLabel'] ?? ($item['category_label'] ?? ($item['categoryType'] ?? ($item['category_type'] ?? null))))
                    ? (string) ($item['categoryLabel'] ?? $item['category_label'] ?? $item['categoryType'] ?? $item['category_type'])
                    : null,
                authors: is_array($item['authors'] ?? null)
                    ? array_values(array_filter($item['authors'], static fn (mixed $author): bool => is_string($author) && $author !== ''))
                    : [],
            ),
            array_filter($items, static fn (mixed $item): bool => is_array($item)),
        ));
    }

    private function eventsFromDraft(mixed $items): array
    {
        if (! is_array($items)) {
            return [];
        }

        return array_values(array_map(
            static fn (array $item): EventCardDTO => new EventCardDTO(
                id: (int) ($item['id'] ?? 0),
                locale: (string) ($item['locale'] ?? 'ar'),
                title: (string) ($item['title'] ?? ''),
                slug: (string) ($item['slug'] ?? ''),
                summary: is_string($item['summary'] ?? ($item['shortDescription'] ?? ($item['short_description'] ?? null)))
                    ? (string) ($item['summary'] ?? $item['shortDescription'] ?? $item['short_description'])
                    : null,
                startsAt: is_string($item['startsAt'] ?? ($item['starts_at'] ?? ($item['date'] ?? null)))
                    ? (string) ($item['startsAt'] ?? $item['starts_at'] ?? $item['date'])
                    : null,
                endsAt: is_string($item['endsAt'] ?? ($item['ends_at'] ?? null)) ? (string) ($item['endsAt'] ?? $item['ends_at']) : null,
                location: is_string($item['location'] ?? null) ? $item['location'] : null,
                url: is_string($item['url'] ?? null) ? $item['url'] : null,
                imageUrl: is_string($item['imageUrl'] ?? ($item['image_url'] ?? null)) ? (string) ($item['imageUrl'] ?? $item['image_url']) : null,
                timeLabel: is_string($item['timeLabel'] ?? ($item['time'] ?? null)) ? (string) ($item['timeLabel'] ?? $item['time']) : null,
            ),
            array_filter($items, static fn (mixed $item): bool => is_array($item)),
        ));
    }

    private function footerColumnsFromDraft(mixed $items): array
    {
        if (! is_array($items)) {
            return [];
        }

        return array_values(array_map(
            fn (array $item): FooterColumnDTO => new FooterColumnDTO(
                title: (string) ($item['title'] ?? ''),
                links: array_values(array_filter(array_map(
                    fn (mixed $link): ?NavigationActionDTO => is_array($link) ? $this->actionFromDraft($link) : null,
                    is_array($item['links'] ?? null) ? $item['links'] : [],
                ))),
            ),
            array_filter($items, static fn (mixed $item): bool => is_array($item)),
        ));
    }

    private function contactLinksFromDraft(mixed $items): array
    {
        if (! is_array($items)) {
            return [];
        }

        return array_values(array_map(
            static fn (array $item): ContactLinkDTO => new ContactLinkDTO(
                type: (string) ($item['type'] ?? 'text'),
                label: (string) ($item['label'] ?? $item['value'] ?? ''),
                value: (string) ($item['value'] ?? ''),
            ),
            array_filter($items, static fn (mixed $item): bool => is_array($item)),
        ));
    }

    private function socialLinksFromDraft(mixed $items): array
    {
        if (! is_array($items)) {
            return [];
        }

        return array_values(array_map(
            static fn (array $item): SocialLinkDTO => new SocialLinkDTO(
                platform: (string) ($item['platform'] ?? $item['label'] ?? 'Social'),
                url: (string) ($item['url'] ?? '#'),
                isEnabled: (bool) ($item['isEnabled'] ?? ($item['is_enabled'] ?? true)),
            ),
            array_filter($items, static fn (mixed $item): bool => is_array($item)),
        ));
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
