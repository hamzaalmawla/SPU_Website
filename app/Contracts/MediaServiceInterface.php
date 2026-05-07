<?php

declare(strict_types=1);

namespace App\Contracts;

use App\DTOs\MediaUploadResultDTO;
use App\DTOs\PaginatedResultDTO;
use Illuminate\Support\Collection;

/**
 * Defines media library operations for CMS-managed assets.
 */
interface MediaServiceInterface
{
    /**
     * @param  array<string, mixed>  $payload
     */
    public function upload(array $payload): MediaUploadResultDTO;

    public function delete(int|string $mediaId, int $userId): bool;

    /**
     * Update stored media metadata.
     *
     * @param  array<string, mixed>  $metadata
     */
    public function updateMetadata(int|string $mediaId, array $metadata, int $userId): bool;

    /**
     * @param  array<string, mixed>  $filters
     * @return Collection<int, MediaUploadResultDTO>
     */
    public function list(int $userId, array $filters = []): Collection;

    /**
     * Paginated media listing for non-Filament consumers.
     *
     * @param  array<string, mixed>  $filters
     */
    public function listPaginated(int $userId, array $filters = [], int $page = 1, int $perPage = 20): PaginatedResultDTO;
}
