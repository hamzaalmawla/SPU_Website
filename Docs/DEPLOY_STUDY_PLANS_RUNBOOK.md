# Task: deploy the SPU study-plan release to v2.spu.edu.sy

You are Claude Code, running over SSH as the cPanel user `spuedu` on the SPU server. A human (the
professor) is supervising you and can answer questions. Your job is to deploy a release that is already
merged on the `dev` branch, verify it, and report back. You are **not** here to change anything else.

Steps marked **STOP** need the human's explicit "go ahead" before you continue. Show them the output you
are asking about. Keep your reports short and factual, and never guess.

## What this release is

- Faculty study plans: a new mobile layout and graph on the public pages, and a visual editor plus
  lecture-PDF upload in the admin panel (each faculty's "Study Plan" page).
- A guarded import command `faculty:import-study-plan` and the converted curricula for five faculties
  (data only, shipped as JSON files; they are NOT loaded into the site by the deploy itself).
- **No database migrations. No composer or npm dependency changes. No new `.env` settings.**
- The release also contains the last ~26 upstream commits already on `dev` (legacy URL continuity, project
  pages, deploy fixes).

**The most important requirement:** deploying code must NOT change any content the editors wrote on the
live site (pages, news, study plans, menus, media, anything). Steps 1 and 4 prove that. If the proof
fails, stop and report.

## Rules: never do any of these

- Never run `migrate:fresh`, `migrate:rollback`, `db:wipe` or `db:seed`. Never write to the database by
  hand (no INSERT/UPDATE/DELETE, no `tinker` statements that write). The only database reads you need are
  the read-only snippets in this document.
- Never print, copy, edit or commit `.env`. The deploy script itself may update only its own
  `SPU_DATABASE_BACKUP_REFERENCE=` line. You may read just the `APP_ENV` and `SPU_DATABASE_BACKUP_REFERENCE`
  lines with `grep`. Never paste secrets or tokens anywhere.
- Never set `SPU_ALLOW_DATA_MIGRATIONS` or `SPU_DEPLOY_ENV`.
- Never touch `storage/`, `bootstrap/cache`, or the hand-maintained `.htaccess` / `.user.ini` in the web root.
- Never use `git push`, `git reset`, `git clean`, `git checkout -- <file>`, or any force flag. In the repository
  you may only use `git status`, `git log`, `git fetch`, `git pull --ff-only`, `git rev-parse`, `git merge-base`.
- Never publish content, never pass `--replace-edited` to the import command.
- If anything unexpected happens, stop, keep the log, and report. Do not improvise, retry blindly, or roll back
  on your own.

## Facts about this server (from Docs/DEPLOYING.md)

| | |
|---|---|
| Git clone the deploy runs from | `/home/spuedu/repositories/spu-v2` (branch `dev`) |
| Application directory | `/home/spuedu/spu_v2_app` |
| Web root | `/home/spuedu/public_html/spu_v2/public` |
| PHP | `/opt/cpanel/ea-php84/root/usr/bin/php` |
| Deploy script (the same one cPanel's `.cpanel.yml` runs) | `deploy/v2-staging/cpanel-deploy.sh` |
| Site | `https://v2.spu.edu.sy` |

Faculty slugs: `artificial-intelligence`, `business-administration`, `building-construction-engineering`,
`dentistry`, `medicine`, `petroleum`, `pharmacy`.

The deploy script takes the site down briefly (`artisan down`), syncs code, runs composer, runs
`migrate --force` (nothing to migrate in this release), rebuilds caches, and brings the site back up. It ends
with a line `▸ Deployed <commit>`.

## Step 0: read-only preflight

```bash
whoami
cd /home/spuedu/repositories/spu-v2
git status --porcelain            # must print nothing; if it prints anything: STOP
git rev-parse --abbrev-ref HEAD   # must be: dev
git rev-parse HEAD                # write this down: it is the PREVIOUS release commit
git fetch origin
git log --oneline HEAD..origin/dev | cat
git merge-base --is-ancestor HEAD origin/dev && echo "fast-forward OK"
git merge-base --is-ancestor 05fa276 origin/dev && echo "release commit present"
df -h /home/spuedu | tail -1
grep -E '^APP_ENV=' /home/spuedu/spu_v2_app/.env
curl -s -o /dev/null -w "homepage before: %{http_code}\n" -H 'Cache-Control: no-cache' https://v2.spu.edu.sy/en
```

Both "fast-forward OK" and "release commit present" must print. If not, **STOP** and report (the release has
not been pushed to `dev` yet, or the clone has local changes).

## Step 1: fingerprint the live content BEFORE deploying (read-only)

```bash
cd /home/spuedu/spu_v2_app
/opt/cpanel/ea-php84/root/usr/bin/php artisan tinker --execute='
$rows = \DB::table("cms_target_contents")->selectRaw("target_key, status, updated_at, MD5(payload_json) as h")->get()->sortBy("target_key");
foreach ($rows as $r) { echo $r->target_key,"|",$r->status,"|",$r->updated_at,"|",$r->h,"\n"; }
echo "DRAFTS|",\DB::table("cms_drafts")->count(),"|",\DB::table("cms_drafts")->max("id"),"|",\DB::table("cms_drafts")->max("version"),"\n";
' 2>/dev/null | grep '|' > ~/fingerprint-before.txt
wc -l ~/fingerprint-before.txt
grep -c 'study_plan' ~/fingerprint-before.txt
```

This lists every published CMS item with a hash of its content (hashed inside MySQL, and sorted in PHP,
because sorting the large JSON in MySQL fails with "Out of sort memory"), plus the draft counters. It writes
nothing to the database. The file should have one line per published CMS item plus the `DRAFTS|...` line (a development copy had
1,368 lines; the server's count will differ). If it has only the `DRAFTS` line, or is empty, the query failed:
**STOP** and report the error (run the snippet again without `2>/dev/null` to see it).

**STOP 1.** Show the human: the previous commit, the commits about to be deployed (`git log --oneline
HEAD..origin/dev`), the `APP_ENV` line, free disk space, and the fingerprint line counts. Wait for "go ahead".

## Step 2: deploy

Run the deploy script detached so a dropped SSH session cannot leave the site in maintenance mode:

```bash
cd /home/spuedu/repositories/spu-v2
git pull --ff-only origin dev
git log -1 --format='%H %s'
STAMP=$(date -u +%Y%m%d-%H%M%S)
nohup /bin/bash deploy/v2-staging/cpanel-deploy.sh > ~/deploy-$STAMP.log 2>&1 &
echo "pid $!  log ~/deploy-$STAMP.log"
```

Poll until it finishes (check every ~20 seconds, do not run other commands that touch the app meanwhile):

```bash
kill -0 <pid> 2>/dev/null && echo running || echo finished
tail -n 25 ~/deploy-$STAMP.log
```

If `git pull --ff-only` fails, or the script exits non-zero, or the log shows a line starting with `✗`:
**STOP** and report the full tail of the log. Do not re-run the script yourself.

## Step 3: verify the deploy

```bash
grep -n '▸ Deployed' ~/deploy-$STAMP.log          # must show the commit from "git log -1" above
grep -n '✗\|ERROR\|Exception' ~/deploy-$STAMP.log  # review anything it prints
ls /home/spuedu/spu_v2_app/storage/framework/down 2>/dev/null && echo "STILL IN MAINTENANCE" || echo "site is up"
grep -E '^SPU_DATABASE_BACKUP_REFERENCE=' /home/spuedu/spu_v2_app/.env   # report it (names the pre-deploy backup)

for f in artificial-intelligence business-administration building-construction-engineering dentistry medicine petroleum pharmacy; do
  for l in ar en; do
    printf "%s/%s: " "$f" "$l"
    curl -s -o /dev/null -w "%{http_code}\n" -H 'Cache-Control: no-cache' "https://v2.spu.edu.sy/$l/faculties/$f/study-plan"
  done
done
curl -s -o /dev/null -w "admin login: %{http_code}\n" https://v2.spu.edu.sy/admin/login
curl -s -H 'Cache-Control: no-cache' https://v2.spu.edu.sy/en/faculties/artificial-intelligence/study-plan | grep -c 'data-study-plan-list'
curl -s "https://v2.spu.edu.sy/build/manifest.json?b=$RANDOM" | grep -c 'studyPlan'
```

Expected: every status is `200` (note any that are not, and compare with Step 0's homepage result); the last two
counts are `1` or more (proves the new code is live; the `?b=` parameter avoids nginx's stale static cache).

## Step 4: prove no editor content changed

```bash
cd /home/spuedu/spu_v2_app
/opt/cpanel/ea-php84/root/usr/bin/php artisan tinker --execute='
$rows = \DB::table("cms_target_contents")->selectRaw("target_key, status, updated_at, MD5(payload_json) as h")->get()->sortBy("target_key");
foreach ($rows as $r) { echo $r->target_key,"|",$r->status,"|",$r->updated_at,"|",$r->h,"\n"; }
echo "DRAFTS|",\DB::table("cms_drafts")->count(),"|",\DB::table("cms_drafts")->max("id"),"|",\DB::table("cms_drafts")->max("version"),"\n";
' 2>/dev/null | grep '|' > ~/fingerprint-after.txt
diff ~/fingerprint-before.txt ~/fingerprint-after.txt && echo "IDENTICAL: no editor content changed"
```

The two files must be identical. A single differing line means content changed during the deploy (or an editor
saved something meanwhile; say which target). **STOP 2.** Report: the deployed commit, the verification results,
the backup reference, and the diff result. Wait for the human before doing anything else.

## Step 5 (only if the human asks): load the new curricula as UNPUBLISHED drafts

The import command never publishes and **skips any study plan that editors have ever saved or published** (it
prints `SKIPPED`). First a dry run, which writes nothing:

```bash
cd /home/spuedu/spu_v2_app
ACTOR=$(/opt/cpanel/ea-php84/root/usr/bin/php artisan tinker --execute='echo (string) (\DB::table("users")->where("role_slug","super_admin")->where(function ($q) { $q->whereNull("locked_at")->orWhere("locked_at",""); })->orderBy("id")->value("id") ?? "");' 2>/dev/null | grep -Eo '^[0-9]+$' | head -n 1)
echo "actor=$ACTOR"
for f in artificial-intelligence building-construction-engineering petroleum pharmacy medicine; do
  echo "=== $f"
  /opt/cpanel/ea-php84/root/usr/bin/php artisan faculty:import-study-plan "$f" "database/seeders/Data/study_plans/$f.json" --user="$ACTOR" --no-ansi
done
```

**STOP 3.** Show the human the result for each faculty (`SKIPPED` or "Dry run: the data is valid"). Only for the
faculties the human approves **and** that were not skipped, run the same command again with `--write` added.
Then repeat the Step 4 fingerprint: `cms_target_contents` lines must still be identical to before, and the
`DRAFTS` count must have grown by exactly the number of plans written. Tell the human to open each faculty's
admin page, Study Plan, review the draft, preview it, and publish it themselves. You never publish.

## If anything goes wrong

Stop. Keep `~/deploy-$STAMP.log`. Report: which step, the exact error, the previous commit from Step 0, and the
backup reference from Step 3. The human decides about any rollback or about bringing the site back up; do not
run `artisan up`, `git reset` or restore a backup on your own.
