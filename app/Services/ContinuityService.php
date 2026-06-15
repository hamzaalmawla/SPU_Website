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

            $existing = UnresolvedLegacyRequest::query()
                ->where('url_hash', hash('sha256', $request->url))
                ->where('method', $request->method)
                ->latest('id')
                ->first();

            if ($existing instanceof UnresolvedLegacyRequest) {
                $existing->forceFill([
                    'query_string' => $request->queryString,
                    'referrer' => $request->referrer,
                    'resolved_locale' => $request->resolvedLocale,
                    'request_type' => $request->requestType,
                    'hit_count' => $existing->hit_count + 1,
                    'last_seen_at' => $now,
                ])->save();
            } else {
                UnresolvedLegacyRequest::query()->create([
                    'url' => $request->url,
                    'url_hash' => hash('sha256', $request->url),
                    'query_string' => $request->queryString,
                    'method' => $request->method,
                    'referrer' => $request->referrer,
                    'resolved_locale' => $request->resolvedLocale,
                    'request_type' => $request->requestType,
                    'hit_count' => 1,
                    'first_seen_at' => $now,
                    'last_seen_at' => $now,
                    'created_at' => $now,
                ]);
            }

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
        $this->detectUnsafeDestinations($errors);
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

        $destinationUrl = (string) $rule->destination_url;

        if (! $this->isAllowedDestination($destinationUrl)) {
            Log::warning('Blocked unsafe exact redirect destination', [
                'rule_id' => $rule->getKey(),
                'path' => $path,
                'destination' => $destinationUrl,
            ]);

            return null;
        }

        $destinationUrl = $this->sanitizeDestination($destinationUrl);

        return new RedirectResultDTO(
            statusCode: (int) $rule->status_code,
            destinationUrl: $destinationUrl,
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

                    if (! $this->isAllowedDestination($destination)) {
                        Log::warning('Blocked unsafe pattern redirect destination', [
                            'rule_id' => $rule->getKey(),
                            'path' => $path,
                            'destination' => $destination,
                        ]);

                        return null;
                    }

                    $destination = $this->sanitizeDestination($destination);

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
    private function detectUnsafeDestinations(array &$errors): void
    {
        foreach (LegacyExactRedirect::query()->active()->get() as $redirect) {
            $destination = (string) $redirect->destination_url;

            if ($this->isAllowedDestination($destination)) {
                continue;
            }

            $errors[] = new ValidationMessageDTO(
                field: 'legacy_exact_redirects',
                messages: ["Unsafe redirect destination '{$destination}' (rule {$redirect->getKey()})"],
            );
        }

        foreach (LegacyPatternRule::query()->active()->get() as $rule) {
            $destination = (string) $rule->replacement;

            if ($this->isAllowedDestination($destination)) {
                continue;
            }

            $errors[] = new ValidationMessageDTO(
                field: 'legacy_pattern_rules',
                messages: ["Unsafe pattern redirect destination '{$destination}' (rule {$rule->getKey()})"],
            );
        }
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

    /**
     * Validate that a redirect destination is safe (internal or allowlisted).
     *
     * Relative paths are always allowed. Absolute URLs must target spu.edu.sy,
     * a subdomain thereof, or an explicitly allowlisted external host.
     */
    private function isAllowedDestination(string $url): bool
    {
        $parsed = parse_url($url);

        if ($parsed === false) {
            return false;
        }

        $scheme = strtolower((string) ($parsed['scheme'] ?? ''));

        if ($scheme !== '' && ! in_array($scheme, ['http', 'https'], true)) {
            return false;
        }

        // Relative paths are always allowed.
        if (! isset($parsed['host'])) {
            return $scheme === '';
        }

        $host = strtolower($parsed['host']);

        /** @var array<int, string> $allowedHosts */
        $allowedHosts = config('continuity.allowed_redirect_hosts', ['spu.edu.sy']);

        if (in_array($host, $allowedHosts, true)) {
            return true;
        }

        // Allow subdomains of spu.edu.sy.
        if (str_ends_with($host, '.spu.edu.sy')) {
            return true;
        }

        return false;
    }

    /**
     * Strip query string and fragment from a redirect destination URL.
     *
     * Preserves scheme, host, and path only.
     */
    private function sanitizeDestination(string $url): string
    {
        $parsed = parse_url($url);

        // Relative path — strip query/fragment only.
        if (! isset($parsed['host'])) {
            return $parsed['path'] ?? '/';
        }

        $scheme = $parsed['scheme'] ?? 'https';
        $host = $parsed['host'];
        $port = isset($parsed['port']) ? ':'.$parsed['port'] : '';
        $path = $parsed['path'] ?? '/';

        return "{$scheme}://{$host}{$port}{$path}";
    }
}
