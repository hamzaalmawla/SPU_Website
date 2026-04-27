<?php

declare(strict_types=1);

namespace App\Services;

use App\Contracts\CacheServiceInterface;
use App\Contracts\ContinuityServiceInterface;
use App\DTOs\FileInventoryItemDTO;
use App\DTOs\PatternRuleDTO;
use App\DTOs\RedirectResultDTO;
use App\DTOs\RedirectRuleDTO;
use App\DTOs\UnresolvedRequestDTO;
use App\DTOs\ValidationMessageDTO;
use App\DTOs\ValidationResultDTO;
use App\Models\LegacyExactRedirect;
use App\Models\LegacyFileInventory;
use App\Models\LegacyPatternRule;
use App\Models\UnresolvedLegacyRequest;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

final class ContinuityService implements ContinuityServiceInterface
{
    private const MAX_REDIRECT_HOPS = 5;

    private const CACHE_TTL = 3600;

    public function __construct(
        private readonly CacheServiceInterface $cacheService,
    ) {}

    public function resolveRedirect(string $path, ?string $queryString = null): ?RedirectResultDTO
    {
        $normalizedPath = '/'.ltrim($path, '/');

        return $this->followRedirectChain($normalizedPath, $queryString);
    }

    public function resolveFileContinuity(string $path): ?string
    {
        $normalizedPath = '/'.ltrim($path, '/');

        $entry = LegacyFileInventory::query()
            ->mapped()
            ->whereRaw('LOWER(legacy_path) = ?', [mb_strtolower($normalizedPath)])
            ->first();

        return $entry?->current_path;
    }

    public function logUnresolved(UnresolvedRequestDTO $request): bool
    {
        try {
            $now = now();

            UnresolvedLegacyRequest::query()
                ->upsert(
                    [
                        [
                            'url' => $request->url,
                            'query_string' => $request->queryString,
                            'method' => $request->method,
                            'referrer' => $request->referrer,
                            'resolved_locale' => $request->resolvedLocale,
                            'request_type' => $request->requestType,
                            'hit_count' => 1,
                            'first_seen_at' => $now,
                            'last_seen_at' => $now,
                            'created_at' => $now,
                        ],
                    ],
                    ['url', 'method'],
                    ['hit_count' => \Illuminate\Database\Query\Expression::raw('hit_count + 1'), 'last_seen_at' => $now],
                );

            return true;
        } catch (\Throwable $e) {
            Log::warning('Failed to log unresolved legacy request', [
                'url' => $request->url,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    public function getExactRedirects(): Collection
    {
        return LegacyExactRedirect::query()
            ->orderBy('id')
            ->get()
            ->map(fn (LegacyExactRedirect $rule): RedirectRuleDTO => new RedirectRuleDTO(
                id: (int) $rule->getKey(),
                legacyPath: (string) $rule->legacy_path,
                destinationUrl: (string) $rule->destination_url,
                statusCode: (int) $rule->status_code,
                locale: $rule->locale,
                isActive: (bool) $rule->is_active,
            ));
    }

    public function getPatternRules(): Collection
    {
        return LegacyPatternRule::query()
            ->ordered()
            ->get()
            ->map(fn (LegacyPatternRule $rule): PatternRuleDTO => new PatternRuleDTO(
                id: (int) $rule->getKey(),
                pattern: (string) $rule->pattern,
                replacement: (string) $rule->replacement,
                statusCode: (int) $rule->status_code,
                priority: (int) $rule->priority,
                isActive: (bool) $rule->is_active,
            ));
    }

    public function validateRedirectRules(): ValidationResultDTO
    {
        $errors = [];

        $this->detectDuplicateExactPaths($errors);
        $this->detectConflictingPatterns($errors);
        $this->detectPotentialLoops($errors);

        return new ValidationResultDTO(
            isValid: $errors === [],
            errors: $errors,
        );
    }

    public function getUnresolvedRequests(array $filters = []): Collection
    {
        $query = UnresolvedLegacyRequest::query()->orderByDesc('last_seen_at');

        if (isset($filters['since']) && is_string($filters['since'])) {
            $query->where('last_seen_at', '>=', $filters['since']);
        }

        if (isset($filters['type']) && is_string($filters['type'])) {
            $query->where('request_type', $filters['type']);
        }

        return $query->get()->map(fn (UnresolvedLegacyRequest $record): UnresolvedRequestDTO => new UnresolvedRequestDTO(
            url: (string) $record->url,
            queryString: $record->query_string,
            method: (string) $record->method,
            referrer: $record->referrer,
            resolvedLocale: $record->resolved_locale,
            requestType: (string) $record->request_type,
            timestamp: $record->last_seen_at?->toIso8601String() ?? '',
        ));
    }

    public function getFileInventory(): Collection
    {
        return LegacyFileInventory::query()
            ->orderBy('id')
            ->get()
            ->map(fn (LegacyFileInventory $entry): FileInventoryItemDTO => new FileInventoryItemDTO(
                id: (int) $entry->getKey(),
                legacyPath: (string) $entry->legacy_path,
                currentPath: $entry->current_path,
                mediaAssetId: $entry->media_asset_id !== null ? (int) $entry->media_asset_id : null,
                status: (string) $entry->status,
            ));
    }

    /**
     * Follow the redirect chain, detecting loops and enforcing max hops.
     */
    private function followRedirectChain(string $path, ?string $queryString): ?RedirectResultDTO
    {
        $visited = [];
        $currentPath = $path;
        $lastResult = null;

        for ($hop = 0; $hop < self::MAX_REDIRECT_HOPS; $hop++) {
            if (in_array($currentPath, $visited, true)) {
                Log::warning('Redirect loop detected', ['chain' => $visited, 'looping_path' => $currentPath]);

                return $lastResult;
            }

            $visited[] = $currentPath;

            $result = $this->resolveExactMatch($currentPath)
                ?? $this->resolvePatternMatch($currentPath);

            if ($result === null) {
                return $lastResult;
            }

            $lastResult = $result;

            $destinationPath = parse_url($result->destinationUrl, PHP_URL_PATH);

            if (! is_string($destinationPath) || $destinationPath === '') {
                return $lastResult;
            }

            $currentPath = '/'.ltrim($destinationPath, '/');
        }

        return $lastResult;
    }

    private function resolveExactMatch(string $path): ?RedirectResultDTO
    {
        $cacheKey = 'continuity:exact:'.md5($path);

        $rule = $this->cacheService->tags('continuity')->remember(
            $cacheKey,
            fn () => LegacyExactRedirect::query()
                ->active()
                ->whereRaw('LOWER(legacy_path) = ?', [mb_strtolower($path)])
                ->first(),
            self::CACHE_TTL,
        );

        if (! $rule instanceof LegacyExactRedirect) {
            return null;
        }

        $rule->increment('hit_count');
        $rule->update(['last_hit_at' => now()]);

        return new RedirectResultDTO(
            statusCode: (int) $rule->status_code,
            destinationUrl: (string) $rule->destination_url,
            matchType: 'exact',
        );
    }

    private function resolvePatternMatch(string $path): ?RedirectResultDTO
    {
        $rules = LegacyPatternRule::query()
            ->active()
            ->ordered()
            ->get();

        foreach ($rules as $rule) {
            try {
                $pattern = (string) $rule->pattern;

                if (@preg_match($pattern, $path, $matches) === 1) {
                    $destination = (string) $rule->replacement;

                    foreach ($matches as $index => $match) {
                        $destination = str_replace('$'.$index, $match, $destination);
                    }

                    $rule->increment('hit_count');
                    $rule->update(['last_hit_at' => now()]);

                    return new RedirectResultDTO(
                        statusCode: (int) $rule->status_code,
                        destinationUrl: $destination,
                        matchType: 'pattern',
                    );
                }
            } catch (\Throwable $e) {
                Log::warning('Invalid pattern rule skipped', [
                    'rule_id' => $rule->getKey(),
                    'pattern' => $rule->pattern,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return null;
    }

    /**
     * @param  array<int, ValidationMessageDTO>  $errors
     */
    private function detectDuplicateExactPaths(array &$errors): void
    {
        $redirects = LegacyExactRedirect::query()
            ->active()
            ->get()
            ->groupBy(fn (LegacyExactRedirect $r): string => mb_strtolower((string) $r->legacy_path));

        foreach ($redirects as $path => $group) {
            if ($group->count() > 1) {
                $ids = $group->pluck('id')->implode(', ');
                $errors[] = new ValidationMessageDTO(
                    field: 'legacy_exact_redirects',
                    messages: ["Duplicate legacy_path '{$path}' found in rules: {$ids}"],
                );
            }
        }
    }

    /**
     * @param  array<int, ValidationMessageDTO>  $errors
     */
    private function detectConflictingPatterns(array &$errors): void
    {
        $patterns = LegacyPatternRule::query()
            ->active()
            ->ordered()
            ->get();

        $seen = [];

        foreach ($patterns as $rule) {
            $key = mb_strtolower((string) $rule->pattern);

            if (isset($seen[$key]) && $seen[$key] !== (string) $rule->replacement) {
                $errors[] = new ValidationMessageDTO(
                    field: 'legacy_pattern_rules',
                    messages: ["Conflicting pattern '{$rule->pattern}' has different replacements (rule {$rule->getKey()})"],
                );
            }

            $seen[$key] = (string) $rule->replacement;
        }
    }

    /**
     * @param  array<int, ValidationMessageDTO>  $errors
     */
    private function detectPotentialLoops(array &$errors): void
    {
        $redirects = LegacyExactRedirect::query()
            ->active()
            ->get();

        $pathMap = [];

        foreach ($redirects as $redirect) {
            $source = mb_strtolower((string) $redirect->legacy_path);
            $destPath = parse_url((string) $redirect->destination_url, PHP_URL_PATH);

            if (is_string($destPath)) {
                $pathMap[$source] = mb_strtolower('/'.ltrim($destPath, '/'));
            }
        }

        foreach ($pathMap as $startPath => $destination) {
            $visited = [$startPath];
            $current = $destination;

            for ($i = 0; $i < self::MAX_REDIRECT_HOPS; $i++) {
                if (in_array($current, $visited, true)) {
                    $chain = implode(' → ', [...$visited, $current]);
                    $errors[] = new ValidationMessageDTO(
                        field: 'legacy_exact_redirects',
                        messages: ["Potential redirect loop detected: {$chain}"],
                    );
                    break;
                }

                if (! isset($pathMap[$current])) {
                    break;
                }

                $visited[] = $current;
                $current = $pathMap[$current];
            }
        }
    }
}
