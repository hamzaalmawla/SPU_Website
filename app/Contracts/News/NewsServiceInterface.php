<?php

declare(strict_types=1);

namespace App\Contracts\News;

use App\DTOs\News\NewsArticleDTO;
use App\DTOs\Shared\PaginatedResultDTO;
use Illuminate\Support\Collection;

interface NewsServiceInterface
{
    /**
     * @param  array{category?: string, search?: string}  $filters
     */
    public function listPublicArticles(string $locale, array $filters = [], int $page = 1, int $perPage = 12): PaginatedResultDTO;

    public function getPublicArticle(string $slug, string $locale): ?NewsArticleDTO;

    /** @return Collection<int, \App\DTOs\Content\ArticleCardDTO> */
    public function getFeaturedArticles(string $locale, int $limit = 3): Collection;

    /** @return Collection<int, \App\DTOs\Content\ArticleCardDTO> */
    public function getLatestArticleCards(string $locale, int $limit = 5, ?string $categoryType = null): Collection;

    /** @return Collection<int, \App\DTOs\Content\ArticleCardDTO> */
    public function getRelatedArticleCards(string $slug, string $locale, int $limit = 3): Collection;

    /** @return array{previous: \App\DTOs\Content\ArticleCardDTO|null, next: \App\DTOs\Content\ArticleCardDTO|null} */
    public function getAdjacentArticleCards(string $slug, string $locale): array;

    /** @return Collection<int, \App\DTOs\News\NewsCategoryDTO> */
    public function getPublicCategories(string $locale): Collection;
}
