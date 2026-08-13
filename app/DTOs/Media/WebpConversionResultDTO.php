<?php

declare(strict_types=1);

namespace App\DTOs\Media;

final readonly class WebpConversionResultDTO
{
    public function __construct(
        public string $path,
        public int $sizeBytes,
        public int $width,
        public int $height,
    ) {}
}
