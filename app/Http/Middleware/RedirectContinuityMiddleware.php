<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Contracts\ContinuityServiceInterface;
use App\DTOs\UnresolvedRequestDTO;
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
                return new RedirectResponse($this->normalizeDestination($currentPath), 301);
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
            $this->continuityService->logUnresolved(new UnresolvedRequestDTO(
                url: $request->fullUrl(),
                queryString: $request->getQueryString(),
                method: $request->method(),
                referrer: $request->headers->get('referer'),
                resolvedLocale: app()->getLocale(),
                requestType: $this->detectRequestType($path),
                timestamp: now()->toIso8601String(),
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

    private function normalizeDestination(string $path): string
    {
        if (Str::startsWith($path, ['http://', 'https://'])) {
            return $path;
        }

        return '/'.ltrim($path, '/');
    }
}
