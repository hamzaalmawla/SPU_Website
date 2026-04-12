<?php

declare(strict_types=1);

namespace App\Services\Placeholders;

use App\Contracts\SeoMetadataServiceInterface;
use App\DTOs\SeoMetadataDTO;
use BadMethodCallException;

/**
 * Placeholder implementation for SEO metadata service contract.
 */
final class SeoMetadataServicePlaceholder implements SeoMetadataServiceInterface
{
    public function getFor(string $entityType, int|string $entityId, string $locale): ?SeoMetadataDTO
    {
        throw new BadMethodCallException(__METHOD__.' is not implemented.');
    }

    public function upsertFor(string $entityType, int|string $entityId, string $locale, array $metadata): bool
    {
        throw new BadMethodCallException(__METHOD__.' is not implemented.');
    }

    public function generate(string $contentType, array $data, string $locale): array
    {
        throw new BadMethodCallException(__METHOD__.' is not implemented.');
    }
}
