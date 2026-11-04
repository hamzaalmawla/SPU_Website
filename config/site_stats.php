<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| First-party visitor statistics (admin dashboard)
|--------------------------------------------------------------------------
|
| Each public page sends one small request after it loads (resources/js/
| siteStats.js). The server counts it without cookies and without storing an
| IP address: a visitor is a keyed hash of the day, IP address and browser, so
| the same person counts once a day and cannot be followed from one day to the
| next. Only daily totals are kept; the per-visitor hashes are deleted after
| `visitor_days` days. Separate from the optional GA4 provider (config/analytics.php).
|
*/

return [
    // Turns recording off (the dashboard then shows what was recorded before).
    'enabled' => (bool) env('SITE_STATS_ENABLED', true),

    // "Today" on the dashboard starts at midnight in this timezone.
    'timezone' => env('SITE_STATS_TIMEZONE', env('ADMIN_TIMEZONE', 'Asia/Damascus')),

    'retention' => [
        // Visitor hashes are only needed to count a visitor once a day.
        'visitor_days' => 2,
        // Per-page, referring site and search term counts.
        'detail_days' => 180,
    ],

    // A visitor seen within this many minutes counts as "on the site now".
    'online_minutes' => 5,
];
