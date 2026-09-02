<?php

declare(strict_types=1);

namespace App\Rules;

use App\Support\TrustedPortalUrl;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Rejects a portal URL the public resolver would refuse to use.
 *
 * Without this, ManageSettings accepted any URL-shaped string. A value on a host
 * outside security.trusted_portal_hosts saved cleanly, then resolved to null on
 * every public read, and /{locale}/campus-life/transport/registration answered
 * 503 - with the failure invisible to the editor who had just saved it.
 *
 * An empty value stays valid: it means "no portal configured", which is a
 * deliberate state rather than a broken one.
 */
final class TrustedPortalUrlRule implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if ($value !== null && ! is_string($value)) {
            $fail('The :attribute must be a text value.');

            return;
        }

        if (TrustedPortalUrl::isAcceptable($value)) {
            return;
        }

        $hosts = TrustedPortalUrl::trustedHosts();

        $fail(sprintf(
            'The :attribute must be an https:// URL on an approved host (%s), or a site-relative path beginning with "/". Saving an unapproved host takes the public portal link offline.',
            $hosts === [] ? 'none currently configured' : implode(', ', $hosts),
        ));
    }
}
