# Moving v2 to the Docker VPS and making it spu.edu.sy

Prepared 2026-11-17 from the repository (dev at fa38cc1) and a read-only look
at the cPanel account through UAPI. The stack itself is described in
[`deploy/docker/README.md`](../deploy/docker/README.md).

Goal: v2 (Laravel, `spuedu_v2`) runs on the new VPS and answers at
`https://spu.edu.sy`; `www.spu.edu.sy` and `v2.spu.edu.sy` redirect to it.
The old site is not moved. **Every old URL keeps the status code and target it
has today** (Webometrics and search engines know the university through them).

**The guided way:** `deploy/docker/migrate.sh` runs every step below on the VPS
(prerequisites, code, copies, databases, certificate, start, checks) with
prompts and "press Enter" pauses, resumes where it stopped, and prints the DNS
and rollback steps. How to run it, in plain language:
[VPS_MIGRATION_GUIDE.md](VPS_MIGRATION_GUIDE.md). This runbook is the reference
behind it.

**Deploys after the move** are pull-based, because the VPS accepts SSH only
from the university network: the server deploys every new commit on dev by
itself within a few minutes, and each commit's *Deploy to the VPS* run on
GitHub shows the result (`migrate.sh` step 14; details in
`deploy/docker/README.md`, how-to in the guide's section 7).

---

## 1. What the current server holds (read 2026-11-17)

| | |
|---|---|
| cPanel account | `spuedu`, 40.8 GB of a 50 GB quota used (9.4 GB free) |
| MariaDB | 10.11.19 |
| `spuedu_v2` | 975 MB on disk; the deploy's own dumps are ~53 MB gzipped |
| v2 docroot | `public_html/spu_v2/public` (PHP 8.4); app in `spu_v2_app` |
| Old site | `public_html` (PHP 8.3); stays where it is |
| Old media used by v2 | `public_html/downloads` (files ~17 GB, files2), `images`, `<subsite>/images`, `FCKBIH`: about 20 GB, symlinked into v2's `_legacy/` |
| Live PHP apps mounted into v2 | `/forms` (+ `spuedu_forms`, 22 MB), `/Medicine` (`spuedu_medical`), `/spuh` (`spuedu_spuh`, ~850 job applications) |
| Other things on the account that are **not** v2 | `rooms.spu.edu.sy` (`spuedu_spu_rooms`), `webmail`, Moodle data, `cv_bank`, `survey`, `erp.spu.edu.sy` folder |
| DNS | Authoritative on the host's nameservers (`ns1/ns2.dubaimonster.com`), edited in this cPanel's Zone Editor. TTL 14400 s (4 h) |
| Mail | MX is `mail.idlib.edu.sy` (not this host). SPF allows `184.107.185.129` and `91.144.20.31`; DMARC is `p=reject` |
| Certificates | `*.spu.edu.sy` + `spu.edu.sy` from Actalis, valid to 2027-04-02; a Let's Encrypt one for `v2.spu.edu.sy` |
| FTP accounts | `it@spu.edu.sy` (root `/home/spuedu`), `legacy-migration@spu.edu.sy` (root `public_html`) |

---

## 2. Redirect map

Four layers answer an old URL. Only the first two change in this move; the
other two travel unchanged (same `.htaccess`, same database).

### 2.1 DNS (changed at cutover, in the cPanel Zone Editor)

| Record | Today | After |
|---|---|---|
| `spu.edu.sy` A | 184.107.185.129 | **VPS IP** |
| `www` CNAME → `spu.edu.sy` | (follows apex) | unchanged, follows |
| `www.spu.edu.sy` A (a stray duplicate of the CNAME) | 184.107.185.129 | **delete**, or set to VPS IP |
| `v2` A, `www.v2` A | 184.107.185.129 | **VPS IP** |
| Everything else: MX, `mail`, `webmail`, `rooms`, `cpanel`, `ftp`, `my`, `portal`, `erp`, `ums`, `new`, `wmail`, SPF, DKIM, DMARC, autodiscover, verification TXT | | **unchanged** |

The cPanel API is then reached at `https://cpanel.spu.edu.sy` (its own A
record), no longer at `spu.edu.sy:2083`.

### 2.2 Host level (Caddy, new)

| Request | Answer |
|---|---|
| `http://<any of the names>/<path>?<query>` | **301** → `https://spu.edu.sy/<path>?<query>` |
| `https://www.spu.edu.sy/<path>?<query>` | **301** → `https://spu.edu.sy/<path>?<query>` |
| `https://v2.spu.edu.sy/<path>?<query>` | **301** → `https://spu.edu.sy/<path>?<query>` |
| `https://spu.edu.sy/...` | passed to Apache |

One hop, path and query untouched; the application's own legacy redirect then
answers on the canonical host. (Today the old site answers `http://` and `www`
with 200 pages; they gain one 301 hop and end on the same target.)

### 2.3 Apache, `public/.htaccess` (unchanged from cPanel)

- Old files stay at their old address and answer **200** (internal rewrite, not
  a redirect): `/downloads/files/*`, `/downloads/files2/*`, loose
  `/downloads/<file>.pdf|doc|xls|ppt|zip|rar|jpg|png|gif`, `/images/*`,
  `/<subsite>/images/*`, `/FCKBIH/*`. Code and markup under those trees: 404.
- `/<path>/` → **301** `/<path>` (except the old subsite roots like `/med/`,
  which go straight to the application so they redirect in one hop).
- `/index.php?...` is a legacy URL handled by the application; `/app.php` 404.
- `/forms`, `/Medicine`, `/spuh`: served by the legacy apps at the same paths.

### 2.4 Application (unchanged; the data moves with `spuedu_v2`)

`RedirectContinuityMiddleware` → `ContinuityService`: the
`legacy_exact_redirects` table (imported rules plus the 25 deterministic rows of
`LegacyEntryPointRedirectSeeder`, re-seeded every release), the query resolvers
(news, pages, research, alumni, faculty projects, subsite home and content,
the 277-id category allow-list in `config/legacy_category_routes.php`), file
continuity, `lang=3/6/7` → **302** `/en`, `/` → **302** `/ar` or `/en`, profile
and `/research/office` 301s. Details: `Docs/LEGACY_REDIRECT_MAINTENANCE_GUIDE.md`.

### 2.5 Proof, before and after

`deploy/docker/redirects/` holds:

- `old-sitemap-paths.txt.gz`: all 28,428 distinct addresses in the old site's
  live `sitemap.xml` (read 2026-11-17).
- `extra-paths.txt`: entry points and old files that are not in the sitemap.
- `baseline-v2-2026-11-17.tsv`: what v2 answered today for a 1,500-path random
  sample plus the extras (status and Location, host-neutral): 1,372 × 301,
  81 × 302, 45 × 404, 2 × 200 in the sample.

On the VPS, before DNS moves, the same paths are requested from the new server
under the real hostname and compared. **The move does not go ahead with a single
difference.**

```bash
cd /srv/spu/src/deploy/docker
python3 scripts/redirect-parity.py record --base https://spu.edu.sy --connect-to <VPS IP> \
    --insecure --paths redirects/baseline-v2-2026-11-17.tsv --out /srv/spu/vps-parity.tsv
python3 scripts/redirect-parity.py compare redirects/baseline-v2-2026-11-17.tsv /srv/spu/vps-parity.tsv
# and the full old sitemap against the new server (local, no load on cPanel):
python3 scripts/redirect-parity.py record --base https://spu.edu.sy --connect-to 127.0.0.1 \
    --insecure --paths redirects/old-sitemap-paths.txt.gz --concurrency 8 --out /srv/spu/vps-full.tsv
```

Found while recording, not caused by the move: 45 of the 1,500 sampled old URLs
(3%) already answer 404 on v2, mostly `index.php?page=show&dir=items` (33) and
alumni item pages. Scaled to the sitemap that is roughly 850 old URLs. The move
keeps them as they are; mapping them is separate work.

---

## 3. Getting the data across

Nothing is written on the cPanel side. Plan A for files is FTPS from the VPS
(`scripts/pull-from-cpanel.sh`, resumable, re-runnable for the final delta).
Port 21 could not be tested from here; test it from the VPS first.

| What | How | When |
|---|---|---|
| Old media (~20 GB) | `pull-from-cpanel.sh media` | as soon as the VPS is reachable (hours) |
| `/forms`, `/Medicine`, `/spuh` | `pull-from-cpanel.sh apps` | first copy early, again at cutover |
| v2 `storage/`, docroot runtime files, `.env` | `pull-from-cpanel.sh site` | first copy early, again at cutover |
| `spuedu_v2` | cPanel → Backup → *Download a MySQL Database Backup* (the download is session-only, so a person clicks it), `scp` to `/srv/spu/dumps/`, then `import-db.sh` | at cutover, after the content freeze |
| `spuedu_forms`, `spuedu_medical`, `spuedu_spuh` (and `spuedu_db` if wanted) | same | at cutover |

Fallbacks if port 21 is closed from the VPS: (a) proxy the old media and apps
to the old host for now (`SPU_LEGACY_MEDIA=remote`, `SPU_LEGACY_APPS=remote`)
and copy later; (b) allow the VPS IP in cPanel *Remote MySQL* and dump
directly, if port 3306 is open; (c) a cPanel deploy writes a fresh dump to
`/home/spuedu/backups/spu_v2/`, fetched by any route that works.

---

## 4. Day-of checklist

Each step says what proves it worked. Nothing here touches the old server
except reading from it, until the DNS step.

### Before (the day before if possible)

- [ ] **Lower the TTL** of `spu.edu.sy`, `www.spu.edu.sy`, `v2`, `www.v2`
      from 14400 to 300 in the Zone Editor, at least 4 hours before cutover.
      Rollback then takes 5 minutes instead of 4 hours.
- [ ] VPS: Docker present; disk at least 100 GB (20 GB media, ~2 GB database,
      backups); 4 GB RAM or more.
- [ ] `git clone` the repository to `/srv/spu/src` (deploy key or token, see
      questions), `git checkout <the commit live on v2>`.
- [ ] `bash /srv/spu/src/deploy/docker/scripts/bootstrap-vps.sh` (dirs, firewall
      keeping the real SSH port, `stack.env` with new DB passwords).
- [ ] `/srv/spu/secrets/ftp.env`, then `pull-from-cpanel.sh media` (long; run in
      `tmux`), `apps`, `site`.
- [ ] `scripts/make-app-env.sh /srv/spu/secrets/cpanel.env /srv/spu/app.env`.
      Check mail settings (question 4).
- [ ] `spu build web legacy-apps && spu up -d db`, import a first copy of the
      databases (`import-db.sh`), create the legacy apps' DB users with the
      passwords from their config files.
- [ ] `spu run --rm release` then `spu up -d`. `launch:validate` must pass.
- [ ] Rehearsal with `SPU_TLS_MODE=internal`: redirect parity (2.5) shows **0
      differences**; smoke test below passes.

### Smoke test (before DNS, with `--resolve spu.edu.sy:443:<VPS IP> -k`)

- [ ] `/ar`, `/en`, a faculty, a news article, search: 200 and styled.
- [ ] `http://`, `www`, `v2` → one 301 to `https://spu.edu.sy`, path kept.
- [ ] An old file (`/downloads/files/...`, `/images/...`, `/FCKBIH/...`): 200.
- [ ] `/forms/`, `/Medicine/`, `/spuh/`: the apps load; a test submission
      works and shows in its dashboard.
- [ ] Admin: `https://spu.edu.sy/<ADMIN_PATH>/login`, log in, open a page,
      save a draft.
- [ ] Contact form: the message arrives (mail settings).
- [ ] `robots.txt` says `Allow: /` and names `https://spu.edu.sy/sitemap.xml`.
- [ ] `spu logs queue scheduler`: no errors; `site-stats:collect` runs.

### Cutover

1. [ ] Tell editors: no admin edits from now until done. `/forms` and `/spuh`
       keep taking submissions on the old host until DNS moves.
2. [ ] Download fresh dumps of `spuedu_v2`, `spuedu_forms`, `spuedu_medical`,
       `spuedu_spuh`; `scp` to `/srv/spu/dumps/`; `import-db.sh` each.
3. [ ] `pull-from-cpanel.sh site` and `apps` (only what changed; minutes).
4. [ ] `spu run --rm release` (launch:validate and redirect validation must
       pass), `spu up -d`.
5. [ ] Redirect parity again: 0 differences.
6. [ ] Certificates: put the `*.spu.edu.sy` certificate and key in
       `/srv/spu/data/certs/` (`fullchain.pem`, `privkey.pem`, chmod 600) and set
       `SPU_TLS_MODE=files`; or plan on `auto` right after step 7. `spu up -d caddy`.
7. [ ] **DNS** (needs Zainab's go-ahead): the four records in 2.1 → VPS IP.
8. [ ] From outside, once `dig +short spu.edu.sy` shows the VPS: the smoke
       test without `--resolve`. If on `files`, switch to `auto` later at leisure.
9. [ ] Google Search Console: the property `https://spu.edu.sy`, submit
       `sitemap.xml`.

### Watch, first 48 hours

- [ ] `spu artisan continuity:report-unresolved` / the `unresolved_legacy_requests`
      table sorted by `hit_count`: old URLs with real traffic that do not resolve.
- [ ] 5xx in `spu logs web`; queue failures (`spu artisan queue:failed`).
- [ ] Nightly backup cron installed (`scripts/backup.sh` header) and one dump
      restored into a scratch database once.

---

## 5. Rollback

The old site is untouched on the old host, so rollback is DNS:

1. Set the four records back to `184.107.185.129` (5 minutes with TTL 300).
2. Anything written on the VPS after cutover (form submissions, admin edits,
   staff intake requests) exists only there. Before switching back, dump
   `spuedu_forms` / `spuedu_spuh` / `spuedu_v2` on the VPS (`backup.sh db`) so
   nothing is lost; re-entering it on the old host is manual.
3. HSTS does not block rollback: the old site already serves HTTPS.

Abort criteria (go back instead of fixing forward): redirect parity shows
differences that cannot be explained in minutes; 5xx on core pages; admin login
or the forms fail; mail cannot be sent and there is no quick fix.

---

## 6. Questions that decide parts of this

1. **Canonical address.** The code, the redirects and the old sitemap all use
   `https://spu.edu.sy` (no www), with www → 301 to it. You wrote
   "www.spu.edu.sy". Keep the bare domain (recommended), or make www the main
   one (then the app's canonical URL, sitemap and every redirect target change
   to www)?
2. **DNS access and TTL.** May I lower the TTL to 300 today through the cPanel
   API (nothing else changes), and do the record change at cutover the same way
   when you say go? Is the old hosting account staying for a while? The DNS zone
   lives on that host's nameservers: if the account is cancelled, the whole
   domain (and mail routing) goes with it unless the nameservers move first.
3. **The VPS.** Its IP, disk size and RAM, SSH port and user (root or sudo),
   and where it is (bandwidth from Canada decides how long the 20 GB copy takes).
4. **Mail.** Which SMTP server does the site send through today? If it is this
   cPanel host (localhost), it stops working on the VPS; if it is
   `mail.idlib.edu.sy` or another relay, it keeps working, and the VPS IP may
   need adding to SPF (DMARC is `p=reject`).
5. **/forms, /Medicine, /spuh.** Move them to the VPS too (planned, PHP 8.3,
   their databases come along), or keep them on the old host behind a proxy?
   `rooms.spu.edu.sy`, webmail and Moodle data stay where they are; correct?
6. **FTP.** The password of `it@spu.edu.sy` (or allow me to set a temporary one
   for the copy and remove it after).
7. **Database download.** At cutover someone clicks cPanel → Backup → download
   `spuedu_v2` (and the three app databases) and copies them to the VPS, or I
   add the VPS to Remote MySQL if port 3306 is open.
8. **Code on the VPS.** A read-only deploy key on the GitHub repository for the
   VPS (you add it in Settings → Deploy keys), or I copy the code over SSH each
   release.
9. **HSTS on subdomains.** Once the site is on the bare domain, its HSTS header
   with `includeSubDomains` makes browsers refuse plain `http://` on every
   subdomain (`rooms`, `webmail`, `my`, `erp`, ...). Do all of them work over
   HTTPS? If not, set `HSTS_INCLUDE_SUBDOMAINS=false` and drop
   `includeSubDomains` from `SPU_HSTS` before cutover.
10. **Backups off the server.** Where should nightly backups be copied (another
    server, object storage)?
