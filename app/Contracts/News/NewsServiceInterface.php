<?php

declare(strict_types=1);

namespace App\Contracts\News;

use App\DTOs\Content\ArticleCardDTO;
use App\DTOs\News\NewsArticleDTO;
use App\DTOs\News\NewsCategoryDTO;
use App\DTOs\Shared\PaginatedResultDTO;
use Illuminate\Support\Collection;

interface NewsServiceInterface
{
    /** @return array<string, mixed> */
    public function getIndexPageContent(string $locale): array;

    /** @param array<string, mixed> $content @return array<string, mixed> */
    public function buildPreviewIndexPage(string $locale, array $content): array;

    /** @return array{translations: array{ar: array<string, mixed>, en: array<string, mixed>}} */
    public function getEditablePayload(string $targetKey): array;

    /**
     * @param  array{category?: string, search?: string}  $filters
     */
    public function listPublicArticles(string $locale, array $filters = [], int $page = 1, int $perPage = 12): PaginatedResultDTO;

    public function getPublicArticle(string $slug, string $locale): ?NewsArticleDTO;

    /** @return Collection<int, ArticleCardDTO> */
    public function getFeaturedArticles(string $locale, int $limit = 3): Collection;

    /** @return Collection<int, ArticleCardDTO> */
    public function getLatestArticleCards(string $locale, int $limit = 5, ?string $categoryType = null): Collection;

    /** @return Collection<int, ArticleCardDTO> */
    public function getRelatedArticleCards(string $slug, string $locale, int $limit = 3): Collection;

    /** @return array{previous: ArticleCardDTO|null, next: ArticleCardDTO|null} */
    public function getAdjacentArticleCards(string $slug, string $locale): array;

    /** @return Collection<int, NewsCategoryDTO> */
    public function getPublicCategories(string $locale): Collection;
}
