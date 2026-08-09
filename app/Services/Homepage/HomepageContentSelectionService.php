<?php

declare(strict_types=1);

namespace App\Services\Homepage;

use App\Contracts\Homepage\HomepageContentSelectionServiceInterface;
use App\Contracts\News\NewsServiceInterface;
use App\Contracts\Research\ResearchPageServiceInterface;
use App\DTOs\Content\ArticleCardDTO;
use App\DTOs\Content\ResearchCardDTO;
use App\DTOs\Homepage\HomepageSectionDataDTO;
use App\DTOs\Homepage\HomepageSectionDTO;
use App\Support\HomepagePayloadMapper;

final class HomepageContentSelectionService implements HomepageContentSelectionServiceInterface
{
    public function __construct(
        private readonly NewsServiceInterface $newsService,
        private readonly ResearchPageServiceInterface $researchService,
    ) {}

    public function hydrateSection(HomepageSectionDTO $section, string $locale): HomepageSectionDTO
    {
        if (! in_array($section->key, ['university_news', 'research_studies'], true)) {
            return $section;
        }

        $arabicPayload = $section->arabicPayload ?? $section->payload;
        $englishPayload = $section->englishPayload ?? $section->payload;
        $localizedPayload = $locale === 'en' ? $englishPayload : $arabicPayload;
        $localizedPayload = $this->hydratePayload($localizedPayload, $section->key, $locale);

        return new HomepageSectionDTO(
            id: $section->id,
            key: $section->key,
            sortOrder: $section->sortOrder,
            isEnabled: $section->isEnabled,
            payload: $localizedPayload,
            arabicTranslation: $section->arabicTranslation,
            englishTranslation: $section->englishTranslation,
            arabicPayload: $locale === 'ar' ? $localizedPayload : $arabicPayload,
            englishPayload: $locale === 'en' ? $localizedPayload : $englishPayload,
        );
    }

    public function hydratePayload(HomepageSectionDataDTO $payload, string $sectionKey, string $locale): HomepageSectionDataDTO
    {
        if (! in_array($sectionKey, ['university_news', 'research_studies'], true)) {
            return $payload;
        }

        $content = $payload->content;
        $manual = ($content['selectionMode'] ?? $content['selection_mode'] ?? null) === 'manual';
        $data = HomepagePayloadMapper::sectionDataToArray($payload);

        if ($sectionKey === 'university_news') {
            $ids = $manual ? $this->selectedArticleIds($content) : [];
            $cards = $this->newsService->getHomepageArticleCards($locale, $ids, null, $manual ? max(1, count($ids)) : 4);
            $data['articles'] = $cards->map(fn (ArticleCardDTO $card): array => $this->articleToArray($card))->all();
        } else {
            $slugs = $manual ? $this->selectedResearchSlugs($content) : [];
            $cards = $this->researchService->getHomepagePublicationCards($locale, $slugs, null, $manual ? max(1, count($slugs)) : 5);
            $data['researchItems'] = $cards->map(fn (ResearchCardDTO $card): array => $this->researchToArray($card))->all();
        }

        return HomepagePayloadMapper::sectionDataFromArray($data);
    }

    public function hasValidManualSelection(HomepageSectionDataDTO $payload, string $sectionKey, string $locale): bool
    {
        $content = $payload->content;

        if (($content['selectionMode'] ?? $content['selection_mode'] ?? null) !== 'manual') {
            return true;
        }

        if ($sectionKey === 'university_news') {
            $ids = $this->selectedArticleIds($content);

            return $ids !== []
                && $this->newsService->getHomepageArticleCards($locale, $ids, null, count($ids))->count() === count($ids);
        }

        if ($sectionKey === 'research_studies') {
            $slugs = $this->selectedResearchSlugs($content);

            return $slugs !== []
                && $this->researchService->getHomepagePublicationCards($locale, $slugs, null, count($slugs))->count() === count($slugs);
        }

        return true;
    }

    /** @param array<string, mixed> $content @return array<int, int> */
    private function selectedArticleIds(array $content): array
    {
        $values = $content['selectedArticleIds'] ?? $content['selected_article_ids'] ?? [];

        return array_values(array_unique(array_map(
            static fn (mixed $id): int => (int) $id,
            array_filter(is_array($values) ? $values : [], static fn (mixed $id): bool => is_numeric($id) && (int) $id > 0),
        )));
    }

    /** @param array<string, mixed> $content @return array<int, string> */
    private function selectedResearchSlugs(array $content): array
    {
        $values = $content['selectedResearchSlugs'] ?? $content['selected_research_slugs'] ?? [];

        return array_values(array_unique(array_filter(
            array_map(static fn (mixed $slug): string => is_string($slug) ? trim($slug) : '', is_array($values) ? $values : []),
            static fn (string $slug): bool => $slug !== '',
        )));
    }

    /** @return array<string, mixed> */
    private function articleToArray(ArticleCardDTO $card): array
    {
        return [
            'id' => $card->id,
            'locale' => $card->locale,
            'title' => $card->title,
            'slug' => $card->slug,
            'excerpt' => $card->excerpt,
            'imageUrl' => $card->imageUrl,
            'publishedAt' => $card->publishedAt,
            'url' => $card->url,
            'categoryLabel' => $card->categoryLabel,
            'badgeTag' => $card->badgeTag,
        ];
    }

    /** @return array<string, mixed> */
    private function researchToArray(ResearchCardDTO $card): array
    {
        return [
            'id' => $card->id,
            'locale' => $card->locale,
            'title' => $card->title,
            'slug' => $card->slug,
            'summary' => $card->summary,
            'imageUrl' => $card->imageUrl,
            'publishedAt' => $card->publishedAt,
            'url' => $card->url,
            'categoryLabel' => $card->categoryLabel,
            'authors' => $card->authors,
        ];
    }
}
