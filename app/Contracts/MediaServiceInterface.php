<?php

declare(strict_types=1);

namespace App\Contracts;

use App\DTOs\MediaUploadResultDTO;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

/**
 * Defines media upload and retrieval operations.
 */
interface MediaServiceInterface
{
    /**
     * @param  array<string, mixed>  $payload
     */
    public function upload(array $payload): MediaUploadResultDTO;

    public function delete(int|string $mediaId): bool;

    /**
     * @param  array<string, mixed>  $filters
     */
    public function paginate(array $filters = []): LengthAwarePaginator;
}
