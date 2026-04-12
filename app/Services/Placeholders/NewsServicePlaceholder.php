<?php

declare(strict_types=1);

namespace App\Services\Placeholders;

use App\Contracts\NewsServiceInterface;
use App\DTOs\ArticleDTO;
use App\DTOs\ArticleWriteDTO;
use BadMethodCallException;
use Carbon\Carbon;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

/**
 * Placeholder implementation for news service contract.
 */
final class NewsServicePlaceholder implements NewsServiceInterface
{
    public function create(ArticleWriteDTO $data): ArticleDTO
    {
        throw new BadMethodCallException(__METHOD__.' is not implemented.');
    }

    public function update(int|string $newsId, ArticleWriteDTO $data): bool
    {
        throw new BadMethodCallException(__METHOD__.' is not implemented.');
    }

    public function publish(int|string $newsId): bool
    {
        throw new BadMethodCallException(__METHOD__.' is not implemented.');
    }

    public function unpublish(int|string $newsId): bool
    {
        throw new BadMethodCallException(__METHOD__.' is not implemented.');
    }

    public function schedule(int|string $newsId, Carbon $publishAt): bool
    {
        throw new BadMethodCallException(__METHOD__.' is not implemented.');
    }

    public function paginate(array $filters = []): LengthAwarePaginator
    {
        throw new BadMethodCallException(__METHOD__.' is not implemented.');
    }
}
