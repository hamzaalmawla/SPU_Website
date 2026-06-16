<?php

declare(strict_types=1);

namespace App\Contracts\Shared;

/**
 * Defines locale-aware slug generation for CMS pages.
 */
interface SlugServiceInterface
{
    /**
     * Generate a unique slug from source text.
     */
    public function generate(string $source, string $modelClass, string $locale = 'ar', ?int $ignoreId = null): string;
}
