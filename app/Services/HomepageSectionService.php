<?php

declare(strict_types=1);

namespace App\Services;

use App\Contracts\AuditServiceInterface;
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
use App\DTOs\ValidationMessageDTO;
use App\DTOs\ValidationResultDTO;
use App\Models\HomepageDraft;
use App\Models\HomepageSection;
use App\Models\HomepageSectionTranslation;
use App\Models\User;
use Illuminate\Contracts\Auth\Factory as AuthFactory;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

final class HomepageSectionService implements HomepageSectionServiceInterface
{
    private const EDITABLE_STATUSES = ['draft', 'scheduled'];

    public function __construct(
        private readonly AuditServiceInterface $auditService,
        private readonly AuthFactory $authFactory,
    ) {}

    /**
     * @return Collection<int, HomepageSectionDTO>
     */
    public function getSections(): Collection
    {
        $draft = $this->latestEditableDraft();

        return $draft instanceof HomepageDraft
            ? $this->sectionsFromDraft($draft)
            : $this->publishedSections();
    }

    public function getSectionByKey(string $key): ?HomepageSectionDTO
    {
        $this->assertApprovedKey($key);

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
            ->map(fn (HomepageSection $section): HomepageSectionDTO => $this->mapSection($section, $locale))
            ->filter(fn (HomepageSectionDTO $section): bool => $this->hasRenderablePayloadForLocale($section, $locale))
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
        $this->assertApprovedKey($key);

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
        $this->assertApprovedKey($key);

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

        if (! $this->hasExactApprovedKeySet($normalizedKeys)) {
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
        $this->assertApprovedKey($key);

        $normalizedPayload = $this->sectionDataToArray($payload);
        $validator = Validator::make($normalizedPayload, $this->rulesForSection($key));
        $this->applyConditionalRules($validator, $key, $normalizedPayload);

        if (! in_array($locale, ['ar', 'en'], true)) {
            $validator->errors()->add('locale', 'The locale must be either ar or en.');
        }

        if (! $validator->fails()) {
            return new ValidationResultDTO(isValid: true);
        }

        $errors = [];

        foreach ($validator->errors()->messages() as $field => $messages) {
            $errors[] = new ValidationMessageDTO(
                field: $field,
                messages: array_values(array_filter($messages, static fn (mixed $message): bool => is_string($message))),
            );
        }

        return new ValidationResultDTO(isValid: false, errors: $errors);
    }

    /**
     * @return Collection<int, HomepageSectionDTO>
     */
    private function publishedSections(): Collection
    {
        return HomepageSection::query()
            ->with('translations')
            ->whereIn('key', self::SECTION_KEYS)
            ->orderBy('sort_order')
            ->get()
            ->map(fn (HomepageSection $section): HomepageSectionDTO => $this->mapSection($section, 'ar'))
            ->sortBy(fn (HomepageSectionDTO $section): int => $section->sortOrder)
            ->values();
    }

    /**
     * @return Collection<string, HomepageSectionDTO>
     */
    private function editableSectionsIndexed(): Collection
    {
        return $this->getSections()->keyBy('key');
    }

    private function latestEditableDraft(): ?HomepageDraft
    {
        return HomepageDraft::query()
            ->where('target_type', 'homepage')
            ->whereIn('status', self::EDITABLE_STATUSES)
            ->latest('updated_at')
            ->first();
    }

    /**
     * @return Collection<int, HomepageSectionDTO>
     */
    private function sectionsFromDraft(HomepageDraft $draft): Collection
    {
        $publishedSections = $this->publishedSections()->keyBy('key');
        $draftSections = [];

        foreach ($this->draftSectionPayloads($draft) as $sectionPayload) {
            $key = is_string($sectionPayload['key'] ?? null) ? $sectionPayload['key'] : null;

            if ($key !== null && in_array($key, self::SECTION_KEYS, true)) {
                $draftSections[$key] = $sectionPayload;
            }
        }

        $sections = collect();

        foreach (self::SECTION_KEYS as $defaultIndex => $key) {
            $fallback = $publishedSections->get($key) ?? $this->emptySection($key, $defaultIndex + 1);
            $sections->push(
                isset($draftSections[$key])
                    ? $this->sectionFromDraftArray($draftSections[$key], $fallback, 'ar')
                    : $fallback,
            );
        }

        return $sections
            ->sortBy(fn (HomepageSectionDTO $section): int => $section->sortOrder)
            ->values();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function draftSectionPayloads(HomepageDraft $draft): array
    {
        $draftHomepage = is_array($draft->payload_json['homepage'] ?? null)
            ? $draft->payload_json['homepage']
            : $draft->payload_json;
        $sections = is_array($draftHomepage['sections'] ?? null) ? $draftHomepage['sections'] : [];

        return array_values(array_filter($sections, static fn (mixed $section): bool => is_array($section)));
    }

    private function mapSection(HomepageSection $section, string $payloadLocale): HomepageSectionDTO
    {
        $arabicTranslation = $this->findTranslation($section, 'ar');
        $englishTranslation = $this->findTranslation($section, 'en');
        $arabicPayload = $this->mapPayload($arabicTranslation);
        $englishPayload = $this->mapPayload($englishTranslation);

        return new HomepageSectionDTO(
            id: (int) $section->getKey(),
            key: (string) $section->key,
            sortOrder: (int) $section->sort_order,
            isEnabled: (bool) $section->is_enabled,
            payload: $payloadLocale === 'en' ? $englishPayload : $arabicPayload,
            arabicTranslation: $this->translationFromPayload($this->translationPayloadArray($arabicTranslation, $arabicPayload), 'ar'),
            englishTranslation: $this->translationFromPayload($this->translationPayloadArray($englishTranslation, $englishPayload), 'en'),
            arabicPayload: $arabicPayload,
            englishPayload: $englishPayload,
        );
    }

    private function sectionFromDraftArray(array $payload, HomepageSectionDTO $fallback, string $payloadLocale): HomepageSectionDTO
    {
        $genericPayload = is_array($payload['payload'] ?? null) ? $payload['payload'] : [];
        $fallbackArabicPayload = $this->sectionDataToArray($fallback->arabicPayload ?? $fallback->payload);
        $fallbackEnglishPayload = $this->sectionDataToArray($fallback->englishPayload ?? $fallback->payload);
        $arabicPayloadArray = is_array($payload['arabicPayload'] ?? null)
            ? $payload['arabicPayload']
            : ($payloadLocale === 'ar' && $genericPayload !== [] ? $genericPayload : $fallbackArabicPayload);
        $englishPayloadArray = is_array($payload['englishPayload'] ?? null)
            ? $payload['englishPayload']
            : ($payloadLocale === 'en' && $genericPayload !== [] ? $genericPayload : $fallbackEnglishPayload);

        $arabicPayload = $this->sectionDataFromArray($arabicPayloadArray);
        $englishPayload = $this->sectionDataFromArray($englishPayloadArray);

        return new HomepageSectionDTO(
            id: is_int($payload['id'] ?? null) ? $payload['id'] : $fallback->id,
            key: is_string($payload['key'] ?? null) && $payload['key'] !== '' ? $payload['key'] : $fallback->key,
            sortOrder: is_int($payload['sortOrder'] ?? null)
                ? $payload['sortOrder']
                : (is_int($payload['sort_order'] ?? null) ? $payload['sort_order'] : $fallback->sortOrder),
            isEnabled: is_bool($payload['isEnabled'] ?? null)
                ? $payload['isEnabled']
                : (is_bool($payload['is_enabled'] ?? null) ? $payload['is_enabled'] : $fallback->isEnabled),
            payload: $payloadLocale === 'en' ? $englishPayload : $arabicPayload,
            arabicTranslation: $this->translationFromPayload(
                is_array($payload['arabicTranslation'] ?? null) ? $payload['arabicTranslation'] : $arabicPayloadArray,
                'ar',
            ),
            englishTranslation: $this->translationFromPayload(
                is_array($payload['englishTranslation'] ?? null) ? $payload['englishTranslation'] : $englishPayloadArray,
                'en',
            ),
            arabicPayload: $arabicPayload,
            englishPayload: $englishPayload,
        );
    }

    private function mapPayload(?HomepageSectionTranslation $translation): HomepageSectionDataDTO
    {
        return $this->sectionDataFromArray(is_array($translation?->payload_json) ? $translation->payload_json : []);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function sectionDataFromArray(array $payload): HomepageSectionDataDTO
    {
        $items = $this->listOfArrays($payload['items'] ?? []);
        $featuredItems = $this->mapFeaturedItems($payload['featuredItems'] ?? $payload['featured_items'] ?? []);

        if ($featuredItems === [] && $items !== []) {
            $featuredItems = $this->mapFeaturedItems($items);
        }

        return new HomepageSectionDataDTO(
            eyebrow: $this->stringValue($payload, 'eyebrow'),
            subtitle: $this->stringValue($payload, 'subtitle') ?? $this->stringValue($payload, 'subheadline'),
            badge: $this->stringValue($payload, 'badge') ?? $this->stringValue($payload, 'kicker'),
            title: $this->stringValue($payload, 'headline') ?? $this->stringValue($payload, 'title'),
            summary: $this->stringValue($payload, 'summary'),
            body: $this->stringValue($payload, 'body'),
            videoUrl: $this->stringValue($payload, 'videoUrl') ?? $this->stringValue($payload, 'video_url'),
            imageUrl: $this->stringValue($payload, 'imageUrl') ?? $this->stringValue($payload, 'image_url'),
            backgroundImageUrl: $this->stringValue($payload, 'backgroundImageUrl') ?? $this->stringValue($payload, 'background_image_url'),
            primaryAction: $this->mapAction($payload['primaryAction'] ?? $payload['primary_action'] ?? null),
            secondaryAction: $this->mapAction($payload['secondaryAction'] ?? $payload['secondary_action'] ?? null),
            sectionAction: $this->mapAction($payload['sectionAction'] ?? $payload['section_action'] ?? null),
            stats: $this->mapStats($payload['stats'] ?? []),
            featuredItems: $featuredItems,
            articles: $this->mapArticles($payload['articles'] ?? []),
            researchItems: $this->mapResearchItems($payload['researchItems'] ?? $payload['research_items'] ?? []),
            events: $this->mapEvents($payload['events'] ?? []),
            footerColumns: $this->mapFooterColumns($payload['footerColumns'] ?? $payload['footer_columns'] ?? []),
            contactLinks: $this->mapContactLinks($payload['contactLinks'] ?? $payload['contact_links'] ?? []),
            socialLinks: $this->mapSocialLinks($payload['socialLinks'] ?? $payload['social_links'] ?? []),
            items: $items,
            content: is_array($payload['content'] ?? null) ? $payload['content'] : [],
        );
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function translationFromPayload(array $payload, string $locale): HomepageSectionTranslationDTO
    {
        $title = $this->stringValue($payload, 'headline') ?? $this->stringValue($payload, 'title');
        $body = $this->stringValue($payload, 'summary') ?? $this->stringValue($payload, 'body');
        $cta = is_array($payload['primaryAction'] ?? null)
            ? $this->stringValue($payload['primaryAction'], 'label')
            : $this->stringValue($payload, 'ctaLabel') ?? $this->stringValue($payload, 'cta_label');

        return new HomepageSectionTranslationDTO(
            locale: $locale,
            headline: $title,
            body: $body,
            ctaLabel: $cta,
            imageAlt: $this->contentString($payload, 'imageAlt') ?? $this->contentString($payload, 'image_alt'),
        );
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

        return new NavigationActionDTO(
            label: $label,
            url: $url,
            target: is_string($payload['target'] ?? null) ? $payload['target'] : null,
        );
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
                prefix: is_string($item['prefix'] ?? null) ? $item['prefix'] : null,
                suffix: is_string($item['suffix'] ?? null) ? $item['suffix'] : null,
                helperText: is_string($item['helperText'] ?? ($item['helper_text'] ?? null)) ? (string) ($item['helperText'] ?? $item['helper_text']) : null,
                url: is_string($item['url'] ?? null) ? $item['url'] : null,
                sortOrder: is_int($item['sortOrder'] ?? null) ? $item['sortOrder'] : (is_int($item['sort_order'] ?? null) ? $item['sort_order'] : null),
            ),
            $this->listOfArrays($items),
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
                summary: is_string($item['summary'] ?? ($item['shortDescription'] ?? ($item['short_description'] ?? null)))
                    ? (string) ($item['summary'] ?? $item['shortDescription'] ?? $item['short_description'])
                    : null,
                imageUrl: is_string($item['imageUrl'] ?? ($item['image_url'] ?? null)) ? (string) ($item['imageUrl'] ?? $item['image_url']) : null,
                url: is_string($item['url'] ?? ($item['ctaUrl'] ?? ($item['cta_url'] ?? null)))
                    ? (string) ($item['url'] ?? $item['ctaUrl'] ?? $item['cta_url'])
                    : null,
                tags: is_array($item['tags'] ?? null)
                    ? array_values(array_filter($item['tags'], static fn (mixed $tag): bool => is_string($tag)))
                    : [],
            ),
            $this->listOfArrays($items),
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
                categoryLabel: is_string($item['categoryLabel'] ?? ($item['category_label'] ?? null)) ? (string) ($item['categoryLabel'] ?? $item['category_label']) : null,
                badgeTag: is_string($item['badgeTag'] ?? ($item['badge_tag'] ?? null)) ? (string) ($item['badgeTag'] ?? $item['badge_tag']) : null,
            ),
            $this->listOfArrays($items),
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
            $this->listOfArrays($items),
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
            $this->listOfArrays($items),
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
                    is_array($item['links'] ?? null) ? $item['links'] : [],
                ))),
            ),
            $this->listOfArrays($items),
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
            $this->listOfArrays($items),
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
                isEnabled: (bool) ($item['is_enabled'] ?? ($item['isEnabled'] ?? true)),
            ),
            $this->listOfArrays($items),
        ));
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
            arabicTranslation: $this->translationFromPayload($this->sectionDataToArray($arabicPayload), 'ar'),
            englishTranslation: $this->translationFromPayload($this->sectionDataToArray($englishPayload), 'en'),
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
                'payload' => $this->sectionDataToArray($section->payload),
                'arabicPayload' => $this->sectionDataToArray($section->arabicPayload ?? $section->payload),
                'englishPayload' => $this->sectionDataToArray($section->englishPayload ?? $section->payload),
                'arabicTranslation' => $this->translationToArray($section->arabicTranslation),
                'englishTranslation' => $this->translationToArray($section->englishTranslation),
            ],
            $sections,
        ));
    }

    /**
     * @return array<string, mixed>
     */
    private function sectionDataToArray(HomepageSectionDataDTO $payload): array
    {
        return array_filter([
            'eyebrow' => $payload->eyebrow,
            'subtitle' => $payload->subtitle,
            'badge' => $payload->badge,
            'title' => $payload->title,
            'summary' => $payload->summary,
            'body' => $payload->body,
            'videoUrl' => $payload->videoUrl,
            'imageUrl' => $payload->imageUrl,
            'backgroundImageUrl' => $payload->backgroundImageUrl,
            'primaryAction' => $this->actionToArray($payload->primaryAction),
            'secondaryAction' => $this->actionToArray($payload->secondaryAction),
            'sectionAction' => $this->actionToArray($payload->sectionAction),
            'stats' => array_values(array_map(
                static fn (HomepageStatItemDTO $item): array => array_filter([
                    'value' => $item->value,
                    'label' => $item->label,
                    'description' => $item->description,
                    'icon' => $item->icon,
                    'prefix' => $item->prefix,
                    'suffix' => $item->suffix,
                    'helperText' => $item->helperText,
                    'url' => $item->url,
                    'sortOrder' => $item->sortOrder,
                ], static fn (mixed $value): bool => $value !== null && $value !== []),
                $payload->stats,
            )),
            'featuredItems' => array_values(array_map(
                static fn (HomepageFeatureItemDTO $item): array => array_filter([
                    'title' => $item->title,
                    'summary' => $item->summary,
                    'imageUrl' => $item->imageUrl,
                    'url' => $item->url,
                    'tags' => $item->tags,
                ], static fn (mixed $value): bool => $value !== null && $value !== []),
                $payload->featuredItems,
            )),
            'articles' => array_values(array_map(
                static fn (ArticleCardDTO $item): array => array_filter([
                    'id' => $item->id,
                    'locale' => $item->locale,
                    'title' => $item->title,
                    'slug' => $item->slug,
                    'excerpt' => $item->excerpt,
                    'imageUrl' => $item->imageUrl,
                    'publishedAt' => $item->publishedAt,
                    'url' => $item->url,
                    'categoryLabel' => $item->categoryLabel,
                    'badgeTag' => $item->badgeTag,
                ], static fn (mixed $value): bool => $value !== null && $value !== []),
                $payload->articles,
            )),
            'researchItems' => array_values(array_map(
                static fn (ResearchCardDTO $item): array => array_filter([
                    'id' => $item->id,
                    'locale' => $item->locale,
                    'title' => $item->title,
                    'slug' => $item->slug,
                    'summary' => $item->summary,
                    'imageUrl' => $item->imageUrl,
                    'publishedAt' => $item->publishedAt,
                    'url' => $item->url,
                    'categoryLabel' => $item->categoryLabel,
                    'authors' => $item->authors,
                ], static fn (mixed $value): bool => $value !== null && $value !== []),
                $payload->researchItems,
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
                    'url' => $item->url,
                    'imageUrl' => $item->imageUrl,
                    'timeLabel' => $item->timeLabel,
                ], static fn (mixed $value): bool => $value !== null && $value !== []),
                $payload->events,
            )),
            'footerColumns' => array_values(array_map(
                fn (FooterColumnDTO $column): array => [
                    'title' => $column->title,
                    'links' => array_values(array_filter(array_map(
                        fn (NavigationActionDTO $action): ?array => $this->actionToArray($action),
                        $column->links,
                    ))),
                ],
                $payload->footerColumns,
            )),
            'contactLinks' => array_values(array_map(
                static fn (ContactLinkDTO $item): array => [
                    'type' => $item->type,
                    'label' => $item->label,
                    'value' => $item->value,
                ],
                $payload->contactLinks,
            )),
            'socialLinks' => array_values(array_map(
                static fn (SocialLinkDTO $item): array => [
                    'platform' => $item->platform,
                    'url' => $item->url,
                    'isEnabled' => $item->isEnabled,
                ],
                $payload->socialLinks,
            )),
            'items' => array_values($payload->items),
            'content' => $payload->content,
        ], static fn (mixed $value): bool => $value !== null && $value !== []);
    }

    /**
     * @return array<string, mixed>|null
     */
    private function actionToArray(?NavigationActionDTO $action): ?array
    {
        if (! $action instanceof NavigationActionDTO) {
            return null;
        }

        return array_filter([
            'label' => $action->label,
            'url' => $action->url,
            'target' => $action->target,
        ], static fn (mixed $value): bool => $value !== null && $value !== '');
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

    private function hasRenderablePayloadForLocale(HomepageSectionDTO $section, string $locale): bool
    {
        $payload = $locale === 'en' ? ($section->englishPayload ?? $section->payload) : ($section->arabicPayload ?? $section->payload);
        $data = $this->sectionDataToArray($payload);

        return $data !== [];
    }

    private function findTranslation(HomepageSection $section, string $locale): ?HomepageSectionTranslation
    {
        return $section->translations->firstWhere('locale', $locale);
    }

    /**
     * @param  array<string, mixed>|null  $payload
     */
    private function translationPayloadArray(?HomepageSectionTranslation $translation, HomepageSectionDataDTO $fallbackPayload): array
    {
        return is_array($translation?->payload_json)
            ? $translation->payload_json
            : $this->sectionDataToArray($fallbackPayload);
    }

    private function emptySection(string $key, int $sortOrder): HomepageSectionDTO
    {
        $payload = new HomepageSectionDataDTO();

        return new HomepageSectionDTO(
            id: 0,
            key: $key,
            sortOrder: $sortOrder,
            isEnabled: true,
            payload: $payload,
            arabicTranslation: new HomepageSectionTranslationDTO(locale: 'ar'),
            englishTranslation: new HomepageSectionTranslationDTO(locale: 'en'),
            arabicPayload: $payload,
            englishPayload: $payload,
        );
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

    private function assertApprovedKey(string $key): void
    {
        if (! in_array($key, self::SECTION_KEYS, true)) {
            throw new \InvalidArgumentException('Unknown homepage section key: '.$key);
        }
    }

    /**
     * @param  array<int, string>  $keys
     */
    private function hasExactApprovedKeySet(array $keys): bool
    {
        sort($keys);
        $approvedKeys = self::SECTION_KEYS;
        sort($approvedKeys);

        return $keys === $approvedKeys;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    private function rulesForSection(string $key): array
    {
        $rules = match ($key) {
            'hero' => [
                'title' => ['required', 'string', 'max:255'],
                'subtitle' => ['required', 'string', 'max:500'],
                'backgroundImageUrl' => ['nullable', 'string', $this->assetPathRule()],
                'videoUrl' => ['nullable', 'string', $this->assetPathRule()],
                'badge' => ['nullable', 'string', 'max:120'],
                'content.images' => ['nullable', 'array', 'min:1'],
                'content.images.*' => ['required', 'string', $this->assetPathRule()],
                'content.overlay' => ['nullable', 'array'],
                'content.alignment' => ['nullable', 'array'],
            ],
            'hero_stats', 'bottom_stats' => [
                'title' => ['required', 'string', 'max:255'],
                'stats' => ['required', 'array', 'min:4'],
                'stats.*.value' => ['required', 'string', 'max:100'],
                'stats.*.label' => ['required', 'string', 'max:255'],
                'stats.*.prefix' => ['nullable', 'string', 'max:50'],
                'stats.*.suffix' => ['nullable', 'string', 'max:50'],
                'stats.*.icon' => ['nullable', 'string', 'max:120'],
                'stats.*.helperText' => ['nullable', 'string', 'max:255'],
                'stats.*.url' => ['nullable', 'string', $this->linkRule()],
                'stats.*.sortOrder' => ['nullable', 'integer', 'min:1'],
            ],
            'academic_faculties' => [
                'title' => ['required', 'string', 'max:255'],
                'subtitle' => ['nullable', 'string', 'max:500'],
                'items' => ['required', 'array', 'min:1'],
                'items.*.title' => ['required', 'string', 'max:255'],
                'items.*.summary' => ['nullable', 'string', 'max:500'],
                'items.*.imageUrl' => ['nullable', 'string', $this->assetPathRule()],
                'items.*.icon' => ['nullable', 'string', 'max:120'],
                'items.*.accent' => ['nullable', 'string', 'max:120'],
                'items.*.metric' => ['nullable', 'string', 'max:120'],
                'items.*.action' => ['required', 'array'],
                'items.*.action.label' => ['required', 'string', 'max:255'],
                'items.*.action.url' => ['required', 'string', $this->linkRule()],
            ],
            'achievements_highlights' => [
                'title' => ['required', 'string', 'max:255'],
                'subtitle' => ['nullable', 'string', 'max:500'],
                'items' => ['required', 'array', 'min:1'],
                'items.*.title' => ['required', 'string', 'max:255'],
                'items.*.summary' => ['required', 'string', 'max:500'],
                'items.*.icon' => ['nullable', 'string', 'max:120'],
                'items.*.metric' => ['nullable', 'string', 'max:120'],
                'items.*.dateLabel' => ['nullable', 'string', 'max:120'],
                'items.*.action' => ['required', 'array'],
                'items.*.action.label' => ['required', 'string', 'max:255'],
                'items.*.action.url' => ['required', 'string', $this->linkRule()],
            ],
            'choose_your_path' => [
                'title' => ['required', 'string', 'max:255'],
                'items' => ['required', 'array', 'min:1'],
                'items.*.title' => ['required', 'string', 'max:255'],
                'items.*.icon' => ['nullable', 'string', $this->assetPathRule()],
                'items.*.links' => ['nullable', 'array'],
                'items.*.action' => ['nullable', 'array'],
                'items.*.action.label' => ['nullable', 'string', 'max:255'],
                'items.*.action.url' => ['nullable', 'string', $this->linkRule()],
            ],
            'university_news' => [
                'title' => ['required', 'string', 'max:255'],
                'articles' => ['required', 'array', 'min:1'],
                'articles.*.imageUrl' => ['required', 'string', $this->assetPathRule()],
                'articles.*.title' => ['required', 'string', 'max:255'],
                'articles.*.excerpt' => ['nullable', 'string', 'max:500'],
                'articles.*.publishedAt' => ['required', 'date'],
                'articles.*.categoryLabel' => ['required', 'string', 'max:120'],
                'articles.*.badgeTag' => ['nullable', 'string', 'max:120'],
                'articles.*.url' => ['required', 'string', $this->linkRule()],
                'content.selectionMode' => ['nullable', Rule::in(['manual', 'fallback'])],
            ],
            'research_studies' => [
                'title' => ['required', 'string', 'max:255'],
                'researchItems' => ['required', 'array', 'min:1'],
                'researchItems.*.imageUrl' => ['nullable', 'string', $this->assetPathRule()],
                'researchItems.*.title' => ['required', 'string', 'max:255'],
                'researchItems.*.summary' => ['nullable', 'string', 'max:500'],
                'researchItems.*.publishedAt' => ['nullable', 'date'],
                'researchItems.*.categoryLabel' => ['required', 'string', 'max:120'],
                'researchItems.*.authors' => ['nullable', 'array'],
                'researchItems.*.authors.*' => ['required', 'string', 'max:120'],
                'researchItems.*.url' => ['required', 'string', $this->linkRule()],
                'content.selectionMode' => ['nullable', Rule::in(['manual', 'fallback'])],
            ],
            'events_activities' => [
                'title' => ['required', 'string', 'max:255'],
                'events' => ['required', 'array', 'min:1'],
                'events.*.imageUrl' => ['nullable', 'string', $this->assetPathRule()],
                'events.*.title' => ['required', 'string', 'max:255'],
                'events.*.startsAt' => ['required', 'date'],
                'events.*.timeLabel' => ['nullable', 'string', 'max:120'],
                'events.*.location' => ['nullable', 'string', 'max:255'],
                'events.*.summary' => ['nullable', 'string', 'max:500'],
                'events.*.url' => ['required', 'string', $this->linkRule()],
                'content.calendarHighlights' => ['nullable', 'array'],
                'content.mobileConfig' => ['nullable', 'array'],
            ],
            'medical_facilities_services' => [
                'title' => ['required', 'string', 'max:255'],
                'items' => ['required', 'array', 'min:1'],
                'items.*.title' => ['required', 'string', 'max:255'],
                'items.*.summary' => ['nullable', 'string', 'max:500'],
                'items.*.imageUrl' => ['required', 'string', $this->assetPathRule()],
                'items.*.typeTag' => ['nullable', 'string', 'max:120'],
                'items.*.action' => ['nullable', 'array'],
                'items.*.action.label' => ['nullable', 'string', 'max:255'],
                'items.*.action.url' => ['nullable', 'string', $this->linkRule()],
            ],
            'footer' => [
                'footerColumns' => ['required', 'array', 'min:1'],
                'footerColumns.*.title' => ['required', 'string', 'max:255'],
                'footerColumns.*.links' => ['required', 'array', 'min:1'],
                'footerColumns.*.links.*.label' => ['required', 'string', 'max:255'],
                'footerColumns.*.links.*.url' => ['required', 'string', $this->linkRule()],
                'contactLinks' => ['required', 'array', 'min:1'],
                'contactLinks.*.label' => ['required', 'string', 'max:255'],
                'contactLinks.*.value' => ['required', 'string', 'max:255'],
                'socialLinks' => ['required', 'array', 'min:1'],
                'socialLinks.*.platform' => ['required', 'string', 'max:120'],
                'socialLinks.*.url' => ['required', 'string', $this->linkRule()],
                'content.brandBlock' => ['required', 'array'],
                'content.brandBlock.title' => ['required', 'string', 'max:255'],
                'content.brandBlock.logoUrl' => ['nullable', 'string', $this->assetPathRule()],
                'content.contactBlock' => ['nullable', 'array'],
                'content.contactBlock.title' => ['nullable', 'string', 'max:255'],
                'content.contactBlock.address' => ['nullable', 'string', 'max:500'],
                'content.contactBlock.phone' => ['nullable', 'string', 'max:120'],
                'content.contactBlock.email' => ['nullable', 'string', 'max:255'],
                'content.mapEmbed' => ['nullable', 'array'],
                'content.legalLinks' => ['required', 'array', 'min:1'],
                'content.legalLinks.*.label' => ['required', 'string', 'max:255'],
                'content.legalLinks.*.url' => ['required', 'string', $this->linkRule()],
                'content.copyrightText' => ['required', 'string', 'max:255'],
                'content.emergencyNotice' => ['nullable', 'array'],
            ],
            default => [],
        };

        $this->addActionRules($rules, 'primaryAction', $key === 'hero');
        $this->addActionRules($rules, 'secondaryAction', $key === 'hero');
        $this->addActionRules($rules, 'sectionAction', in_array($key, ['university_news', 'research_studies'], true));

        return $rules;
    }

    /**
     * @param  array<string, array<int, mixed>>  $rules
     */
    private function addActionRules(array &$rules, string $key, bool $required): void
    {
        $rules[$key] = [$required ? 'required' : 'nullable', 'array'];
        $rules[$key.'.label'] = [$required ? 'required' : 'nullable', 'string', 'max:255'];
        $rules[$key.'.url'] = [$required ? 'required' : 'nullable', 'string', $this->linkRule()];
        $rules[$key.'.target'] = ['nullable', 'string', 'max:32'];
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function applyConditionalRules(\Illuminate\Validation\Validator $validator, string $key, array $payload): void
    {
        $validator->after(function (\Illuminate\Validation\Validator $validator) use ($key, $payload): void {
            if ($key === 'hero') {
                $images = [];
                $contentImages = $payload['content']['images'] ?? [];

                if (is_array($contentImages)) {
                    $images = array_values(array_filter($contentImages, static fn (mixed $image): bool => is_string($image) && $image !== ''));
                }

                if (! is_string($payload['backgroundImageUrl'] ?? null) && $images === []) {
                    $validator->errors()->add('backgroundImageUrl', 'The hero section must include a background image or at least one carousel image.');
                }
            }

            if (in_array($key, ['academic_faculties', 'medical_facilities_services'], true)) {
                foreach ($this->listOfArrays($payload['items'] ?? []) as $index => $item) {
                    if (! is_string($item['imageUrl'] ?? null) && ! is_string($item['icon'] ?? null)) {
                        $validator->errors()->add('items.'.$index, 'Each item must include an imageUrl or icon.');
                    }
                }
            }

            if ($key === 'footer') {
                $contactBlock = is_array($payload['content']['contactBlock'] ?? null) ? $payload['content']['contactBlock'] : [];

                if ($contactBlock !== [] &&
                    ! is_string($contactBlock['address'] ?? null)
                    && ! is_string($contactBlock['phone'] ?? null)
                    && ! is_string($contactBlock['email'] ?? null)
                ) {
                    $validator->errors()->add('content.contactBlock', 'The contact block must include address, phone, or email.');
                }
            }
        });
    }

    private function linkRule(): \Closure
    {
        return static function (string $attribute, mixed $value, \Closure $fail): void {
            if ($value === null || $value === '') {
                return;
            }

            if (! is_string($value) || preg_match('~^(\/|https?://|mailto:|tel:|#)~i', $value) !== 1) {
                $fail('The '.$attribute.' field must be a valid internal or absolute URL.');
            }
        };
    }

    private function assetPathRule(): \Closure
    {
        return static function (string $attribute, mixed $value, \Closure $fail): void {
            if ($value === null || $value === '') {
                return;
            }

            if (! is_string($value) || preg_match('~^(\/|https?://)~i', $value) !== 1) {
                $fail('The '.$attribute.' field must be an internal asset path or absolute URL.');
            }
        };
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function listOfArrays(mixed $items): array
    {
        if (! is_array($items)) {
            return [];
        }

        return array_values(array_filter($items, static fn (mixed $item): bool => is_array($item)));
    }

    /**
     * @param  array<string, mixed>|null  $payload
     */
    private function stringValue(?array $payload, string $key): ?string
    {
        $value = $payload[$key] ?? null;

        return is_string($value) && $value !== '' ? $value : null;
    }

    /**
     * @param  array<string, mixed>|null  $payload
     */
    private function contentString(?array $payload, string $key): ?string
    {
        $content = is_array($payload['content'] ?? null) ? $payload['content'] : [];
        $value = $content[$key] ?? null;

        return is_string($value) && $value !== '' ? $value : null;
    }
}
