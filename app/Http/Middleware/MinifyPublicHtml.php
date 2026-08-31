<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Strips the indentation Blade leaves in rendered HTML.
 *
 * On a compressed origin this would be pointless — gzip reduces repeated indent
 * runs to almost nothing. This origin is not compressed: nginx terminates TLS
 * and neither compresses nor forwards Accept-Encoding upstream, so the
 * mod_deflate rules in public/.htaccess never fire and every byte of markup is
 * sent verbatim. Measured on the live Arabic homepage, 42% of a 240,855-byte
 * response was leading whitespace.
 *
 * This runs inside the public page cache, so what is stored is already reduced
 * and the cache files shrink with the responses.
 *
 * Delete this the day compression is enabled at the proxy.
 */
final class MinifyPublicHtml
{
    /**
     * Whitespace carries meaning inside these, so their contents are lifted out
     * before the collapse and put back afterwards.
     */
    private const PRESERVED = 'pre|textarea|script|style';

    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if (! $this->shouldMinify($response)) {
            return $response;
        }

        $content = $response->getContent();

        if (! is_string($content) || $content === '') {
            return $response;
        }

        $minified = $this->collapse($content);

        if ($minified === $content) {
            return $response;
        }

        $response->setContent($minified);

        // A stale Content-Length truncates the body in the browser.
        if ($response->headers->has('Content-Length')) {
            $response->headers->set('Content-Length', (string) strlen($minified));
        }

        return $response;
    }

    private function shouldMinify(Response $response): bool
    {
        if (! (bool) config('edge.minify_html', true)) {
            return false;
        }

        if ($response instanceof StreamedResponse || $response instanceof BinaryFileResponse) {
            return false;
        }

        return str_contains((string) $response->headers->get('Content-Type'), 'text/html');
    }

    /**
     * Removes an indentation run only where it directly follows a closing '>'.
     *
     * Anchoring on '>' is what makes this safe rather than merely careful: a
     * match cannot begin inside a tag, so no attribute value can be rewritten,
     * however many newlines it contains. One newline is always left behind, and
     * HTML collapses any whitespace run to a single space, so the rendered
     * result is identical — including between inline-block elements, where
     * removing the whitespace entirely would close up real visual gaps.
     */
    private function collapse(string $html): string
    {
        $preserved = [];

        $stashed = preg_replace_callback(
            '#<('.self::PRESERVED.')\b[^>]*>.*?</\1>#si',
            static function (array $match) use (&$preserved): string {
                $preserved[] = $match[0];

                return "\x00".(count($preserved) - 1)."\x00";
            },
            $html,
        );

        if (! is_string($stashed)) {
            return $html;
        }

        $collapsed = preg_replace('/(>)[ \t]*\n[ \t\n]+/', '$1'."\n", $stashed);

        if (! is_string($collapsed)) {
            return $html;
        }

        foreach ($preserved as $index => $original) {
            $collapsed = str_replace("\x00".$index."\x00", $original, $collapsed);
        }

        return $collapsed;
    }
}
