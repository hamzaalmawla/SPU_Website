<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\URL;
use Symfony\Component\HttpFoundation\Response;

/**
 * Applies the request locale from the URL prefix and exposes language headers.
 */
final class LocaleSetterMiddleware
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $locale = $this->resolveLocale($request);

        if ($locale !== null) {
            App::setLocale($locale);
            URL::defaults(['locale' => $locale]);
        }

        $response = $next($request);

        if ($locale !== null) {
            $response->headers->set('Content-Language', $locale);
            $response->headers->set('X-Page-Direction', $locale === 'ar' ? 'rtl' : 'ltr');
        }

        return $response;
    }

    private function resolveLocale(Request $request): ?string
    {
        $locale = $request->route('locale');

        if (! is_string($locale) || ! in_array($locale, ['ar', 'en'], true)) {
            $locale = $request->segment(1);
        }

        return is_string($locale) && in_array($locale, ['ar', 'en'], true) ? $locale : null;
    }
}
