<?php

declare(strict_types=1);

namespace App\Services\Placeholders;

use App\Contracts\MediaServiceInterface;
use App\DTOs\MediaUploadResultDTO;
use BadMethodCallException;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

/**
 * Placeholder implementation for media service contract.
 */
final class MediaServicePlaceholder implements MediaServiceInterface
{
    public function upload(array $payload): MediaUploadResultDTO
    {
        throw new BadMethodCallException(__METHOD__.' is not implemented.');
    }

    public function delete(int|string $mediaId): bool
    {
        throw new BadMethodCallException(__METHOD__.' is not implemented.');
    }

    public function paginate(array $filters = []): LengthAwarePaginator
    {
        throw new BadMethodCallException(__METHOD__.' is not implemented.');
    }
}
