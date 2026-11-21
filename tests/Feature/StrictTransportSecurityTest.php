<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Http\Middleware\SecurityHeadersMiddleware;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\HttpFoundation\Response;
use Tests\TestCase;

/**
 * HSTS is the one header on this application that cannot be taken back.
 *
 * `includeSubDomains` served from the apex binds webmail, rooms and every
 * other SPU subdomain to HTTPS for the whole max-age in any browser that has
 * loaded the site once, and `preload` is removed by petitioning the browser
 * vendors rather than by deploying. Both were hard-coded at a year until the
 * cutover audit; these tests exist so the safe default cannot drift back
 * silently.
 */
final class StrictTransportSecurityTest extends TestCase
{
    private function headerFor(array $config, bool $secure = true, string $environment = 'production'): ?string
    {
        config($config);
        $this->app->detectEnvironment(static fn (): string => $environment);

        $middleware = $this->app->make(SecurityHeadersMiddleware::class);
        $request = Request::create($secure ? 'https://spu.edu.sy/ar' : 'http://spu.edu.sy/ar');

        $response = $middleware->handle(
            $request,
            static fn (): Response => new HttpResponse('ok'),
        );

        return $response->headers->get('Strict-Transport-Security');
    }

    #[Test]
    public function it_defaults_to_a_short_max_age_without_preload(): void
    {
        $header = $this->headerFor([
            'security.hsts_max_age' => 604800,
            'security.hsts_preload' => false,
            'security.hsts_include_subdomains' => true,
        ]);

        $this->assertSame('max-age=604800; includeSubDomains', $header);
        $this->assertStringNotContainsString('preload', (string) $header);
    }

    #[Test]
    public function it_refuses_to_advertise_preload_below_the_one_year_minimum(): void
    {
        // Browsers reject a preload submission under a year, so emitting the
        // token at a shorter max-age advertises a policy that cannot be
        // honoured. The shorter max-age is treated as the real intent.
        $header = $this->headerFor([
            'security.hsts_max_age' => 604800,
            'security.hsts_preload' => true,
            'security.hsts_include_subdomains' => true,
        ]);

        $this->assertStringNotContainsString('preload', (string) $header);
    }

    #[Test]
    public function it_emits_preload_only_when_deliberately_configured_at_a_year(): void
    {
        $header = $this->headerFor([
            'security.hsts_max_age' => 31536000,
            'security.hsts_preload' => true,
            'security.hsts_include_subdomains' => true,
        ]);

        $this->assertSame('max-age=31536000; includeSubDomains; preload', $header);
    }

    #[Test]
    public function it_can_scope_the_policy_to_the_apex_only(): void
    {
        $header = $this->headerFor([
            'security.hsts_max_age' => 604800,
            'security.hsts_preload' => false,
            'security.hsts_include_subdomains' => false,
        ]);

        $this->assertSame('max-age=604800', $header);
    }

    #[Test]
    public function it_is_not_sent_over_plain_http(): void
    {
        // An HSTS header on an insecure response is ignored by browsers, and
        // sending one would mask a misconfigured origin during cutover.
        $this->assertNull($this->headerFor([
            'security.hsts_max_age' => 604800,
        ], secure: false));
    }

    #[Test]
    public function it_is_not_sent_outside_production(): void
    {
        $this->assertNull($this->headerFor([
            'security.hsts_max_age' => 604800,
        ], environment: 'staging'));
    }
}
