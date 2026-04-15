<?php

declare(strict_types=1);

namespace App\Services\Placeholders;

use App\Contracts\SeoMetadataServiceInterface;
use App\DTOs\PageSeoDTO;
use BadMethodCallException;

/**
 * Placeholder implementation for SEO metadata service contract.
 */
final class SeoMetadataServicePlaceholder implements SeoMetadataServiceInterface
{
    public function buildForPage(int $pageId, string $locale): PageSeoDTO
    {
        throw new BadMethodCallException(__METHOD__.' is not implemented.');
    }

    public function buildFallback(string $locale, array $context = []): PageSeoDTO
    {
        throw new BadMethodCallException(__METHOD__.' is not implemented.');
    }

    public function resolveCanonical(string $path, string $locale): string
    {
        throw new BadMethodCallException(__METHOD__.' is not implemented.');
    }

    public function resolveHreflang(array $localePathMap): array
    {
        throw new BadMethodCallException(__METHOD__.' is not implemented.');
    }

    public function toMetaArray(PageSeoDTO $dto): array
    {
        throw new BadMethodCallException(__METHOD__.' is not implemented.');
    }
}
