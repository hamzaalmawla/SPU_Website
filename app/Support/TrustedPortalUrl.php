<?php

declare(strict_types=1);

namespace App\Support;

/**
 * The portal-link policy, in one place.
 *
 * A portal setting is accepted only as a site-relative path, or as an https URL
 * whose host is listed in security.trusted_portal_hosts. Anything else resolves
 * to null - and a null portal URL is precisely what makes
 * /{locale}/campus-life/transport/registration abort(503).
 *
 * This logic used to live privately inside SettingsService, which meant the admin
 * form that WRITES the setting had no way to ask whether a value would later be
 * accepted. The form saved anything shaped like a URL, the public route rejected
 * it, and the result was a hard 503 on a live route with no error surfaced
 * anywhere an editor could see. Both sides now share this class, so what the form
 * accepts and what the resolver accepts cannot drift apart again.
 */
final class TrustedPortalUrl
{
    /**
     * Resolve a stored setting value to a safe portal URL, or null if the policy
     * rejects it. Behaviourally identical to the original private implementation.
     */
    public static function sanitize(string $value): ?string
    {
        if (str_starts_with($value, '/') && ! str_starts_with($value, '//')) {
            return UrlSanitizer::sanitize($value);
        }

        $url = UrlSanitizer::sanitize($value, ['https'], true);

        if ($url === null) {
            return null;
        }

        $parts = parse_url($url);
        $host = is_array($parts) && is_string($parts['host'] ?? null)
            ? strtolower($parts['host'])
            : null;

        if ($host === null
            || isset($parts['user'], $parts['pass'])
            || ! in_array($host, self::trustedHosts(), true)
        ) {
            return null;
        }

        return $url;
    }

    /**
     * Would this value survive sanitize()? An empty value is acceptable and means
     * "no portal configured" - the route then 503s deliberately rather than
     * redirecting somewhere unintended.
     */
    public static function isAcceptable(?string $value): bool
    {
        if ($value === null || trim($value) === '') {
            return true;
        }

        return self::sanitize(trim($value)) !== null;
    }

    /** @return array<int, string> */
    public static function trustedHosts(): array
    {
        /** @var array<int, string> $hosts */
        $hosts = (array) config('security.trusted_portal_hosts', []);

        return array_values(array_filter($hosts, static fn (mixed $host): bool => is_string($host) && $host !== ''));
    }
}
