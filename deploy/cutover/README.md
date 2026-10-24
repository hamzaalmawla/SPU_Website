# Cutover

`cutover.sh` promotes v2 from `v2.spu.edu.sy` to the live SPU domain.

It exists because every step it performs is currently a line in a checklist,
and every one of those steps fails *silently*. Nothing 500s if the canonical
host is left pointing at v2 — the site just tells search engines that the
university's address is a staging subdomain. Nothing 500s if the noindex header
survives — the site just disappears from search results over the following
weeks. Manual steps that fail quietly are the ones that get missed.

## Running it

From the deployed application directory on the server:

```bash
bash deploy/cutover/cutover.sh --dry-run     # show every change, touch nothing
bash deploy/cutover/cutover.sh               # apply
bash deploy/cutover/cutover.sh --rollback    # restore from the last backup
```

Start with `--dry-run`. It prints a full diff of the `.htaccess` changes and
every `.env` value it would rewrite, and it is genuinely read-only — verified.

## What it will not do

**DNS.** That stays a human decision, taken after the script reports success.

**Secrets.** `MAIL_*` and `SENTRY_LARAVEL_DSN` must already be set by a person.
The script checks that they are and refuses to continue otherwise; it never
writes a credential.

## The gates

Preflight refuses to continue while any of these is unresolved. They are the
findings from the cutover readiness audit, expressed as code, so that cutover
is *impossible* while one is outstanding rather than merely inadvisable.

| Gate | Why it blocks |
|---|---|
| `MAIL_MAILER` is `log`/`array`/unset | Every message is written to a log file while the sender sees a success page. Contact forms, complaints, job applications and 2FA recovery all go nowhere. |
| `QUEUE_CONNECTION=database` with no `queue:work` cron | Queued mail sits in the jobs table forever with nothing to send it. |
| `SENTRY_LARAVEL_DSN` empty | Sentry is installed but reporting nowhere, so an exception on the live site alerts no one — including about whatever this script fails to anticipate. |
| `APP_DEBUG` not `false` | Stack traces would be public. |
| No rehearsed database restore | Warns and asks for confirmation. A backup that has never been restored is a hope, not a rollback plan. |

## What it changes

1. **Canonical host** — `APP_URL`, `APP_CANONICAL_URL`, `SESSION_DOMAIN`,
   `ENFORCE_CANONICAL_HOST`. `SESSION_DOMAIN` is the one that breaks
   immediately: left at the staging host, no session cookie is valid on the
   live domain and nobody can log in to the admin panel.

2. **Staging overlays** — removes the `X-Robots-Tag: noindex` header and the
   host guard from the docroot `.htaccess`, retargets the forced-HTTPS origin,
   and deletes the `robots.txt` overlay. The host guard is the one that takes
   the site *down* rather than merely hiding it: it 404s every request whose
   `Host` is not the staging subdomain, which after cutover is all of them.

   These files are hand-maintained and deliberately diverge from the
   repository, so nothing in git can remove them for you.

3. **Deploys in production mode** — `SPU_DEPLOY_ENV=production`, so
   `cpanel-deploy.sh` applies its own gates: it refuses to finish if the
   noindex header survived step 2, and it runs
   `launch:validate --environment=production` as a hard failure. The cutover
   cannot complete on a red gate. If the deploy fails, the script rolls itself
   back rather than leaving the site half-promoted.

4. **Verifies from outside** — checks `robots.txt`, the `X-Robots-Tag` header,
   whether the staging host still appears in the rendered homepage, and admin
   reachability. Until DNS moves these will not resolve, so a failure here is
   reported rather than fatal.

## Rollback

Every write is backed up to `storage/cutover-backups/<timestamp>/` first.

```bash
bash deploy/cutover/cutover.sh --rollback
```

restores `.env`, `.htaccess` and the `robots.txt` overlay from the most recent
backup and rebuilds the configuration cache. It does not touch DNS — if that
has already moved, move it back.

Note that HSTS constrains what rollback can achieve. Once the apex has sent
`Strict-Transport-Security` with `includeSubDomains`, browsers that saw it will
refuse plain HTTP for the whole `max-age`. This is why `config/security.php`
defaults to one week without `preload` rather than the year that was previously
hard-coded — see that file for what each part costs to undo.

## Re-running

Safe. Each step checks whether it has already been applied: `.env` values that
already match are reported and skipped, and the overlay stripper reports
"already clean" on a second run.
