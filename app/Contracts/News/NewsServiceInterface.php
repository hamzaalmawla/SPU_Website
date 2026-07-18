<?php

declare(strict_types=1);

namespace App\Contracts\News;

use App\DTOs\Content\ArticleCardDTO;
use App\DTOs\News\NewsArticleDTO;
use App\DTOs\News\NewsCategoryDTO;
use App\DTOs\News\NewsEventDTO;
use App\DTOs\News\NewsGalleryItemDTO;
use App\DTOs\Shared\PaginatedResultDTO;
use Illuminate\Support\Collection;

interface NewsServiceInterface
{
    /** @return array<string, mixed> */
    public function getIndexPageContent(string $locale): array;

    /** @param array<string, mixed> $content @return array<string, mixed> */
    public function buildPreviewIndexPage(string $locale, array $content): array;

    /** @return array<string, mixed> */
    public function getAnnouncementsPageContent(string $locale): array;

    /** @param array<string, mixed> $content @return array<string, mixed> */
    public function buildPreviewAnnouncementsPage(string $locale, array $content): array;

    /** @return array<string, mixed> */
    public function getEventsPageContent(string $locale): array;

    /** @param array<string, mixed> $content @return array<string, mixed> */
    public function buildPreviewEventsPage(string $locale, array $content): array;

    /** @return Collection<int, NewsEventDTO> */
    public function listNewsEvents(string $locale, bool $past = false, ?string $category = null): Collection;

    /** @param array<string, mixed> $content @return Collection<int, NewsEventDTO> */
    public function listPreviewNewsEvents(string $locale, array $content, bool $past = false, ?string $category = null): Collection;

    public function findNewsEvent(string $eventId, string $locale, ?bool $past = null): ?NewsEventDTO;

    /** @return array{month: string, monthLabel: string, previousMonth: string, nextMonth: string, events: Collection<int, NewsEventDTO>, days: array<int, array{date: string, day: int, inMonth: bool, events: Collection<int, NewsEventDTO>}>} */
    public function getNewsEventCalendar(string $locale, ?string $month = null): array;

    /** @return array<string, mixed> */
    public function getGalleryPageContent(string $locale): array;

    /** @param array<string, mixed> $content @return array<string, mixed> */
    public function buildPreviewGalleryPage(string $locale, array $content): array;

    /** @return array{page: array<string, mixed>, featured: NewsGalleryItemDTO|null, items: PaginatedResultDTO} */
    public function getGalleryListing(string $locale, ?string $category = null, int $page = 1, int $perPage = 8): array;

    /** @param array<string, mixed> $content @return array{page: array<string, mixed>, featured: NewsGalleryItemDTO|null, items: PaginatedResultDTO} */
    public function buildPreviewGalleryListing(string $locale, array $content, ?string $category = null, int $page = 1, int $perPage = 8): array;

    /** @return array{translations: array{ar: array<string, mixed>, en: array<string, mixed>}} */
    public function getEditablePayload(string $targetKey): array;

    /**
     * @param  array{category?: string, search?: string, categoryType?: string, excludeId?: int}  $filters
     */
    public function listPublicArticles(string $locale, array $filters = [], int $page = 1, int $perPage = 12): PaginatedResultDTO;

    public function getPublicArticle(string $slug, string $locale): ?NewsArticleDTO;

    /** @return Collection<int, ArticleCardDTO> */
    public function getFeaturedArticles(string $locale, int $limit = 3, ?string $categoryType = null): Collection;

    /** @return Collection<int, ArticleCardDTO> */
    public function getLatestArticleCards(string $locale, int $limit = 5, ?string $categoryType = null): Collection;

    /** @return Collection<int, ArticleCardDTO> */
    public function getRelatedArticleCards(string $slug, string $locale, int $limit = 3): Collection;

    /** @return array{previous: ArticleCardDTO|null, next: ArticleCardDTO|null} */
    public function getAdjacentArticleCards(string $slug, string $locale): array;

    /** @return Collection<int, NewsCategoryDTO> */
    public function getPublicCategories(string $locale, ?string $type = null): Collection;
}
