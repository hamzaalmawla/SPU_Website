# v2.spu.edu.sy — staging overlay

Everything the deployed host has that the repository does not. Nothing about the
v2 deployment should exist only on cPanel; if it does, it is lost the moment the
account is rebuilt.

The application code on the server is byte-identical to the repository — verified
by hashing all 808 deployed PHP files. What follows is the rest.

---

## 1. Files that differ from the repo, and why

| Path in the web root | Repo version | Deployed version |
|---|---|---|
| `.htaccess` | ships as-is at cutover | repo file **plus** the staging blocks in §2 |
| `robots.txt` | app serves its own via `SitemapController` | replaced by the disallow-all in §3 |
| `.user.ini` | same file | same file |

`app.php`, `index.php`, `site.webmanifest` and every PHP file are identical.

---

## 2. The `.htaccess` staging overlay

The deployed `.htaccess` is the repository's `public/.htaccess` with two blocks
added. Both are marked `STAGING ONLY` in the deployed file. **Remove both at
cutover** and the repository file is what ships.

Prepended at the very top:

```apache
<IfModule mod_headers.c>
    # STAGING ONLY — this host must never be indexed while spu.edu.sy is live.
    Header always set X-Robots-Tag "noindex, nofollow, noarchive"
</IfModule>
```

Inserted immediately after `RewriteEngine On`, along with `Options +FollowSymLinks`
(needed because the legacy media trees are symlinks):

```apache
    Options +FollowSymLinks

    # STAGING ONLY — host guard.
    # This document root physically lives under public_html, so it is also
    # reachable as spu.edu.sy/spu_v2/public/... Refuse to answer there so the
    # new site has exactly one address and cannot shadow the old site.
    RewriteCond %{HTTP_HOST} !^v2\.spu\.edu\.sy$ [NC]
    RewriteRule ^ - [R=404,L]
```

At cutover, `Options +FollowSymLinks` must be **kept** if the legacy media
symlinks are kept. Only the two `STAGING ONLY` blocks go.

---

## 3. The staging `robots.txt`

The deployed file replaces the application's own robots route so the rehearsal
host cannot be indexed:

```
# v2.spu.edu.sy is the pre-cutover staging host for the new SPU website.
# The canonical site is https://spu.edu.sy — this host must not be indexed.
# At cutover: delete this file so the application serves its own robots.txt.
User-agent: *
Disallow: /
```

**Delete this file at cutover** so `SitemapController@robots` serves the real one.

---

## 4. Filesystem layout and symlinks

cPanel forces subdomain document roots under `public_html`, so the application
lives outside the web root and only the front controller is inside it.

```
/home/spuedu/spu_v2_app                  application (.env chmod 600)
/home/spuedu/public_html/spu_v2/public   document root for v2.spu.edu.sy
/home/spuedu/.spu_v2_tools/composer.phar composer
```

Recreate with:

```bash
APP=/home/spuedu/spu_v2_app
WEB=/home/spuedu/public_html/spu_v2/public
OLD=/home/spuedu/public_html

# public_path() must resolve, or @vite silently emits no stylesheet and the
# whole site renders unstyled.
ln -sfn "$WEB" "$APP/public"

# storage:link equivalent
ln -sfn "$APP/storage/app/public" "$WEB/storage"

# Legacy media: symlinked read-only, never copied. downloads/files alone is
# 17 GB and the account has ~12 GB of quota free.
mkdir -p "$WEB/_legacy/downloads"
ln -sfn "$OLD/downloads/files"  "$WEB/_legacy/downloads/files"
ln -sfn "$OLD/downloads/files2" "$WEB/_legacy/downloads/files2"
ln -sfn "$OLD/images"           "$WEB/_legacy/images"
for f in med dent pharm info petrol admin research hospital dent_clinic alumni clubs; do
  mkdir -p "$WEB/_legacy/$f"
  ln -sfn "$OLD/$f/images" "$WEB/_legacy/$f/images"
done
```

`cv_bank` is deliberately **not** linked — it holds applicant CVs and was
removed from scope. Do not re-add it without an explicit decision.

---

## 5. cPanel configuration

Not files, so not in git. Recreate through cPanel or its API:

| Setting | Value |
|---|---|
| Subdomain | `v2.spu.edu.sy`, docroot `public_html/spu_v2/public` |
| PHP version | `ea-php84` — **on this vhost only**; `spu.edu.sy` stays on `ea-php83` |
| SSL | the account's wildcard `*.spu.edu.sy` certificate |
| nginx caching | enabled for the vhost |
| Databases | `spuedu_v2` (app) and a **SELECT-only** user on `spuedu_db` (legacy) |

Cron (the app's own scheduler and queue worker):

```cron
* * * * * /opt/cpanel/ea-php84/root/usr/bin/php /home/spuedu/spu_v2_app/artisan schedule:run
* * * * * /opt/cpanel/ea-php84/root/usr/bin/php /home/spuedu/spu_v2_app/artisan queue:work --stop-when-empty --max-time=50
```

---

## 6. `.env`

Not in git and must not be. The deployed values differ from
`.env.production.example` in three ways worth knowing:

- `CACHE_STORE=file` — there is no Redis or Memcached on this host, and `file`
  benchmarks 5× faster than `database` while keeping page HTML out of MySQL.
- `SESSION_DRIVER=database`, `QUEUE_CONNECTION=database` — same reason.
- `OLD_DB_*` points at `spuedu_db` through a **SELECT-only** user, so the new
  site can never write to the legacy database.

Credentials are recorded outside the repo, on the operator's machine.

---

## 7. Database content

The migrated content — 2,145 articles, 9,881 attachments, 253 research
publications, 260 faculty members, 4,939 alumni — lives in `spuedu_v2` and is
**not** in git, which is correct: it is content, not code.

It is reproducible from the legacy database by re-running the documented import
pipeline. See `Docs/LEGACY_REDIRECT_MAINTENANCE_GUIDE.md` §12 for the commands
and approval tokens.

Two pieces of that work *are* in git, because they are code:

- `database/seeders/LegacyEntryPointRedirectSeeder.php` — the 25 deterministic
  redirect rows.
- `config/legacy_category_routes.php` — the generated 277-id allow-list.
