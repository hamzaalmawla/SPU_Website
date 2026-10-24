<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Contracts\Analytics\AnalyticsServiceInterface;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

/**
 * Analytics must be off by default, and when it is on the Content-Security-Policy
 * must actually permit the script it injects.
 *
 * The site sends a strict CSP (script-src 'self' 'unsafe-inline', connect-src
 * 'self'). A third-party tag added without widening those directives would be
 * silently blocked by the browser, so the two are asserted together here.
 */
final class AnalyticsInjectionTest extends TestCase
{
    use RefreshDatabase;

    private const MEASUREMENT_ID = 'G-TESTID1234';

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);
        Cache::flush();
    }

    public function test_analytics_is_off_by_default(): void
    {
        $this->assertFalse(config('analytics.enabled'), 'Analytics must ship disabled.');
        $this->assertNull(app(AnalyticsServiceInterface::class)->snippet());
        $this->assertSame([], app(AnalyticsServiceInterface::class)->contentSecurityPolicySources());
    }

    public function test_no_analytics_script_is_rendered_when_unconfigured(): void
    {
        $content = $this->get('/ar')->assertOk()->getContent() ?: '';

        $this->assertStringNotContainsString('googletagmanager.com', $content);
        $this->assertStringNotContainsString('gtag(', $content);
    }

    public function test_strict_policy_is_unchanged_when_analytics_is_off(): void
    {
        $policy = $this->publicPolicy();

        $this->assertStringContainsString("script-src 'self' 'unsafe-inline'", $policy);
        $this->assertStringContainsString("connect-src 'self'", $policy);
        $this->assertStringNotContainsString('googletagmanager', $policy);
        $this->assertStringNotContainsString('google-analytics', $policy);
        // The public policy must never fall back to a wildcard script source.
        $this->assertStringNotContainsString("script-src 'self' 'unsafe-inline' https:", $policy);
        $this->assertStringNotContainsString("'unsafe-eval'", $policy);
    }

    public function test_script_is_injected_when_configured(): void
    {
        $this->enableAnalytics();

        $content = $this->get('/ar')->assertOk()->getContent() ?: '';

        $this->assertStringContainsString('https://www.googletagmanager.com/gtag/js?id='.self::MEASUREMENT_ID, $content);
        $this->assertStringContainsString('gtag(', $content);
        $this->assertStringContainsString(self::MEASUREMENT_ID, $content);
    }

    /**
     * The consistency guard: every origin the injected markup contacts must be
     * permitted by the policy that ships with the same response.
     */
    public function test_policy_permits_every_origin_the_injected_script_uses(): void
    {
        $this->enableAnalytics();

        $response = $this->get('/ar')->assertOk();
        $policy = (string) $response->headers->get('Content-Security-Policy');
        $content = $response->getContent() ?: '';

        $scriptSrc = $this->directive($policy, 'script-src');
        $connectSrc = $this->directive($policy, 'connect-src');

        // The loader the page actually requests.
        $this->assertStringContainsString('https://www.googletagmanager.com', $scriptSrc);
        preg_match('#src="(https://[^"]+)"#', $content, $matches);
        $this->assertNotEmpty($matches, 'Expected an external analytics script tag.');
        $origin = (string) parse_url($matches[1], PHP_URL_SCHEME).'://'.(string) parse_url($matches[1], PHP_URL_HOST);
        $this->assertStringContainsString($origin, $scriptSrc, 'CSP blocks the script the page loads.');

        // The beacon endpoints gtag.js posts to.
        $this->assertStringContainsString('https://www.google-analytics.com', $connectSrc);
        $this->assertStringContainsString('https://*.analytics.google.com', $connectSrc);

        // The inline gtag bootstrap relies on the pre-existing 'unsafe-inline'.
        $this->assertStringContainsString("'unsafe-inline'", $scriptSrc);

        // Baseline sources must survive the merge.
        $this->assertStringContainsString("'self'", $scriptSrc);
        $this->assertStringContainsString("'self'", $connectSrc);

        // Every configured origin, and nothing beyond it.
        foreach (config('analytics.csp.script-src') as $expected) {
            $this->assertStringContainsString($expected, $scriptSrc);
        }

        foreach (config('analytics.csp.connect-src') as $expected) {
            $this->assertStringContainsString($expected, $connectSrc);
        }
    }

    public function test_admin_policy_is_never_widened_for_analytics(): void
    {
        $this->enableAnalytics();

        $policy = (string) $this->get('/admin/login')->headers->get('Content-Security-Policy');

        $this->assertStringNotContainsString('googletagmanager', $policy);
        $this->assertStringNotContainsString('google-analytics', $policy);
    }

    public function test_analytics_is_never_injected_into_preview(): void
    {
        $this->enableAnalytics();

        $this->assertNull(
            app(AnalyticsServiceInterface::class)->snippet(true),
            'Editor preview traffic must not be measured.'
        );
    }

    public function test_analytics_stays_off_without_a_measurement_id(): void
    {
        config()->set('analytics.enabled', false);
        config()->set('analytics.provider', null);
        config()->set('analytics.measurement_id', null);
        config()->set('analytics.script_url', null);
        config()->set('analytics.csp', ['script-src' => [], 'connect-src' => []]);

        $this->assertNull(app(AnalyticsServiceInterface::class)->snippet());
        $this->assertStringNotContainsString('googletagmanager', $this->publicPolicy());
    }

    public function test_referrer_policy_preserves_attribution(): void
    {
        // no-referrer would make every inbound visit look "direct".
        $this->get('/ar')
            ->assertOk()
            ->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
    }

    private function enableAnalytics(): void
    {
        config()->set('analytics.enabled', true);
        config()->set('analytics.provider', 'ga4');
        config()->set('analytics.measurement_id', self::MEASUREMENT_ID);
        config()->set('analytics.script_url', 'https://www.googletagmanager.com/gtag/js?id='.self::MEASUREMENT_ID);
        config()->set('analytics.csp', [
            'script-src' => ['https://www.googletagmanager.com'],
            'connect-src' => [
                'https://www.googletagmanager.com',
                'https://www.google-analytics.com',
                'https://*.google-analytics.com',
                'https://*.analytics.google.com',
            ],
        ]);

        Cache::flush();
    }

    private function publicPolicy(): string
    {
        return (string) $this->get('/ar')->headers->get('Content-Security-Policy');
    }

    private function directive(string $policy, string $name): string
    {
        foreach (explode(';', $policy) as $part) {
            $part = trim($part);

            if (str_starts_with($part, $name.' ')) {
                return $part;
            }
        }

        return '';
    }
}
