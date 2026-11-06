<?php

declare(strict_types=1);

namespace App\Support;

use App\DTOs\Contact\ContactLinkDTO;
use App\DTOs\Content\ArticleCardDTO;
use App\DTOs\Content\EventCardDTO;
use App\DTOs\Content\ResearchCardDTO;
use App\DTOs\Homepage\HomepageFeatureItemDTO;
use App\DTOs\Homepage\HomepageSectionDataDTO;
use App\DTOs\Homepage\HomepageSectionDTO;
use App\DTOs\Homepage\HomepageSectionTranslationDTO;
use App\DTOs\Homepage\HomepageStatItemDTO;
use App\DTOs\Navigation\NavigationActionDTO;
use App\DTOs\Settings\FooterColumnDTO;
use App\DTOs\Settings\SocialLinkDTO;

/**
 * Centralised mapper for transforming raw arrays into typed homepage DTOs.
 *
 * Extracted from duplicated mapping logic previously spread across
 * HomepageSectionService, HomepagePublishingService, and PreviewService.
 */
final class HomepagePayloadMapper
{
    // ──────────────────────────────────────────────
    //  Static factory methods — array → DTO
    // ──────────────────────────────────────────────

    /**
     * @return array<int, HomepageStatItemDTO>
     */
    public static function statsFromArray(mixed $items): array
    {
        if (! is_array($items)) {
            return [];
        }

        return array_values(array_map(
            static fn (array $item): HomepageStatItemDTO => new HomepageStatItemDTO(
                value: (string) ($item['value'] ?? ''),
                label: (string) ($item['label'] ?? ''),
                description: is_string($item['description'] ?? null) ? $item['description'] : null,
                icon: self::safeAssetUrl(is_string($item['icon'] ?? null) ? $item['icon'] : null),
                prefix: is_string($item['prefix'] ?? null) ? $item['prefix'] : null,
                suffix: is_string($item['suffix'] ?? null) ? $item['suffix'] : null,
                helperText: is_string($item['helperText'] ?? ($item['helper_text'] ?? null))
                    ? (string) ($item['helperText'] ?? $item['helper_text'])
                    : null,
                url: self::safeUrl(is_string($item['url'] ?? null) ? $item['url'] : null),
                sortOrder: is_int($item['sortOrder'] ?? null)
                    ? $item['sortOrder']
                    : (is_int($item['sort_order'] ?? null) ? $item['sort_order'] : null),
            ),
            self::listOfArrays($items),
        ));
    }

    /**
     * @return array<int, HomepageFeatureItemDTO>
     */
    public static function featuredItemsFromArray(mixed $items): array
    {
        if (! is_array($items)) {
            return [];
        }

        return array_values(array_map(
            static fn (array $item): HomepageFeatureItemDTO => new HomepageFeatureItemDTO(
                title: (string) ($item['title'] ?? ''),
                summary: is_string($item['summary'] ?? ($item['shortDescription'] ?? ($item['short_description'] ?? null)))
                    ? (string) ($item['summary'] ?? $item['shortDescription'] ?? $item['short_description'])
                    : null,
                imageUrl: self::safeAssetUrl(is_string($item['imageUrl'] ?? ($item['image_url'] ?? null))
                    ? (string) ($item['imageUrl'] ?? $item['image_url'])
                    : null),
                url: self::safeUrl(is_string($item['url'] ?? ($item['ctaUrl'] ?? ($item['cta_url'] ?? null)))
                    ? (string) ($item['url'] ?? $item['ctaUrl'] ?? $item['cta_url'])
                    : null),
                tags: is_array($item['tags'] ?? null)
                    ? array_values(array_filter($item['tags'], static fn (mixed $tag): bool => is_string($tag)))
                    : [],
            ),
            self::listOfArrays($items),
        ));
    }

    /**
     * @return array<int, ArticleCardDTO>
     */
    public static function articlesFromArray(mixed $items): array
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
                imageUrl: self::safeAssetUrl(is_string($item['imageUrl'] ?? ($item['image_url'] ?? null))
                    ? (string) ($item['imageUrl'] ?? $item['image_url'])
                    : null),
                publishedAt: is_string($item['publishedAt'] ?? ($item['published_at'] ?? null))
                    ? (string) ($item['publishedAt'] ?? $item['published_at'])
                    : null,
                url: self::safeUrl(is_string($item['url'] ?? null) ? $item['url'] : null),
                categoryLabel: is_string($item['categoryLabel'] ?? ($item['category_label'] ?? null))
                    ? (string) ($item['categoryLabel'] ?? $item['category_label'])
                    : null,
                badgeTag: is_string($item['badgeTag'] ?? ($item['badge_tag'] ?? null))
                    ? (string) ($item['badgeTag'] ?? $item['badge_tag'])
                    : null,
            ),
            self::listOfArrays($items),
        ));
    }

    /**
     * @return array<int, ResearchCardDTO>
     */
    public static function researchItemsFromArray(mixed $items): array
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
                summary: is_string($item['summary'] ?? ($item['excerpt'] ?? null))
                    ? (string) ($item['summary'] ?? $item['excerpt'])
                    : null,
                imageUrl: self::safeAssetUrl(is_string($item['imageUrl'] ?? ($item['image_url'] ?? null))
                    ? (string) ($item['imageUrl'] ?? $item['image_url'])
                    : null),
                publishedAt: is_string($item['publishedAt'] ?? ($item['published_at'] ?? null))
                    ? (string) ($item['publishedAt'] ?? $item['published_at'])
                    : null,
                url: self::safeUrl(is_string($item['url'] ?? null) ? $item['url'] : null),
                categoryLabel: is_string($item['categoryLabel'] ?? ($item['category_label'] ?? ($item['categoryType'] ?? ($item['category_type'] ?? null))))
                    ? (string) ($item['categoryLabel'] ?? $item['category_label'] ?? $item['categoryType'] ?? $item['category_type'])
                    : null,
                authors: is_array($item['authors'] ?? null)
                    ? array_values(array_filter($item['authors'], static fn (mixed $author): bool => is_string($author) && $author !== ''))
                    : [],
            ),
            self::listOfArrays($items),
        ));
    }

    /**
     * @return array<int, EventCardDTO>
     */
    public static function eventsFromArray(mixed $items): array
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
                endsAt: is_string($item['endsAt'] ?? ($item['ends_at'] ?? null))
                    ? (string) ($item['endsAt'] ?? $item['ends_at'])
                    : null,
                location: is_string($item['location'] ?? null) ? $item['location'] : null,
                url: self::safeUrl(is_string($item['url'] ?? null) ? $item['url'] : null),
                imageUrl: self::safeAssetUrl(is_string($item['imageUrl'] ?? ($item['image_url'] ?? null))
                    ? (string) ($item['imageUrl'] ?? $item['image_url'])
                    : null),
                timeLabel: is_string($item['timeLabel'] ?? ($item['time'] ?? null))
                    ? (string) ($item['timeLabel'] ?? $item['time'])
                    : null,
            ),
            self::listOfArrays($items),
        ));
    }

    /**
     * @return array<int, FooterColumnDTO>
     */
    public static function footerColumnsFromArray(mixed $items): array
    {
        if (! is_array($items)) {
            return [];
        }

        return array_values(array_map(
            static fn (array $item): FooterColumnDTO => new FooterColumnDTO(
                title: (string) ($item['title'] ?? ''),
                links: array_values(array_filter(array_map(
                    static fn (mixed $link): ?NavigationActionDTO => is_array($link) ? self::actionFromArray($link) : null,
                    is_array($item['links'] ?? null) ? $item['links'] : [],
                ))),
            ),
            self::listOfArrays($items),
        ));
    }

    /**
     * @return array<int, ContactLinkDTO>
     */
    public static function contactLinksFromArray(mixed $items): array
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
            self::listOfArrays($items),
        ));
    }

    /**
     * @return array<int, SocialLinkDTO>
     */
    public static function socialLinksFromArray(mixed $items): array
    {
        if (! is_array($items)) {
            return [];
        }

        return array_values(array_map(
            static fn (array $item): SocialLinkDTO => new SocialLinkDTO(
                platform: (string) ($item['platform'] ?? $item['label'] ?? 'Social'),
                url: self::safeUrl(is_string($item['url'] ?? null) ? $item['url'] : null) ?? '#',
                isEnabled: (bool) ($item['isEnabled'] ?? ($item['is_enabled'] ?? true)),
            ),
            self::listOfArrays($items),
        ));
    }

    /**
     * @param  array<string, mixed>|null  $payload
     */
    public static function actionFromArray(mixed $payload): ?NavigationActionDTO
    {
        if (! is_array($payload)) {
            return null;
        }

        $label = self::stringValue($payload, 'label');
        $url = self::stringValue($payload, 'url');

        $url = self::safeUrl($url);

        if ($label === null || $url === null) {
            return null;
        }

        return new NavigationActionDTO(
            label: $label,
            url: $url,
            target: is_string($payload['target'] ?? null) ? $payload['target'] : null,
        );
    }

    // ──────────────────────────────────────────────
    //  Composite mapping — array ↔ HomepageSectionDataDTO
    // ──────────────────────────────────────────────

    /**
     * @param  array<string, mixed>  $payload
     */
    public static function sectionDataFromArray(array $payload): HomepageSectionDataDTO
    {
        $items = self::listOfArrays($payload['items'] ?? []);
        $featuredItems = self::featuredItemsFromArray($payload['featuredItems'] ?? $payload['featured_items'] ?? []);

        if ($featuredItems === [] && $items !== []) {
            $featuredItems = self::featuredItemsFromArray($items);
        }

        return new HomepageSectionDataDTO(
            eyebrow: self::stringValue($payload, 'eyebrow'),
            subtitle: self::stringValue($payload, 'subtitle') ?? self::stringValue($payload, 'subheadline'),
            badge: self::stringValue($payload, 'badge') ?? self::stringValue($payload, 'kicker'),
            title: self::stringValue($payload, 'headline') ?? self::stringValue($payload, 'title'),
            summary: self::stringValue($payload, 'summary'),
            body: self::stringValue($payload, 'body'),
            videoUrl: self::safeUrl(self::stringValue($payload, 'videoUrl') ?? self::stringValue($payload, 'video_url'), ['http', 'https'], false),
            imageUrl: self::safeAssetUrl(self::stringValue($payload, 'imageUrl') ?? self::stringValue($payload, 'image_url')),
            backgroundImageUrl: self::safeAssetUrl(self::stringValue($payload, 'backgroundImageUrl') ?? self::stringValue($payload, 'background_image_url')),
            primaryAction: self::actionFromArray($payload['primaryAction'] ?? $payload['primary_action'] ?? null),
            secondaryAction: self::actionFromArray($payload['secondaryAction'] ?? $payload['secondary_action'] ?? null),
            sectionAction: self::actionFromArray($payload['sectionAction'] ?? $payload['section_action'] ?? null),
            stats: self::statsFromArray($payload['stats'] ?? []),
            featuredItems: $featuredItems,
            articles: self::articlesFromArray($payload['articles'] ?? []),
            researchItems: self::researchItemsFromArray($payload['researchItems'] ?? $payload['research_items'] ?? []),
            events: self::eventsFromArray($payload['events'] ?? []),
            footerColumns: self::footerColumnsFromArray($payload['footerColumns'] ?? $payload['footer_columns'] ?? []),
            contactLinks: self::contactLinksFromArray($payload['contactLinks'] ?? $payload['contact_links'] ?? []),
            socialLinks: self::socialLinksFromArray($payload['socialLinks'] ?? $payload['social_links'] ?? []),
            items: $items,
            content: is_array($payload['content'] ?? null) ? $payload['content'] : [],
        );
    }

    /**
     * @return array<string, mixed>
     */
    public static function sectionDataToArray(HomepageSectionDataDTO $data): array
    {
        return array_filter([
            'eyebrow' => $data->eyebrow,
            'subtitle' => $data->subtitle,
            'badge' => $data->badge,
            'title' => $data->title,
            'summary' => $data->summary,
            'body' => $data->body,
            'videoUrl' => self::safeUrl($data->videoUrl, ['http', 'https'], false),
            'imageUrl' => self::safeAssetUrl($data->imageUrl),
            'backgroundImageUrl' => self::safeAssetUrl($data->backgroundImageUrl),
            'primaryAction' => self::actionToArray($data->primaryAction),
            'secondaryAction' => self::actionToArray($data->secondaryAction),
            'sectionAction' => self::actionToArray($data->sectionAction),
            'stats' => array_values(array_map(
                static fn (HomepageStatItemDTO $item): array => array_filter([
                    'value' => $item->value,
                    'label' => $item->label,
                    'description' => $item->description,
                    'icon' => self::safeAssetUrl($item->icon),
                    'prefix' => $item->prefix,
                    'suffix' => $item->suffix,
                    'helperText' => $item->helperText,
                    'url' => self::safeUrl($item->url),
                    'sortOrder' => $item->sortOrder,
                ], static fn (mixed $value): bool => $value !== null && $value !== []),
                $data->stats,
            )),
            'featuredItems' => array_values(array_map(
                static fn (HomepageFeatureItemDTO $item): array => array_filter([
                    'title' => $item->title,
                    'summary' => $item->summary,
                    'imageUrl' => self::safeAssetUrl($item->imageUrl),
                    'url' => self::safeUrl($item->url),
                    'tags' => $item->tags,
                ], static fn (mixed $value): bool => $value !== null && $value !== []),
                $data->featuredItems,
            )),
            'articles' => array_values(array_map(
                static fn (ArticleCardDTO $item): array => array_filter([
                    'id' => $item->id,
                    'locale' => $item->locale,
                    'title' => $item->title,
                    'slug' => $item->slug,
                    'excerpt' => $item->excerpt,
                    'imageUrl' => self::safeAssetUrl($item->imageUrl),
                    'publishedAt' => $item->publishedAt,
                    'url' => self::safeUrl($item->url),
                    'categoryLabel' => $item->categoryLabel,
                    'badgeTag' => $item->badgeTag,
                ], static fn (mixed $value): bool => $value !== null && $value !== []),
                $data->articles,
            )),
            'researchItems' => array_values(array_map(
                static fn (ResearchCardDTO $item): array => array_filter([
                    'id' => $item->id,
                    'locale' => $item->locale,
                    'title' => $item->title,
                    'slug' => $item->slug,
                    'summary' => $item->summary,
                    'imageUrl' => self::safeAssetUrl($item->imageUrl),
                    'publishedAt' => $item->publishedAt,
                    'url' => self::safeUrl($item->url),
                    'categoryLabel' => $item->categoryLabel,
                    'authors' => $item->authors,
                ], static fn (mixed $value): bool => $value !== null && $value !== []),
                $data->researchItems,
            )),
            'events' => array_values(array_map(
                static fn (EventCardDTO $item): array => array_filter([
                    'id' => $item->id,
                    'locale' => $item->locale,
                    'title' => $item->title,
                    'slug' => $item->slug,
                    'summary' => $item->summary,
                    'startsAt' => $item->startsAt,
                    'endsAt' => $item->endsAt,
                    'location' => $item->location,
                    'url' => self::safeUrl($item->url),
                    'imageUrl' => self::safeAssetUrl($item->imageUrl),
                    'timeLabel' => $item->timeLabel,
                ], static fn (mixed $value): bool => $value !== null && $value !== []),
                $data->events,
            )),
            'footerColumns' => array_values(array_map(
                static fn (FooterColumnDTO $column): array => [
                    'title' => $column->title,
                    'links' => array_values(array_filter(array_map(
                        static fn (NavigationActionDTO $action): ?array => self::actionToArray($action),
                        $column->links,
                    ))),
                ],
                $data->footerColumns,
            )),
            'contactLinks' => array_values(array_map(
                static fn (ContactLinkDTO $item): array => [
                    'type' => $item->type,
                    'label' => $item->label,
                    'value' => $item->value,
                ],
                $data->contactLinks,
            )),
            'socialLinks' => array_values(array_map(
                static fn (SocialLinkDTO $item): array => [
                    'platform' => $item->platform,
                    'url' => self::safeUrl($item->url) ?? '#',
                    'isEnabled' => $item->isEnabled,
                ],
                $data->socialLinks,
            )),
            'items' => self::sanitizePayloadUrls(array_values($data->items)),
            'content' => self::sanitizePayloadUrls($data->content),
        ], static fn (mixed $value): bool => $value !== null && $value !== []);
    }

    // ──────────────────────────────────────────────
    //  Section serialization — DTO → array
    // ──────────────────────────────────────────────

    /**
     * Serialize an array of HomepageSectionDTOs into a plain array structure
     * suitable for JSON storage in draft payloads.
     *
     * Consolidates the duplicated serialization logic previously in
     * HomepageSectionService and HomepagePublishingService.
     *
     * @param  array<int, HomepageSectionDTO>  $sections
     * @return array<int, array<string, mixed>>
     */
    public static function serializeSections(array $sections): array
    {
        return array_values(array_map(
            static fn (HomepageSectionDTO $section): array => [
                'id' => $section->id,
                'key' => $section->key,
                'sortOrder' => $section->sortOrder,
                'isEnabled' => $section->isEnabled,
                'payload' => self::sectionDataToArray($section->payload),
                'arabicPayload' => self::sectionDataToArray($section->arabicPayload ?? $section->payload),
                'englishPayload' => self::sectionDataToArray($section->englishPayload ?? $section->payload),
                'arabicTranslation' => self::translationToArray($section->arabicTranslation),
                'englishTranslation' => self::translationToArray($section->englishTranslation),
            ],
            $sections,
        ));
    }

    /**
     * Convert a HomepageSectionTranslationDTO into a plain array,
     * filtering out null and empty-string values.
     *
     * Consolidates the duplicated logic previously in
     * HomepageSectionService and HomepagePublishingService.
     *
     * @return array<string, mixed>
     */
    public static function translationToArray(HomepageSectionTranslationDTO $translation): array
    {
        return array_filter([
            'headline' => $translation->headline,
            'body' => $translation->body,
            'ctaLabel' => $translation->ctaLabel,
            'imageAlt' => $translation->imageAlt,
        ], static fn (mixed $value): bool => $value !== null && $value !== '');
    }

    // ──────────────────────────────────────────────
    //  Internal helpers
    // ──────────────────────────────────────────────

    /**
     * @return array<string, mixed>|null
     */
    public static function actionToArray(?NavigationActionDTO $action): ?array
    {
        if (! $action instanceof NavigationActionDTO) {
            return null;
        }

        return array_filter([
            'label' => $action->label,
            'url' => self::safeUrl($action->url),
            'target' => $action->target,
        ], static fn (mixed $value): bool => $value !== null && $value !== '');
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private static function listOfArrays(mixed $items): array
    {
        if (! is_array($items)) {
            return [];
        }

        return array_values(array_filter($items, static fn (mixed $item): bool => is_array($item)));
    }

    /**
     * @param  array<string, mixed>|null  $payload
     */
    private static function stringValue(?array $payload, string $key): ?string
    {
        $value = $payload[$key] ?? null;

        return is_string($value) && $value !== '' ? $value : null;
    }

    /**
     * @param  array<int, string>  $schemes
     */
    private static function safeUrl(?string $url, array $schemes = ['http', 'https', 'mailto', 'tel'], bool $allowRelative = true): ?string
    {
        return UrlSanitizer::sanitize($url, $schemes, $allowRelative);
    }

    private static function safeAssetUrl(?string $url): ?string
    {
        return MediaUrlResolver::resolve($url);
    }

    private static function sanitizePayloadUrls(mixed $payload): mixed
    {
        if (! is_array($payload)) {
            return $payload;
        }

        foreach ($payload as $key => $value) {
            if (is_array($value)) {
                $payload[$key] = self::sanitizePayloadUrls($value);

                continue;
            }

            if (! is_string($value)) {
                continue;
            }

            $normalizedKey = strtolower((string) $key);

            if (str_contains($normalizedKey, 'url') || in_array($normalizedKey, ['href', 'src'], true)) {
                $payload[$key] = self::safeUrl($value) ?? '';
            }
        }

        return $payload;
    }
}
