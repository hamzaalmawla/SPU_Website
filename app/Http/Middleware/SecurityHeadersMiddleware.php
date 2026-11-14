<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Contracts\Analytics\AnalyticsServiceInterface;
use App\Support\AdminPath;
use App\Support\CspNonce;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class SecurityHeadersMiddleware
{
    /**
     * The one inline script the public site keeps, in the layout's <head>: it
     * marks the page as scripted before the first paint. The public policy
     * allows it by hash, so this must match the layout byte for byte.
     */
    public const JS_CLASS_BOOTSTRAP = "document.documentElement.classList.add('js');";

    public function __construct(
        private readonly AnalyticsServiceInterface $analyticsService,
        private readonly CspNonce $cspNonce,
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
        // microphone=(self): the AI assistant takes spoken questions on this
        // site's own pages (never in a frame from another origin). The
        // browser still asks the visitor before it records anything.
        // public/.htaccess sends the same value for static files.
        $response->headers->set('Permissions-Policy', 'camera=(), microphone=(self), geolocation=(), payment=(), usb=()');

        // Keeps pages this site opens from reaching back into it through
        // window.opener, while popups it opens (share dialogs) still work.
        $coop = trim((string) config('security.coop', 'same-origin-allow-popups'));
        if ($coop !== '') {
            $response->headers->set('Cross-Origin-Opener-Policy', $coop);
        }

        $response->headers->set('Content-Security-Policy', $this->contentSecurityPolicy($request, $response));

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

    private function contentSecurityPolicy(Request $request, Response $response): string
    {
        // A 404 under the admin prefix renders the public error page, so it
        // carries the public policy too; a different header there would mark
        // the prefix as special to anyone probing for the panel.
        if (AdminPath::matches($request) && $response->getStatusCode() !== 404) {
            return implode('; ', [
                "default-src 'self'",
                "base-uri 'self'",
                "object-src 'none'",
                "frame-ancestors 'self'",
                "form-action 'self'",
                // blob: for the upload fields' previews of images not yet saved.
                "img-src 'self' data: blob: https:",
                "font-src 'self' data: https:",
                "style-src 'self' 'unsafe-inline' https:",
                "script-src 'self' 'unsafe-inline' 'unsafe-eval'",
                // The upload fields read image previews in a blob: worker.
                "worker-src 'self' blob:",
                "frame-src 'self' https://www.google.com https://maps.google.com",
                "connect-src 'self' https:",
            ]);
        }

        // Analytics is not injected into the admin panel, so the admin policy
        // above is never widened for it. On the public site the directives
        // below gain the configured provider's origins — and only those
        // origins — when analytics is switched on. With analytics off (the
        // default) `analyticsSources()` is empty and the policy is byte-for-byte
        // the strict one: script-src 'self' plus the bootstrap's hash,
        // connect-src 'self'. Public views carry no other inline script or
        // on* attribute; their behaviour lives in resources/js.
        $analytics = $this->analyticsSources();

        // Fonts, stylesheets and images are all self-hosted, so none of
        // these directives admits other origins; a scanner rightly reads
        // `https:` as a wildcard (ZAP "CSP: Wildcard Directive"). A crawl of
        // 750 live pages in 2026-11 found no image from another site. If CMS
        // content ever needs one, list its origin in CSP_IMG_SOURCES rather
        // than widening this back to every https: host.
        //
        // Styles: inline <style> elements run only with this request's nonce
        // (style-src-elem), so an injected <style> is ignored. Inline style=""
        // attributes stay allowed (style-src-attr): templates and stored rich
        // text use them, and an attribute cannot load or run anything. The
        // plain style-src keeps 'unsafe-inline' only for browsers too old to
        // know the two split directives, which fall back to it.
        $nonce = "'nonce-".$this->cspNonce->value()."'";

        return implode('; ', [
            "default-src 'self'",
            "base-uri 'self'",
            "object-src 'none'",
            "frame-ancestors 'self'",
            "form-action 'self'",
            $this->directive('img-src', ["'self'", 'data:', ...$this->configuredSources('security.csp_img_sources')], $analytics),
            "font-src 'self' data:",
            "style-src 'self' 'unsafe-inline'",
            "style-src-elem 'self' ".$nonce,
            "style-src-attr 'unsafe-inline'",
            $this->directive('script-src', ["'self'", self::inlineScriptHash(self::JS_CLASS_BOOTSTRAP)], $analytics),
            "frame-src 'self' https://www.google.com https://maps.google.com",
            $this->directive('connect-src', ["'self'", ...$this->assistantConnectSources()], $analytics),
        ]);
    }

    /**
     * The AI assistant's live transcription: the visitor's browser streams
     * the microphone to OpenAI's Realtime WebSocket (a transcription-only
     * session with a key this site mints). Only that origin, only while the
     * feature is on.
     *
     * @return list<string>
     */
    private function assistantConnectSources(): array
    {
        if (! (bool) config('chatbot.enabled', true) || ! (bool) config('chatbot.voice.enabled', true) || ! (bool) config('chatbot.voice.live_transcription.enabled', true)) {
            return [];
        }

        $url = (string) config('chatbot.voice.live_transcription.url', '');
        $scheme = parse_url($url, PHP_URL_SCHEME);
        $host = parse_url($url, PHP_URL_HOST);

        return is_string($scheme) && is_string($host) && in_array($scheme, ['wss', 'https'], true) ? [$scheme.'://'.$host] : [];
    }

    /**
     * Extra origins from a space- or comma-separated config value, each one a
     * plain https origin (no wildcard scheme, no keyword).
     *
     * @return list<string>
     */
    private function configuredSources(string $key): array
    {
        $raw = config($key, []);
        $items = is_array($raw) ? $raw : preg_split('/[\s,]+/', (string) $raw, -1, PREG_SPLIT_NO_EMPTY);

        $sources = [];
        foreach ($items ?: [] as $item) {
            $item = rtrim(trim((string) $item), '/');
            if (preg_match('#^https://(\*\.)?[a-z0-9.-]+(:\d+)?$#i', $item) === 1) {
                $sources[] = strtolower($item);
            }
        }

        return array_values(array_unique($sources));
    }

    public static function inlineScriptHash(string $script): string
    {
        return "'sha256-".base64_encode(hash('sha256', $script, true))."'";
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
