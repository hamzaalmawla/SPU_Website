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
| A **cPanel API token** for `spuedu` (when FTP and the cPanel login page are closed to the new server; see section 9) | cPanel → Security → Manage API Tokens → Create |
| Or the **FTP password** of `it@spu.edu.sy` | cPanel → FTP Accounts (you can set a new password there if nobody knows it) |
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

## 4. The 14 steps

| # | Step | What it asks you | Time |
|---|---|---|---|
| 1 | Check this server, install Docker and tools | Nothing (maybe: stop an old web server on port 80) | 2–5 min |
| 2 | Questions | Main address (`spu.edu.sy`), this server's IP, old server's IP, cPanel address and user, then how the data arrives: **folder**, **api**, **laptop** or **ftp** | 2 min |
| 3 | Download the code | A GitHub token only if GitHub asks | 1 min |
| 4 | Prepare folders and firewall | Turn on the firewall? (**y**) | 1 min |
| 5 | Find the old server's data | With **folder**: where the uploaded folder is; it shows what it found, you confirm. With **api**: nothing (it tests the token). With **ftp**: the cPanel password (kept in memory only) | 1 min |
| 6 | Copy the website's own files | Nothing | 5–20 min |
| 7 | Copy `/forms`, `/Medicine`, `/spuh` | Nothing | 5 min |
| 8 | Copy the old images and downloads (~20 GB) | Copy now? (**y**) | hours |
| 9 | Website settings and e-mail | Do all subdomains work with https? (if unsure: **n**). E-mail settings if the old ones cannot work here | 2 min |
| 10 | Databases | Usually nothing (downloaded with the cPanel password). It finds each app's database login itself and asks you to confirm | 5–15 min |
| 11 | HTTPS certificate | Choose **1** (copy the current `*.spu.edu.sy` certificate) | 1 min |
| 12 | Build and start the website | Nothing | 5–15 min |
| 13 | Check everything | Optional: the long check of all 28,428 old links, and a test e-mail | 5–40 min |
| 14 | Result | Nothing. It prints **READY** or **NOT READY**, the DNS steps and the rollback steps | – |

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
2. Run `sudo bash /root/migrate.sh` and go through all 14 steps. It must end
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

## 7. Useful commands

| Command | What it does |
|---|---|
| `sudo bash /srv/spu/src/deploy/docker/migrate.sh status` | Which steps are done |
| `sudo bash /srv/spu/src/deploy/docker/migrate.sh step 13` | Run one step again (here: the checks) |
| `sudo bash /srv/spu/src/deploy/docker/migrate.sh report` | Show the result and the DNS/rollback steps again |
| `spu ps` | Is everything running? |
| `spu logs --tail 100 web` | The website's last log lines |
| `cat /srv/spu/MIGRATION-REPORT.txt` | The last result |

Logs of every run are in `/srv/spu/migrate-logs/`.

## 8. Common problems

| You see | What to do |
|---|---|
| `Port 21 ... does not answer` | The old host blocks FTP from the new server. Choose **h** (cPanel password over HTTPS), or run step 2 again and answer **api** (section 9). |
| `The API does not answer with this token` | Check the token in cPanel → Security → Manage API Tokens, and that the new server can open `https://spu.edu.sy:2083`. |
| `The deploy ran but did not export anything` | The old server's copy of the code is older than `deploy/v2-staging/vps-export.sh`. Push the current code to `dev` and run the step again. |
| `If cPanel says the file is too large` | Run the step again with smaller pieces: `sudo CPANEL_CHUNK_KB=256 bash migrate.sh step 6`. |
| `cPanel login failed` | Check the password (log in to cPanel in a browser). After 3 wrong tries the host may block the new server for a while. |
| `asks for a two-factor code` | Choose the manual database download (section 4). |
| `The build failed` | Usually the internet of the new server. Run the same step again. |
| `The release job stopped` | Read the lines just above it; they name the problem. Nothing was shown to visitors. |
| `NOT READY` / `Old links: some addresses answer differently` | **Do not change DNS.** Keep the report file (`/srv/spu/MIGRATION-REPORT.txt`) and the folder `/srv/spu/parity/` for a developer. |
| A step stopped for any other reason | Run the script again. Every step can be repeated safely. |

## 9. When FTP and the cPanel login page are closed (API token)

Choose **api** in step 2. The script then needs only the cPanel API port
(`https://spu.edu.sy:2083`) and a token; no FTP, no cPanel password, no zipping
by hand:

1. The new server asks cPanel (Git Version Control) to update its copy of the
   code and start a **deploy that only exports**: `cpanel-deploy.sh` sees the
   request file `~/vps-export.request` and runs `deploy/v2-staging/vps-export.sh`
   instead of deploying. Nothing on the site changes: no file sync, no
   migration, no maintenance mode.
2. On the old server that script packs (tar + gzip, and `mysqldump`) only what
   is needed: v2's `.env`, `storage/` (without caches, sessions and logs), the
   document root's runtime files and the `spuedu_v2` database; also `/forms`,
   `/Medicine`, `/spuh` and their databases unless they stay on the old server.
   Each pack is cut into small pieces saved as text in `~/vps-export/`.
3. The new server reads the pieces through the API, checks each one (sha256),
   puts the files together and unpacks them into `/srv/spu/data`, then deletes
   `~/vps-export/` on the old server.

Step 6 makes this package; steps 7 and 10 use the same one (a package younger
than 6 hours is re-used; `final` always makes a fresh one). If one application
database cannot be packed (its login is not found), the script asks you to
upload that one dump by hand, as in section 4.

The old images and downloads (step 8, about 20 GB) are packed one folder at a
time, and the old account needs room for the largest folder. Answering **n**
keeps them served from the old server; run `migrate.sh step 8` later.

The deploy needs `deploy/v2-staging/vps-export.sh` in the `dev` branch on
GitHub. After the move, delete the token in cPanel → Security → Manage API
Tokens; `after-dns` offers to delete the copy saved on the new server.

### 9a. Download on a laptop, upload to the VPS later

When the VPS cannot reach port 2083 either, or you want the data ready in
advance, download it on a Windows laptop that can reach
`https://spu.edu.sy:2083` (needs Python 3), in this repository on `dev`:

```
powershell -ExecutionPolicy Bypass -File deploy\docker\laptop\get-old-server-data.ps1
```

It asks for the API token and writes one folder,
`%USERPROFILE%\spu-vps-data\core-<date>-<time>`, holding v2's `.env`, `storage`,
runtime files, `/forms`, `/Medicine`, `/spuh` and the databases. The old
images and downloads are not included; they stay served from the old server.
Keep the folder private (it holds the `.env` and full database dumps).

Upload it into `/srv/spu/xfer/` on the VPS (`scp -r <folder> root@<VPS-IP>:/srv/spu/xfer/`),
then run `migrate.sh step 5` and answer **laptop**: steps 6, 7 and 10 use that
folder and the VPS never contacts the old server. For the final sync,
download a fresh folder after the editors stop, upload it, and run `final`.
Delete the folder from the laptop once it is on the VPS.

### 9b. A folder you copied by hand (SPU-Old-Server-Data)

Upload the folder to the VPS, for example from Windows:

```
scp -r "C:\path\SPU-Old-Server-Data" root@<VPS-IP>:/srv/spu/
```

Answer **folder** in step 2 (or in step 5) and give the folder's path. Step 5
looks through it (`scripts/find-old-data.py`) and lists what it found; any
layout works, and each item may be a folder, a `.zip`, a `.tar.gz` or (dumps)
`.sql` / `.sql.gz` / `.zip`:

| Item | Recognised by | Needed |
|---|---|---|
| v2 `.env` | a file holding `APP_KEY=` and `DB_DATABASE=` (`.env`, `env`, `env.txt`, ...) | yes |
| v2 `storage` | a folder `storage` holding `app/`, or `storage.zip` / `.tar.gz` | yes |
| `spuedu_v2` dump | a file whose name contains `spuedu_v2` (`.sql`, `.sql.gz`, `.zip`) | yes |
| v2 document root | `spu_v2/public` holding `index.php` | no |
| `/forms`, `/Medicine`, `/spuh` and their dumps | folders or zips with those names; `spuedu_forms`, `spuedu_medical`, `spuedu_spuh` | no: missing ones stay on the old server |
| old media | `downloads/files`, `downloads/files2`, `downloads`, `images`, `FCKBIH`, `<faculty>/images` | no: missing ones stay on the old server |

Type an item's name to point it at another path. The list is kept in
`/srv/spu/old-data.map`; steps 6, 7, 8 and 10 copy from it, and the folder
itself is never changed. In step 11 choose **3** (Let's Encrypt after the DNS
switch) or **2** if you have the certificate files; option 1 needs the cPanel
login. Old media or apps left on the old server are fetched from it by this
server, so the old site must stay on until they are copied.
