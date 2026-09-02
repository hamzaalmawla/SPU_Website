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

    // Compress even when the request carries no Accept-Encoding at all. A proxy
    // may be stripping it, leaving the application unable to know what the
    // client supports. Not compliant, so it is off until someone confirms the
    // header genuinely never arrives - if pages compress with this off, it does.
    'compress_without_accept_encoding' => (bool) env('COMPRESS_WITHOUT_ACCEPT_ENCODING', false),

    // Emits X-Compress-Debug reporting what PHP actually receives. Enable for a
    // single deploy to settle whether Accept-Encoding survives the proxy, read
    // it with one `curl -I`, then turn it off. It exposes no secrets, but it is
    // diagnostics and does not belong on a public site permanently.
    'compression_diagnostics' => (bool) env('COMPRESSION_DIAGNOSTICS', true),

    // Runs inside the page cache, so it shrinks the stored body rather than the
    // wire - cache files on disk, and the string CSRF substitution runs over on
    // every hit. compress_responses above handles the wire. Safe to turn off
    // when debugging rendered markup.
    'minify_html' => (bool) env('MINIFY_HTML', true),

    // cPanel's nginx-to-Apache hop is local. Never use "*" here: forwarded
    // headers from arbitrary internet clients must not become authoritative.
    'trusted_proxies' => ['127.0.0.1', '::1'],
];
