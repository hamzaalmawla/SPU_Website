<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Sends redirects without a body.
 *
 * Symfony's RedirectResponse carries a small HTML page ("Redirecting to
 * <url>") for clients that ignore the Location header. No browser or crawler
 * needs it, and a scanner reads any body on a redirect as a possible leak (ZAP
 * "Big Redirect Detected", 2026-11 scan: the contact form, the expert-finder
 * alias and the old-site redirects).
 *
 * Only the body goes. The status code and the Location header are untouched,
 * so the old site's links (and the ranking they carry) redirect exactly as
 * before.
 */
final class EmptyRedirectBody
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if (! $response->isRedirection()
            || ! $response->headers->has('Location')
            || $response instanceof StreamedResponse
            || $response instanceof BinaryFileResponse) {
            return $response;
        }

        $response->setContent('');
        // An encoding header would describe a body that is no longer there.
        $response->headers->remove('Content-Encoding');
        $response->headers->remove('Content-Length');

        return $response;
    }
}
