<?php

declare(strict_types=1);

namespace App\Contracts;

/**
 * Defines slug generation and uniqueness checks.
 */
interface SlugServiceInterface
{
    /**
     * Generate a unique slug from source text.
     */
    public function generate(string $source, string $table, int|string|null $ignoreId = null): string;

    /**
     * Check slug uniqueness in a storage table.
     */
    public function isUnique(string $slug, string $table, int|string|null $ignoreId = null): bool;
}
