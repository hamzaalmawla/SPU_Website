# Deploying to v2.spu.edu.sy

How a change on `dev` reaches the server, what runs when it does, and the things
that have gone wrong before.

Push-to-deploy works. `git push origin dev`, then pull and deploy in cPanel.

---

## Current state

**Push-to-deploy works.** The cPanel repository is a real clone of the GitHub
repo, tracking `dev`.

| | |
|---|---|
| cPanel repository | `/home/spuedu/repositories/spu-v2` |
| Remote | `github.com/hamzaalmawla/SPU_Website` |
| Branch | `dev` |

Skip to [Deploying a change](#deploying-a-change). The setup section below is
kept for whoever has to rebuild this.

---

## One-time setup (already done — kept for reference)

**The repository is private, so it can only be cloned over SSH with a registered
deploy key.** cPanel's clone dialog does *not* prompt for HTTPS credentials — it
runs `git clone` non-interactively, so an `https://` URL fails with
`could not read Username for 'https://github.com'`. An earlier version of this
document said otherwise and cost two failed attempts.

The account has a key at `~/.ssh/SPU_REPO`, and `~/.ssh/config` points git at it
for `github.com` (with `IdentitiesOnly yes`, or ssh offers the default `id_rsa`
first and is refused before it tries the right key). The matching public key is
registered on the repository under **Settings → Deploy keys**, read-only.

To rebuild it from scratch:

1. **cPanel → SSH Access → Manage SSH Keys** — confirm `SPU_REPO` exists, or
   generate a key with an **empty passphrase** (a passphrase cannot be entered
   during an unattended clone).
2. Copy the **public** key and add it on **GitHub → the repository → Settings →
   Deploy keys**, read-only. A deploy key is scoped to one repository, which a
   personal access token is not.
3. Ensure `~/.ssh/config` contains the `Host github.com` block above.
4. **Git Version Control → Create** → **Clone a Repository**
   - Clone URL: `git@github.com:hamzaalmawla/SPU_Website.git` — the SSH form,
     not `https://`
   - Repository Path: `repositories/spu-v2`
5. **Manage** → set the checked-out branch to `dev`.

`.cpanel.yml` is then recognised and **Deploy HEAD Commit** becomes available.

---

## Deploying a change

### 1. Rebuild the front-end if you touched CSS, JS or Blade

```bash
php artisan view:clear && npm run build
git add public/build
```

`public/build` is committed on purpose — there is no Node on the server, so the
repository has to carry its own assets.

**`view:clear` first is not optional.** Tailwind scans
`storage/framework/views/*.php`, so whatever compiled Blade happens to be lying
around on your machine ends up in the stylesheet. Skipping it produced a 326 KB
CSS file where a clean build produces 300 KB, and it makes the output hash
differ between developers for no reason.

### 2. Push

```bash
git push origin dev
```

### 3. Deploy

**cPanel → Git Version Control → Manage → Pull or Deploy → Update from Remote**,
then **Deploy HEAD Commit**.

Watch the log. It ends with `▸ Deployed <commit>`.

---

## What the deploy actually does

`.cpanel.yml` runs `deploy/v2-staging/cpanel-deploy.sh`, which:

1. **Checks preconditions** and refuses to start if any fail — missing `.env`,
   missing web root, missing asset manifest. Each of these has taken the site
   down at least once.
2. **Takes the site down** (`artisan down`) around the destructive part. Between
   the source sync and the migration, the code and the database schema disagree
   while PHP is still serving.
3. **Publishes the front-end build** first, and without `--delete`. Vite
   content-hashes filenames, and the page cache holds rendered HTML for an hour —
   removing the old build would strip the stylesheet out from under every page
   already cached.
4. **Syncs the source**: `app bootstrap config database lang resources routes`.
   Never `.env`, never `storage/`, never `bootstrap/cache`.
5. **Runs composer**, then deletes the compiled package manifest so it is
   regenerated here rather than inherited.
6. **Runs migrations** (`--force`).
7. **Rebuilds framework caches** (`artisan optimize`).
8. **Rebuilds the search index** and **regenerates the static sitemap**. Neither
   is in git; neither appears by deploying code. Miss them and the site comes up
   with a search box that finds nothing.
9. **Publishes SVG assets**, **restarts queue workers**, **warms caches**.
10. **Verifies**: proves the routes still boot, then runs `launch:validate`.

On staging a failing gate is a warning. Set `SPU_DEPLOY_ENV=production` and it
becomes fatal — the check that catches the noindex trap lives in there.

---

## Reading a failed deploy

The log is at `/home/spuedu/.cpanel/logs/vc_*_git_deploy.log`, readable in File
Manager. The script prints a `▸` line before each stage, so the last one tells
you where it stopped.

| Message | What it means |
|---|---|
| `No Vite manifest in the release` | You did not commit `public/build`. Rebuild and commit. |
| `No vendor/autoload.php` | Composer never ran or failed. Read the lines above it. |
| `.env is missing` | Someone deleted it. It is not in git and never should be. Restore from the backup in `/home/spuedu/.spu_backups/`. |
| `Routes do not boot` | The release is broken. Roll back — see below. |
| `advertises a different host` | `APP_CANONICAL_URL` changed but the sitemap was not regenerated. Re-run the deploy. |

Three checks fail on staging and are **expected**:

- **robots.txt correctness** — staging serves a disallow-all overlay by design.
- **Admin preview safety** — a false positive from the validator's internal
  request. Over real HTTP `/ar/preview` returns 404. Confirm with curl if you
  want to be sure.
- Anything mentioning content that only exists in production.

---

## Rolling back

Both sites live on the same account, so this is not a DNS change:

- **Bad code:** deploy the previous commit. In **Manage**, check out the earlier
  commit and deploy again.
- **Worse:** the last known-good tree is in `/home/spuedu/.spu_backups/`.
  `Docs/V2_PRE_CUTOVER_ACTIONS.md` §E3 explains why restoring from a tar archive
  went wrong once and why `git archive` is the reliable source.

---

## Things that have actually bitten us

**Never ship `bootstrap/cache/`.** It carries your machine's package manifest,
which lists dev packages the server does not have, and every page returns 500.
The deploy script excludes it and regenerates it on the server.

**Anchor every `tar --exclude`.** `--exclude=public` matches *every* path segment
named `public`, including `resources/views/public/`. That silently produced a
backup missing 118 view files.

**`/images/*.svg` and the legacy trees share a namespace.** `public/.htaccess`
blocks markup and SVG under the prefixes where old uploads are mounted — but
`/images/` is also where this application keeps its own icons. The rule needs its
`RewriteCond %{REQUEST_FILENAME} !-f` guard, or all 37 icons 404 while the files
sit there perfectly intact. This cost a lot of time to find.

**A menu item that 404s is the worst kind of bug** — visitors read it as "this
site is broken". Anything that decides whether to render a link must agree with
what the page actually does. `ProfileAvailabilityTest` states that invariant;
keep it green.

**Do not add a cron or web endpoint to run deploy commands.** `REM-07` prohibits
it and cPanel's Git deployment is the approved mechanism.

---

## Still outstanding on the server

Two things this deploy did not fix, both one line, neither in code:

**`QUEUE_CONNECTION=sync`** in `/home/spuedu/spu_v2_app/.env`. Seven mailables
are queued and no worker is running, so every contact-form message and event
registration is silently discarded while the sender sees a success page. This is
the most damaging open defect on the site.

**Compression.** nginx neither compresses nor forwards `Accept-Encoding`
upstream, so the `mod_deflate` rules in `public/.htaccess` never fire and pages
ship uncompressed. This needs the host. See `Docs/V2_PRE_CUTOVER_ACTIONS.md` §B2
and §B4 for the request to send them.

---

## If push-to-deploy cannot be set up

If the GitHub token is genuinely unavailable, the release can still be pushed by
hand the way the 1 September one was: build a small git repository whose working
tree is `.cpanel.yml`, `deploy.sh` and a `release.tar.gz`, upload it through the
cPanel file API, register it, and deploy. It works, but every release is a manual
repackage and the repository drifts from `dev` immediately.

Prefer the clone. It is five minutes once.
