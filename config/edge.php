<?php

declare(strict_types=1);

$appEnvironment = (string) env('APP_ENV', 'production');
$defaultOrigin = $appEnvironment === 'production' ? 'https://spu.edu.sy' : 'http://localhost';
$canonicalUrl = rtrim((string) env('APP_CANONICAL_URL', env('APP_URL', $defaultOrigin)), '/');

return [
    'canonical_url' => $canonicalUrl,
    'canonical_host' => (string) (parse_url($canonicalUrl, PHP_URL_HOST) ?: 'spu.edu.sy'),
    'enforce_canonical_host' => (bool) env('ENFORCE_CANONICAL_HOST', $appEnvironment === 'production'),

    // Nothing at the edge compresses, and this origin degrades sharply above a
    // ~24KB response (Docs/PERFORMANCE_MEASUREMENT.md). gzip puts every page back
    // under that threshold, so this is a usability fix rather than a tuning knob.
    // Turn it off the day the edge compresses properly, and delete the
    // middleware with it - two compressors is worse than none.
    'compress_responses' => (bool) env('COMPRESS_RESPONSES', true),
    'compression_level' => (int) env('COMPRESSION_LEVEL', 6),

    // Compress even when the request carries no Accept-Encoding at all.
    //
    // Measured on this host, 2 September: a request sending
    // `Accept-Encoding: gzip, deflate, br` reaches PHP with the header ABSENT.
    // nginx strips it entirely rather than rewriting it, so the application
    // cannot negotiate and every response was going out uncompressed on an
    // origin that degrades sharply above ~24KB.
    //
    // Compressing without negotiation is not compliant, and it is enabled here
    // anyway with that understood. The only clients it can hurt are ones that
    // deliberately send `Accept-Encoding: identity`, which are effectively
    // limited to monitoring tools; every browser has accepted gzip for
    // twenty-five years. The alternative is a site that is slower than the
    // twenty-year-old one it replaces.
    //
    // Self-correcting: the moment the header does arrive, the normal negotiated
    // path takes over and this is never consulted. Responses say which happened
    // - X-Compressed: forced or negotiated.
    //
    // Defaulted per environment rather than globally, because the reason for it
    // is one specific proxy in front of one specific host. Everywhere else -
    // local, CI, the test suite - Accept-Encoding behaves normally and there is
    // nothing to work around; forcing there would only mean every test asserting
    // on response text was reading gzip.
    'compress_without_accept_encoding' => (bool) env(
        'COMPRESS_WITHOUT_ACCEPT_ENCODING',
        $appEnvironment === 'production',
    ),

    // Emits X-Compress-Debug reporting what PHP actually receives. Enable for a
    // single deploy to settle whether Accept-Encoding survives the proxy, read
    // it with one `curl -I`, then turn it off. It exposes no secrets, but it is
    // diagnostics and does not belong on a public site permanently.
    'compression_diagnostics' => (bool) env('COMPRESSION_DIAGNOSTICS', false),

    // Runs inside the page cache, so it shrinks the stored body rather than the
    // wire - cache files on disk, and the string CSRF substitution runs over on
    // every hit. compress_responses above handles the wire. Safe to turn off
    // when debugging rendered markup.
    'minify_html' => (bool) env('MINIFY_HTML', true),

    // cPanel's nginx-to-Apache hop is local. Never use "*" here: forwarded
    // headers from arbitrary internet clients must not become authoritative.
    'trusted_proxies' => ['127.0.0.1', '::1'],
];
