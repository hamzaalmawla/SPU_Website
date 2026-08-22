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
    /**
     * Stand-in written into cached HTML in place of the CSRF token.
     *
     * The public layout renders <meta name="csrf-token"> on every page, and
     * resources/js/alpine/dynamicFormStore.js sends it as X-CSRF-TOKEN. Caching
     * the rendered token would hand one visitor's per-session token to everyone
     * who hits the cache, so every AJAX form post from a cached page would fail
     * CSRF with a 419. The token is swapped out on the way into the cache and
     * back in, per request, on the way out.
     */
    private const CSRF_PLACEHOLDER = '__SPU_CSRF_TOKEN_PLACEHOLDER__';

    private const BROWSER_CACHE_CONTROL = 'private, no-store, max-age=0';

    /**
     * These are the query parameters used by public routes when a route name
     * is not available (for example, when this middleware is tested directly).
     * Named routes below narrow this list further to avoid irrelevant query
     * values fragmenting otherwise identical page caches.
     *
     * @var list<string>
     */
    private const SUPPORTED_QUERY_PARAMETERS = [
        'academic_phase',
        'category',
        'course',
        'department',
        'event',
        'expertise',
        'faculty',
        'id',
        'job',
        'lab',
        'month',
        'page',
        'q',
        'search',
        'semester',
        'slug',
        'source',
        'status',
        'tab',
        'theme',
        'topic',
        'type',
        'year',
    ];

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

        if ($this->hasSessionState($request) || $this->hasPrivateRequestHeaders($request)) {
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
            'public.e-services.suggestions-complaints',
            'public.news.events-list.register',
            'public.research.conferences.register',
        ], true)) {
            return true;
        }

        if ($request->is('*/e-services/suggestions-complaints')) {
            return true;
        }

        return $this->isPreviewRequest($request);
    }

    private function hasSessionState(Request $request): bool
    {
        if (! $request->hasSession()) {
            return false;
        }

        $session = $request->session();

        if ($session->hasOldInput() || $session->has('errors')) {
            return true;
        }

        foreach (['_flash.old', '_flash.new'] as $flashKey) {
            if (is_array($session->get($flashKey, [])) && $session->get($flashKey, []) !== []) {
                return true;
            }
        }

        return false;
    }

    private function hasPrivateRequestHeaders(Request $request): bool
    {
        if ($request->headers->has('Authorization')) {
            return true;
        }

        foreach (['Cache-Control', 'Pragma'] as $header) {
            $value = $request->headers->get($header);

            if (is_string($value) && preg_match('/(?:^|,)\s*(?:no-cache|no-store)(?:\s*=\s*[^,]*)?\s*(?:,|$)/i', $value) === 1) {
                return true;
            }
        }

        return false;
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
        $normalizedQuery = $this->normalizeQuery($request);
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
     * @return array<string, string|int>
     */
    private function normalizeQuery(Request $request): array
    {
        $allowedParameters = $this->supportedQueryParameters($request);
        $query = [];

        foreach ($allowedParameters as $key) {
            if (! array_key_exists($key, $request->query())) {
                continue;
            }

            $value = $this->canonicalQueryValue($key, $request->query($key));

            if ($value !== null) {
                $query[$key] = $value;
            }
        }

        ksort($query);

        return $query;
    }

    /**
     * @return list<string>
     */
    private function supportedQueryParameters(Request $request): array
    {
        $routeName = $request->route()?->getName();

        if (! is_string($routeName)) {
            return self::SUPPORTED_QUERY_PARAMETERS;
        }

        return match ($routeName) {
            'public.alumni.index' => ['q', 'year', 'faculty', 'department', 'page'],
            'public.about.leadership' => ['faculty'],
            'public.about.directorates.staff' => ['faculty', 'page'],
            'public.about.partnerships' => ['category', 'q', 'page'],
            'public.about.profile' => ['source'],
            'public.admissions.section' => $request->route('section') === 'documents' ? ['tab'] : [],
            'public.campus-life.career-development.jobs' => ['q', 'category', 'type', 'page'],
            'public.campus-life.career-development.jobs.apply' => ['job'],
            'public.facilities.subpage' => match ($request->route('subpage')) {
                'alumni', 'valedictorians' => ['q', 'year', 'department', 'faculty', 'semester', 'academic_phase', 'page'],
                'projects', 'research' => ['page'],
                'labs' => ['lab', 'page'],
                default => [],
            },
            'public.facilities.study-plan' => ['department'],
            'public.facilities.study-plan.course' => ['department', 'course', 'type'],
            'public.news.articles' => ['category', 'search', 'page'],
            'public.news.announcements' => ['category', 'page'],
            'public.news.events' => ['month'],
            'public.news.events-list' => ['category'],
            'public.news.events-list.register', 'public.news.events-list.past' => ['event'],
            'public.news.gallery' => ['category', 'page'],
            'public.research.repository', 'public.research.publications.index' => ['q', 'faculty', 'type', 'year', 'page'],
            'public.research.projects.index' => ['q', 'status', 'faculty', 'theme', 'page'],
            'public.research.researchers.index' => ['q', 'faculty', 'expertise', 'page'],
            'public.research.expert-finder' => ['q', 'faculty', 'page'],
            'public.research.conferences.register' => ['event'],
            default => [],
        };
    }

    private function canonicalQueryValue(string $key, mixed $value): string|int|null
    {
        if (! is_scalar($value)) {
            return null;
        }

        $value = (string) $value;

        if ($key === 'page') {
            $page = filter_var($value, FILTER_VALIDATE_INT);

            return is_int($page) && $page > 1 ? $page : null;
        }

        if ($key === 'q') {
            $value = trim($value);
        }

        if ($key === 'source' && ! in_array($value, ['person', 'faculty-member'], true)) {
            return null;
        }

        return $value === '' ? null : $value;
    }

    private function serializeResponse(Request $request, Response $response): ?array
    {
        if (! $this->isCacheableResponse($request, $response)) {
            return null;
        }

        return [
            'status' => $response->getStatusCode(),
            'headers' => $this->filterHeaders($response),
            'content' => $this->maskCsrfToken($request, (string) $response->getContent()),
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
        $response = new HttpResponse($this->restoreCsrfToken($payload['content']), $payload['status']);

        foreach ($payload['headers'] as $name => $values) {
            foreach ($values as $value) {
                $response->headers->set($name, $value, false);
            }
        }

        return $response;
    }

    /**
     * Replace this request's CSRF token with a placeholder before caching.
     *
     * The token is a 40-character random string, so an exact replace cannot
     * collide with page content.
     */
    private function maskCsrfToken(Request $request, string $content): string
    {
        $token = $request->hasSession() ? $request->session()->token() : null;

        if (! is_string($token) || $token === '') {
            return $content;
        }

        return str_replace($token, self::CSRF_PLACEHOLDER, $content);
    }

    /**
     * Put the current visitor's own CSRF token back into cached HTML.
     */
    private function restoreCsrfToken(string $content): string
    {
        if (! str_contains($content, self::CSRF_PLACEHOLDER)) {
            return $content;
        }

        return str_replace(self::CSRF_PLACEHOLDER, csrf_token(), $content);
    }

    private function withCacheHeader(Response $response, string $value): Response
    {
        $response->headers->set('X-Cache', $value);
        $response->headers->set('Cache-Control', self::BROWSER_CACHE_CONTROL);

        return $response;
    }

    private function toResponse(mixed $response): Response
    {
        return $response instanceof Response ? $response : response($response);
    }
}
