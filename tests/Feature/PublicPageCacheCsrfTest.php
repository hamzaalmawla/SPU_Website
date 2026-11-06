<?php

declare(strict_types=1);

namespace Tests\Feature;

use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * The public page cache stores rendered HTML and serves it to every anonymous
 * visitor. The public layout renders <meta name="csrf-token"> on every page and
 * resources/js/alpine/dynamicFormStore.js sends that value as X-CSRF-TOKEN, so a
 * cached token would be one visitor's per-session token handed to everyone —
 * every AJAX form post from a cached page would fail CSRF with a 419.
 *
 * CachePublicPages masks the token on the way into the cache and substitutes the
 * requesting visitor's own token on the way out.
 */
final class PublicPageCacheCsrfTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // The homepage 404s without published sections, and this test is about
        // what the page cache stores, so it needs a real renderable page.
        $this->seed(DatabaseSeeder::class);

        Route::middleware(['web', 'cache.public'])
            ->get('/testing/cache-form', function (Request $request) {
                return response(
                    '<form><input name="name" value="'.e((string) old('name')).'"><output>'.e((string) session('form_flash')).'</output><span data-errors="'.($request->session()->has('errors') ? '1' : '0').'">form</span></form>',
                    200,
                    ['Content-Type' => 'text/html'],
                );
            })
            ->name('testing.cache-form');
    }

    public function test_cached_public_pages_do_not_share_one_csrf_token_between_visitors(): void
    {
        // First visitor populates the cache.
        $first = $this->get('/ar');
        $first->assertOk();
        $firstToken = $this->csrfTokenFrom($first->getContent());

        // Second visitor, a fresh session, must be served from cache but with
        // their own token.
        $this->flushSession();
        $second = $this->get('/ar');
        $second->assertOk();
        $second->assertHeader('X-Cache', 'HIT');
        $secondToken = $this->csrfTokenFrom($second->getContent());

        $this->assertNotNull($firstToken, 'The public layout should render a csrf-token meta tag.');
        $this->assertNotNull($secondToken);
        $this->assertNotSame(
            $firstToken,
            $secondToken,
            'A cached page must not serve one visitor\'s CSRF token to another.',
        );
    }

    public function test_the_cache_placeholder_never_reaches_the_browser(): void
    {
        $this->get('/ar')->assertOk();

        $cached = $this->get('/ar');

        $cached->assertHeader('X-Cache', 'HIT');
        $cached->assertDontSee('__SPU_CSRF_TOKEN_PLACEHOLDER__', escape: false);
    }

    public function test_the_visitors_own_token_validates_against_their_session(): void
    {
        $this->get('/ar')->assertOk();

        $this->flushSession();
        $response = $this->get('/ar');

        $token = $this->csrfTokenFrom($response->getContent());

        $this->assertNotNull($token);
        $this->assertSame(session()->token(), $token);
    }

    public function test_form_old_input_errors_and_flash_data_are_not_shared_between_clients(): void
    {
        $first = $this->withSession([
            '_old_input' => ['name' => 'First client input'],
            '_flash.new' => ['form_flash'],
            'form_flash' => 'First client flash',
            'errors' => ['name' => ['First client error']],
        ])->get('/testing/cache-form');

        $first->assertHeader('X-Cache', 'BYPASS')
            ->assertSee('First client input')
            ->assertSee('First client flash')
            ->assertSee('data-errors="1"', false);

        $this->flushSession();

        $this->get('/testing/cache-form')
            ->assertHeader('X-Cache', 'MISS')
            ->assertDontSee('First client input')
            ->assertDontSee('First client flash')
            ->assertSee('data-errors="0"', false);
    }

    public function test_cache_responses_are_never_browser_cacheable(): void
    {
        $this->get('/ar')
            ->assertHeader('X-Cache', 'MISS')
            ->assertHeader('Cache-Control', 'max-age=0, no-store, private');

        $this->get('/ar')
            ->assertHeader('X-Cache', 'HIT')
            ->assertHeader('Cache-Control', 'max-age=0, no-store, private');

        $this->get('/ar', ['Cache-Control' => 'no-cache'])
            ->assertHeader('X-Cache', 'BYPASS')
            ->assertHeader('Cache-Control', 'max-age=0, no-store, private');
    }

    public function test_authorization_and_no_cache_requests_bypass_the_internal_cache(): void
    {
        foreach ([
            ['Authorization' => 'Bearer test-token'],
            ['Pragma' => 'no-cache'],
        ] as $headers) {
            $this->get('/ar', $headers)
                ->assertHeader('X-Cache', 'BYPASS')
                ->assertHeader('Cache-Control', 'max-age=0, no-store, private');
        }
    }

    public function test_suggestions_and_complaints_get_requests_always_bypass_the_cache(): void
    {
        $this->get('/en/e-services/suggestions-complaints')
            ->assertNotFound()
            ->assertHeader('X-Cache', 'BYPASS')
            ->assertHeader('Cache-Control', 'max-age=0, no-store, private');
    }

    public function test_unknown_query_parameters_do_not_fragment_the_public_page_cache(): void
    {
        $this->get('/ar?utm_source=first-client')
            ->assertHeader('X-Cache', 'MISS');

        $this->get('/ar?utm_source=second-client')
            ->assertHeader('X-Cache', 'HIT');
    }

    public function test_supported_filters_are_retained_and_equivalent_page_values_are_canonicalized(): void
    {
        $this->get('/en/research/publications?q=cache-filter&page=01')
            ->assertOk()
            ->assertHeader('X-Cache', 'MISS');

        $this->get('/en/research/publications?q=cache-filter&page=1')
            ->assertOk()
            ->assertHeader('X-Cache', 'HIT');

        $this->get('/en/research/publications?q=other-filter&page=2')
            ->assertOk()
            ->assertHeader('X-Cache', 'MISS');
    }

    private function csrfTokenFrom(string $html): ?string
    {
        return preg_match('/<meta name="csrf-token" content="([^"]+)"/', $html, $m) === 1
            ? $m[1]
            : null;
    }
}
