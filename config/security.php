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
];
