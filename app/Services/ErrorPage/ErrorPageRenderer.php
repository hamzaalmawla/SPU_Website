<?php

declare(strict_types=1);

namespace App\Services\ErrorPage;

use App\Contracts\ErrorPage\ErrorPageRendererInterface;
use App\Contracts\ErrorPage\ErrorPageServiceInterface;
use App\Contracts\Navigation\NavigationServiceInterface;
use App\Contracts\Seo\SeoMetadataServiceInterface;
use App\Contracts\Settings\SettingsServiceInterface;
use App\DTOs\ErrorPage\ErrorPageContentDTO;
use App\DTOs\Navigation\LanguageSwitchLinkDTO;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Throwable;

/**
 * Renders branded error responses with layered degradation.
 *
 * Layer 1 — locale: taken from the URL path segment, then Accept-Language,
 *           then `ar`. Never from a service, a session, or the database.
 * Layer 2 — application errors (403/404/419/429) attempt the full public
 *           layout. Every service call needed for that shell is wrapped, so a
 *           failure inside the error page degrades instead of cascading.
 * Layer 3 — server errors (500/503) and any failure in layer 2 fall back to
 *           the self-contained standalone view, which touches no service, no
 *           database, no cache and no compiled asset bundle.
 */
final class ErrorPageRenderer implements ErrorPageRendererInterface
{
    public function __construct(
        private readonly ErrorPageServiceInterface $errorPageService,
        private readonly NavigationServiceInterface $navigationService,
        private readonly SettingsServiceInterface $settingsService,
        private readonly SeoMetadataServiceInterface $seoMetadataService,
    ) {}

    public function render(Throwable $exception, Request $request): ?Response
    {
        // A JSON/API caller must receive JSON. Laravel's default handler
        // already negotiates that, so defer to it entirely.
        if ($request->expectsJson()) {
            return null;
        }

        $status = $this->status($exception);

        if ($status === null) {
            return null;
        }

        $content = $this->errorPageService->content(
            $status,
            $request->path(),
            $request->headers->get('Accept-Language'),
        );

        // The Filament admin panel has its own chrome; an admin 404 must not be
        // wrapped in the public site header and footer. It still gets the
        // branded standalone page rather than the stock Laravel one. Admin
        // auth failures never reach here — AuthenticationException is not an
        // HttpExceptionInterface, so its login redirect is untouched.
        $useFullLayout = $this->errorPageService->supportsFullLayout($status)
            && ! $request->is('admin', 'admin/*');

        $response = $useFullLayout ? $this->renderFullLayout($content) : null;

        return ($response ?? $this->renderStandalone($content))
            ->setStatusCode($status)
            ->withHeaders($this->headers($exception));
    }

    /**
     * Resolve the HTTP status to render, or null to defer to Laravel.
     */
    private function status(Throwable $exception): ?int
    {
        if ($exception instanceof HttpExceptionInterface) {
            return $exception->getStatusCode();
        }

        // Keep the debug page for developers; in production a raw exception is
        // converted to a 500 by Laravel and picks up errors/500.blade.php.
        return config('app.debug') === true ? null : 500;
    }

    /**
     * @return array<string, string>
     */
    private function headers(Throwable $exception): array
    {
        if (! $exception instanceof HttpExceptionInterface) {
            return [];
        }

        $headers = [];

        foreach ($exception->getHeaders() as $name => $value) {
            if (is_string($name) && is_scalar($value)) {
                $headers[$name] = (string) $value;
            }
        }

        return $headers;
    }

    /**
     * Attempt the full public layout. Returns null when any dependency needed
     * by the shell is unavailable, so the caller can degrade.
     */
    private function renderFullLayout(ErrorPageContentDTO $content): ?\Illuminate\Http\Response
    {
        try {
            $locale = $content->locale;
            $path = '/'.$locale.'/error/'.$content->status;

            return response()->view('errors.full', [
                'error' => $content,
                'locale' => $locale,
                'direction' => $content->direction,
                'navigation' => $this->navigationService->getFullNavigationPayload($locale, ltrim($path, '/')),
                'settings' => $this->settingsService->getPublicSettings($locale),
                'seo' => $this->seoMetadataService->buildFallback($locale, [
                    'path' => $path,
                    'title' => $content->title,
                    'meta_description' => $content->message,
                    'robots' => 'noindex,follow',
                ]),
                'languageSwitch' => [
                    new LanguageSwitchLinkDTO('ar', 'AR', '/ar', $locale === 'ar'),
                    new LanguageSwitchLinkDTO('en', 'EN', '/en', $locale === 'en'),
                ],
                'isPreview' => false,
            ]);
        } catch (Throwable) {
            // Navigation, settings or SEO needs the database and the cache.
            // If either is down the error page must not become a second error.
            return null;
        }
    }

    private function renderStandalone(ErrorPageContentDTO $content): \Illuminate\Http\Response
    {
        return response()->view('errors.'.$this->standaloneView($content->status), [
            'error' => $content,
        ]);
    }

    /**
     * Only the documented statuses ship a dedicated view; anything else is
     * presented with the generic server-error page.
     */
    private function standaloneView(int $status): string
    {
        return in_array($status, [403, 404, 419, 429, 500, 503], true)
            ? (string) $status
            : '500';
    }
}
