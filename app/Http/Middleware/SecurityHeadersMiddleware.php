<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Contracts\Analytics\AnalyticsServiceInterface;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class SecurityHeadersMiddleware
{
    public function __construct(
        private readonly AnalyticsServiceInterface $analyticsService,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $response->headers->set('X-Content-Type-Options', 'nosniff');

        // strict-origin-when-cross-origin, not no-referrer.
        //
        // `no-referrer` stripped the Referer header from every outbound
        // navigation, which destroyed referral attribution: analytics saw all
        // inbound traffic as "direct", and other sites linking to spu.edu.sy
        // could not be credited. This value still never leaks a path or query
        // string to a third party — cross-origin requests send only the bare
        // origin (https://spu.edu.sy), and nothing at all is sent when
        // downgrading HTTPS to HTTP. Same-origin requests keep the full URL,
        // which is what makes internal funnel analysis work.
        //
        // public/.htaccess sets the same value for physical static and
        // rewritten legacy files, which never reach this middleware. Keep the
        // two in step.
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');

        $response->headers->set('X-Frame-Options', 'SAMEORIGIN');
        $response->headers->set('Permissions-Policy', 'camera=(), microphone=(), geolocation=(), payment=(), usb=()');
        $response->headers->set('Content-Security-Policy', $this->contentSecurityPolicy($request));

        if ($request->is('*/preview')) {
            $response->headers->set('Cache-Control', 'private, no-store, max-age=0');
            $response->headers->set('Pragma', 'no-cache');
            $response->headers->set('X-Robots-Tag', 'noindex, nofollow, noarchive');
        }

        if (app()->environment('production') && $request->isSecure()) {
            $response->headers->set('Strict-Transport-Security', $this->strictTransportSecurity());
        }

        return $response;
    }

    /**
     * Built from config rather than hard-coded because the safe value differs
     * either side of the cutover: a year with `preload` is a reasonable steady
     * state and a poor thing to switch on the day a domain changes hands. See
     * config/security.php for what each part costs to undo.
     */
    private function strictTransportSecurity(): string
    {
        $maxAge = max(0, (int) config('security.hsts_max_age', 604800));
        $directives = ['max-age='.$maxAge];

        if ((bool) config('security.hsts_include_subdomains', true)) {
            $directives[] = 'includeSubDomains';
        }

        // Browsers only accept a preload submission at a year or more, so
        // emitting the token below that advertises a policy that cannot be
        // honoured. Treat the shorter max-age as the intent and drop it.
        if ((bool) config('security.hsts_preload', false) && $maxAge >= 31536000) {
            $directives[] = 'preload';
        }

        return implode('; ', $directives);
    }

    private function contentSecurityPolicy(Request $request): string
    {
        if ($request->is('admin') || $request->is('admin/*')) {
            return implode('; ', [
                "default-src 'self'",
                "base-uri 'self'",
                "object-src 'none'",
                "frame-ancestors 'self'",
                "form-action 'self'",
                "img-src 'self' data: https:",
                "font-src 'self' data: https:",
                "style-src 'self' 'unsafe-inline' https:",
                "script-src 'self' 'unsafe-inline' 'unsafe-eval'",
                "frame-src 'self' https://www.google.com https://maps.google.com",
                "connect-src 'self' https:",
            ]);
        }

        // Analytics is not injected into the admin panel, so the admin policy
        // above is never widened for it. On the public site the directives
        // below gain the configured provider's origins — and only those
        // origins — when analytics is switched on. With analytics off (the
        // default) `analyticsSources()` is empty and the policy is byte-for-byte
        // the strict one: script-src 'self' 'unsafe-inline', connect-src 'self'.
        $analytics = $this->analyticsSources();

        return implode('; ', [
            "default-src 'self'",
            "base-uri 'self'",
            "object-src 'none'",
            "frame-ancestors 'self'",
            "form-action 'self'",
            "img-src 'self' data: https:",
            "font-src 'self' data: https:",
            "style-src 'self' 'unsafe-inline' https:",
            $this->directive('script-src', ["'self'", "'unsafe-inline'"], $analytics),
            "frame-src 'self' https://www.google.com https://maps.google.com",
            $this->directive('connect-src', ["'self'"], $analytics),
        ]);
    }

    /**
     * Build one directive from its baseline sources plus any analytics origins.
     *
     * @param  list<string>  $baseline
     * @param  array<string, list<string>>  $analytics
     */
    private function directive(string $name, array $baseline, array $analytics): string
    {
        $sources = array_values(array_unique(array_merge($baseline, $analytics[$name] ?? [])));

        return $name.' '.implode(' ', $sources);
    }

    /**
     * @return array<string, list<string>>
     */
    private function analyticsSources(): array
    {
        // Never let an analytics misconfiguration break the security headers.
        try {
            return $this->analyticsService->contentSecurityPolicySources();
        } catch (\Throwable) {
            return [];
        }
    }
}
