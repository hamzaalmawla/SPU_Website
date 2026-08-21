<?php

declare(strict_types=1);

namespace Tests\Feature;

use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
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

    private function csrfTokenFrom(string $html): ?string
    {
        return preg_match('/<meta name="csrf-token" content="([^"]+)"/', $html, $m) === 1
            ? $m[1]
            : null;
    }
}
