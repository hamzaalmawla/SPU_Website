<?php

declare(strict_types=1);

namespace App\Services;

use App\Contracts\AuditServiceInterface;
use App\Contracts\CacheServiceInterface;
use App\Contracts\HomepagePublishingServiceInterface;
use App\Contracts\HomepageSectionServiceInterface;
use App\DTOs\ArticleCardDTO;
use App\DTOs\ContactLinkDTO;
use App\DTOs\DraftPayloadDTO;
use App\DTOs\EventCardDTO;
use App\DTOs\HomepageDraftDataDTO;
use App\DTOs\HomepageDraftDTO;
use App\DTOs\HomepageFeatureItemDTO;
use App\DTOs\HomepageSectionDataDTO;
use App\DTOs\HomepageSectionDTO;
use App\DTOs\HomepageSectionTranslationDTO;
use App\DTOs\HomepageStatItemDTO;
use App\DTOs\NavigationActionDTO;
use App\DTOs\ResearchCardDTO;
use App\DTOs\SocialLinkDTO;
use App\Models\HomepageDraft;
use App\Models\HomepageSection;
use App\Models\HomepageSectionTranslation;
use DateTimeInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

final class HomepagePublishingService implements HomepagePublishingServiceInterface
{
    public function __construct(
        private readonly HomepageSectionServiceInterface $homepageSectionService,
        private readonly CacheServiceInterface $cacheService,
        private readonly AuditServiceInterface $auditService,
    ) {}

    public function saveDraft(HomepageDraftDataDTO $payload, int $userId): HomepageDraftDTO
    {
        $sections = $this->normalizeSections($payload->sections);
        $draft = HomepageDraft::query()->create([
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
        ]);

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
                        'is_enabled' => $section->isEnabled,
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
                        'payload_json' => $this->sectionPayloadToArray($section->arabicPayload ?? $section->payload),
                    ],
                );

                HomepageSectionTranslation::query()->updateOrCreate(
                    [
                        'section_id' => (int) $sectionModel->getKey(),
                        'locale' => 'en',
                    ],
                    [
                        'payload_json' => $this->sectionPayloadToArray($section->englishPayload ?? $section->payload),
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
        });

        $this->invalidateHomepageCache();

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
        if ($targetType !== 'homepage' || $targetId !== null) {
            return false;
        }

        HomepageSection::query()
            ->whereIn('key', HomepageSectionServiceInterface::SECTION_KEYS)
            ->update(['is_enabled' => false]);

        $this->invalidateHomepageCache();

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
        );
    }

    /**
     * @param  array<int, HomepageSectionDTO>  $providedSections
     * @return array<int, HomepageSectionDTO>
     */
    private function normalizeSections(array $providedSections): array
    {
        $currentSections = $this->homepageSectionService->getSections()->keyBy('key');
        $providedByKey = collect($providedSections)->keyBy('key');
        $normalized = [];

        foreach (HomepageSectionServiceInterface::SECTION_KEYS as $index => $key) {
            $current = $currentSections->get($key);

            if (! $current instanceof HomepageSectionDTO) {
                continue;
            }

            $provided = $providedByKey->get($key);

            $normalized[] = $provided instanceof HomepageSectionDTO
                ? $this->mergeSection($provided, $current, $index + 1)
                : $current;
        }

        return array_values($normalized);
    }

    /**
     * @return array<int, HomepageSectionDTO>
     */
    private function sectionsFromDraft(HomepageDraft $draft): array
    {
        $draftHomepage = is_array($draft->payload_json['homepage'] ?? null)
            ? $draft->payload_json['homepage']
            : $draft->payload_json;
        $sections = is_array($draftHomepage['sections'] ?? null) ? $draftHomepage['sections'] : [];
        $currentSections = $this->homepageSectionService->getSections()->keyBy('key');
        $normalized = [];

        foreach (HomepageSectionServiceInterface::SECTION_KEYS as $index => $key) {
            $current = $currentSections->get($key);

            if (! $current instanceof HomepageSectionDTO) {
                continue;
            }

            $sectionPayload = collect($sections)->first(
                static fn (mixed $section): bool => is_array($section) && ($section['key'] ?? null) === $key,
            );

            $normalized[] = is_array($sectionPayload)
                ? $this->sectionFromArray($sectionPayload, $current, $index + 1)
                : $current;
        }

        return array_values($normalized);
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

    private function mergeSection(HomepageSectionDTO $provided, HomepageSectionDTO $fallback, int $defaultSortOrder): HomepageSectionDTO
    {
        return new HomepageSectionDTO(
            id: $provided->id > 0 ? $provided->id : $fallback->id,
            key: $fallback->key,
            sortOrder: $provided->sortOrder > 0 ? $provided->sortOrder : ($fallback->sortOrder > 0 ? $fallback->sortOrder : $defaultSortOrder),
            isEnabled: $provided->isEnabled,
            payload: $provided->payload,
            arabicTranslation: $provided->arabicTranslation,
            englishTranslation: $provided->englishTranslation,
            arabicPayload: $provided->arabicPayload ?? $fallback->arabicPayload ?? $fallback->payload,
            englishPayload: $provided->englishPayload ?? $fallback->englishPayload ?? $fallback->payload,
        );
    }

    private function sectionFromArray(array $payload, HomepageSectionDTO $fallback, int $defaultSortOrder): HomepageSectionDTO
    {
        $arabicPayload = is_array($payload['arabicPayload'] ?? null)
            ? $this->sectionPayloadFromArray($payload['arabicPayload'])
            : ($fallback->arabicPayload ?? $fallback->payload);
        $englishPayload = is_array($payload['englishPayload'] ?? null)
            ? $this->sectionPayloadFromArray($payload['englishPayload'])
            : ($fallback->englishPayload ?? $fallback->payload);

        return new HomepageSectionDTO(
            id: is_int($payload['id'] ?? null) ? $payload['id'] : $fallback->id,
            key: is_string($payload['key'] ?? null) ? $payload['key'] : $fallback->key,
            sortOrder: is_int($payload['sortOrder'] ?? null) ? $payload['sortOrder'] : $defaultSortOrder,
            isEnabled: is_bool($payload['isEnabled'] ?? null) ? $payload['isEnabled'] : $fallback->isEnabled,
            payload: $arabicPayload,
            arabicTranslation: $this->translationFromArray(is_array($payload['arabicTranslation'] ?? null) ? $payload['arabicTranslation'] : [], 'ar', $arabicPayload),
            englishTranslation: $this->translationFromArray(is_array($payload['englishTranslation'] ?? null) ? $payload['englishTranslation'] : [], 'en', $englishPayload),
            arabicPayload: $arabicPayload,
            englishPayload: $englishPayload,
        );
    }

    private function sectionPayloadFromArray(array $payload): HomepageSectionDataDTO
    {
        return new HomepageSectionDataDTO(
            eyebrow: $this->stringValue($payload, 'eyebrow'),
            subtitle: $this->stringValue($payload, 'subtitle') ?? $this->stringValue($payload, 'subheadline'),
            badge: $this->stringValue($payload, 'badge') ?? $this->stringValue($payload, 'kicker'),
            title: $this->stringValue($payload, 'title') ?? $this->stringValue($payload, 'headline'),
            summary: $this->stringValue($payload, 'summary'),
            body: $this->stringValue($payload, 'body'),
            videoUrl: $this->stringValue($payload, 'videoUrl') ?? $this->stringValue($payload, 'video_url'),
            imageUrl: $this->stringValue($payload, 'imageUrl') ?? $this->stringValue($payload, 'image_url'),
            backgroundImageUrl: $this->stringValue($payload, 'backgroundImageUrl') ?? $this->stringValue($payload, 'background_image_url'),
            primaryAction: $this->actionFromArray(is_array($payload['primaryAction'] ?? null) ? $payload['primaryAction'] : (is_array($payload['primary_action'] ?? null) ? $payload['primary_action'] : null)),
            secondaryAction: $this->actionFromArray(is_array($payload['secondaryAction'] ?? null) ? $payload['secondaryAction'] : (is_array($payload['secondary_action'] ?? null) ? $payload['secondary_action'] : null)),
            sectionAction: $this->actionFromArray(is_array($payload['sectionAction'] ?? null) ? $payload['sectionAction'] : (is_array($payload['section_action'] ?? null) ? $payload['section_action'] : null)),
            stats: $this->statsFromArray($payload['stats'] ?? []),
            featuredItems: $this->featuredItemsFromArray($payload['featuredItems'] ?? $payload['featured_items'] ?? []),
            articles: $this->articlesFromArray($payload['articles'] ?? []),
            researchItems: $this->researchItemsFromArray($payload['researchItems'] ?? $payload['research_items'] ?? []),
            events: $this->eventsFromArray($payload['events'] ?? []),
            footerColumns: $this->footerColumnsFromArray($payload['footerColumns'] ?? $payload['footer_columns'] ?? []),
            contactLinks: $this->contactLinksFromArray($payload['contactLinks'] ?? $payload['contact_links'] ?? []),
            socialLinks: $this->socialLinksFromArray($payload['socialLinks'] ?? $payload['social_links'] ?? []),
            items: is_array($payload['items'] ?? null)
                ? array_values(array_filter($payload['items'], static fn (mixed $item): bool => is_array($item)))
                : [],
            content: is_array($payload['content'] ?? null) ? $payload['content'] : [],
        );
    }

    private function translationFromArray(array $payload, string $locale, HomepageSectionDataDTO $fallback): HomepageSectionTranslationDTO
    {
        return new HomepageSectionTranslationDTO(
            locale: $locale,
            headline: $this->stringValue($payload, 'headline') ?? $fallback->title,
            body: $this->stringValue($payload, 'body') ?? $fallback->summary ?? $fallback->body,
            ctaLabel: $this->stringValue($payload, 'ctaLabel') ?? $fallback->primaryAction?->label ?? $fallback->sectionAction?->label,
            imageAlt: $this->stringValue($payload, 'imageAlt') ?? $this->stringValue($payload, 'image_alt'),
        );
    }

    /**
     * @param  array<int, HomepageSectionDTO>  $sections
     * @return array<int, array<string, mixed>>
     */
    private function serializeSections(array $sections): array
    {
        return array_values(array_map(fn (HomepageSectionDTO $section): array => [
            'id' => $section->id,
            'key' => $section->key,
            'sortOrder' => $section->sortOrder,
            'isEnabled' => $section->isEnabled,
            'payload' => $this->sectionPayloadToArray($section->payload),
            'arabicPayload' => $this->sectionPayloadToArray($section->arabicPayload ?? $section->payload),
            'englishPayload' => $this->sectionPayloadToArray($section->englishPayload ?? $section->payload),
            'arabicTranslation' => $this->translationToArray($section->arabicTranslation),
            'englishTranslation' => $this->translationToArray($section->englishTranslation),
        ], $sections));
    }

    /**
     * @return array<string, mixed>
     */
    private function sectionPayloadToArray(HomepageSectionDataDTO $payload): array
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
                fn (\App\DTOs\FooterColumnDTO $column): array => [
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
            'items' => $payload->items,
            'content' => $payload->content,
        ], static fn (mixed $value): bool => $value !== null && $value !== []);
    }

    /**
     * @return array<string, mixed>|null
     */
    private function actionToArray(?\App\DTOs\NavigationActionDTO $action): ?array
    {
        if (! $action instanceof \App\DTOs\NavigationActionDTO) {
            return null;
        }

        return array_filter([
            'label' => $action->label,
            'url' => $action->url,
            'target' => $action->target,
        ], static fn (mixed $value): bool => $value !== null && $value !== '');
    }

    private function actionFromArray(?array $payload): ?\App\DTOs\NavigationActionDTO
    {
        if (! is_array($payload)) {
            return null;
        }

        $label = $this->stringValue($payload, 'label');
        $url = $this->stringValue($payload, 'url');

        if ($label === null || $url === null) {
            return null;
        }

        return new \App\DTOs\NavigationActionDTO(
            label: $label,
            url: $url,
            target: $this->stringValue($payload, 'target'),
        );
    }

    private function statsFromArray(mixed $items): array
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
            array_filter($items, static fn (mixed $item): bool => is_array($item)),
        ));
    }

    private function featuredItemsFromArray(mixed $items): array
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
            array_filter($items, static fn (mixed $item): bool => is_array($item)),
        ));
    }

    private function articlesFromArray(mixed $items): array
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

    private function researchItemsFromArray(mixed $items): array
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

    private function eventsFromArray(mixed $items): array
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

    private function footerColumnsFromArray(mixed $items): array
    {
        if (! is_array($items)) {
            return [];
        }

        return array_values(array_map(
            fn (array $item): \App\DTOs\FooterColumnDTO => new \App\DTOs\FooterColumnDTO(
                title: (string) ($item['title'] ?? ''),
                links: array_values(array_filter(array_map(
                    fn (mixed $link): ?NavigationActionDTO => is_array($link) ? $this->actionFromArray($link) : null,
                    is_array($item['links'] ?? null) ? $item['links'] : [],
                ))),
            ),
            array_filter($items, static fn (mixed $item): bool => is_array($item)),
        ));
    }

    private function contactLinksFromArray(mixed $items): array
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

    private function socialLinksFromArray(mixed $items): array
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

    private function sectionType(string $key): string
    {
        return match ($key) {
            'hero' => 'hero',
            'hero_stats', 'bottom_stats' => 'stats',
            'footer' => 'footer',
            default => 'listing',
        };
    }

    private function invalidateHomepageCache(): void
    {
        foreach (['ar', 'en'] as $locale) {
            $this->cacheService->forget('public_pages:'.sha1($locale.'|'.$locale.'|'));
            $this->cacheService->flushTags(['public-pages', 'public-shell', 'public-shell:'.$locale]);
        }
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
    public function discardEditableDraft(): int
    {
        return HomepageDraft::query()
            ->where('target_type', 'homepage')
            ->whereIn('status', ['draft', 'scheduled'])
            ->delete();
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
}
