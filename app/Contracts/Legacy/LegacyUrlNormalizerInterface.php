<?php

declare(strict_types=1);

namespace App\Contracts\Legacy;

use App\DTOs\Legacy\NormalizedLegacyUrlDTO;

interface LegacyUrlNormalizerInterface
{
    public function normalize(string $path, ?string $queryString = null): NormalizedLegacyUrlDTO;

    /**
     * @return array<string, mixed>
     */
    public function toLogPayload(NormalizedLegacyUrlDTO $url): array;
}
