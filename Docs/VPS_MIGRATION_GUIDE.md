# Moving the website to the new server: how to run the script

This guide is for the person who runs the move. You do not need to know Docker
or Laravel. One script, `migrate.sh`, does the work. It asks you questions and
waits for you to press **Enter** before each step.

The script never changes anything on the old server, and it never changes DNS.
It only tells you what to change, and when.

Technical background: [VPS_MIGRATION_RUNBOOK.md](VPS_MIGRATION_RUNBOOK.md).

---

## 1. Have these ready before you start

| What | Where to find it |
|---|---|
| The new server's **IP address** and its **root password** (or a user with `sudo`) | Your VPS provider's panel |
| The **FTP password** of `it@spu.edu.sy` | cPanel → FTP Accounts (you can set a new password there if nobody knows it) |
| The **cPanel password** of user `spuedu` | The one you use to log in to cPanel |
| A **GitHub token**, only if GitHub asks for a login | GitHub → Settings → Developer settings → Personal access tokens → Fine-grained → only the `SPU_Website` repository → *Contents: Read-only* |
| The **e-mail (SMTP) settings**, only if the site sent mail through the old server itself | Your mail administrator |

Time: about 1 hour of your attention. The copy of the old images and downloads
(about 20 GB) runs by itself and can take several hours.

---

## 2. Put the script on the new server

Get `migrate.sh` (from the project files, or from the repository at
`deploy/docker/migrate.sh`). Then, on your Windows computer, open
**PowerShell** in the folder where the file is and type (put the real IP):

```
scp migrate.sh root@NEW-SERVER-IP:/root/
```

Or use **WinSCP**: connect to the new server and drag `migrate.sh` into `/root`.

---

## 3. Run it

Connect to the new server:

```
ssh root@NEW-SERVER-IP
```

Then:

```
sudo bash /root/migrate.sh
```

- First it asks: *Run inside tmux (recommended)?* Answer **y**. Then the work
  continues even if your internet or SSH connection drops.
- Before each step it shows what comes next. Press **Enter** to go on, or type
  **q** to stop. Next time it starts where you stopped.
- A value in `[brackets]` is the suggested answer. Press **Enter** to accept it.
- Passwords are hidden while you type (or paste). That is normal.
- Yes/no questions: type **y** or **n**. On an Arabic keyboard the same keys
  (غ for yes, ى for no) work too.

### If the connection drops

Connect again with `ssh` and type:

```
sudo tmux attach -t spu-migrate
```

You are back where you were. If it says there is no session, just run
`sudo bash /root/migrate.sh` again: it continues from the last step that did
not finish.

---

## 4. The 15 steps

| # | Step | What it asks you | Time |
|---|---|---|---|
| 1 | Check this server, install Docker and tools | Nothing (maybe: stop an old web server on port 80) | 2–5 min |
| 2 | Questions | Main address (`spu.edu.sy`), this server's IP, old server's IP, cPanel address and user, FTP account and password | 2 min |
| 3 | Download the code | A GitHub token only if GitHub asks | 1 min |
| 4 | Prepare folders and firewall | Turn on the firewall? (**y**) | 1 min |
| 5 | Test the connection to the old server | The cPanel password (kept in memory only) | 1 min |
| 6 | Copy the website's own files | Nothing | 5–20 min |
| 7 | Copy `/forms`, `/Medicine`, `/spuh` | Nothing | 5 min |
| 8 | Copy the old images and downloads (~20 GB) | Copy now? (**y**) | hours |
| 9 | Website settings and e-mail | Do all subdomains work with https? (if unsure: **n**). E-mail settings if the old ones cannot work here | 2 min |
| 10 | Databases | Usually nothing (downloaded with the cPanel password). It finds each app's database login itself and asks you to confirm | 5–15 min |
| 11 | HTTPS certificate | Choose **1** (copy the current `*.spu.edu.sy` certificate) | 1 min |
| 12 | Build and start the website | Nothing | 5–15 min |
| 13 | Check everything | Optional: the long check of all 28,428 old links, and a test e-mail | 5–40 min |
| 14 | Deploy from home (the GitHub button, section 7) | Deploy every change on dev automatically? (if unsure: **n**) | 1 min |
| 15 | Result | Nothing. It prints **READY** or **NOT READY**, the DNS steps and the rollback steps | – |

**Tip:** step 8 takes the longest. If you are short of time, answer **n** to
*Copy them now?*. The website then takes the old images from the old server
until you run `sudo bash migrate.sh step 8` later. The old server must stay on
in that case.

### If you download the databases yourself

If you prefer not to give the cPanel password to the script, step 10 shows
how: in cPanel open **Backup**, under *Download a MySQL Database Backup* click
`spuedu_v2` (and `spuedu_forms`, `spuedu_medical`, `spuedu_spuh`), then upload
the files with WinSCP into `/srv/spu/dumps/`. Press Enter and the script finds
them.

---

## 5. The day of the switch

1. **Morning, at least 4 hours before the switch:** in cPanel → Zone Editor →
   `spu.edu.sy` → Manage, change the **TTL** of `spu.edu.sy`, `www.spu.edu.sy`,
   `v2.spu.edu.sy` and `www.v2.spu.edu.sy` from 14400 to **300**. This makes the
   switch and a rollback take 5 minutes instead of 4 hours.
2. Run `sudo bash /root/migrate.sh` and go through all 15 steps. It must end
   with **READY**. (You can do this the day before.)
3. When you are ready to switch, ask the editors to stop editing, then run:

   ```
   sudo bash /srv/spu/src/deploy/docker/migrate.sh final
   ```

   It copies the latest files and databases again and repeats every check.
   **Go on only if it says READY.**
4. Change DNS by hand in cPanel → Zone Editor. The script prints the exact
   records with the new IP. Only these four A records change: `spu.edu.sy`,
   `www.spu.edu.sy` (if it is an A record), `v2.spu.edu.sy`, `www.v2.spu.edu.sy`.
   **Do not touch** the mail records (MX, `mail`, `webmail`), `cpanel`, `ftp`,
   `rooms`, or the TXT records.
5. After 5–10 minutes run:

   ```
   sudo bash /srv/spu/src/deploy/docker/migrate.sh after-dns
   ```

   It checks the live site, turns on HTTPS if needed, and installs the nightly
   backup.
6. Open https://spu.edu.sy on your phone (mobile data, not Wi-Fi) and check a
   few pages, an old link, `/forms/`, and the admin login.

## 6. If something is wrong after the switch: go back

The old server was never changed, so going back is only DNS:

1. On the new server, save what visitors sent meanwhile:
   `sudo bash /srv/spu/src/deploy/docker/scripts/backup.sh db`
2. In cPanel → Zone Editor, set the same four records back to the old IP
   **184.107.185.129**.
3. With TTL 300, visitors are back on the old site in about 5 minutes.

The full rollback text, with your IPs filled in, is in
`/srv/spu/MIGRATION-REPORT.txt` on the new server.

---

## 7. Putting a new version live later (from home)

The new server accepts SSH only from the university network. You do not need
SSH to deploy: the server itself asks GitHub every minute whether someone
pressed the deploy button. Nothing from outside connects to the server.

**To deploy the latest `dev`:**

1. Open the repository on GitHub, then **Actions** → **Deploy to the VPS** (left
   column) → **Run workflow**.
2. *Use workflow from*: **dev**. Leave the other fields empty. Press the green
   **Run workflow** button.
3. Within a minute or two the server starts. Open the run to watch the steps.
   It turns **green** when the new version is live, and **red** if it stopped.
   When it stops, the site stays on (or goes back to) the version it had, so
   visitors never see a half-finished release.

What the server does, in order: downloads `dev`, builds the new version while
the old one keeps serving, saves every database, runs the release (migrations,
search index, sitemap, launch checks), switches the site to the new version,
and checks that the Arabic and English home pages answer. Only one deploy runs
at a time.

| The run says | What it means, what to do |
|---|---|
| green: *… is live* | Done. |
| red: *Data migrations need approval: …* | The new version changes rows in the database. Nothing went live. If you know these changes are wanted, run the workflow again and paste the names it printed into **approve_migrations**. |
| red: *… did not answer after the switch, so … is serving again* | The new version broke the site, so the server went back by itself. A developer should read the log named in the run. |
| red: *The server did not pick this request up within 10 minutes* | The server is off, or the watcher is not installed (step 14). |

**To go back to the previous version:** run the workflow with **action:
rollback**. The database is not changed by a rollback. If the release you
are leaving had migrated the database, the dump taken before it is in
`/srv/spu/backups/db/`.

**Before DNS points `spu.edu.sy` at the new server**, the run cannot find the
server by its name: fill in **server_ip** with the new server's IP address.

**Automatic mode:** if you answered **y** in step 14, every change merged into
`dev` goes live by itself within a few minutes (each commit is tried once). To
switch it on or off later, change `AUTO_DEPLOY=` in `/srv/spu/deploy.conf` to
`yes` or `no` (on the server).

**From the university network** (SSH), the same deploy is:

```
sudo spu-deploy              # the latest dev
sudo spu-deploy --rollback   # the release before this one
sudo spu-deploy status       # what the button did lately
```

Logs of every deploy: `/srv/spu/deploy-logs/`.

**Note:** GitHub shows the **Run workflow** button only for workflows that are
on the repository's default branch. The default branch is `main`, which is
months behind `dev`. Either make `dev` the default branch (GitHub → Settings →
General → Default branch), or copy `.github/workflows/deploy-vps.yml` to `main`.

## 8. Useful commands

| Command | What it does |
|---|---|
| `sudo bash /srv/spu/src/deploy/docker/migrate.sh status` | Which steps are done |
| `sudo bash /srv/spu/src/deploy/docker/migrate.sh step 13` | Run one step again (here: the checks) |
| `sudo bash /srv/spu/src/deploy/docker/migrate.sh report` | Show the result and the DNS/rollback steps again |
| `sudo spu-deploy status` | What the GitHub deploy button did lately |
| `spu ps` | Is everything running? |
| `spu logs --tail 100 web` | The website's last log lines |
| `cat /srv/spu/MIGRATION-REPORT.txt` | The last result |

Logs of every run are in `/srv/spu/migrate-logs/`.

## 9. Common problems

| You see | What to do |
|---|---|
| `Port 21 ... does not answer` | The old host blocks FTP from the new server. Choose **h**: the script copies over HTTPS with the cPanel password instead (slower). |
| `cPanel login failed` | Check the password (log in to cPanel in a browser). After 3 wrong tries the host may block the new server for a while. |
| `asks for a two-factor code` | Choose the manual database download (section 4). |
| `The build failed` | Usually the internet of the new server. Run the same step again. |
| `The release job stopped` | Read the lines just above it; they name the problem. Nothing was shown to visitors. |
| `NOT READY` / `Old links: some addresses answer differently` | **Do not change DNS.** Keep the report file (`/srv/spu/MIGRATION-REPORT.txt`) and the folder `/srv/spu/parity/` for a developer. |
| A step stopped for any other reason | Run the script again. Every step can be repeated safely. |
