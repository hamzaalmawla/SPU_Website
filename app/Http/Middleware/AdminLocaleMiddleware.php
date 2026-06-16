<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class AdminLocaleMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        $locale = $request->session()->get('admin_locale', 'ar');

        if (! in_array($locale, ['ar', 'en'], true)) {
            $locale = 'ar';
        }

        app()->setLocale($locale);
        $request->attributes->set('admin_locale', $locale);
        $request->attributes->set('admin_direction', $locale === 'ar' ? 'rtl' : 'ltr');
        view()->share('adminLocale', $locale);
        view()->share('adminDirection', $locale === 'ar' ? 'rtl' : 'ltr');

        return $next($request);
    }
}
