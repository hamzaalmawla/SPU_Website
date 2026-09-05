<?php

declare(strict_types=1);

$defaultTrustedPortalHosts = 'my.spu.edu.sy';

// env() falls back to its default only when the key is ABSENT. A key that is
// present but empty - a bare `TRUSTED_PORTAL_HOSTS=` line, or one holding only
// whitespace - returns '', which explodes to [''] and filters down to an EMPTY
// allow-list. An empty allow-list rejects every absolute portal URL, so
// getStudentPortalUrl() returns null and /{locale}/campus-life/transport/registration
// serves a hard 503 whose branded view reads "Under maintenance" - with nothing
// logged and nothing visibly wrong in the admin panel.
//
// A blank value is never a deliberate "trust nothing"; it is an unfinished .env.
// Treat it as unset so a misconfiguration degrades to the documented default
// instead of silently taking a public route offline.
$configuredTrustedPortalHosts = trim((string) env('TRUSTED_PORTAL_HOSTS', ''));

if ($configuredTrustedPortalHosts === '') {
    $configuredTrustedPortalHosts = $defaultTrustedPortalHosts;
}

return [
    'trusted_portal_hosts' => array_values(array_unique(array_filter(array_map(
        static fn (string $host): string => strtolower(trim($host)),
        explode(',', $configuredTrustedPortalHosts),
    )))),

    // HSTS. Both values matter most on the day the apex domain starts serving
    // this application, which is why neither is hard-coded any more.
    //
    // `includeSubDomains` from spu.edu.sy binds webmail, rooms and every other
    // subdomain to HTTPS for the whole max-age, in every browser that has
    // loaded the homepage once. Every certificate on the account currently
    // expires on the same day, so a failed renewal after cutover is not a
    // warning page the user can click through - it is total inaccessibility
    // across every subdomain, for as long as max-age says.
    //
    // The default is therefore a week rather than a year: long enough to be a
    // real policy, short enough that a mistake ages out. Raise it once the
    // domain has run clean and renewal has been observed to work.
    'hsts_max_age' => (int) env('HSTS_MAX_AGE', 604800),

    // Preload is close to irreversible: removal requires a request to the
    // browser vendors' list and ships on their release schedule, not yours.
    // Off by default; turn it on deliberately, months after cutover, never as
    // part of one.
    'hsts_preload' => (bool) env('HSTS_PRELOAD', false),

    'hsts_include_subdomains' => (bool) env('HSTS_INCLUDE_SUBDOMAINS', true),
];
