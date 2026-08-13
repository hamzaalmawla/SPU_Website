<?php

declare(strict_types=1);

namespace App\Contracts\Media;

use App\DTOs\Media\MediaUploadResultDTO;
use App\DTOs\Media\PublicMediaAssetDTO;
use App\DTOs\Shared\PaginatedResultDTO;
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

    public function find(int|string $mediaId, int $userId): ?MediaUploadResultDTO;

    public function importPublicAsset(string $publicRelativePath, ?int $userId = null): ?MediaUploadResultDTO;

    /**
     * Promote a legacy archive asset into the main media library without moving or deleting the original.
     *
     * @param  array<string, mixed>  $metadata
     */
    public function promoteLegacyAsset(int|string $mediaId, array $metadata, int $userId): MediaUploadResultDTO;

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

    /**
     * @param  array<int, int>  $mediaIds
     * @return Collection<int, PublicMediaAssetDTO>
     */
    public function resolvePublicImages(array $mediaIds, string $locale): Collection;

    /** @param array<int, int> $mediaIds */
    public function publicImagesArePublishable(array $mediaIds): bool;

    /** @param array<int, int> $mediaIds */
    public function publicDocumentsArePublishable(array $mediaIds): bool;

    public function convertImages(int $userId, ?int $limit = null): int;
}
