<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Compresses public responses, because nothing else on this origin does.
 *
 * WHY THIS EXISTS
 *
 * Measured on the deployed host: response time is flat and reliable up to about
 * 24KB and degrades sharply above it — 100KB responses took 27.9s at 15
 * concurrent with only 20 of 30 completing, while a 23KB response served in
 * 1.00s with 30 of 30. Static files behave identically to PHP pages, and
 * time-to-first-byte stays flat throughout, so the constraint is bytes leaving
 * the server rather than anything the application does.
 *
 * The legacy site this replaces serves a 27,842-byte homepage in 1.15s on the
 * same account, same nginx, same uplink. It is not better engineered. It fits
 * under the knee.
 *
 * Compression is what puts these pages back under it: /ar is 183,527 bytes raw
 * and 23,080 gzipped, landing exactly in the band that measures flat. That makes
 * this a correctness fix for the site's usability, not an optimisation.
 *
 * WHERE THIS RUNS, AND WHY IT MATTERS
 *
 * Registered first in the public route group, which makes it outermost on the
 * response path — so it compresses after CachePublicPages has restored a cached
 * body and swapped the per-request CSRF token back in. That ordering is load
 * bearing: the page cache substitutes that token as text, so a compressed body
 * in the store would break CSRF on every cached page.
 *
 * THE ABSENT-HEADER CASE
 *
 * A proxy in front of this application may strip Accept-Encoding before PHP sees
 * it, which would leave the application unable to know the client supports gzip
 * even though essentially every client has for twenty-five years. Compressing on
 * an absent header is not compliant, so it is off by default and behind its own
 * flag. Turn it on only after confirming the header genuinely never arrives —
 * if pages compress with the flag off, the header is arriving and the flag
 * should stay off.
 *
 * Delete all of this the day the edge compresses properly.
 */
final class CompressPublicResponses
{
    /**
     * Below this, compression costs more than it saves: gzip has framing
     * overhead and a small response is already under the threshold that matters.
     */
    private const MIN_BYTES = 1024;

    private const COMPRESSIBLE = [
        'text/html',
        'text/plain',
        'text/css',
        'text/xml',
        'application/json',
        'application/xml',
        'application/ld+json',
        'application/javascript',
        'image/svg+xml',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $this->addDiagnostics($request, $response);

        // Advertise that the representation varies on every path, so a cache in
        // front of us can never serve a compressed body to a client that did
        // not ask for one.
        $this->markVary($response);

        return $this->compress($request, $response);
    }

    private function compress(Request $request, Response $response): Response
    {
        if (! $this->shouldCompress($request, $response)) {
            return $this->mark($response, 'off');
        }

        $content = $response->getContent();

        if (! is_string($content) || strlen($content) < self::MIN_BYTES) {
            return $this->mark($response, 'skipped');
        }

        $compressed = gzencode($content, (int) config('edge.compression_level', 6));

        // gzencode returns false on failure, and a "compressed" body larger than
        // the original is worth discarding rather than shipping.
        if (! is_string($compressed) || strlen($compressed) >= strlen($content)) {
            return $this->mark($response, 'skipped');
        }

        $response->setContent($compressed);
        $response->headers->set('Content-Encoding', 'gzip');
        $response->headers->set('Content-Length', (string) strlen($compressed));

        return $this->mark(
            $response,
            $request->headers->has('Accept-Encoding') ? 'negotiated' : 'forced',
        );
    }

    /**
     * States what this middleware decided, on every path it can take.
     *
     * This host does not give us access logs, so these headers are the only
     * instrument available for confirming behaviour in production. That only
     * works if an absent header means exactly one thing — "this middleware did
     * not run" — so every branch that returns must say something.
     */
    private function mark(Response $response, string $state): Response
    {
        $response->headers->set('X-Compressed', $state);

        return $response;
    }

    /**
     * Reports what PHP actually receives, so the Accept-Encoding question can be
     * answered with one `curl -I` instead of inference.
     *
     * The header this site needs to know about may be removed by a proxy before
     * PHP ever sees it, and no amount of probing from outside can distinguish
     * that from "the origin chose not to compress". Off by default; turn it on
     * for one deploy, read the answer, turn it off again.
     */
    private function addDiagnostics(Request $request, Response $response): void
    {
        if (! (bool) config('edge.compression_diagnostics', false)) {
            return;
        }

        $response->headers->set('X-Compress-Debug', implode('; ', [
            'accept_encoding='.($request->headers->get('Accept-Encoding') ?? '(absent)'),
            'zlib='.(ini_get('zlib.output_compression') ?: '(off)'),
            'sapi='.PHP_SAPI,
        ]));
    }

    private function shouldCompress(Request $request, Response $response): bool
    {
        if (! (bool) config('edge.compress_responses', true)) {
            return false;
        }

        if (! function_exists('gzencode')) {
            return false;
        }

        // A streamed or file response has no in-memory body to compress, and
        // buffering one to compress it would defeat the point of streaming.
        if ($response instanceof StreamedResponse || $response instanceof BinaryFileResponse) {
            return false;
        }

        // Something upstream already encoded this. Never double-encode: if the
        // edge is fixed later, this is the guard that stops us corrupting every
        // response on the day it happens.
        if ($response->headers->has('Content-Encoding')) {
            return false;
        }

        // HEAD must carry the same headers as GET but no body, and these
        // statuses are defined to have no body at all.
        if ($request->isMethod('HEAD') || in_array($response->getStatusCode(), [204, 205, 304], true)) {
            return false;
        }

        // A partial response is a byte range of the *identity* representation.
        // Compressing it changes what those byte offsets mean.
        if ($response->getStatusCode() === 206 || $response->headers->has('Content-Range')) {
            return false;
        }

        if (! $this->isCompressibleType($response)) {
            return false;
        }

        return $this->clientAcceptsGzip($request);
    }

    private function isCompressibleType(Response $response): bool
    {
        $contentType = strtolower((string) $response->headers->get('Content-Type'));

        foreach (self::COMPRESSIBLE as $type) {
            if (str_contains($contentType, $type)) {
                return true;
            }
        }

        return false;
    }

    private function clientAcceptsGzip(Request $request): bool
    {
        $accept = $request->headers->get('Accept-Encoding');

        if (is_string($accept) && $accept !== '') {
            return str_contains(strtolower($accept), 'gzip');
        }

        // No header at all. Either the client genuinely cannot decompress, or a
        // proxy removed it on the way in. Only the operator can know which, so
        // this stays off unless explicitly enabled.
        return (bool) config('edge.compress_without_accept_encoding', false);
    }

    private function markVary(Response $response): void
    {
        $vary = $response->headers->get('Vary');

        if (! is_string($vary) || $vary === '') {
            $response->headers->set('Vary', 'Accept-Encoding');

            return;
        }

        if (! str_contains(strtolower($vary), 'accept-encoding')) {
            $response->headers->set('Vary', $vary.', Accept-Encoding');
        }
    }
}
