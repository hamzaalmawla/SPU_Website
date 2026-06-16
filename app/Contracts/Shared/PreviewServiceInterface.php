<?php

declare(strict_types=1);

namespace App\Contracts\Shared;

use App\DTOs\Preview\PreviewDTO;

/**
 * Defines preview token generation and payload resolution for draft content.
 */
interface PreviewServiceInterface
{
    public const TARGET_TYPES = [
        'homepage',
        'page',
    ];

    /**
     * Create a preview token and payload for a draft target.
     */
    public function createToken(string $targetType, ?int $targetId, string $locale, int $userId, ?string $device = null): PreviewDTO;

    /**
     * Resolve a preview token to a preview payload.
     */
    public function resolveToken(string $token, ?string $locale = null): ?PreviewDTO;

    /**
     * Validate a preview token.
     */
    public function validateToken(string $token): bool;

    /**
     * Invalidate a preview token.
     */
    public function invalidateToken(string $token): bool;
}
