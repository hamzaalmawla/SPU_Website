<?php

declare(strict_types=1);

namespace App\Contracts;

use App\DTOs\PreviewDTO;

/**
 * Defines preview token generation and payload resolution for draft content.
 */
interface PreviewServiceInterface
{
    /**
     * Create a preview token and payload for a draft target.
     */
    public function createToken(string $targetType, int $targetId, string $locale, string $device, int $userId): PreviewDTO;

    /**
     * Resolve a preview token to a preview payload.
     */
    public function resolveToken(string $token): ?PreviewDTO;

    /**
     * Validate a preview token.
     */
    public function validateToken(string $token): bool;

    /**
     * Invalidate a preview token.
     */
    public function invalidateToken(string $token): bool;
}
