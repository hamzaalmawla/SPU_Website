<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Contracts\ErrorPage\ErrorPageServiceInterface;
use App\Contracts\Navigation\NavigationServiceInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\View;
use Mockery;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\TestCase;

/**
 * Error pages must survive the failure that produced them.
 *
 * The 500/503 views are asserted to render with no database connection at all,
 * and the full-layout views are asserted to degrade rather than cascade when
 * the services behind the public shell throw.
 */
final class ErrorPageRenderingTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array<string, array{int}>
     */
    public static function statusProvider(): array
    {
        return [
            '403' => [403],
            '404' => [404],
            '419' => [419],
            '429' => [429],
            '500' => [500],
            '503' => [503],
        ];
    }

    #[DataProvider('statusProvider')]
    public function test_error_view_renders_standalone_in_arabic(int $status): void
    {
        $html = $this->renderErrorView($status, '/ar/does-not-exist');

        $this->assertStringContainsString('<html lang="ar" dir="rtl">', $html);
        $this->assertStringContainsString((string) $status, $html);
        $this->assertStringContainsString('lang="en" dir="ltr"', $html, 'Error pages must stay bilingual.');
    }

    #[DataProvider('statusProvider')]
    public function test_error_view_renders_standalone_in_english(int $status): void
    {
        $html = $this->renderErrorView($status, '/en/does-not-exist');

        $this->assertStringContainsString('<html lang="en" dir="ltr">', $html);
        $this->assertStringContainsString((string) $status, $html);
        $this->assertStringContainsString('lang="ar" dir="rtl"', $html, 'Error pages must stay bilingual.');
    }

    #[DataProvider('statusProvider')]
    public function test_error_view_is_self_contained(int $status): void
    {
        $html = $this->renderErrorView($status, '/ar/does-not-exist');

        // No Vite bundle: the asset build may be missing when this renders.
        $this->assertStringNotContainsString('/build/assets/', $html);
        $this->assertStringNotContainsString('@vite', $html);
        // Critical CSS must be inline so the page is legible with no stylesheet.
        $this->assertStringContainsString('<style>', $html);
        $this->assertStringNotContainsString('<link rel="stylesheet"', $html);
        // Branding survives a missing logo asset.
        $this->assertStringContainsString('<svg', $html);
    }

    /**
     * The whole point of the standalone views: a 500 caused by the database
     * being unreachable must not turn into a white screen or an error loop.
     */
    public function test_server_error_views_render_with_the_database_unavailable(): void
    {
        DB::shouldReceive('connection')->andThrow(new RuntimeException('Connection refused'));

        foreach ([500, 503] as $status) {
            $html = $this->renderErrorView($status, '/ar');

            $this->assertStringContainsString('<html lang="ar" dir="rtl">', $html);
            $this->assertStringContainsString((string) $status, $html);
        }
    }

    public function test_locale_falls_back_to_accept_language_then_arabic(): void
    {
        $service = app(ErrorPageServiceInterface::class);

        $this->assertSame('en', $service->content(404, 'en/news', null)->locale, 'Path segment wins.');
        $this->assertSame('en', $service->content(404, 'legacy/page.php', 'en-GB,en;q=0.9')->locale);
        $this->assertSame('ar', $service->content(404, 'legacy/page.php', 'fr-FR,fr;q=0.9')->locale);
        $this->assertSame('ar', $service->content(404, 'legacy/page.php', null)->locale);
    }

    public function test_json_requests_receive_json_not_html(): void
    {
        $response = $this->getJson('/ar/definitely-not-a-real-page');

        $response->assertNotFound();
        $this->assertStringContainsString('application/json', (string) $response->headers->get('Content-Type'));
        $this->assertStringNotContainsString('<html', $response->getContent() ?: '');
    }

    public function test_html_requests_receive_the_branded_error_page(): void
    {
        $response = $this->get('/ar/definitely-not-a-real-page');

        $response->assertNotFound();
        $content = $response->getContent() ?: '';

        $this->assertStringContainsString('spu-error', $content);
        $this->assertStringContainsString('الصفحة غير موجودة', $content);
        $this->assertStringContainsString('Page not found', $content);

        // The full public shell, not the standalone fallback: navigation is
        // present and the shared critical CSS rode in on the styles stack.
        $this->assertStringContainsString('id="main-content"', $content);
        $this->assertStringContainsString('.spu-error__code', $content);
        // Real routes back into the site.
        $this->assertStringContainsString('/ar/news', $content);
    }

    /**
     * A failure inside the richer error page must degrade to the standalone
     * shell, never cascade into a second exception.
     */
    public function test_full_layout_failure_degrades_to_the_standalone_view(): void
    {
        $navigation = Mockery::mock(NavigationServiceInterface::class);
        $navigation->shouldReceive('getFullNavigationPayload')
            ->andThrow(new RuntimeException('Navigation cache unavailable'));
        $this->app->instance(NavigationServiceInterface::class, $navigation);

        $response = $this->get('/ar/definitely-not-a-real-page');

        $response->assertNotFound();
        $content = $response->getContent() ?: '';

        $this->assertStringContainsString('spu-error', $content);
        $this->assertStringContainsString('<html lang="ar" dir="rtl">', $content);
    }

    public function test_search_link_is_omitted_until_a_search_route_exists(): void
    {
        $content = app(ErrorPageServiceInterface::class)->content(404, '/ar/missing');

        if (Route::has('public.search') || Route::has('public.search.index') || Route::has('search')) {
            $this->assertNotNull($content->searchUrl);
            $this->assertStringContainsString('?q=', (string) $content->searchUrl);

            return;
        }

        $this->assertNull($content->searchUrl, 'Search must be guarded by Route::has().');
    }

    public function test_application_error_pages_offer_real_routes_back_into_the_site(): void
    {
        $content = app(ErrorPageServiceInterface::class)->content(404, '/ar/missing');

        $this->assertNotEmpty($content->links);

        foreach ($content->links as $link) {
            $this->assertNotSame('', $link->url);
        }
    }

    public function test_server_error_pages_do_not_advertise_deep_links(): void
    {
        // During an outage every deep link would lead to another 500.
        $this->assertSame([], app(ErrorPageServiceInterface::class)->content(500, '/ar')->links);
        $this->assertSame([], app(ErrorPageServiceInterface::class)->content(503, '/ar')->links);
    }

    private function renderErrorView(int $status, string $path): string
    {
        $this->app['request']->server->set('REQUEST_URI', $path);

        $content = app(ErrorPageServiceInterface::class)->content(
            $status,
            $path,
            $path === '/en/does-not-exist' ? 'en' : null,
        );

        return View::make('errors.'.$status, ['error' => $content])->render();
    }

    protected function tearDown(): void
    {
        Mockery::close();

        parent::tearDown();
    }
}
