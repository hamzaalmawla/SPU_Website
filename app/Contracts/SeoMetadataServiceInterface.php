<?php

declare(strict_types=1);

namespace App\Contracts;

use App\DTOs\SeoMetadataDTO;

/**
 * Defines SEO metadata retrieval, generation, and persistence operations.
 */
interface SeoMetadataServiceInterface
{
    /**
     * Retrieve SEO metadata for an entity and locale.
     */
    public function getFor(string $entityType, int|string $entityId, string $locale): ?SeoMetadataDTO;

    /**
     * Create or update SEO metadata for an entity and locale.
     *
     * @param  array<string, mixed>  $metadata
     */
    public function upsertFor(string $entityType, int|string $entityId, string $locale, array $metadata): bool;

    /**
     * Generate metadata payload from content data.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function generate(string $contentType, array $data, string $locale): array;
}
