<?php

declare(strict_types=1);

$appEnvironment = (string) env('APP_ENV', 'production');
$defaultOrigin = $appEnvironment === 'production' ? 'https://spu.edu.sy' : 'http://localhost';
$canonicalUrl = rtrim((string) env('APP_CANONICAL_URL', env('APP_URL', $defaultOrigin)), '/');

return [
    'canonical_url' => $canonicalUrl,
    'canonical_host' => (string) (parse_url($canonicalUrl, PHP_URL_HOST) ?: 'spu.edu.sy'),
    'enforce_canonical_host' => (bool) env('ENFORCE_CANONICAL_HOST', $appEnvironment === 'production'),

    // cPanel's nginx-to-Apache hop is local. Never use "*" here: forwarded
    // headers from arbitrary internet clients must not become authoritative.
    'trusted_proxies' => ['127.0.0.1', '::1'],
];
