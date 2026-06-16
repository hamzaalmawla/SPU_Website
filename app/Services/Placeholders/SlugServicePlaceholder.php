<?php

declare(strict_types=1);

namespace App\Services\Placeholders;

use App\Contracts\Shared\SlugServiceInterface;
use BadMethodCallException;

/**
 * Placeholder implementation for slug service contract.
 */
final class SlugServicePlaceholder implements SlugServiceInterface
{
    public function generate(string $source, string $modelClass, string $locale = 'ar', ?int $ignoreId = null): string
    {
        throw new BadMethodCallException(__METHOD__.' is not implemented.');
    }
}
