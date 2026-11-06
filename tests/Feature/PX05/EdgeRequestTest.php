<?php

declare(strict_types=1);

namespace Tests\Feature\PX05;

use App\Http\Middleware\SecurityHeadersMiddleware;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Symfony\Component\HttpFoundation\Response;
use Tests\TestCase;

final class EdgeRequestTest extends TestCase
{
    public function test_direct_front_controller_paths_are_not_public_routes(): void
    {
        config()->set('edge.enforce_canonical_host', false);

        $this->get('/app.php')->assertNotFound();
        $this->get('/app.php/anything')->assertNotFound();
        $this->get('/index.php/anything')->assertNotFound();
    }

    public function test_production_canonical_redirect_never_reflects_an_untrusted_host(): void
    {
        config()->set('edge.canonical_url', 'https://spu.edu.sy');
        config()->set('edge.enforce_canonical_host', true);

        $this->withServerVariables([
            'REMOTE_ADDR' => '203.0.113.10',
            'HTTP_HOST' => 'attacker.example',
        ])->withHeaders([
            'X-Forwarded-Host' => 'spu.edu.sy',
            'X-Forwarded-Proto' => 'https',
        ])->get('/ar/about?source=edge')
            ->assertRedirect('https://spu.edu.sy/ar/about?source=edge')
            ->assertStatus(301);
    }

    public function test_apache_https_redirect_uses_a_fixed_origin(): void
    {
        $rules = file_get_contents(public_path('.htaccess'));

        $this->assertIsString($rules);
        $this->assertStringContainsString('https://spu.edu.sy%{REQUEST_URI}', $rules);
        $this->assertStringNotContainsString('https://%{HTTP_HOST}', $rules);
    }

    public function test_apache_hardens_static_and_legacy_browser_active_files(): void
    {
        $rules = file_get_contents(public_path('.htaccess'));

        $this->assertIsString($rules);
        $this->assertStringContainsString('Header always set X-Content-Type-Options "nosniff"', $rules);
        $this->assertStringContainsString('Header always set Referrer-Policy "strict-origin-when-cross-origin"', $rules);
        $this->assertStringContainsString('(?:html?|xhtml|xml|svgz?)', $rules);
        $this->assertStringContainsString('(?:php[0-9]?|phtml|phar|cgi', $rules);
        $this->assertMatchesRegularExpression('/<FilesMatch "[^"]*\|txt\|[^"]*">\s*Header always set X-Content-Type-Options "nosniff"/', $rules);
    }

    /**
     * Apache sends HSTS on the responses it answers itself, and the value must
     * be the one the application sends, or browsers flip between two policies.
     */
    public function test_apache_hsts_matches_the_application_default(): void
    {
        $rules = file_get_contents(public_path('.htaccess'));

        $this->assertIsString($rules);
        $this->assertSame(1, preg_match('/Header always set Strict-Transport-Security "([^"]+)" "expr=([^"]+)"/', $rules, $match));

        config([
            'security.hsts_max_age' => 604800,
            'security.hsts_include_subdomains' => true,
            'security.hsts_preload' => false,
        ]);
        $this->app->detectEnvironment(static fn (): string => 'production');

        $response = $this->app->make(SecurityHeadersMiddleware::class)->handle(
            Request::create('https://spu.edu.sy/ar'),
            static fn (): Response => new HttpResponse('ok'),
        );

        $this->assertSame($response->headers->get('Strict-Transport-Security'), $match[1]);
        $this->assertStringContainsString("-z resp('Strict-Transport-Security')", $match[2]);
    }
}
