<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Contracts\Shared\CacheServiceInterface;
use BadMethodCallException;
use Closure;
use Illuminate\Contracts\Auth\Factory as AuthFactory;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Caches cacheable public HTML responses while bypassing preview and admin traffic.
 */
final class CachePublicPages
{
    public function __construct(
        private readonly CacheServiceInterface $cacheService,
        private readonly AuthFactory $authFactory,
    ) {}

    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        if ($this->shouldBypass($request)) {
            return $this->withCacheHeader($this->toResponse($next($request)), 'BYPASS');
        }

        $cacheHit = true;
        $freshResponse = null;

        $cacheService = $this->taggedCacheService($request);

        $cachedPayload = $cacheService->remember(
            $this->buildCacheKey($request),
            function () use (&$cacheHit, &$freshResponse, $next, $request): ?array {
                $cacheHit = false;
                $freshResponse = $this->toResponse($next($request));

                return $this->serializeResponse($request, $freshResponse);
            },
            (int) config('cache.public_page_ttl', 300),
        );

        if ($cacheHit && is_array($cachedPayload)) {
            return $this->withCacheHeader($this->restoreResponse($cachedPayload), 'HIT');
        }

        if ($freshResponse instanceof Response) {
            return $this->withCacheHeader($freshResponse, is_array($cachedPayload) ? 'MISS' : 'BYPASS');
        }

        return $this->withCacheHeader($this->toResponse($next($request)), 'BYPASS');
    }

    private function shouldBypass(Request $request): bool
    {
        if (! $request->isMethod('GET')) {
            return true;
        }

        if ($request->is('admin') || $request->is('admin/*')) {
            return true;
        }

        if ($request->user() !== null || $this->authFactory->guard((string) config('auth.admin_guard', 'web'))->check()) {
            return true;
        }

        if (in_array($request->route()?->getName(), [
            'public.contact',
            'public.news.events-list.register',
            'public.research.conferences.register',
        ], true)) {
            return true;
        }

        return $this->isPreviewRequest($request);
    }

    private function isPreviewRequest(Request $request): bool
    {
        $route = $request->route();
        $routeName = $route?->getName();

        if (is_string($routeName) && str_contains($routeName, 'preview')) {
            return true;
        }

        if ($request->is('preview') || $request->is('preview/*') || $request->is('*/preview') || $request->is('*/preview/*')) {
            return true;
        }

        if ($request->headers->has('X-Preview-Token')) {
            return true;
        }

        return $request->hasAny(['preview', 'preview_token', 'token']) || $request->route('token') !== null;
    }

    private function buildCacheKey(Request $request): string
    {
        $locale = $request->route('locale');
        $normalizedQuery = $this->normalizeQuery($request->query());
        $queryString = http_build_query($normalizedQuery);

        return 'public_pages:'.sha1(implode('|', [
            is_string($locale) ? $locale : app()->getLocale(),
            trim($request->path(), '/'),
            $queryString,
        ]));
    }

    private function taggedCacheService(Request $request): CacheServiceInterface
    {
        $locale = $request->route('locale');
        $tags = ['public-pages', 'public-shell'];

        if (is_string($locale) && $locale !== '') {
            $tags[] = 'public-shell:'.$locale;
        }

        try {
            return $this->cacheService->tags($tags);
        } catch (BadMethodCallException) {
            return $this->cacheService;
        }
    }

    /**
     * @param  array<string, mixed>  $query
     * @return array<string, mixed>
     */
    private function normalizeQuery(array $query): array
    {
        ksort($query);

        foreach ($query as $key => $value) {
            if (is_array($value)) {
                $query[$key] = $this->normalizeQuery($value);
            }
        }

        return $query;
    }

    private function serializeResponse(Request $request, Response $response): ?array
    {
        if (! $this->isCacheableResponse($request, $response)) {
            return null;
        }

        return [
            'status' => $response->getStatusCode(),
            'headers' => $this->filterHeaders($response),
            'content' => (string) $response->getContent(),
        ];
    }

    private function isCacheableResponse(Request $request, Response $response): bool
    {
        if (! $request->isMethod('GET') || $response->getStatusCode() !== 200) {
            return false;
        }

        if ($response instanceof BinaryFileResponse || $response instanceof StreamedResponse) {
            return false;
        }

        $contentType = strtolower((string) $response->headers->get('Content-Type', ''));

        return str_contains($contentType, 'text/html') || str_contains($contentType, 'application/xhtml+xml');
    }

    /**
     * @return array<string, array<int, string>>
     */
    private function filterHeaders(Response $response): array
    {
        $headers = $response->headers->all();

        unset($headers['cache-control'], $headers['date'], $headers['set-cookie'], $headers['x-cache']);

        return $headers;
    }

    /**
     * @param  array{status:int, headers:array<string, array<int, string>>, content:string}  $payload
     */
    private function restoreResponse(array $payload): Response
    {
        $response = new HttpResponse($payload['content'], $payload['status']);

        foreach ($payload['headers'] as $name => $values) {
            foreach ($values as $value) {
                $response->headers->set($name, $value, false);
            }
        }

        return $response;
    }

    private function withCacheHeader(Response $response, string $value): Response
    {
        $response->headers->set('X-Cache', $value);

        return $response;
    }

    private function toResponse(mixed $response): Response
    {
        return $response instanceof Response ? $response : response($response);
    }
}
