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
 * This was written when nothing on this origin compressed, and 42% of the live
 * Arabic homepage — 240,855 bytes — was leading whitespace sent verbatim to
 * every visitor. CompressPublicResponses now runs outside this middleware, and
 * gzip reduces repeated indent runs to almost nothing, so most of that original
 * justification is gone.
 *
 * What remains is worth keeping. This runs *inside* the public page cache, so
 * what shrinks is the stored body: cache files on disk, and the string the
 * cache reads back and runs CSRF substitution over on every hit. The compressor
 * runs outside the cache and cannot do either.
 *
 * So: no longer the load-bearing fix it was, still a real one. If it ever gets
 * in the way of debugging rendered markup, MINIFY_HTML=false costs little now.
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
     * Removes an indentation run only between one tag and the next.
     *
     * The run must sit after a '>' and before a '<'. Anchoring on both is what
     * makes this safe rather than merely careful. Anchoring on '>' alone is not
     * enough: a '>' can legitimately appear inside an attribute value, and
     * `<div title="a>` followed by a newline would then have the whitespace
     * inside its own attribute collapsed. Requiring a '<' next means the run has
     * to be inter-tag whitespace, which no attribute value can contain.
     *
     * Blade escapes '>' to '&gt;', so the unsafe case needs raw output to
     * produce it — but it costs 2.3 percentage points of saving to rule out
     * entirely, and a silently rewritten attribute is not a defect anyone would
     * successfully diagnose.
     *
     * One newline is always left behind, and HTML collapses any whitespace run
     * to a single space, so the rendered result is identical — including
     * between inline-block elements, where removing the whitespace entirely
     * would close up real visual gaps.
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

        $collapsed = preg_replace('/(>)[ \t]*\n[ \t\n]+(?=<)/', '$1'."\n", $stashed);

        if (! is_string($collapsed)) {
            return $html;
        }

        foreach ($preserved as $index => $original) {
            $collapsed = str_replace("\x00".$index."\x00", $original, $collapsed);
        }

        return $collapsed;
    }
}
