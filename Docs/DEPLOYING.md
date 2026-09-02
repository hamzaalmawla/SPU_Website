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

Three things used to fail on every staging deploy and no longer do. None of
them was the site:

- **The gate ran against a site in maintenance mode.** `artisan down` was lifted
  by the deploy script's exit trap, so warm and verify both talked to a kernel
  that answered 503. That single bug produced most of the noise below, and it
  meant `cache:warm` had never once warmed a public page — 4,558 of its targets
  came back 503. It now warms 33 targets and the 503s are gone.
- **robots.txt correctness** judged the file against `APP_ENV`. v2 runs as
  production so it behaves like the real thing, while its docroot carries a
  `Disallow` overlay so it stays out of Google — so the check demanded `Allow: /`
  from a host whose whole purpose is to be unindexed. It now judges against the
  environment the deploy is *for*. It also stopped requiring a `Sitemap:` line
  on a disallow-all file, where such a line does nothing.
- **Admin preview safety** built its request for host `localhost`, so
  `EnforcePublicOrigin` answered with a 301 before the token check ran, and the
  gate reported that redirect as "responded successfully without a token".

What the robots check will now catch, which is the reason it exists: a
**production** deploy while the staging overlay is still in place. That means
the live university site telling every search engine to go away. Deleting the
overlay is a manual cutover step (`Docs/V2_PRE_CUTOVER_ACTIONS.md` §C), so it is
exactly the kind of thing that gets forgotten.

Still expected on staging: two warnings (the `file` cache store has no tag
support; the pre-generated sitemap drifts as content changes), and anything
mentioning content that only exists in production.

A gate that cries wolf on every deploy is worse than no gate, because people
stop reading it. If a check fails for a reason that is not a defect, fix the
check.

---

## Rolling back

Both sites live on the same account, so this is not a DNS change:

- **Bad code:** deploy the previous commit. In **Manage**, check out the earlier
  commit and deploy again.
- **Worse:** the last known-good tree is in `/home/spuedu/.spu_backups/`.
  `Docs/V2_PRE_CUTOVER_ACTIONS.md` §E3 explains why restoring from a tar archive
  went wrong once and why `git archive` is the reliable source.

---

## Files the deploy does not touch

The deploy syncs `app bootstrap config database lang resources routes` into the
application directory, and copies `public/build/` and the SVG assets into the
docroot. **That is all it copies into the docroot.** Three files live there that
git does not deploy:

| File | Why it diverges |
|---|---|
| `public/.htaccess` | Carries the `STAGING ONLY` blocks — the `X-Robots-Tag: noindex` header and the host guard — which must **not** reach the live domain. Removing them is a cutover step (`Docs/V2_PRE_CUTOVER_ACTIONS.md` §C). |
| `public/robots.txt` | The staging `Disallow: /` overlay. Deleted at cutover so the application serves its own. Gitignored on purpose: shipping it would send `Disallow: /` to the live site. |
| `public/.user.ini` | PHP-FPM settings. |

The divergence is deliberate and correct. The trap is that **editing any of them
in git changes nothing on the server, and nothing tells you.** A commit can say
a setting was removed, be entirely true about the repository, and leave the
server exactly as it was.

That is how `zlib.output_compression = On` stayed live for a day after being
removed in git — a setting that, combined with the application's own
compression, would have made every page on the site unreadable. Edit these on
the server (cPanel File Manager, or the file API), and change the repo copy in
the same sitting so the two do not drift further.

The deploy now refuses to run if it finds `zlib.output_compression` active in
the deployed `.user.ini`. That check exists because no amount of care in git can
catch a file git does not own.

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

**Compression** is no longer one of them. nginx still neither compresses nor
forwards `Accept-Encoding` upstream, so the `mod_deflate` rules in
`public/.htaccess` never fire — but the application now compresses its own
responses in `CompressPublicResponses`, which is what mattered: this origin
degrades sharply above a ~24KB response and gzip puts every page back under
that line. The host request in `Docs/V2_PRE_CUTOVER_ACTIONS.md` §B2 and §B4 is
still worth sending, because compressing in PHP costs CPU that nginx would not,
and static assets are still shipped uncompressed. It is no longer a blocker.

**Checking whether it is working.** Not with `curl -I`. HEAD is skipped by
design — a HEAD response must carry the same headers as GET with no body — so
`curl -I` reports `X-Compressed: off` whether compression works or not. Use a
GET that throws the body away:

```bash
curl -s --http1.1 -o /dev/null -D - -H 'Accept-Encoding: gzip' https://v2.spu.edu.sy/ar
```

`X-Compressed` says what the middleware decided: `negotiated` (the client asked
and got it), `forced` (no `Accept-Encoding` arrived and forcing is on), `off`
(declined — wrong method, wrong status, wrong content type, or the client did
not ask), `skipped` (ran, but the body was too small or did not shrink). An
absent header means the middleware did not run at all.

If it reports `off` on every page, the likely cause is that nginx strips
`Accept-Encoding` before PHP sees it. Confirm rather than assume: set
`COMPRESSION_DIAGNOSTICS=true` in `/home/spuedu/spu_v2_app/.env`, rebuild the
config cache (the next deploy does this, or run `config:clear` then
`config:cache`), and read `X-Compress-Debug` from the same GET. Its `accept_encoding=` field is the
answer. If it says `(absent)`, set `COMPRESS_WITHOUT_ACCEPT_ENCODING=true`;
if it shows a real value, leave that flag off — the header is arriving and
something else is declining. Turn diagnostics back off once read.

One thing must be true before forcing is enabled: `X-Compress-Debug`'s `zlib=`
field must read `(off)`. PHP's own `zlib.output_compression` compresses at the
output layer *after* this middleware has set its headers, so the middleware
cannot detect it, and the two together produce `gzip(gzip(body))` under a single
`Content-Encoding` header — unreadable pages site-wide. `public/.user.ini`
documents why it was removed.

If the host does enable compression at the edge, set `COMPRESS_RESPONSES=false`
and delete the middleware in the same change. Two compressors stacked produce
`gzip(gzip(body))` under one `Content-Encoding` header — unreadable pages
site-wide, triggered by someone else's config change rather than a deploy of
ours. The middleware refuses to encode a body that already carries
`Content-Encoding`, so the realistic failure is wasted CPU rather than
breakage — but do not rely on that as the plan.

---

## If push-to-deploy cannot be set up

If the GitHub token is genuinely unavailable, the release can still be pushed by
hand the way the 1 September one was: build a small git repository whose working
tree is `.cpanel.yml`, `deploy.sh` and a `release.tar.gz`, upload it through the
cPanel file API, register it, and deploy. It works, but every release is a manual
repackage and the repository drifts from `dev` immediately.

Prefer the clone. It is five minutes once.
