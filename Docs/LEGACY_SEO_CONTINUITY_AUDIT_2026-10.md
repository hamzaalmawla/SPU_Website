# Legacy URL continuity and SEO audit — October 2026

**Date:** 2026-10-11 · **Deployed commit audited:** `1ede080` · **Database:** deploy
dump `spuedu_v2-20261010-090008` (the one `SPU_DATABASE_BACKUP_REFERENCE` names)

Purpose: when spu.edu.sy moves to the Laravel site, every URL that search
engines, link indexes (Ahrefs and Majestic — the two sources Webometrics uses
for Visibility) and people actually use must land on its real equivalent in one
permanent hop, or return an honest 404 when nothing equivalent exists. This
records what was measured, what was fixed, and what still needs a decision.

Companion: `Docs/LEGACY_REDIRECT_MAINTENANCE_GUIDE.md` §14 (rules for anyone
changing the code).

---

## 1. Evidence

| Source | What it is | Size |
|---|---|---|
| Old sitemap `https://spu.edu.sy/sitemap.xml` | What search engines were fed | 28,488 unique URLs |
| Raw nginx access logs, `/home/spuedu/logs/spu.edu.sy-*-{Sep,Oct}-2026_NGINX.gz` | What was actually requested, 1 Sep – 11 Oct | 11.2 M unique URLs |
| Old document root listing | Files Apache served outside the CMS | 97 entries |
| v2 sitemap and full crawl | Every page the new site publishes | 5,508 sitemap URLs |

Traffic in 10 days of October: Bingbot 1.23 M requests, AhrefsBot 228 k,
SemrushBot 140 k, Googlebot 62 k, MJ12bot (Majestic) 37 k. The link indexes
Webometrics reads are crawling the old URL space heavily right now.

The log corpus was reduced to URLs the old site answered (2xx/3xx/304) and that a
search engine, SEO indexer or person requested. 2.47 M of those are crawler traps
of the form `/index.php/<dirs>/index.php?…` that the old server answered as
duplicates; v2's `.htaccess` 404s them at the edge, which is the right outcome.
**418,914 distinct real legacy URLs remain** (deduplicated on everything that
affects resolution — path, language, page, dir, service, record id, faculty code;
pure paging and tracking parameters collapsed).

Every one of them, and the whole old sitemap, was replayed through the deployed
code and the production database (a local replica — see §6), following each
redirect to its final status.

## 2. Results

### Old sitemap (28,428 URLs)

| Outcome | Before | After |
|---|---:|---:|
| One permanent 301 to the real page | 10,443 (36.7%) | **26,111 (91.8%)** |
| 302 to the `/en` homepage (retired FR/ES/DE with no English equivalent) | 17,055 (60.0%) | 1,389 (4.9%) |
| 404 | 926 (3.3%) | 926 (3.3%) |
| Redirect chains | 2 | 0 |

The 926 sitemap 404s are unchanged on purpose. They are news and announcement
items that are unpublished or were never imported, plus unreviewed list pages —
§4. Two more sitemap entries were not URLs at all (free text in the old
sitemap).

### Crawled corpus (418,914 distinct URLs, Sep–Oct 2026)

Weighted by what was actually requested (search-engine, SEO-indexer and human
requests, 7.37 M in total), after the fixes in #54–#56:

| Outcome | Share of requests | Distinct URLs |
|---|---:|---:|
| One permanent 301 to the real page | **71.8%** | 212,935 (50.8%) |
| 302 locale negotiation / section root | 1.4% | 6,683 (1.6%) |
| 302 `/en` fallback (retired language, no English equivalent) | 13.1% | 88,973 (21.2%) |
| 404 | 13.7% | 110,310 (26.3%) |
| Redirect chains | 0.0% | 1 |

On the 70,044 URLs that were also replayed before the fixes, the one-hop 301
share went from 30.9% to 74.9%, and the homepage fallback from 52.4% to 13.0%.

#57 then resolved the largest remaining fixable 404 family: the paged old News
and Announcements lists (174 URLs, about 248,000 requests) and the old site
search (217 URLs, about 5,200 requests). That moves roughly another 3.4% of all
requests from 404 to a one-hop 301.

What remains 404 by traffic is, in order: the old `/images/` directory listing (a
single URL hit 109k times by a hotlinking site), news and announcement records
that are not published, root about-items outside the reviewed allow-list
(`service=1`), and service numbers that belong to a different subsite. All are
covered in §4.

## 3. What was fixed

1. **Retired languages resolve to the same record in English.** 60% of the old
   sitemap and roughly half the crawled corpus are FR/ES/DE copies (`lang=3/6/7`).
   They all went to the English homepage with a temporary redirect — a soft 404
   to search engines, and the equity of the specific record was discarded. They
   now resolve as their `lang=2` twin through the full chain and receive that
   destination permanently. The approved 302 `/en` applies only when the twin has
   no destination.
2. **Directory spellings of router URLs** (`/?cat_id=…&dir=items…`, `/med/?lang=2`,
   `/alumni/?dir=…`, `/members/?…`) resolve like their `index.php` spelling, which
   is what Apache served. When that names nothing precise, the previous
   directory handling still applies, so nothing that resolved before regressed.
3. **`&amp;`-escaped query keys** (`?page=show&amp;dir=items…`) — copied by bots
   from old hrefs — are decoded.
4. **Faculty graduate lists** (`/{faculty}/index.php?dir=graduated_students&page=list…`
   with paging and year) resolve to the alumni directory filtered by faculty and
   year. Only the alumni subsite's bare list was mapped before.
5. **Dental clinic and hospital staff pages** (`dir=councils`, `councils7`) land
   on their section page, exactly as faculty staff pages already did.
6. **A two-hop chain** (legacy academic-warnings category) now lands in one hop.
7. **Ownership tokens.** The Ahrefs, Majestic and two Google Search Console
   verification files lived only in the old document root and 404'd on v2.
   Losing them at cutover loses verified ownership of the domain's link and
   indexing data. They now ship in `public/` and the deploy installs them.
8. **`favicon.ico` was an empty file** (0 bytes) in the repository and on v2.
   Search engines and browsers request it by name. A real multi-size icon and an
   `apple-touch-icon.png` (plus sized/precomposed variants via `.htaccess`) now
   ship, generated from the site logo.
9. **`/FCKBIH/`** — the old editor's upload tree, referenced from old article
   bodies and from migrated ones — was not mounted. It is now served read-only
   through `_legacy/` with the same never-execute, never-markup guards as the other
   legacy trees. Loose files directly in `/downloads/` likewise.
10. **Events calendar crawl trap.** Previous/next month links walked without end
    (the crawl reached the 1760s and the 2200s), every month `index,follow`.
    Month views are now `noindex,follow` (canonical was already the calendar) and
    the navigation links are `rel="nofollow"`.
11. **Job-application canonical/hreflang** named the bare `/apply` URL, which is a
    404 without a job. They now carry the job.
12. **Sitemap listed a redirecting URL** (`/about/directorates/it-services`). Removed;
    its destination was already listed.
13. **Subsite roots in one hop** (#55): `/med/?lang=2` used to be slash-stripped by
    Apache before the continuity redirect. It now resolves in one hop.
14. **Project reports that never existed** (#56): 20 project pages offered a
    download for a file missing on the old site as well. A report is now offered
    only when its file is present in the mounted legacy tree.
15. **Paged legacy lists and the old search** (#57), as above.

## 4. Deliberately still 404 or fallback — decisions for SPU

These are not oversights. Each needs content or an editorial decision, and a
guessed redirect would hide the URL from `unresolved_legacy_requests`.

| Family | Why | What would fix it |
|---|---|---|
| Root news/announcements not published (`/index.php?…dir=items&page=show&service=3|4`) | 194 are imported drafts whose bodies are effectively empty (Arabic avg 23 chars); 174 were never imported, and a sample shows some do carry real text | Editorial review of the non-imported items; publish those with content |
| Subsite `items` URLs whose service number belongs to another subsite (e.g. `/petrol/…service=24`) | Invariant 1.3 of the maintenance guide | None — correct |
| Alumni subsite content items (`/alumni/index.php?…service=111–119`) | No alumni news/content section on the new site | Decide whether alumni content is recreated |
| Root `/index.php?…service=11` (student card offers) | Content does not exist on the new site (A5 in `V2_PRE_CUTOVER_ACTIONS.md`) | Recreate or retire |
| Root `service=1` about-pages not in the allow-list (e.g. `cat_id=2287, 2219, 2315`) | Not in the reviewed category allow-list | Review and regenerate `config/legacy_category_routes.php` |
| `/Portals/…`, `/SiteMap/…/Default.aspx` | Pre-2014 site; already 404 on the old site | None |
| `/index.php/<dirs>/index.php?…` | Crawler traps, duplicates | None — 404 is right |

## 5. Cutover risks found outside the application

- **The primary domain's document root.** cPanel fixes it at `public_html`, and the
  cutover script does not move it. Whatever mechanism is chosen (WHM docroot
  change or a rewrite in `public_html`), anything in the old root that is not
  mounted into the new docroot disappears. Everything with search or link value
  is mounted under `_legacy/`. The two live PHP applications, **`/forms`**
  (surveys, complaints) and **`/Medicine`** (Faculty of Medicine recruitment),
  are mounted at their own paths by `cpanel-deploy.sh` (`ensure_app_mount`), so
  they keep answering at the same URLs. Each carries an `.htaccess` with
  `DirectoryIndex index.php`; the Laravel docroot's `app.php` would otherwise be
  inherited. The deploy refuses to mount an app without that file.
  `/CMS_SPU_SyrianMonster_17` (the old CMS) is deliberately not mounted, so it
  retires at cutover.
- **Exposure fixed on the live old site (2026-10-11).** `/Medicine` published every
  applicant's CV with a directory listing, plus its PHP session files, error log
  and schema. Source archives (`/forms_spu.zip`, `/forms-29-8-26.zip`, the old
  CMS `engine.zip`), `/error_log2`, `composer.lock`, `phpunit.xml` and directory
  listings (including all of `/downloads/files/`) were public. CVs are now served
  only to a logged-in admin (`Medicine/admin/cv.php`). The internals and archives
  return 403, and listings are off site-wide. Originals are in
  `/home/spuedu/backups/old-site-hardening-20261011/`. Because the session
  directory was listable, **change the `/Medicine` admin password** (its
  default is hard-coded in `config/config.php`) to invalidate any captured
  session, and treat the CVs as having been publicly exposed.
- **The docroot `.htaccess` is hand-maintained** and is not deployed from git. The
  new rules in `public/.htaccess` (FCKBIH, `/downloads/` loose files, touch-icon
  variants, `Options +FollowSymLinks`) must be applied to the server copy, keeping
  its STAGING ONLY blocks until cutover.
- **ea-nginx caches responses for about an hour.** Purge it
  (`NginxCaching::clear_cache`) after every deploy that changes redirects or static
  files, or old answers keep being served.
- **Internal links inside migrated content** still point at old absolute URLs
  (`http://www.spu.edu.sy/index.php?…`, `/downloads/files/…`). They resolve, but
  each costs a redirect hop; rewriting them in content is a separate, approved data
  migration.

## 6. How to reproduce

1. Logs: copy the `.gz` files from `/home/spuedu/logs/` into a random directory
   under the v2 docroot protected by `Require ip <your-ip>`, download, delete it,
   then **purge the nginx cache** — ea-nginx otherwise keeps serving the deleted
   files for up to an hour to anyone with the URL.
2. Database: the newest `/home/spuedu/backups/spu_v2/spuedu_v2-*.sql.gz`, imported
   into a private MariaDB.
3. A worktree of the deployed commit with production-style env, `artisan
   optimize`, served by `php -S` through a router that sets
   `SCRIPT_NAME=/app.php` (invariant 1.1). Pages are force-gzipped; decompress
   before parsing.
4. Replay each URL without following redirects automatically; record the chain
   to the final status. Destination pages are shared, so memoise them.
5. Never replay against the live host — the old site is the one serving real
   visitors.
