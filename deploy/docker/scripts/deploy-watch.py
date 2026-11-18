#!/usr/bin/env python3
"""Pull-based deploys: the VPS asks GitHub whether someone pressed "Deploy to the VPS".

The server accepts SSH only from the university network, so nothing from
outside connects to it to deploy. Instead a systemd timer runs this every
minute; it only makes outgoing HTTPS calls to GitHub:

  1. It lists the runs of .github/workflows/deploy-vps.yml (started with the
     "Run workflow" button on dev; only people with write access can start one).
  2. For a new run it starts deploy.sh with what the run asked for (deploy or
     rollback, and any data migrations to approve, carried in the run's name).
  3. It writes the progress and the result to /deploy-status.json on the
     website. The workflow run on GitHub waits for that file and shows the
     result, so whoever pressed the button sees it there.

With AUTO_DEPLOY=yes in /srv/spu/deploy.conf (the usual setting) it deploys
every new commit on dev by itself, each commit once; its status entry is
"auto-<first 8 characters of the commit>", which the workflow's push run waits for.

    deploy-watch.py            one check (what the timer runs)
    deploy-watch.py --init     remember the runs that exist now, so they are not deployed
    deploy-watch.py --status   print the last results

Full log of every deploy: /srv/spu/deploy-logs/.
"""

from __future__ import annotations

import fcntl
import json
import os
import re
import shutil
import subprocess
import sys
import time
import urllib.error
import urllib.request
from datetime import datetime, timezone

ROOT = os.environ.get("SPU_ROOT", "/srv/spu")
SRC = f"{ROOT}/src"
CONF = f"{ROOT}/deploy.conf"
STATE = f"{ROOT}/deploy-watch.json"
LOCK = f"{ROOT}/deploy.lock"
LOGDIR = f"{ROOT}/deploy-logs"
PUBLIC = os.environ.get("SPU_DEPLOY_STATUS_FILE", f"{ROOT}/data/web/deploy-status.json")
CREDS = f"{ROOT}/secrets/git-credentials"
API = os.environ.get("SPU_GITHUB_API", "https://api.github.com")

WORKFLOW_PATH = ".github/workflows/deploy-vps.yml"
TITLE = re.compile(r"^VPS deploy request: (deploy|rollback)\b(.*)$")
MIGRATION = re.compile(r"^[0-9]{4}_[0-9]{2}_[0-9]{2}_[0-9]{6}_[A-Za-z0-9_]+$")
KEEP_RUNS = 20
EXIT_STATE = {0: "success", 2: "blocked", 3: "failed", 4: "down", 5: "busy"}


def now() -> str:
    return datetime.now(timezone.utc).strftime("%Y-%m-%dT%H:%M:%SZ")


def log(msg: str) -> None:
    print(f"{now()} {msg}", flush=True)


def read_conf() -> dict[str, str]:
    conf = {"REPO": "hamzaalmawla/SPU_Website", "BRANCH": "dev", "AUTO_DEPLOY": "no"}
    try:
        for line in open(CONF, encoding="utf-8"):
            m = re.match(r"^\s*([A-Z_]+)=(.*?)\s*$", line)
            if m:
                conf[m.group(1)] = m.group(2).strip("\"'")
    except OSError:
        pass
    return conf


def token() -> str | None:
    try:
        line = open(CREDS, encoding="utf-8").readline().strip()
    except OSError:
        return None
    m = re.match(r"^https://[^:/]+:([^@]+)@github\.com", line)
    return m.group(1) if m else None


def load_state() -> dict:
    try:
        return json.load(open(STATE, encoding="utf-8"))
    except (OSError, ValueError):
        return {}


def write_json(path: str, data: dict, mode: int) -> None:
    os.makedirs(os.path.dirname(path), exist_ok=True)
    tmp = f"{path}.tmp"
    with open(tmp, "w", encoding="utf-8") as fh:
        json.dump(data, fh, indent=1, ensure_ascii=False)
    os.chmod(tmp, mode)
    os.replace(tmp, path)


def save(state: dict) -> None:
    runs = state.get("runs", {})
    state["runs"] = dict(sorted(runs.items(), key=lambda kv: kv[1].get("updated", ""))[-KEEP_RUNS:])
    write_json(STATE, state, 0o600)
    # The public copy holds only what the GitHub run needs to show: no log lines.
    public = {"updated": now(), "runs": state["runs"]}
    write_json(PUBLIC, public, 0o644)


def set_run(st: dict, key: str, **fields) -> None:
    run = st.setdefault("runs", {}).setdefault(key, {})
    run.update(fields)
    run["updated"] = now()
    save(st)


def github_runs(conf: dict[str, str], use_token: bool = True) -> list[dict]:
    url = (f"{API}/repos/{conf['REPO']}/actions/workflows/deploy-vps.yml/runs"
           f"?event=workflow_dispatch&branch={conf['BRANCH']}&per_page=20")
    req = urllib.request.Request(url, headers={"Accept": "application/vnd.github+json",
                                               "User-Agent": "spu-vps-deploy-watch",
                                               "X-GitHub-Api-Version": "2022-11-28"})
    tok = token() if use_token else None
    if tok:
        req.add_header("Authorization", f"Bearer {tok}")
    try:
        with urllib.request.urlopen(req, timeout=30) as resp:
            return json.load(resp).get("workflow_runs", [])
    except urllib.error.HTTPError as err:
        if err.code == 404:
            log("GitHub has no deploy-vps.yml workflow on this repository yet.")
            return []
        if err.code in (401, 403) and tok:
            log("GitHub refused the saved token (expired?); asking without it.")
            return github_runs(conf, use_token=False)
        raise


def valid(run: dict, conf: dict[str, str]) -> tuple[str, list[str]] | str:
    """(action, migrations) for a genuine request, else the reason it is refused."""
    repo = conf["REPO"].lower()
    if run.get("event") != "workflow_dispatch":
        return "not started with the Run workflow button"
    if run.get("head_branch") != conf["BRANCH"]:
        return f"started on {run.get('head_branch')}, not {conf['BRANCH']}"
    if (run.get("path") or "").split("@")[0] != WORKFLOW_PATH:
        return "not the deploy workflow"
    if (run.get("repository") or {}).get("full_name", "").lower() != repo \
            or (run.get("head_repository") or {}).get("full_name", "").lower() != repo:
        return "from another repository"
    m = TITLE.match(run.get("display_title") or "")
    if not m:
        return "the request could not be read"
    words = [w for w in re.split(r"[\s,]+", m.group(2)) if w]
    bad = [w for w in words if not MIGRATION.match(w)]
    if bad:
        return f"not migration names: {' '.join(bad)}"
    return m.group(1), words


def git(*args: str) -> str:
    return subprocess.run(["git", "-C", SRC, *args], capture_output=True, text=True, timeout=120).stdout.strip()


def deploy_busy() -> bool:
    fd = os.open(LOCK, os.O_RDWR | os.O_CREAT, 0o644)
    try:
        fcntl.flock(fd, fcntl.LOCK_EX | fcntl.LOCK_NB)
        fcntl.flock(fd, fcntl.LOCK_UN)
        return False
    except BlockingIOError:
        return True
    finally:
        os.close(fd)


def run_deploy(state: dict, key: str, args: list[str], meta: dict) -> int:
    """Run dev's own deploy.sh from a copy (the checkout replaces the original) and follow its markers."""
    os.makedirs(LOGDIR, mode=0o700, exist_ok=True)
    logfile = f"{LOGDIR}/{datetime.now().strftime('%Y%m%d-%H%M%S')}-{key}.log"
    script = f"{ROOT}/.deploy-running.sh"
    conf = read_conf()
    subprocess.run(["git", "-C", SRC, "fetch", "-q", "origin",
                    f"+refs/heads/{conf['BRANCH']}:refs/remotes/origin/{conf['BRANCH']}"], timeout=300)
    latest = subprocess.run(["git", "-C", SRC, "show", f"origin/{conf['BRANCH']}:deploy/docker/scripts/deploy.sh"],
                            capture_output=True, timeout=60)
    if latest.returncode == 0 and latest.stdout:
        with open(script, "wb") as fh:
            fh.write(latest.stdout)
    else:
        shutil.copy(f"{SRC}/deploy/docker/scripts/deploy.sh", script)
    set_run(state, key, state="running", step="start", message="Deploy started on the server.",
            started=now(), pending=[], **meta)
    message, pending = "", []
    with open(logfile, "w", encoding="utf-8") as out:
        out.write(f"# {now()} deploy.sh {' '.join(args)}  ({key})\n")
        proc = subprocess.Popen(["bash", script, *args], stdout=subprocess.PIPE, stderr=subprocess.STDOUT,
                                text=True, errors="replace", env={**os.environ, "TERM": "dumb"})
        for line in proc.stdout:
            out.write(line)
            out.flush()
            if line.startswith("::step::"):
                set_run(state, key, step=line[8:].strip())
            elif line.startswith("::sha::"):
                set_run(state, key, sha=line[7:].strip())
            elif line.startswith("::pending::"):
                pending.append(line[11:].strip())
            elif line.startswith("::result::"):
                message = line[10:].strip().split(" ", 1)[-1]
        rc = proc.wait()
    os.chmod(logfile, 0o600)
    result = EXIT_STATE.get(rc, "failed")
    set_run(state, key, state=result, step="done", message=message or f"deploy.sh ended with code {rc}",
            pending=pending, finished=now(), log=os.path.basename(logfile))
    log(f"{key}: {result}: {message}")
    return rc


def check(init: bool = False) -> int:
    conf = read_conf()
    state = load_state()
    if not init and deploy_busy():
        return 0  # a deploy or migrate.sh is running; look again next minute
    try:
        runs = github_runs(conf)
    except (urllib.error.URLError, OSError, ValueError) as err:
        log(f"Could not ask GitHub: {err}")
        runs = None
    if runs is not None:
        runs.sort(key=lambda r: r["id"])
        last = int(state.get("last_run_id", 0))
        if init or "last_run_id" not in state:
            state["last_run_id"] = max([last] + [r["id"] for r in runs])
            save(state)
            log(f"Watching for new deploy requests (last seen run {state['last_run_id']}).")
            return 0
        new = [r for r in runs if r["id"] > last]
        for run in new[:-1]:
            set_run(state, str(run["id"]), state="skipped", step="done",
                    message="A newer request came in before this one started; that one was deployed instead.")
        if new:
            run = new[-1]
            key = str(run["id"])
            state["last_run_id"] = run["id"]
            who = (run.get("triggering_actor") or run.get("actor") or {}).get("login", "?")
            req = valid(run, conf)
            if isinstance(req, str):
                set_run(state, key, state="rejected", step="done", message=f"Not deployed: {req}.", by=who)
                log(f"run {key} refused: {req}")
                return 0
            action, migrations = req
            log(f"run {key} by {who}: {action} {' '.join(migrations)}")
            if action == "rollback":
                args = ["--rollback"]
            else:
                args = [a for m in migrations for a in ("--approve", m)] + [run["head_sha"]]
            rc = run_deploy(state, key, args, {"by": who, "action": action, "sha": run["head_sha"][:8]})
            if rc == 5:  # lost the race with migrate.sh: ask again next minute
                state["last_run_id"] = last
                set_run(state, key, state="waiting", step="queued", message="Waiting for another job on the server.")
            return 0

    if conf.get("AUTO_DEPLOY", "no").lower() in ("yes", "true", "1"):
        subprocess.run(["git", "-C", SRC, "fetch", "-q", "origin",
                        f"+refs/heads/{conf['BRANCH']}:refs/remotes/origin/{conf['BRANCH']}"], timeout=300)
        head = git("rev-parse", f"origin/{conf['BRANCH']}")
        live = git("rev-parse", "HEAD")
        if head and head != live and head != state.get("auto_tried"):
            state["auto_tried"] = head
            save(state)
            if run_deploy(state, f"auto-{head[:8]}", [head], {"by": "auto", "action": "deploy", "sha": head[:8]}) == 5:
                state["auto_tried"] = ""
                save(state)
    return 0


def status() -> int:
    state = load_state()
    conf = read_conf()
    print(f"Repository {conf['REPO']}, branch {conf['BRANCH']}, automatic deploys: {conf['AUTO_DEPLOY']}")
    print(f"Live commit: {git('log', '-1', '--format=%h %cd %s', '--date=short')}")
    runs = sorted(state.get("runs", {}).items(), key=lambda kv: kv[1].get("updated", ""))
    if not runs:
        print("No deploy requests handled yet.")
    for key, run in runs[-10:]:
        print(f"  {run.get('updated', '')}  {key:>12}  {run.get('state', ''):9} {run.get('sha', ''):9} "
              f"{run.get('by', '')}: {run.get('message', '')}")
        if run.get("pending"):
            print(f"{'':40}pending: {' '.join(run['pending'])}")
    print(f"Logs: {LOGDIR}")
    return 0


if __name__ == "__main__":
    arg = sys.argv[1] if len(sys.argv) > 1 else ""
    if arg == "--status":
        sys.exit(status())
    if arg in ("-h", "--help"):
        print(__doc__)
        sys.exit(0)
    sys.exit(check(init=arg == "--init"))
