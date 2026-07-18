<?php

declare(strict_types=1);

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

final class BrowserLocaleRedirectController extends Controller
{
    public function __invoke(Request $request): RedirectResponse
    {
        $acceptLanguage = $request->headers->get('Accept-Language');
        $locale = is_string($acceptLanguage) && trim($acceptLanguage) !== ''
            ? ($request->getPreferredLanguage(['ar', 'en']) ?? 'ar')
            : 'ar';
        $path = trim($request->path(), '/');
        $target = '/'.$locale.($path !== '' ? '/'.$path : '');
        $query = $request->getQueryString();
        $response = redirect($query !== null ? $target.'?'.$query : $target);

        $response->headers->set('Vary', 'Accept-Language');
        $response->headers->set('Cache-Control', 'no-store, private');

        return $response;
    }
}
