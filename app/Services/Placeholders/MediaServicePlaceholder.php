<?php

declare(strict_types=1);

namespace App\Services\Placeholders;

use App\Contracts\MediaServiceInterface;
use App\DTOs\MediaUploadResultDTO;
use App\DTOs\PaginatedResultDTO;
use BadMethodCallException;
use Illuminate\Support\Collection;

/**
 * Placeholder implementation for media service contract.
 */
final class MediaServicePlaceholder implements MediaServiceInterface
{
    public function upload(array $payload): MediaUploadResultDTO
    {
        throw new BadMethodCallException(__METHOD__.' is not implemented.');
    }

    public function delete(int|string $mediaId, ?int $userId = null): bool
    {
        throw new BadMethodCallException(__METHOD__.' is not implemented.');
    }

    public function updateMetadata(int|string $mediaId, array $metadata, ?int $userId = null): bool
    {
        throw new BadMethodCallException(__METHOD__.' is not implemented.');
    }

    /**
     * @return Collection<int, MediaUploadResultDTO>
     */
    public function list(array $filters = []): Collection
    {
        throw new BadMethodCallException(__METHOD__.' is not implemented.');
    }

    public function listPaginated(array $filters = [], int $page = 1, int $perPage = 20): PaginatedResultDTO
    {
        throw new BadMethodCallException(__METHOD__.' is not implemented.');
    }
}
