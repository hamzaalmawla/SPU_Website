# Analytics and the Content-Security-Policy

## Why this document exists

The public site sends a deliberately strict Content-Security-Policy from
`app/Http/Middleware/SecurityHeadersMiddleware.php`:

```
script-src 'self' 'unsafe-inline'
connect-src 'self'
```

Any third-party analytics tag added without changing that policy is **silently
blocked by the browser**. No console error reaches the server, no measurement
arrives, and the failure looks exactly like "analytics is broken" months later.
This document records the decision that was made instead of adding a tag and
hoping.

## The decision: extend the CSP precisely, only when analytics is on

Two options were considered.

**A first-party proxy** (self-hosted collector, or reverse-proxying the vendor
script under `/analytics/*`) would keep `script-src 'self'` untouched. It was
rejected because the production host runs **5 PHP workers with no OPcache**.
Proxying analytics means every page view becomes a *second* PHP request. On a
5-worker box that trades a security-header edit for a capacity problem, on the
exact day traffic peaks — the domain cutover.

**Extending the CSP** was chosen. The extension is precise, minimal, and
reverts on its own:

| Directive     | Baseline (analytics off)  | Added when analytics is on                                                                                            | Why                                                              |
| ------------- | ------------------------- | --------------------------------------------------------------------------------------------------------------------- | ---------------------------------------------------------------- |
| `script-src`  | `'self' 'unsafe-inline'`  | `https://www.googletagmanager.com`                                                                                      | `gtag.js` is served from this origin.                            |
| `connect-src` | `'self'`                  | `https://www.googletagmanager.com`, `https://www.google-analytics.com`, `https://*.google-analytics.com`, `https://*.analytics.google.com` | GA4 beacons POST to the regional collection endpoints, and `gtag.js` fetches its container config. |
| `img-src`     | `'self' data: https:`     | *(nothing)*                                                                                                             | Already permits the GA measurement pixel.                        |

No wildcard (`https:`) is ever added to `script-src`. `MiddlewarePipelineTest`
asserts that explicitly.

### The policy cannot drift open

`config/analytics.php` computes a single `enabled` flag, and the `csp` block is
derived from it:

```php
'csp' => [
    'script-src'  => $enabled ? ['https://www.googletagmanager.com'] : [],
    'connect-src' => $enabled ? [ ... ] : [],
],
```

`SecurityHeadersMiddleware` and the injected `<script>` tag both read that one
source of truth through `AnalyticsServiceInterface`. So:

- With analytics off (the default) the origin lists are empty and the policy is
  **byte-for-byte** the strict one that ships today.
- Turning analytics off again automatically narrows the policy back. There is no
  second place to remember to edit.
- The tag and the policy can never disagree, because a null snippet implies an
  empty origin list.

`AnalyticsInjectionTest::test_policy_permits_every_origin_the_injected_script_uses()`
enforces this: it parses the rendered page for the script tag it actually emits
and asserts the response's own CSP permits that origin.

### The admin panel is not widened

Analytics is injected from `resources/views/layouts/public.blade.php`, which
Filament does not use. The admin CSP branch in the middleware is untouched, and
a test asserts no analytics origin appears in it.

## Configuration

Analytics is **off by default** and stays off unless every condition holds:

1. `ANALYTICS_PROVIDER=ga4`
2. `ANALYTICS_GA4_MEASUREMENT_ID` matches `G-XXXXXXXXXX`
3. `APP_ENV=production`, **or** `ANALYTICS_ENABLE_NON_PRODUCTION=true`

Everything is resolved once at config load and baked by `config:cache`, so
enabling analytics costs **zero database queries and zero extra per-request
work** — a hard requirement on the 5-worker host.

After changing any of these variables in production:

```bash
php artisan config:cache
```

### Privacy posture

The injected `gtag('config', ...)` call sets:

- `anonymize_ip: true`
- `allow_google_signals: false` — no cross-device advertising profile
- `allow_ad_personalization_signals: false`
- `cookie_flags: 'SameSite=Lax;Secure'`

Analytics is never injected into the tokenized preview shell, so editor traffic
is not measured.

## Referrer-Policy change

`Referrer-Policy` moved from `no-referrer` to `strict-origin-when-cross-origin`
in both `SecurityHeadersMiddleware` and `public/.htaccess`.

`no-referrer` stripped the `Referer` header from every outbound navigation. The
cost was total loss of referral attribution: every inbound visit looked
"direct", and no partner site linking to spu.edu.sy could be credited. That is
a real problem during a domain migration, when knowing *where traffic comes
from* is the point of having analytics at all.

`strict-origin-when-cross-origin` remains privacy-respecting:

- cross-origin requests send **only the bare origin** (`https://spu.edu.sy`) —
  never a path, never a query string, so no page a visitor read and no search
  term they typed leaks to a third party;
- nothing at all is sent when downgrading HTTPS → HTTP;
- same-origin requests keep the full URL, which is what makes internal funnel
  analysis work.

It is also the modern browser default, so this aligns the site with the
behaviour visitors already get elsewhere.

## Adding another provider

1. Add the origin lists to the `csp` block in `config/analytics.php`.
2. Extend `AnalyticsService::snippet()` and the provider check.
3. Add the markup branch in `resources/views/public/layout/analytics.blade.php`.
4. `AnalyticsInjectionTest` will fail until the policy and the emitted script
   agree — which is the point.
