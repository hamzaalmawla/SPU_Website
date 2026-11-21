#!/usr/bin/env python3
"""Copy files and databases from the old cPanel account with an API token only.

For when the VPS cannot use FTP or the cPanel login page, and the account has no
shell. Everything goes over cPanel's API port (2083) with an API token:

    1. VersionControl/update          the cPanel copy of the repository gets this code
    2. Fileman/save_file_content      writes ~/vps-export.request (what to pack)
    3. VersionControlDeployment/create runs cpanel-deploy.sh, which sees the request and
                                      runs deploy/v2-staging/vps-export.sh instead of a
                                      deploy: tar + gzip / mysqldump, cut into chunks,
                                      each chunk saved as base64 text
    4. Fileman/get_file_content       reads the chunks back; each is checked against
                                      its sha256, then the files are put together here

    cpanel-api.py check
    cpanel-api.py export <out dir> <item> [<item> ...]
    cpanel-api.py clean                     delete the export folder on the old server

Items: env, storage, web, app:<dir>, db:<name>, db:<name>:<app dir>,
media:<path under home>, media-root:<path under home> (see vps-export.sh).
<out dir>/INDEX lists "item<TAB>file name" for every item that arrived (names relative to
<out dir>, so the folder can be made on a laptop and uploaded to the VPS).

Settings come from the environment (the token never goes on a command line):
    CPANEL_TOKEN     the API token (cPanel > Security > Manage API Tokens)
    CPANEL_USER      spuedu
    CPANEL_HOST      spu.edu.sy          CPANEL_PORT  2083
    CPANEL_REPO      /home/spuedu/repositories/spu-v2   CPANEL_BRANCH  dev
    CPANEL_HOME      /home/spuedu
    CPANEL_CHUNK_KB  512  (raise it, e.g. 4096, if the host allows bigger reads)
    CPANEL_PARALLEL  6    CPANEL_INSECURE=1 skips the certificate check

Nothing on the old server is changed except ~/vps-export.request and ~/vps-export/,
and a deploy started this way changes nothing on the site (see cpanel-deploy.sh).
Only the Python standard library is used.
"""

from __future__ import annotations

import base64
import concurrent.futures
import hashlib
import json
import os
import secrets
import ssl
import sys
import time
import urllib.error
import urllib.parse
import urllib.request

UA = "spu-migrate-api/1.0"

# Exit codes the laptop script and migrate.sh act on.
EXIT_CERT, EXIT_NETWORK, EXIT_TOKEN = 3, 4, 5


class Fatal(SystemExit):
    def __init__(self, code: int, message: str) -> None:
        super().__init__(code)
        self.message = message
EXPORT_SCRIPT = "deploy/v2-staging/vps-export.sh"


def env(name: str, default: str = "") -> str:
    return os.environ.get(name, default).strip()


class Api:
    def __init__(self) -> None:
        self.user = env("CPANEL_USER", "spuedu")
        self.token = env("CPANEL_TOKEN")
        if not self.token:
            raise SystemExit("CPANEL_TOKEN is not set.")
        self.base = f"https://{env('CPANEL_HOST', 'spu.edu.sy')}:{env('CPANEL_PORT', '2083')}"
        self.home = env("CPANEL_HOME", f"/home/{self.user}").rstrip("/")
        self.repo = env("CPANEL_REPO", f"{self.home}/repositories/spu-v2").rstrip("/")
        self.branch = env("CPANEL_BRANCH", "dev")
        ctx = ssl.create_default_context()
        if env("CPANEL_INSECURE") == "1":
            ctx.check_hostname = False
            ctx.verify_mode = ssl.CERT_NONE
        self.ctx = ctx
        self.attempts = 6

    def call(self, module: str, function: str, post: bool = False, quiet: bool = False,
             timeout: float = 180, **params: str) -> dict:
        data = urllib.parse.urlencode(params).encode() if post else None
        url = f"{self.base}/execute/{module}/{function}"
        if not post and params:
            url += "?" + urllib.parse.urlencode(params)
        headers = {"Authorization": f"cpanel {self.user}:{self.token}", "User-Agent": UA}
        last: Exception | None = None
        for attempt in range(self.attempts):
            try:
                req = urllib.request.Request(url, data=data, headers=headers)
                with urllib.request.urlopen(req, timeout=timeout, context=self.ctx) as resp:
                    raw = resp.read()
                break
            except urllib.error.HTTPError as exc:
                if exc.code in (401, 403):
                    raise Fatal(EXIT_TOKEN, f"cPanel refused the token (HTTP {exc.code}). Check that the user name is "
                                f"'{self.user}' and that the token is copied completely and not deleted or expired "
                                "(cPanel > Security > Manage API Tokens).")
                if exc.code in (429, 500, 502, 503, 504):
                    last = exc
                    time.sleep(5 + attempt * 10)
                    continue
                raise SystemExit(f"{module}/{function}: HTTP {exc.code}")
            except (urllib.error.URLError, TimeoutError, ConnectionError, ssl.SSLError) as exc:
                reason = getattr(exc, "reason", exc)
                if isinstance(reason, ssl.SSLCertVerificationError) or "CERTIFICATE_VERIFY_FAILED" in str(exc):
                    raise Fatal(EXIT_CERT, f"The certificate of {self.base} is not trusted by this computer "
                                f"({getattr(reason, 'verify_message', '') or reason}). The connection works, "
                                "but the certificate check failed.")
                last = exc
                if attempt + 1 < self.attempts:
                    time.sleep(5 + attempt * 10)
        else:
            raise Fatal(EXIT_NETWORK, f"Cannot reach {self.base} from this computer: {last}. The port is blocked "
                        "on this network or the server's firewall blocks this computer's IP address.")
        try:
            answer = json.loads(raw.decode("utf-8", "replace"))
        except ValueError:
            head = raw[:200].decode("utf-8", "replace").replace("\n", " ")
            raise Fatal(EXIT_NETWORK, f"{self.base} answered, but not like the cPanel API (a firewall or bot-check "
                        f"page?): {head}")
        if not answer.get("status"):
            message = "; ".join(answer.get("errors") or ["failed"])
            if quiet:
                raise LookupError(message)
            raise SystemExit(f"{module}/{function}: {message}")
        return answer

    # ── small helpers ──────────────────────────────────────────────────────
    def read_text(self, path: str, timeout: float = 300) -> str:
        directory, name = path.rsplit("/", 1)
        answer = self.call("Fileman", "get_file_content", quiet=True, timeout=timeout,
                           dir=directory, file=name)
        return (answer.get("data") or {}).get("content") or ""

    def try_read(self, path: str) -> str | None:
        try:
            return self.read_text(path, timeout=60)
        except LookupError:
            return None

    def write_text(self, directory: str, name: str, content: str) -> None:
        self.call("Fileman", "save_file_content", post=True, dir=directory, file=name, content=content)

    def files_in(self, directory: str) -> set[str]:
        try:
            answer = self.call("Fileman", "list_files", quiet=True, dir=directory)
        except LookupError:
            return set()
        return {f.get("file", "") for f in (answer.get("data") or [])}

    def repo_info(self) -> dict:
        answer = self.call("VersionControl", "retrieve")
        for repo in answer.get("data") or []:
            if (repo.get("repository_root") or "").rstrip("/") == self.repo:
                return repo
        raise SystemExit(f"No cPanel Git repository at {self.repo} (Git Version Control).")

    def update_repo(self) -> None:
        self.call("VersionControl", "update", post=True, timeout=600,
                  repository_root=self.repo, branch=self.branch)

    def deploy(self) -> int:
        answer = self.call("VersionControlDeployment", "create", post=True, repository_root=self.repo)
        return int((answer.get("data") or {}).get("deploy_id") or 0)

    def deploy_finished(self, deploy_id: int) -> str | None:
        last = self.repo_info().get("last_deployment") or {}
        if int(last.get("deploy_id") or 0) < deploy_id:
            return None
        stamps = last.get("timestamps") or {}
        for state in ("succeeded", "failed", "canceled"):
            if state in stamps:
                return state
        return None


def mb(n: float) -> str:
    return f"{n / 1048576:,.1f} MB"


def check(api: Api) -> int:
    api.attempts = 2  # answer quickly; the real work retries longer
    repo = api.repo_info()
    head = (repo.get("last_update") or {}).get("identifier") or "?"
    print(f"token works for {api.user} at {api.base}")
    print(f"repository {api.repo}, branch {repo.get('branch', '?')}, commit {str(head)[:10]}")
    try:
        quota = (api.call("Quota", "get_quota_info", quiet=True).get("data") or {})
        used, limit = quota.get("megabytes_used"), quota.get("megabyte_limit")
        print(f"disk: {used} MB used of {limit or 'unlimited'} MB")
    except LookupError:
        pass
    return 0


def request_export(api: Api, export_id: str, items: list[str], chunk_kb: int) -> int:
    body = f"id={export_id}\nchunk_kb={chunk_kb}\n" + "".join(f"item={i}\n" for i in items)
    api.write_text(api.home, "vps-export.request", body)
    return api.deploy()


def wait_for_export(api: Api, export_id: str, items: list[str], chunk_kb: int, deploy_id: int,
                    limit_s: int) -> None:
    folder = f"{api.home}/vps-export/{export_id}"
    started = time.time()
    shown = ""
    restarts = 0
    while True:
        time.sleep(10)
        status = (api.try_read(f"{folder}/STATUS") or "").strip()
        if status and status != shown:
            print(f"   old server: {status}", flush=True)
            shown = status
        if status == "done":
            return
        if status.startswith("partial"):
            log = api.try_read(f"{folder}/export.log") or ""
            print("\n".join("   | " + line for line in log.splitlines()[-15:]))
            print(f"   WARNING: the old server could not pack: {status[8:]}", flush=True)
            return
        if status.startswith("failed"):
            log = api.try_read(f"{folder}/export.log") or ""
            print("\n".join("   | " + line for line in log.splitlines()[-25:]))
            raise SystemExit(f"The export on the old server failed: {status[7:]}")
        finished = api.deploy_finished(deploy_id)
        if finished and status != "done":
            time.sleep(10)  # STATUS is written just before the deploy ends
            status = (api.try_read(f"{folder}/STATUS") or "").strip()
            if status == "done" or status.startswith(("failed", "partial")):
                continue
            if not status:
                raise SystemExit(
                    "The deploy ran but did not export anything. The old server's copy of the code is "
                    f"probably older than {EXPORT_SCRIPT}: push it to {api.branch} and run again. "
                    "The deploy log is in ~/.cpanel/logs on the old server.")
            restarts += 1
            if restarts > 3:
                raise SystemExit("The export stopped before it finished, three times. Try fewer items at once.")
            print("   the export was stopped before it finished (time limit?); continuing it", flush=True)
            deploy_id = request_export(api, export_id, items, chunk_kb)
        if time.time() - started > limit_s:
            raise SystemExit(f"The export did not finish within {limit_s // 60} minutes.")


def parse_manifest(text: str) -> tuple[list[dict], dict[str, list[dict]]]:
    items: list[dict] = []
    chunks: dict[str, list[dict]] = {}
    for line in text.splitlines():
        cols = line.split("\t")
        if cols[0] == "item" and len(cols) == 6:
            items.append({"name": cols[1], "file": cols[2], "kind": cols[3], "bytes": int(cols[4]), "n": int(cols[5])})
        elif cols[0] == "chunk" and len(cols) == 6:
            chunks.setdefault(cols[1], []).append({"idx": cols[2], "sha": cols[3], "bytes": int(cols[4]), "path": cols[5]})
    return items, chunks


def download(api: Api, export_id: str, out: str, parallel: int) -> list[dict]:
    folder = f"{api.home}/vps-export/{export_id}"
    manifest = api.read_text(f"{folder}/MANIFEST")
    items, chunks = parse_manifest(manifest)
    if not items:
        print("   nothing to copy (the requested folders are not on the old server)", flush=True)
        return []
    parts = os.path.join(out, ".parts")
    os.makedirs(parts, exist_ok=True)
    jobs = [(c, os.path.join(parts, f"{i['name']}.{c['idx']}")) for i in items for c in chunks.get(i["name"], [])]
    total = sum(c["bytes"] for c, _ in jobs)
    done = 0
    started = time.time()

    def have(chunk: dict, dest: str) -> bool:
        if not os.path.exists(dest) or os.path.getsize(dest) != chunk["bytes"]:
            return False
        with open(dest, "rb") as fh:
            return hashlib.sha256(fh.read()).hexdigest() == chunk["sha"]

    def fetch(job: tuple[dict, str]) -> int:
        chunk, dest = job
        if have(chunk, dest):
            return chunk["bytes"]
        for attempt in range(5):
            try:
                text = api.read_text(f"{folder}/{chunk['path']}")
                data = base64.b64decode("".join(text.split()), validate=True)
            except (LookupError, ValueError) as exc:
                if attempt == 4:
                    raise SystemExit(f"cannot read {chunk['path']}: {exc}. If cPanel says the file is too "
                                     "large, run again with a smaller CPANEL_CHUNK_KB (e.g. 256).")
                time.sleep(5 + attempt * 5)
                continue
            if hashlib.sha256(data).hexdigest() == chunk["sha"] and len(data) == chunk["bytes"]:
                with open(dest + ".tmp", "wb") as fh:
                    fh.write(data)
                os.replace(dest + ".tmp", dest)
                return len(data)
            time.sleep(3)
        raise SystemExit(f"{chunk['path']} arrived damaged five times.")

    print(f"   downloading {len(jobs)} chunk(s), {mb(total)}", flush=True)
    last = 0.0
    with concurrent.futures.ThreadPoolExecutor(max_workers=max(1, parallel)) as pool:
        for size in pool.map(fetch, jobs):
            done += size
            now = time.time()
            if now - last > 15 or done == total:
                rate = done / max(1.0, now - started)
                print(f"   {mb(done)} of {mb(total)} ({rate / 1048576:.1f} MB/s)", flush=True)
                last = now

    index = []
    for item in items:
        dest = os.path.join(out, item["file"])
        with open(dest + ".tmp", "wb") as fh:
            for chunk in sorted(chunks.get(item["name"], []), key=lambda c: c["idx"]):
                with open(os.path.join(parts, f"{item['name']}.{chunk['idx']}"), "rb") as src:
                    fh.write(src.read())
        if os.path.getsize(dest + ".tmp") != item["bytes"]:
            raise SystemExit(f"{item['file']}: wrong size after joining the chunks.")
        os.replace(dest + ".tmp", dest)
        for chunk in chunks.get(item["name"], []):
            os.remove(os.path.join(parts, f"{item['name']}.{chunk['idx']}"))
        index.append(item)
    if not os.listdir(parts):
        os.rmdir(parts)
    return index


def item_name(item: str) -> str:
    return "".join(c if c.isalnum() or c in "._-" else "_" for c in item)


def export(api: Api, out: str, items: list[str]) -> int:
    chunk_kb = int(env("CPANEL_CHUNK_KB", "512") or 512)
    parallel = int(env("CPANEL_PARALLEL", "6") or 6)
    os.makedirs(out, mode=0o700, exist_ok=True)

    print("   updating the old server's copy of the code (Git Version Control)", flush=True)
    api.update_repo()
    if os.path.basename(EXPORT_SCRIPT) not in api.files_in(f"{api.repo}/{os.path.dirname(EXPORT_SCRIPT)}"):
        raise SystemExit(f"{EXPORT_SCRIPT} is not in {api.repo} on the old server. "
                         f"Push the current code to {api.branch} first.")

    export_id = secrets.token_hex(16)
    print(f"   asking the old server to pack: {' '.join(items)}", flush=True)
    deploy_id = request_export(api, export_id, items, chunk_kb)
    wait_for_export(api, export_id, items, chunk_kb, deploy_id, int(env("CPANEL_EXPORT_MINUTES", "360")) * 60)

    arrived = download(api, export_id, out, parallel)
    with open(os.path.join(out, "INDEX"), "w", encoding="utf-8") as fh:
        for wanted in items:
            for item in arrived:
                if item["name"] == item_name(wanted):
                    fh.write(f"{wanted}\t{item['file']}\n")
    with open(os.path.join(out, "COMPLETE"), "w", encoding="utf-8") as fh:
        fh.write(time.strftime("%Y-%m-%d %H:%M:%S\n"))
    missing = [i for i in items if item_name(i) not in {a["name"] for a in arrived}]
    print(f"   {len(arrived)} item(s) arrived intact in {out}", flush=True)
    if missing:
        print(f"   missing: {' '.join(missing)}", flush=True)
    return 0


def clean(api: Api) -> int:
    api.write_text(api.home, "vps-export.request", "mode=clean\n")
    deploy_id = api.deploy()
    for _ in range(60):
        time.sleep(10)
        state = api.deploy_finished(deploy_id)
        if state:
            left = api.files_in(f"{api.home}/vps-export")
            if left - {"", ".", ".."}:
                print(f"   the old server still has export folders: {', '.join(sorted(left))}")
                return 1
            print("   the export folder on the old server is empty")
            return 0
    print("   the clean-up deploy did not finish within 10 minutes")
    return 1


def main(argv: list[str]) -> int:
    if len(argv) < 2 or argv[1] not in ("check", "export", "clean"):
        print(__doc__)
        return 2
    os.umask(0o077)  # database dumps and the old .env are secrets
    api = Api()
    if argv[1] == "check":
        return check(api)
    if argv[1] == "clean":
        return clean(api)
    if len(argv) < 4:
        print("usage: cpanel-api.py export <out dir> <item> [<item> ...]")
        return 2
    return export(api, argv[2], argv[3:])


if __name__ == "__main__":
    try:
        sys.exit(main(sys.argv))
    except Fatal as exc:
        print(f"ERROR: {exc.message}", file=sys.stderr)
        sys.exit(exc.code)
    except KeyboardInterrupt:
        sys.exit(130)
