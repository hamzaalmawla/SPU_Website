<?php

declare(strict_types=1);

namespace App\DTOs;

/**
 * Data transfer object for content preview payloads.
 */
final readonly class PreviewDTO
{
    /**
     * @param  array<string, mixed>  $payload
     */
    public function __construct(
        public string $entityType,
        public int|string $entityId,
        public string $locale,
        public array $payload,
    ) {}
}
