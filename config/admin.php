<?php

declare(strict_types=1);

// Where the admin panel lives, and who may reach it.
//
// /admin is the first address every scanner tries, so the panel is not served
// there in production. ADMIN_PATH holds the real prefix, and every route the
// panel owns (Filament pages, login, 2FA challenge, logout, attachment
// downloads) is registered under it. Anything else - /admin included - is an
// ordinary unknown URL: the same public 404 any other mistyped address gets,
// so a probe cannot tell that a panel exists, let alone where.
//
// The value is a secret. It lives in .env only and must never be written into
// public markup, robots.txt, the sitemap or a redirect a guest can trigger.
// `php artisan launch:validate` fails in production while it is guessable.
$path = trim((string) env('ADMIN_PATH', ''), " \t\n\r\0\x0B/");

// ADMIN_ALLOWED_IPS is optional defence in depth on top of the hidden path:
// a comma-separated list of IPs or CIDR ranges. When set, requests to the
// panel from any other address get the same 404 as an unknown URL. Leave it
// empty when staff log in from changing addresses (home, mobile data).
$allowedIps = array_values(array_filter(array_map(
    static fn (string $ip): string => trim($ip),
    explode(',', (string) env('ADMIN_ALLOWED_IPS', '')),
)));

return [
    'path' => $path !== '' ? $path : 'admin',

    'allowed_ips' => $allowedIps,
];
