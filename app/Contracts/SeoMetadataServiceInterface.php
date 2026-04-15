<?php

declare(strict_types=1);

namespace App\Contracts;

use App\DTOs\PageSeoDTO;

/**
 * Defines SEO metadata resolution for public pages and shared fallbacks.
 */
interface SeoMetadataServiceInterface
{
    /**
     * Build page-level SEO metadata.
     */
    public function buildForPage(int $pageId, string $locale): PageSeoDTO;

    /**
     * Build fallback SEO metadata from global settings and request context.
     *
     * @param  array<string, mixed>  $context
     */
    public function buildFallback(string $locale, array $context = []): PageSeoDTO;

    /**
     * Resolve the canonical URL for a localized path.
     */
    public function resolveCanonical(string $path, string $locale): string;

    /**
     * Resolve hreflang metadata from locale-path pairs.
     *
     * @param  array<string, string>  $localePathMap
     * @return array<int, array<string, string>>
     */
    public function resolveHreflang(array $localePathMap): array;

    /**
     * Convert a SEO DTO to a meta tag payload.
     *
     * @return array<string, mixed>
     */
    public function toMetaArray(PageSeoDTO $dto): array;
}
