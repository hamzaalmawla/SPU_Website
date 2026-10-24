<?php

declare(strict_types=1);

namespace App\DTOs\Cms;

final readonly class CmsPreviewTokenDTO
{
    public function __construct(
        public string $token,
        public string $targetKey,
        public string $locale,
        public string $previewUrl,
        public ?string $expiresAt,
        public ?string $device = null,
    ) {}
}
