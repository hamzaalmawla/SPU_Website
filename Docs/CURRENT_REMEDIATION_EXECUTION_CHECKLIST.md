# Current Remediation Execution Checklist

Status date: 2026-08-22

This is the single execution checklist for the current production-readiness
remediation. It supersedes current-state claims elsewhere when they conflict,
but it does not erase dated migration reports or earlier test evidence.

Provider execution runbook: `Docs/PROVIDER_V2_INFRASTRUCTURE_REMEDIATION_RUNBOOK.md`

Status meanings:

- `Implemented locally`: code exists in the current working tree; it is not deployed.
- `Verified locally`: the stated local command or inspection passed; it is not host evidence.
- `Pending`: work or evidence is still required.
- `Deferred`: intentionally excluded from this remediation and must remain disabled.
- `Approved, provider pending`: the outcome is approved, but no provider execution
  or host evidence has been recorded.

No row in this checklist constitutes deployment approval, cutover approval, product
sign-off, accessibility sign-off, or production sign-off.

| ID | Finding / required outcome | Current status | Required verification / close condition |
|---|---|---|---|
| REM-01 | Public runtime must not expose frontend fixture/sample records as production content. | Implemented locally. Runtime fallback use has been removed across the remediated Admissions, Campus Life, E-Services, Research, News Events/Gallery, and faculty-project paths. Some fixture readers/files remain for editor defaults or unrelated paths, so this is not a claim that every fixture file was deleted. | Deploy the code, inspect affected AR/EN routes with production-like data, and prove no fixture/sample record is public. |
| REM-02 | Real CMS content and explicit product decisions must replace removed fallbacks. | Approved by supervisor on 2026-08-22. Implemented locally: publish only approved real migrated research publications; hide/retire empty CMS-only research sections; provide localized global alumni directories; keep unverifiable alumni URLs retired; keep uncertain duplicate groups private; retain distinct records with source-ID slugs; merge identical records only through explicit canonical/redirect dispositions. | Deploy, verify the live migrated data, publish the approved publication batch through its gated command, review navigation, and record any remaining content-specific decisions. |
| REM-03 | Real published research publications must be discoverable without requiring a synthetic `research.publications` CMS payload; sitemap timestamps/canonical origin/hreflang must remain valid. | Implemented locally. Publication sitemap generation was corrected in code. Deployment is pending. | Deploy, clear/rebuild application caches, request production-like `/sitemap.xml`, validate XML and canonical host, and confirm eligible published AR/EN publication URLs are present while drafts remain absent. |
| REM-04 | Accessibility defects in navigation, forms, status/error announcements, iframe names, inert links, breadcrumbs, and interactive public content must be corrected. | Implemented locally with automated coverage added. Manual browser/assistive-technology QA is pending. | Test AR/RTL and EN/LTR on mobile and desktop with keyboard, focus inspection, at least one screen reader, reduced motion, form errors/success, menus, and affected detail pages. Record browser/version and defects. |
| REM-05 | Locked PHP and JavaScript dependencies must have no known advisories in the local audit. | Verified locally on 2026-08-21: `composer audit --locked` reported no advisories; `npm audit --package-lock-only` reported 0 vulnerabilities. This is time-bound local evidence only. | Repeat both audits from the release artifact/lockfiles immediately before deployment and retain output. |
| REM-06 | Canonical host, trusted local proxy, HTTPS redirects, and front-controller exposure must be hardened. | Implemented locally in configuration/bootstrap/`.htaccess`; not deployed or host-verified. | Deploy to staging, verify the actual nginx-to-Apache proxy addresses, then probe HTTP/HTTPS, canonical/noncanonical hosts, spoofed forwarding headers, `/app.php`, `/app.php/*`, `/index.php/*`, normal routes, and legacy routes. No redirect loop or reflected Host value is allowed. |
| REM-07 | Server-side deployment commands cannot assume cPanel shell access. | Approved, provider pending. cPanel shell/Terminal and SSH are currently disabled. The approved outcome is jailed SSH or another auditable deployment mechanism; a persistent or temporary web/cron execution bridge is prohibited. | Provider records the access mechanism, executor, scope, and output. Do not close this row from local access or a web bridge. |
| REM-08 | OPcache must be installed/enabled for the selected production PHP runtime. | Approved, provider pending. Install `ea-php84-php-opcache` and apply the reviewed values `enable=1`, `memory_consumption=192`, `max_accelerated_files=20000`, `validate_timestamps=1`, and `revalidate_freq=60`. No installation is claimed. | Provider confirms effective settings from the v2 PHP-FPM/web runtime, not only CLI, and captures the post-change PHP probe. |
| REM-09 | Dynamic text responses must be compressed at the terminating proxy or correctly passed to Apache. | Approved, provider pending. nginx gzip must cover text/HTML, JSON, CSS, JavaScript, and SVG. No compression change is claimed. | Provider records config-test/reload output and before/after HTTPS probes showing `Content-Encoding` and transfer sizes for HTML, JSON, CSS, JS, and SVG. |
| REM-10 | PHP-FPM pool sizing/recycling must be corrected for expected load. | Approved, provider pending and load-gated. The reviewed candidate is `pm.max_children=16`, `pm.max_requests=1000`, and `pm.process_idle_timeout=60`; values must not be applied before a bounded load and memory check. | Provider records the bounded test, failed requests, latency, memory headroom, effective pool values, reload output, and retest. |
| REM-11 | The release-candidate automated suite must be green. | Verified locally on 2026-08-22: `php artisan test` passed 4,279 tests with 24,784 assertions; `npm test` passed 24 tests; `npm run build` passed; Composer and npm audits were clean. | Repeat from the final release artifact, run `php artisan route:list`, and complete production-like launch validation after deployment. |
| REM-12 | Additional application/full-page caching optimization must not be introduced during this remediation. | Approved product decision: deferred. Current correctness, session, CSRF, publication, and invalidation behavior must not be weakened for performance claims. | Keep the deferral recorded until a separate reviewed design and test plan is approved. |
| REM-13 | nginx private/full-page caching must remain disabled. | Approved, provider pending. v2 must not use nginx dynamic/private/full-page, `fastcgi_cache`, or `proxy_cache` for Laravel HTML, personalized/session responses, admin, preview, form pages, or dynamic JSON. Versioned static assets may be cached separately. No host change is claimed. | Provider proves dynamic cache off/bypassed, static-only cache scope, and repeated HTTPS requests do not replay `Set-Cookie`, CSRF tokens, private data, drafts, or preview content. |
| REM-14 | Deployment, browser QA, cutover, and sign-off must remain unclaimed until independently completed. | Pending. Current changes are local and the prior v2 deployment snapshot is historical evidence only. | Complete all applicable rows above, the provider runbook evidence, production-like smoke tests, rollback evidence, content/product approval, accessibility QA, and named operational sign-off. |
| REM-15 | Queue, scheduler, failed-job, log-rotation, Sentry, disk/quota, and privileged-2FA operations must be verified. | Pending provider/account-owner evidence. The required checks and ownership boundary are approved; no operational verification is claimed. | Run `Docs/PROVIDER_V2_INFRASTRUCTURE_REMEDIATION_RUNBOOK.md`, record redacted outputs, verify fresh scheduler/queue logs and failed-job review, confirm daily log retention, Sentry test event, disk headroom, and supervised privileged 2FA login/challenge. |
| REM-16 | Before/after infrastructure probes and rollback evidence must be retained. | Pending provider evidence. Required curl/PHP probes and rollback steps are now documented; documentation is not host evidence. | Attach redacted before/after curl and PHP outputs, nginx/FPM reload tests, bounded-load results, cron verification, and any rollback transcript without secrets. |
| REM-17 | Application cache must be correct without leaking session state or serving stale domain data. | Verified locally on 2026-08-22. Public HTML cache now bypasses session/flash/error/form, authorization, no-cache, preview, admin, and registration traffic; browser responses are `private, no-store`; query keys are allowlisted/canonicalized; tag generations persist across requests; CMS invalidation runs after commit; About, settings, profile, faculty, media, menu, research, and sitemap paths are covered. | Deploy the release, verify Redis/application cache behavior, prove dynamic nginx cache is disabled, and repeat the cache probe matrix against v2. |

## Execution Order

1. Deploy the approved REM-02 content policy without reintroducing public fixtures.
2. Repeat the green REM-11 suite and REM-05 dependency audits from the final release artifact.
3. Arrange the approved jailed-SSH or auditable deployment process under REM-07; do not create a web bridge.
4. Capture the runbook before probes, then deploy only through the approved mechanism.
5. Apply the approved nginx cache boundary and gzip work while keeping dynamic/private/full-page cache disabled and static asset caching separate.
6. Install and verify OPcache, run the bounded load check, and only then decide whether the reviewed PHP-FPM candidate is safe.
7. Install the exact flock cron entries and complete REM-15 and REM-16 operational/probe evidence.
8. Run sitemap, origin/front-controller, content, accessibility, rollback, and release-candidate verification.
9. Obtain explicit approvals only after evidence is recorded; do not infer sign-off from code merge, documentation, or deployment.
