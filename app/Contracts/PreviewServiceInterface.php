<?php

declare(strict_types=1);

namespace App\Contracts;

use App\DTOs\PreviewDTO;

/**
 * Defines content preview generation and validation operations.
 */
interface PreviewServiceInterface
{
    /**
     * Build preview data for an entity.
     */
    public function generate(string $entityType, int|string $entityId, string $locale): PreviewDTO;

    /**
     * Validate a preview token.
     */
    public function validateToken(string $token): bool;
}
