<?php

declare(strict_types=1);

namespace App\Services;

use App\Contracts\HomepageSectionServiceInterface;
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
use App\DTOs\ResearchCardDTO;
use App\DTOs\SocialLinkDTO;
use App\DTOs\ValidationResultDTO;
use App\Models\HomepageSection;
use App\Models\HomepageSectionTranslation;
use BadMethodCallException;
use Illuminate\Support\Collection;

final class HomepageSectionService implements HomepageSectionServiceInterface
{
    /**
     * @return Collection<int, HomepageSectionDTO>
     */
    public function getSections(): Collection
    {
        return HomepageSection::query()
            ->with('translations')
            ->orderBy('sort_order')
            ->get()
            ->map(fn (HomepageSection $section): HomepageSectionDTO => $this->mapSection($section, 'ar'));
    }

    public function getSectionByKey(string $key): ?HomepageSectionDTO
    {
        $section = HomepageSection::query()
            ->with('translations')
            ->where('key', $key)
            ->first();

        return $section instanceof HomepageSection ? $this->mapSection($section, 'ar') : null;
    }

    public function getPublicHomepage(string $locale): HomepageDTO
    {
        $sections = HomepageSection::query()
            ->with('translations')
            ->enabled()
            ->orderBy('sort_order')
            ->get()
            ->map(fn (HomepageSection $section): HomepageSectionDTO => $this->mapSection($section, $locale))
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
        throw new BadMethodCallException(__METHOD__.' is outside the current public-runtime phase.');
    }

    public function toggleSection(string $key, bool $enabled): bool
    {
        return HomepageSection::query()->where('key', $key)->update(['is_enabled' => $enabled]) > 0;
    }

    public function reorderSections(array $orderedKeys): bool
    {
        foreach (array_values($orderedKeys) as $index => $key) {
            HomepageSection::query()->where('key', $key)->update(['sort_order' => $index + 1]);
        }

        return true;
    }

    public function validateSectionPayload(string $key, HomepageSectionDataDTO $payload, string $locale): ValidationResultDTO
    {
        throw new BadMethodCallException(__METHOD__.' is outside the current public-runtime phase.');
    }

    private function mapSection(HomepageSection $section, string $payloadLocale): HomepageSectionDTO
    {
        $arabicTranslation = $this->findTranslation($section, 'ar');
        $englishTranslation = $this->findTranslation($section, 'en');

        return new HomepageSectionDTO(
            id: (int) $section->getKey(),
            key: (string) $section->key,
            sortOrder: (int) $section->sort_order,
            isEnabled: (bool) $section->is_enabled,
            payload: $this->mapPayload($payloadLocale === 'en' ? $englishTranslation : $arabicTranslation),
            arabicTranslation: $this->mapTranslation('ar', $arabicTranslation),
            englishTranslation: $this->mapTranslation('en', $englishTranslation),
        );
    }

    private function mapTranslation(string $locale, ?HomepageSectionTranslation $translation): HomepageSectionTranslationDTO
    {
        $payload = $translation?->payload_json;

        return new HomepageSectionTranslationDTO(
            locale: $locale,
            headline: $this->stringValue($payload, 'headline') ?? $this->stringValue($payload, 'title'),
            body: $this->stringValue($payload, 'body'),
            ctaLabel: $this->stringValue($payload, 'ctaLabel') ?? $this->stringValue($payload, 'cta_label'),
            imageAlt: $this->stringValue($payload, 'imageAlt') ?? $this->stringValue($payload, 'image_alt'),
        );
    }

    private function mapPayload(?HomepageSectionTranslation $translation): HomepageSectionDataDTO
    {
        $payload = is_array($translation?->payload_json) ? $translation->payload_json : [];

        return new HomepageSectionDataDTO(
            eyebrow: $this->stringValue($payload, 'eyebrow'),
            title: $this->stringValue($payload, 'headline') ?? $this->stringValue($payload, 'title'),
            summary: $this->stringValue($payload, 'summary'),
            body: $this->stringValue($payload, 'body'),
            imageUrl: $this->stringValue($payload, 'imageUrl') ?? $this->stringValue($payload, 'image_url'),
            backgroundImageUrl: $this->stringValue($payload, 'backgroundImageUrl') ?? $this->stringValue($payload, 'background_image_url'),
            primaryAction: $this->mapAction($payload['primaryAction'] ?? $payload['primary_action'] ?? null),
            secondaryAction: $this->mapAction($payload['secondaryAction'] ?? $payload['secondary_action'] ?? null),
            stats: $this->mapStats($payload['stats'] ?? []),
            featuredItems: $this->mapFeaturedItems($payload['featuredItems'] ?? $payload['featured_items'] ?? []),
            articles: $this->mapArticles($payload['articles'] ?? []),
            researchItems: $this->mapResearchItems($payload['researchItems'] ?? $payload['research_items'] ?? []),
            events: $this->mapEvents($payload['events'] ?? []),
            footerColumns: $this->mapFooterColumns($payload['footerColumns'] ?? $payload['footer_columns'] ?? []),
            contactLinks: $this->mapContactLinks($payload['contactLinks'] ?? $payload['contact_links'] ?? []),
            socialLinks: $this->mapSocialLinks($payload['socialLinks'] ?? $payload['social_links'] ?? []),
        );
    }

    private function findTranslation(HomepageSection $section, string $locale): ?HomepageSectionTranslation
    {
        return $section->translations->firstWhere('locale', $locale);
    }

    /**
     * @param  array<string, mixed>|null  $payload
     */
    private function mapAction(?array $payload): ?NavigationActionDTO
    {
        if (! is_array($payload)) {
            return null;
        }

        $label = $payload['label'] ?? null;
        $url = $payload['url'] ?? null;

        if (! is_string($label) || ! is_string($url) || $label === '' || $url === '') {
            return null;
        }

        return new NavigationActionDTO($label, $url, is_string($payload['target'] ?? null) ? $payload['target'] : null);
    }

    /**
     * @return array<int, HomepageStatItemDTO>
     */
    private function mapStats(mixed $items): array
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
    private function mapFeaturedItems(mixed $items): array
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

    /**
     * @return array<int, ArticleCardDTO>
     */
    private function mapArticles(mixed $items): array
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
            ),
            array_filter($items, static fn (mixed $item): bool => is_array($item))
        ));
    }

    /**
     * @return array<int, ResearchCardDTO>
     */
    private function mapResearchItems(mixed $items): array
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
                summary: is_string($item['summary'] ?? null) ? $item['summary'] : null,
                imageUrl: is_string($item['imageUrl'] ?? ($item['image_url'] ?? null)) ? (string) ($item['imageUrl'] ?? $item['image_url']) : null,
                publishedAt: is_string($item['publishedAt'] ?? ($item['published_at'] ?? null)) ? (string) ($item['publishedAt'] ?? $item['published_at']) : null,
                url: is_string($item['url'] ?? null) ? $item['url'] : null,
            ),
            array_filter($items, static fn (mixed $item): bool => is_array($item))
        ));
    }

    /**
     * @return array<int, EventCardDTO>
     */
    private function mapEvents(mixed $items): array
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
                summary: is_string($item['summary'] ?? null) ? $item['summary'] : null,
                startsAt: is_string($item['startsAt'] ?? ($item['starts_at'] ?? null)) ? (string) ($item['startsAt'] ?? $item['starts_at']) : null,
                endsAt: is_string($item['endsAt'] ?? ($item['ends_at'] ?? null)) ? (string) ($item['endsAt'] ?? $item['ends_at']) : null,
                location: is_string($item['location'] ?? null) ? $item['location'] : null,
                url: is_string($item['url'] ?? null) ? $item['url'] : null,
            ),
            array_filter($items, static fn (mixed $item): bool => is_array($item))
        ));
    }

    /**
     * @return array<int, FooterColumnDTO>
     */
    private function mapFooterColumns(mixed $items): array
    {
        if (! is_array($items)) {
            return [];
        }

        return array_values(array_map(
            fn (array $item): FooterColumnDTO => new FooterColumnDTO(
                title: (string) ($item['title'] ?? ''),
                links: array_values(array_filter(array_map(
                    fn (mixed $link): ?NavigationActionDTO => is_array($link) ? $this->mapAction($link) : null,
                    is_array($item['links'] ?? null) ? $item['links'] : []
                ))),
            ),
            array_filter($items, static fn (mixed $item): bool => is_array($item))
        ));
    }

    /**
     * @return array<int, ContactLinkDTO>
     */
    private function mapContactLinks(mixed $items): array
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
            array_filter($items, static fn (mixed $item): bool => is_array($item))
        ));
    }

    /**
     * @return array<int, SocialLinkDTO>
     */
    private function mapSocialLinks(mixed $items): array
    {
        if (! is_array($items)) {
            return [];
        }

        return array_values(array_map(
            static fn (array $item): SocialLinkDTO => new SocialLinkDTO(
                platform: (string) ($item['platform'] ?? $item['label'] ?? 'Social'),
                url: (string) ($item['url'] ?? '#'),
                isEnabled: (bool) ($item['is_enabled'] ?? true),
            ),
            array_filter($items, static fn (mixed $item): bool => is_array($item))
        ));
    }

    /**
     * @param  array<string, mixed>|null  $payload
     */
    private function stringValue(?array $payload, string $key): ?string
    {
        $value = $payload[$key] ?? null;

        return is_string($value) && $value !== '' ? $value : null;
    }
}
