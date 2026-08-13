<?php

declare(strict_types=1);

namespace App\Contracts\Media;

use App\DTOs\Media\WebpConversionResultDTO;

interface ImageConversionServiceInterface
{
    public function isAvailable(): bool;

    public function convert(string $diskName, string $sourcePath, string $mimeType): ?WebpConversionResultDTO;
}
