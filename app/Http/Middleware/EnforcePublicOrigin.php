<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class EnforcePublicOrigin
{
    public function handle(Request $request, Closure $next): Response
    {
        $path = '/'.ltrim($request->path(), '/');

        if (preg_match('#^/app\.php(?:/|$)#i', $path) === 1
            || preg_match('#^/index\.php/.+#i', $path) === 1) {
            abort(404);
        }

        if (! (bool) config('edge.enforce_canonical_host', false)) {
            return $next($request);
        }

        $canonicalUrl = rtrim((string) config('edge.canonical_url'), '/');
        $canonicalHost = (string) parse_url($canonicalUrl, PHP_URL_HOST);

        if ($canonicalHost !== '' && strcasecmp($request->getHost(), $canonicalHost) !== 0) {
            return redirect()->away($canonicalUrl.$request->getRequestUri(), 301);
        }

        return $next($request);
    }
}
