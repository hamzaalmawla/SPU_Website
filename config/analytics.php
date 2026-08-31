<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Analytics
|--------------------------------------------------------------------------
|
| Off by default. Analytics only switches on when a provider AND a valid
| measurement ID are configured, and then only in production unless
| ANALYTICS_ENABLE_NON_PRODUCTION is explicitly set.
|
| Everything below is resolved once at config load (and baked by
| `config:cache`), so enabling analytics costs zero database queries and zero
| extra work per request — important on a 5-worker, no-OPcache host.
|
| The `csp` block is the single source of truth shared by
| SecurityHeadersMiddleware and the injected <script> tag. Because it is
| derived from `enabled`, the strict default policy comes back automatically
| the moment analytics is switched off — the CSP can never drift open.
|
*/

$provider = strtolower(trim((string) env('ANALYTICS_PROVIDER', '')));
$measurementId = trim((string) env('ANALYTICS_GA4_MEASUREMENT_ID', ''));
$allowNonProduction = filter_var(env('ANALYTICS_ENABLE_NON_PRODUCTION', false), FILTER_VALIDATE_BOOLEAN);
$isProduction = (string) env('APP_ENV', 'production') === 'production';

$enabled = $provider === 'ga4'
    && preg_match('/^G-[A-Z0-9]{4,20}$/i', $measurementId) === 1
    && ($isProduction || $allowNonProduction);

return [

    /*
    | Active provider, or null when analytics is off. Only 'ga4' is supported.
    */
    'provider' => $enabled ? $provider : null,

    /*
    | Master switch consulted by the middleware and the layout.
    */
    'enabled' => $enabled,

    'measurement_id' => $enabled ? $measurementId : null,

    /*
    | Loader script for the active provider.
    */
    'script_url' => $enabled
        ? 'https://www.googletagmanager.com/gtag/js?id='.$measurementId
        : null,

    /*
    | Privacy posture. Google Signals and ad personalisation are refused so no
    | advertising profile is built from university visitors, and the cookie is
    | scoped tightly. anonymize_ip is GA4's default but is set explicitly so
    | the intent survives a provider default change.
    */
    'options' => [
        'anonymize_ip' => true,
        'allow_google_signals' => false,
        'allow_ad_personalization_signals' => false,
        'cookie_flags' => 'SameSite=Lax;Secure',
    ],

    /*
    | Content-Security-Policy origins required by the active provider.
    |
    | Empty when analytics is off, which leaves the public policy exactly as
    | strict as it is today: script-src 'self' 'unsafe-inline', connect-src
    | 'self'. img-src already permits https: so the GA measurement pixel needs
    | no directive of its own.
    |
    | script-src  — gtag.js is served from googletagmanager.com.
    | connect-src — GA4 beacons POST to the google-analytics /
    |               analytics.google.com regional collection endpoints, and
    |               gtag.js fetches its container config from googletagmanager.
    */
    'csp' => [
        'script-src' => $enabled ? [
            'https://www.googletagmanager.com',
        ] : [],
        'connect-src' => $enabled ? [
            'https://www.googletagmanager.com',
            'https://www.google-analytics.com',
            'https://*.google-analytics.com',
            // A CSP wildcard requires at least one label, so *.analytics.google.com
            // does not match the bare host that consent mode posts to.
            'https://analytics.google.com',
            'https://*.analytics.google.com',
        ] : [],
    ],

];
