<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| First-party visitor statistics (admin dashboard)
|--------------------------------------------------------------------------
|
| Each public page sends one small request after it loads (resources/js/
| public/siteStats.js) to public/stats-hit.php, which does not boot Laravel: it
| appends one line to this minute's queue file. `site-stats:collect` writes the
| queue into daily totals every minute. No cookie is set and no IP address is
| stored: a visitor is a hash of the day, IP address and browser keyed with a
| random per-day key that is deleted after two days, so the same person counts
| once a day and cannot be followed from one day to the next.
|
| "Today" starts at midnight in Asia/Damascus; the collector script names days
| and queue files in that timezone too, so it is not configurable here.
|
*/

return [
    // Off: pages stop sending (no request at all) and queued views are discarded.
    'enabled' => (bool) env('SITE_STATS_ENABLED', true),

    'timezone' => 'Asia/Damascus',

    // Queue files and day keys; public/stats-hit.php writes to <app>/storage/app/site-stats.
    'directory' => storage_path('app/site-stats'),

    'retention' => [
        // Visitor hashes are only needed to count a visitor once a day.
        'visitor_days' => 2,
        // Per-page, referring site and search term counts.
        'detail_days' => 180,
    ],

    // A visitor seen within this many minutes counts as "on the site now".
    'online_minutes' => 5,
];
