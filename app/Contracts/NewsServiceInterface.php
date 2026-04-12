<?php

declare(strict_types=1);

namespace App\Contracts;

use App\DTOs\ArticleDTO;
use App\DTOs\ArticleWriteDTO;
use Carbon\Carbon;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

/**
 * Defines news lifecycle operations including creation and publishing.
 */
interface NewsServiceInterface
{
    /**
     * Create a news article.
     */
    public function create(ArticleWriteDTO $data): ArticleDTO;

    /**
     * Update a news article.
     */
    public function update(int|string $newsId, ArticleWriteDTO $data): bool;

    /**
     * Publish a news article.
     */
    public function publish(int|string $newsId): bool;

    /**
     * Unpublish a news article.
     */
    public function unpublish(int|string $newsId): bool;

    /**
     * Schedule publication of a news article.
     */
    public function schedule(int|string $newsId, Carbon $publishAt): bool;

    /**
     * Paginate news articles using filter criteria.
     *
     * @param  array<string, mixed>  $filters
     */
    public function paginate(array $filters = []): LengthAwarePaginator;
}
