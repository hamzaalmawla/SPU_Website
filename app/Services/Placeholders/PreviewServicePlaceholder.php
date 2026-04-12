<?php

declare(strict_types=1);

namespace App\Services\Placeholders;

use App\Contracts\PreviewServiceInterface;
use App\DTOs\PreviewDTO;
use BadMethodCallException;

/**
 * Placeholder implementation for preview service contract.
 */
final class PreviewServicePlaceholder implements PreviewServiceInterface
{
    public function generate(string $entityType, int|string $entityId, string $locale): PreviewDTO
    {
        throw new BadMethodCallException(__METHOD__.' is not implemented.');
    }

    public function validateToken(string $token): bool
    {
        throw new BadMethodCallException(__METHOD__.' is not implemented.');
    }
}
