<?php

declare(strict_types=1);

namespace App\Services\Legacy;

use App\Contracts\Legacy\LegacyQueryRedirectResolverInterface;
use App\Contracts\Legacy\LegacyQueryResolverRegistryInterface;
use App\Contracts\Legacy\LegacyUrlNormalizerInterface;
use App\DTOs\Legacy\RedirectResultDTO;

final class LegacyQueryRedirectResolver implements LegacyQueryRedirectResolverInterface
{
    public function __construct(
        private readonly LegacyUrlNormalizerInterface $normalizer,
        private readonly LegacyQueryResolverRegistryInterface $registry,
    ) {}

    public function resolve(string $path, ?string $queryString = null): ?RedirectResultDTO
    {
        $normalized = $this->normalizer->normalize($path, $queryString);
        $resolution = $this->registry->resolve($normalized);

        if ($resolution === null) {
            return null;
        }

        return new RedirectResultDTO(
            statusCode: $resolution->statusCode,
            destinationUrl: $resolution->targetUrl,
            matchType: 'legacy_query',
        );
    }
}
