<?php

declare(strict_types=1);

namespace App\DTOs;

/**
 * Data transfer object for preview tokens and payloads.
 */
final readonly class PreviewDTO
{
    public function __construct(
        public string $token,
        public string $targetType,
        public int $targetId,
        public string $locale,
        public string $device,
        public string $previewUrl,
        public PreviewPayloadDTO $payload,
        public ?string $expiresAt = null,
    ) {}
}
