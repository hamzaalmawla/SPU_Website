<?php

declare(strict_types=1);

namespace Tests\Feature\PX05;

use App\Http\Middleware\CachePublicPages;
use App\Http\Middleware\CompressPublicResponses;
use App\Http\Middleware\MinifyPublicHtml;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Router;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * Compression is the fix for this origin's response-size cliff, and it is only
 * safe because of where it sits in the stack. These pin both.
 */
final class ResponseCompressionTest extends TestCase
{
    use RefreshDatabase;

    /**
     * The load-bearing fact about this middleware is its position, not its
     * content. Compression must be OUTERMOST on the response path, which means
     * FIRST in the group: the page cache stores plain text and substitutes a
     * per-request CSRF token into it on every read, so anything that encodes
     * the body has to run after that substitution has already happened.
     *
     * Asserted across every route the page cache applies to, not just the
     * homepage. {locale}/{slugPath} alone covers most of the site's URLs, and a
     * single-route test would not notice a group it had fallen out of.
     */
    public function test_compression_wraps_the_page_cache_everywhere_the_cache_runs(): void
    {
        $router = app(Router::class);
        $checked = 0;

        foreach ($router->getRoutes()->getRoutes() as $route) {
            $middleware = $router->gatherRouteMiddleware($route);

            if (! in_array(CachePublicPages::class, $middleware, true)) {
                continue;
            }

            $name = $route->getName() ?? $route->uri();
            $compress = array_search(CompressPublicResponses::class, $middleware, true);

            $this->assertIsInt(
                $compress,
                "{$name} is page-cached but not compressed: its responses ship uncompressed on an "
                .'origin that degrades sharply above ~24KB.',
            );

            $this->assertLessThan(
                array_search(CachePublicPages::class, $middleware, true),
                $compress,
                "{$name} compresses inside the page cache, so a gzipped body would be stored and "
                .'CSRF substitution would corrupt every cached hit.',
            );

            if (($minify = array_search(MinifyPublicHtml::class, $middleware, true)) !== false) {
                $this->assertLessThan($minify, $compress, "{$name} compresses before minifying.");
            }

            $checked++;
        }

        $this->assertGreaterThan(50, $checked, 'Expected the page cache to apply to the public route group.');
    }

    /**
     * The invariant behind the ordering, asserted on the artefact itself: what
     * the page cache stores is decodable text carrying the CSRF placeholder.
     * If a gzip body ever reaches the store this fails loudly here rather than
     * silently on every cached page in production.
     */
    public function test_the_page_cache_stores_plain_text_and_never_an_encoded_body(): void
    {
        Route::middleware(['compress', 'cache.public'])->get('/__compression-cache-probe', fn () => response(
            str_repeat('<p>سياسة القبول</p>', 400),
        )->header('Content-Type', 'text/html; charset=UTF-8'));

        // First request populates the cache, second one is served from it —
        // the served-from-cache path is the one that can go wrong.
        $this->get('/__compression-cache-probe', ['Accept-Encoding' => 'gzip'])->assertOk();

        $second = $this->get('/__compression-cache-probe', ['Accept-Encoding' => 'gzip']);

        $second->assertOk();
        $this->assertSame('HIT', $second->headers->get('X-Cache'), 'The second request must be a cache hit.');

        $body = (string) $second->getContent();

        $this->assertSame(
            'gzip',
            $second->headers->get('Content-Encoding'),
            'The probe response should have been compressed on the way out.',
        );

        $decoded = gzdecode($body);

        $this->assertIsString($decoded, 'The compressed body must be exactly one gzip layer deep.');
        $this->assertStringContainsString('سياسة القبول', $decoded);
        $this->assertTrue(mb_check_encoding($decoded, 'UTF-8'), 'The decoded body must be valid UTF-8.');
        $this->assertFalse(
            is_string(@gzdecode($decoded)),
            'A body that decodes twice means something compressed it twice.',
        );
    }

    public function test_a_client_that_does_not_advertise_gzip_gets_an_uncompressed_body(): void
    {
        config()->set('edge.compress_without_accept_encoding', false);

        Route::middleware(['compress'])->get('/__compression-plain-probe', fn () => response(
            str_repeat('<p>hello</p>', 400),
        )->header('Content-Type', 'text/html; charset=UTF-8'));

        $response = $this->get('/__compression-plain-probe', ['Accept-Encoding' => 'identity']);

        $response->assertOk();
        $this->assertNull($response->headers->get('Content-Encoding'));
        $this->assertSame('off', $response->headers->get('X-Compressed'));
        $this->assertStringContainsString('Accept-Encoding', (string) $response->headers->get('Vary'));
    }

    /**
     * The day the host fixes nginx, or anyone re-enables zlib, this is the
     * guard that stops the site shipping gzip(gzip(body)) under a single
     * Content-Encoding header.
     */
    public function test_an_already_encoded_response_is_never_encoded_again(): void
    {
        Route::middleware(['compress'])->get('/__compression-preencoded-probe', fn () => response(
            (string) gzencode(str_repeat('<p>hello</p>', 400)),
        )->withHeaders([
            'Content-Type' => 'text/html; charset=UTF-8',
            'Content-Encoding' => 'gzip',
        ]));

        $response = $this->get('/__compression-preencoded-probe', ['Accept-Encoding' => 'gzip']);

        $body = (string) $response->getContent();

        $this->assertSame('gzip', $response->headers->get('Content-Encoding'));
        $this->assertIsString(gzdecode($body));
        $this->assertFalse(is_string(@gzdecode((string) gzdecode($body))), 'Body was double-encoded.');
    }

    /**
     * The header this middleware strips when storing a response, asserted on
     * the stored artefact rather than on the convention that keeps it empty.
     * `compress` is deliberately absent from this route: the point is what
     * happens if an encoded body ever does reach the cache.
     */
    public function test_the_page_cache_strips_framing_headers_it_must_not_replay(): void
    {
        Route::middleware(['cache.public'])->get('/__cache-header-probe', fn () => response(
            str_repeat('<p>hello</p>', 400),
        )->withHeaders([
            'Content-Type' => 'text/html; charset=UTF-8',
            'Content-Encoding' => 'gzip',
            'Content-Length' => '999',
            'Content-Range' => 'bytes 0-99/1000',
        ]));

        $this->get('/__cache-header-probe')->assertOk();

        $hit = $this->get('/__cache-header-probe');

        $this->assertSame('HIT', $hit->headers->get('X-Cache'), 'The second request must be a cache hit.');

        foreach (['Content-Encoding', 'Content-Length', 'Content-Range', 'Transfer-Encoding'] as $header) {
            $this->assertNull(
                $hit->headers->get($header),
                $header.' describes one specific wire and must never be replayed from the cache.',
            );
        }
    }

    /**
     * HEAD is skipped by design — it must carry a GET's headers with no body.
     * The trap is that `curl -I` sends HEAD, so anyone verifying compression
     * that way sees "off" whether it works or not. Pinned so the reason stays
     * attached to the behaviour.
     */
    public function test_head_is_declined_and_says_so(): void
    {
        Route::middleware(['compress'])->get('/__compression-head-probe', fn () => response(
            str_repeat('<p>hello</p>', 400),
        )->header('Content-Type', 'text/html; charset=UTF-8'));

        $head = $this->call('HEAD', '/__compression-head-probe', server: ['HTTP_ACCEPT_ENCODING' => 'gzip']);

        $this->assertSame('off', $head->headers->get('X-Compressed'));
        $this->assertNull($head->headers->get('Content-Encoding'));

        // The same URL over GET is the probe that actually answers the question.
        $this->assertSame(
            'gzip',
            $this->get('/__compression-head-probe', ['Accept-Encoding' => 'gzip'])->headers->get('Content-Encoding'),
        );
    }

    /** A response too small to be worth compressing must still say it ran. */
    public function test_a_declined_small_response_is_distinguishable_from_no_middleware(): void
    {
        Route::middleware(['compress'])->get('/__compression-tiny-probe', fn () => response(
            'ok',
        )->header('Content-Type', 'text/html; charset=UTF-8'));

        $this->assertSame(
            'skipped',
            $this->get('/__compression-tiny-probe', ['Accept-Encoding' => 'gzip'])->headers->get('X-Compressed'),
            'An absent X-Compressed must mean "this middleware did not run" and nothing else.',
        );
    }

    /**
     * The contract every internal probe relies on: with forcing enabled, an
     * absent Accept-Encoding compresses, and an explicit `identity` still opts
     * out. Request::create() sets no Accept-Encoding, so console commands that
     * drive the kernel — launch:validate, cache:warm — sit in the first case
     * unless they say otherwise, and `identity` is what they say.
     *
     * If the second half of this ever stops holding, `identity` is no longer an
     * opt-out and every one of those probes is silently receiving gzip.
     */
    public function test_forcing_compresses_an_absent_header_but_never_identity(): void
    {
        config()->set('edge.compress_without_accept_encoding', true);

        Route::middleware(['compress'])->get('/__compression-forcing-probe', fn () => response(
            str_repeat('<p>hello</p>', 400),
        )->header('Content-Type', 'text/html; charset=UTF-8'));

        $absent = $this->call('GET', '/__compression-forcing-probe');

        $this->assertSame('gzip', $absent->headers->get('Content-Encoding'));
        $this->assertSame('forced', $absent->headers->get('X-Compressed'));

        $identity = $this->call('GET', '/__compression-forcing-probe', server: [
            'HTTP_ACCEPT_ENCODING' => 'identity',
        ]);

        $this->assertNull(
            $identity->headers->get('Content-Encoding'),
            'identity must remain a reliable opt-out, or every internal probe receives gzip.',
        );
        $this->assertSame('off', $identity->headers->get('X-Compressed'));
    }

    /**
     * The forcing default is deliberately environment-scoped: it exists to work
     * around one proxy in front of one host. If it ever became the global
     * default, every test in this suite asserting on response text would be
     * reading gzip, and the failures would point everywhere except here.
     */
    public function test_forcing_is_not_the_default_outside_production(): void
    {
        $this->assertFalse(
            (bool) config('edge.compress_without_accept_encoding'),
            'Compression must stay negotiated everywhere Accept-Encoding behaves normally.',
        );
    }

    public function test_the_diagnostic_header_is_off_unless_explicitly_enabled(): void
    {
        Route::middleware(['compress'])->get('/__compression-debug-probe', fn () => response('ok'));

        $this->assertNull(
            $this->get('/__compression-debug-probe')->headers->get('X-Compress-Debug'),
            'Diagnostics must never be on by default on a public site.',
        );

        config()->set('edge.compression_diagnostics', true);

        $this->assertStringContainsString(
            'accept_encoding=',
            (string) $this->get('/__compression-debug-probe')->headers->get('X-Compress-Debug'),
        );
    }
}
