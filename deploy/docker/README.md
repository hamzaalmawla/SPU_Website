# SPU website on the Docker VPS

How v2 runs on the new Ubuntu + Docker server, and how to operate it. The move
itself (copying data, DNS, rollback) is in
[`Docs/VPS_MIGRATION_RUNBOOK.md`](../../Docs/VPS_MIGRATION_RUNBOOK.md).

The guided move (one interactive script, run as root on the VPS):
`sudo bash deploy/docker/migrate.sh`; see
[`Docs/VPS_MIGRATION_GUIDE.md`](../../Docs/VPS_MIGRATION_GUIDE.md).
`scripts/cpanel-web.py` is its read-only HTTPS helper for the old cPanel
(database backup download, certificate, file copy when FTP is closed).
`scripts/cpanel-api.py` copies with a cPanel API token alone (port 2083), when
FTP and the cPanel login page are both closed: the old server packs the files
and dumps in an export-only deploy (`deploy/v2-staging/vps-export.sh`) and the
VPS reads them back through the API.

## What runs

| Service | Image | Job |
|---|---|---|
| `caddy` | `caddy:2.8.4-alpine` | The only public ports (80, 443). TLS, `http://` → `https://`, and `www.spu.edu.sy` / `v2.spu.edu.sy` → `https://spu.edu.sy` (301, path and query kept). |
| `web` | `spu-web` (this Dockerfile) | Apache 2.4 + PHP 8.4, the site and the admin panel. `public/.htaccess` is the same file as on cPanel, so every legacy file rule and front-controller rule behaves the same. |
| `queue` | `spu-web` | `queue:work database` (form mail, event registrations). |
| `scheduler` | `spu-web` | `schedule:work` (site statistics, scheduled publishing, sitemap, search index, image derivatives). |
| `release` | `spu-web` | One-off per deploy: maintenance window, migrations (with the data-migration gate), redirect seed, search index, sitemap, cache warm, `launch:validate`, `continuity:validate-redirects`. |
| `legacy-apps` | `spu-legacy-apps` | PHP 8.3 for `/forms`, `/Medicine`, `/spuh`, the three live applications from the old document root. |
| `db` | `mariadb:10.11` | Same major version as cPanel (10.11.19). Holds `spuedu_v2` and the legacy apps' databases. |

Why Apache and not nginx: the old-site continuity rules, the `app.php` front
controller trick (`/index.php?...` is a legacy URL, not the front controller)
and the legacy apps' own `.htaccess` files are all Apache configuration. Keeping
Apache means none of it is translated, so none of it can change by accident.

## Layout on the server

```
/srv/spu/
  src/            this repository, checked out at the deployed commit
  stack.env       compose settings and database passwords        (chmod 600)
  app.env         Laravel .env, built from the cPanel one          (chmod 600, owner 33)
  secrets/        ftp.env or cpanel-api.env, cpanel.env (the copied cPanel .env)
  data/
    storage/      Laravel storage/ (uploads, media library, site stats)
    web/          the document root: the release's public/ plus files written
                  at runtime (sitemap.xml, sitemaps/, image derivatives)
    legacy/       the old site's media, mounted read-only at public/_legacy
    legacy-apps/  forms/, Medicine/, spuh/
    mysql/        MariaDB data
    caddy/        certificates Caddy obtained
    certs/        certificate files for SPU_TLS_MODE=files
  backups/        db/ dumps and files/ snapshots (backup.sh)
  dumps/          database dumps brought over from cPanel
```

`data/legacy` mirrors the cPanel `_legacy/` symlinks:
`downloads/files`, `downloads/files2`, `downloads/root` (loose files of
`/downloads/`), `images`, `<subsite>/images` for med, dent, pharm, info,
petrol, admin, research, hospital, dent_clinic, alumni, clubs, and `FCKBIH`.
`cv_bank` (applicants' CVs) is deliberately not copied, as on cPanel.

## Everyday commands

`bootstrap-vps.sh` links `bin/spu` to `/usr/local/bin/spu`:

```bash
spu ps                                  # what is running
spu logs -f web                         # Apache + PHP log (also caddy, queue, scheduler)
spu artisan migrate:status              # any artisan command, as www-data
spu artisan continuity:validate-redirects --probe
/srv/spu/src/deploy/docker/scripts/deploy.sh          # release the latest dev
/srv/spu/src/deploy/docker/scripts/deploy.sh <sha>    # or a given commit
/srv/spu/src/deploy/docker/scripts/backup.sh          # databases + file snapshot
```

`deploy.sh` builds the new image while the old one keeps serving, dumps every
database, runs `release`, and only then recreates the services. If `release`
fails, the site stays on the previous image; `artisan up` is run on the way out
so it is not left in maintenance. To go back a release:
`SPU_IMAGE_TAG=<old sha> spu up -d` (images are tagged by commit), and restore
the dump `deploy.sh` took if that release migrated.

The approval-file data jobs of `cpanel-deploy.sh` (study plans, people unify,
faculty positions, ...) are not part of `release`. With a shell they are run
directly when wanted, dry run first, for example
`spu artisan faculty:import-study-plan business-administration <file> --user=1`.

## Settings that matter

`stack.env` (see `env/stack.env.example`):

- `SPU_TLS_MODE`: `internal` (self-signed, for testing before DNS moves),
  `files` (certificate files in `data/certs`, e.g. the `*.spu.edu.sy`
  certificate from cPanel, so HTTPS works the moment DNS moves), `auto`
  (Let's Encrypt; needs DNS already pointing here).
- `SPU_CANONICAL_HOST` / `SPU_ALIAS_HOSTS`: the one address and the names
  that 301 to it. Must agree with `APP_URL` / `APP_CANONICAL_URL` in app.env.
- `SPU_HSTS`: keep identical to the application's HSTS (`HSTS_*` in app.env).
- Page cache warm-up: `/etc/cron.d/spu-cache-warm` runs `cache:warm` every 15
  minutes (about 20 s; home page, main landing pages, menus), so pages stay
  cached after a publish empties the cache. `migrate.sh after-dns` installs it.
- `SPU_INDEXING`, `SPU_WRITES`: `on` normally. `off` only while a temporary
  preview address is on (`migrate.sh preview on preview.spu.edu.sy`, before the
  switch): every response gets `X-Robots-Tag: noindex`, and Caddy answers
  anything but GET/HEAD/OPTIONS with 403, since that copy's database is
  overwritten by `final` (a form sent there would be lost). `preview on` needs an
  A record for the name pointing here; it saves `stack.env` and `app.env`, sets
  the preview name as canonical (`APP_URL`, `APP_CANONICAL_URL`, host-only
  `SESSION_DOMAIN`), gives the real names plain HTTP only (no certificate
  requests for names that still point at the old host) and restarts.
  `preview off` puts the saved files back; every step, `final` and `after-dns`
  refuse to run while the preview is on.
- `SPU_LEGACY_MEDIA`, `SPU_LEGACY_APPS`: `local` once the files are copied;
  `remote` proxies them to the old host (a bridge if a copy is unfinished; the
  old host must then stay up). `SPU_LEGACY_APPS=off` retires `/forms`,
  `/Medicine` and `/spuh` (they answer 404); with it set `COMPOSE_PROFILES=""`
  and `APPS_MODE=off` in `secrets/migrate.conf`, so step 7, `final` and the
  old-links check skip them. Retired on 2026-11-21 by product decision; their
  databases stay loaded on the VPS.
- `INNODB_BUFFER_POOL_SIZE`, `APACHE_MAX_WORKERS`: size to the VPS.

`app.env` is the cPanel `.env` with only host-specific keys changed
(`scripts/make-app-env.sh`, list in `env/app.env.overrides`). `APP_KEY` and
`ADMIN_PATH` must stay the same: the key opens every encrypted value (2FA
secrets, sessions, the assistant's sealed memory), and staff already know the
admin address.

## How the proxy chain keeps Laravel honest

Caddy → Apache is plain HTTP inside the Docker network. Apache's `mod_remoteip`
takes the client address from `X-Forwarded-For` (only from the proxy subnet),
so throttles, the audit log and `stats-hit.php` see the visitor, not Caddy.
`SetEnvIf X-Forwarded-Proto ^https$ HTTPS=on` tells PHP the request was HTTPS,
so URLs and secure cookies stay on https **without** widening the application's
trusted-proxy list in `bootstrap/app.php`. `ServerName https://spu.edu.sy`
makes Apache's own redirects (trailing slash, `/forms` → `/forms/`) use https.

## Tested

Built and run locally on 2026-11-17 against an empty database: all services
healthy; `http://`, `www` and `v2` each answer one 301 to
`https://spu.edu.sy` with path and query kept; trailing-slash and `/forms`
redirects keep https; `/med`, `/index.php?lang=2`, `/admin` give the same 301
targets as v2; legacy files under `/images`, `/downloads/files` and `/_legacy`
answer 200 and a `.php` among them 404; `/forms/` runs on PHP 8.3 and reaches
MariaDB over the shared socket; `robots.txt` invites crawling; the file cache
stores values; `launch:validate --environment=production` passes everything
that does not need real content. Not tested: real data (no dump here), TLS
modes `auto` and `files`.
