<?php

declare(strict_types=1);

namespace App\Services\Legacy\QueryResolvers;

use App\Contracts\Legacy\LegacyQueryModuleResolverInterface;
use App\DTOs\Legacy\LegacyQueryResolutionDTO;
use App\DTOs\Legacy\NormalizedLegacyUrlDTO;

final class LegacyUnsupportedLanguageQueryResolver implements LegacyQueryModuleResolverInterface
{
    public function canResolve(NormalizedLegacyUrlDTO $url): bool
    {
        $languageIds = config('old_database.unsupported_language_continuity.old_language_ids', [3, 6, 7]);

        return $url->requestType === 'legacy_router'
            && ! $url->language->isSupportedLocale
            && is_array($languageIds)
            && in_array($url->language->oldLanguageId, $languageIds, true);
    }

    public function resolve(NormalizedLegacyUrlDTO $url): ?LegacyQueryResolutionDTO
    {
        if (! $this->canResolve($url)) {
            return null;
        }

        $target = config('old_database.unsupported_language_continuity.target', '/en');
        $statusCode = config('old_database.unsupported_language_continuity.status_code', 302);

        return new LegacyQueryResolutionDTO(
            module: 'unsupported_language_fallback',
            sourceTable: 'legacy_router',
            sourceId: $url->language->oldLanguageId,
            targetUrl: is_string($target) && str_starts_with($target, '/') ? $target : '/en',
            statusCode: is_numeric($statusCode) ? (int) $statusCode : 302,
            confidence: 'high',
            notes: 'Product-approved fallback for retired FR, ES, and DE legacy routes to the English homepage.',
        );
    }
}
