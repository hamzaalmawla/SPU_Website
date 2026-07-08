<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Contracts\Shared\ContinuityServiceInterface;
use App\Contracts\Legacy\LegacyUrlNormalizerInterface;
use App\DTOs\Legacy\UnresolvedRequestDTO;
use Closure;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

/**
 * Resolves legacy URL redirects and logs unresolved 404 requests.
 */
final class RedirectContinuityMiddleware
{
    /** Prefixes that should bypass redirect resolution entirely. */
    private const SKIP_PREFIXES = ['/admin', '/livewire', '/filament'];

    public function __construct(
        private readonly ContinuityServiceInterface $continuityService,
        private readonly LegacyUrlNormalizerInterface $legacyUrlNormalizer,
    ) {}

    /**
     * Handle an incoming request — resolve redirects for non-admin paths.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $path = '/'.ltrim($request->path(), '/');

        if ($this->shouldSkip($path)) {
            return $next($request);
        }

        $result = $this->continuityService->resolveRedirect($path, $request->getQueryString());

        if ($result !== null) {
            return new RedirectResponse($result->destinationUrl, $result->statusCode);
        }

        if ($this->detectRequestType($path) === 'file') {
            $currentPath = $this->continuityService->resolveFileContinuity($path);

            if ($currentPath !== null) {
                $destination = $this->normalizeDestination($currentPath);

                if ($destination !== null) {
                    return new RedirectResponse($destination, 301);
                }
            }
        }

        return $next($request);
    }

    /**
     * Terminate — log unresolved requests on 404 responses.
     */
    public function terminate(Request $request, Response $response): void
    {
        if ($response->getStatusCode() !== 404) {
            return;
        }

        $path = '/'.ltrim($request->path(), '/');

        if ($this->shouldSkip($path)) {
            return;
        }

        try {
            $normalized = $this->legacyUrlNormalizer->normalize($path, $request->getQueryString());

            $this->continuityService->logUnresolved(new UnresolvedRequestDTO(
                url: $request->fullUrl(),
                queryString: $request->getQueryString(),
                method: $request->method(),
                referrer: $request->headers->get('referer'),
                resolvedLocale: app()->getLocale(),
                requestType: $this->detectRequestType($path),
                timestamp: now()->toIso8601String(),
                normalized: $this->legacyUrlNormalizer->toLogPayload($normalized),
                handler: $normalized->handlerKey,
                outcome: 'unresolved',
                subsite: $normalized->subsite->key,
                oldSiteId: $normalized->subsite->siteId,
                oldLanguageId: $normalized->language->oldLanguageId,
                oldLanguageSymbol: $normalized->language->oldSymbol,
            ));
        } catch (\Throwable) {
            // Fire-and-forget: never block the user's 404 response.
        }
    }

    /**
     * Determine whether the given path should skip redirect resolution.
     */
    private function shouldSkip(string $path): bool
    {
        if ($path === '/admin/index.php') {
            return false;
        }

        foreach (self::SKIP_PREFIXES as $prefix) {
            if (str_starts_with($path, $prefix)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Detect whether the request is for a file or a page.
     * A path with a file extension (e.g. .pdf, .doc, .jpg) is classified as 'file'.
     */
    private function detectRequestType(string $path): string
    {
        $extension = pathinfo($path, PATHINFO_EXTENSION);

        return $extension !== '' ? 'file' : 'page';
    }

    private function normalizeDestination(string $path): ?string
    {
        if (Str::startsWith($path, ['http://', 'https://'])) {
            return $this->isAllowedAbsoluteDestination($path) ? $path : null;
        }

        return '/'.ltrim($path, '/');
    }

    private function isAllowedAbsoluteDestination(string $url): bool
    {
        $host = parse_url($url, PHP_URL_HOST);

        if (! is_string($host) || $host === '') {
            return false;
        }

        $host = strtolower($host);
        /** @var array<int, string> $allowedHosts */
        $allowedHosts = config('continuity.allowed_redirect_hosts', ['spu.edu.sy']);

        return in_array($host, $allowedHosts, true) || str_ends_with($host, '.spu.edu.sy');
    }
}
