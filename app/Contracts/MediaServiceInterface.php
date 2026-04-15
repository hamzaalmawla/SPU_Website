<?php

declare(strict_types=1);

namespace App\Contracts;

use App\DTOs\MediaUploadResultDTO;
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

    public function delete(int|string $mediaId): bool;

    /**
     * Update stored media metadata.
     *
     * @param  array<string, mixed>  $metadata
     */
    public function updateMetadata(int|string $mediaId, array $metadata): bool;

    /**
     * @param  array<string, mixed>  $filters
     * @return Collection<int, MediaUploadResultDTO>
     */
    public function list(array $filters = []): Collection;
}
