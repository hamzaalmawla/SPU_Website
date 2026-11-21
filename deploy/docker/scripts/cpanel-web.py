#!/usr/bin/env python3
"""Read-only helper for the old cPanel host, over HTTPS (port 443).

migrate.sh uses it for what FTP cannot do, and as a fallback when FTP is closed:

    cpanel-web.py check                      log in, print the account name
    cpanel-web.py sqldump <db> <out.sql.gz>  cPanel's "Download a MySQL Database Backup"
    cpanel-web.py cert <domain> <dir>        the installed certificate + key for <domain>
                                             (writes fullchain.pem and privkey.pem)
    cpanel-web.py getfile <remote> <out>     one file
    cpanel-web.py mirror <remote dir> <local dir> [--exclude NAME] [--no-recursion]
                                             [--include-ext .pdf ...]
                                             copy a folder; only new or changed files

Credentials come from the environment, never from the command line:
    CPANEL_URL       https://cpanel.spu.edu.sy
    CPANEL_USER      spuedu
    CPANEL_PASSWORD  the cPanel login password (downloads need a login session)
    CPANEL_TFA       six-digit code, only if the account has two-factor login

Nothing on the cPanel side is changed: every call is a GET except the login form.
Only the Python standard library is used.
"""

from __future__ import annotations

import concurrent.futures
import http.cookiejar
import json
import os
import ssl
import sys
import time
import urllib.error
import urllib.parse
import urllib.request

BOT_MARKERS = (b"One moment, please", b"One moment please")
UA = "spu-migrate/1.0"


class CPanel:
    def __init__(self) -> None:
        self.base = os.environ.get("CPANEL_URL", "https://cpanel.spu.edu.sy").rstrip("/")
        self.user = os.environ.get("CPANEL_USER", "spuedu")
        self.password = os.environ.get("CPANEL_PASSWORD", "")
        self.jar = http.cookiejar.CookieJar()
        ctx = ssl.create_default_context()
        if os.environ.get("CPANEL_INSECURE") == "1":
            ctx.check_hostname = False
            ctx.verify_mode = ssl.CERT_NONE
        self.opener = urllib.request.build_opener(
            urllib.request.HTTPCookieProcessor(self.jar), urllib.request.HTTPSHandler(context=ctx)
        )
        self.token = ""

    # ── plumbing ────────────────────────────────────────────────────────────
    def _open(self, url: str, data: bytes | None = None, timeout: float = 120):
        req = urllib.request.Request(url, data=data, headers={"User-Agent": UA})
        last: Exception | None = None
        for attempt in range(5):
            try:
                resp = self.opener.open(req, timeout=timeout)
                ctype = resp.headers.get("Content-Type", "")
                if "text/html" in ctype:
                    head = resp.read(4096)
                    if any(m in head for m in BOT_MARKERS):
                        resp.close()
                        last = RuntimeError("the host's bot check answered instead of cPanel")
                        time.sleep(10 + attempt * 10)
                        continue
                    return _Prefixed(head, resp)
                return resp
            except urllib.error.HTTPError as exc:
                if exc.code in (502, 503, 504):
                    last = exc
                    time.sleep(5 + attempt * 5)
                    continue
                raise
            except (urllib.error.URLError, TimeoutError, ConnectionError) as exc:
                last = exc
                time.sleep(5 + attempt * 5)
        raise RuntimeError(f"cannot reach {url.split('/cpsess')[0]}: {last}")

    def login(self) -> None:
        if self.token:
            return
        if not self.password:
            raise SystemExit("CPANEL_PASSWORD is not set.")
        form = {"user": self.user, "pass": self.password}
        if os.environ.get("CPANEL_TFA"):
            form["tfa_token"] = os.environ["CPANEL_TFA"]
        body = urllib.parse.urlencode(form).encode()
        try:
            raw = self._open(f"{self.base}/login/?login_only=1", data=body, timeout=60).read()
        except urllib.error.HTTPError as exc:
            raw = exc.read()
        try:
            answer = json.loads(raw.decode("utf-8", "replace"))
        except ValueError:
            raise SystemExit("cPanel did not answer the login (no JSON). Is the address right?")
        token = answer.get("security_token") or ""
        if not token.startswith("/cpsess"):
            text = json.dumps(answer).lower()
            if "tfa" in text or "two-factor" in text or "security code" in text:
                raise SystemExit("TFA: this cPanel account asks for a two-factor code (set CPANEL_TFA).")
            message = answer.get("message") or answer.get("error") or "wrong user or password"
            raise SystemExit(f"cPanel login failed: {message}")
        self.token = token

    def uapi(self, module: str, function: str, **params: str) -> dict:
        self.login()
        query = urllib.parse.urlencode(params)
        url = f"{self.base}{self.token}/execute/{module}/{function}?{query}"
        answer = json.loads(self._open(url).read().decode("utf-8", "replace"))
        if not answer.get("status"):
            raise RuntimeError(f"{module}::{function}: {'; '.join(answer.get('errors') or ['failed'])}")
        return answer

    def download(self, url_path: str, out: str, expect_gzip: bool = False) -> int:
        self.login()
        url = f"{self.base}{self.token}{url_path}"
        tmp = out + ".part"
        resp = self._open(url, timeout=600)
        size = 0
        started = time.time()
        last_print = 0.0
        with open(tmp, "wb") as fh:
            first = True
            while True:
                chunk = resp.read(1024 * 256)
                if not chunk:
                    break
                if first:
                    if expect_gzip and chunk[:2] != b"\x1f\x8b":
                        raise RuntimeError("the download is not a gzip file (cPanel answered with a page)")
                    first = False
                fh.write(chunk)
                size += len(chunk)
                now = time.time()
                if sys.stderr.isatty() and now - last_print > 2:
                    print(f"\r    {size / 1048576:,.1f} MB  ({size / 1048576 / max(now - started, 1):.1f} MB/s)",
                          end="", file=sys.stderr)
                    last_print = now
        if sys.stderr.isatty():
            print("", file=sys.stderr)
        os.replace(tmp, out)
        return size

    def logout(self) -> None:
        if self.token:
            try:
                self._open(f"{self.base}{self.token}/logout/?locale=en", timeout=20).read()
            except Exception:
                pass


class _Prefixed:
    """A response whose first bytes were already read (to look for the bot check)."""

    def __init__(self, head: bytes, resp) -> None:
        self.head, self.resp = head, resp

    def read(self, n: int = -1) -> bytes:
        if self.head:
            if n < 0:
                data, self.head = self.head + self.resp.read(), b""
                return data
            data, self.head = self.head[:n], self.head[n:]
            return data
        return self.resp.read() if n < 0 else self.resp.read(n)


# ── commands ────────────────────────────────────────────────────────────────
def cmd_check(cp: CPanel, _args: list[str]) -> int:
    cp.login()
    print(f"logged in to {cp.base} as {cp.user}")
    return 0


def cmd_sqldump(cp: CPanel, args: list[str]) -> int:
    db, out = args
    size = cp.download(f"/getsqlbackup/{urllib.parse.quote(db)}.sql.gz", out, expect_gzip=True)
    print(f"{db}: {size / 1048576:,.1f} MB -> {out}")
    return 0


def cmd_cert(cp: CPanel, args: list[str]) -> int:
    domain, outdir = args
    data = cp.uapi("SSL", "fetch_best_for_domain", domain=domain).get("data") or {}
    crt, key, cab = data.get("crt"), data.get("key"), data.get("cab") or ""
    if not crt or not key:
        raise SystemExit(f"cPanel has no certificate with a key for {domain}.")
    os.makedirs(outdir, exist_ok=True)
    old = os.umask(0o077)
    try:
        with open(os.path.join(outdir, "fullchain.pem"), "w") as fh:
            fh.write(crt.strip() + "\n" + (cab.strip() + "\n" if cab.strip() else ""))
        with open(os.path.join(outdir, "privkey.pem"), "w") as fh:
            fh.write(key.strip() + "\n")
    finally:
        os.umask(old)
    print(f"certificate for {domain} -> {outdir}/fullchain.pem, privkey.pem")
    return 0


def cmd_getfile(cp: CPanel, args: list[str]) -> int:
    remote, out = args
    path = "/download?" + urllib.parse.urlencode({"skipencode": "1", "file": remote})
    size = cp.download(path, out)
    print(f"{remote}: {size:,} bytes -> {out}")
    return 0


def cmd_mirror(cp: CPanel, args: list[str]) -> int:
    excludes: set[str] = set()
    exts: list[str] = []
    recursive = True
    positional: list[str] = []
    it = iter(args)
    for a in it:
        if a == "--exclude":
            excludes.add(next(it).strip("/"))
        elif a == "--include-ext":
            exts.append(next(it).lower())
        elif a == "--no-recursion":
            recursive = False
        else:
            positional.append(a)
    remote_root, local_root = positional
    remote_root = remote_root.rstrip("/")

    todo: list[tuple[str, str, int, int]] = []
    dirs = [("", remote_root)]
    seen = 0
    list_failures = 0
    while dirs:
        rel, remote = dirs.pop()
        try:
            listing = cp.uapi("Fileman", "list_files", dir=remote, show_hidden="1",
                              include_mime="0", include_permissions="0").get("data") or []
        except RuntimeError as exc:
            # A folder that cannot be read is a failure: a re-run must not look complete.
            print(f"  ! cannot list {remote}: {exc}", file=sys.stderr)
            list_failures += 1
            continue
        for item in listing:
            name = item.get("file", "")
            rel_path = f"{rel}/{name}" if rel else name
            if name in ("", ".", "..") or rel_path in excludes:
                continue
            kind = item.get("type")
            if kind == "dir":
                if recursive:
                    dirs.append((rel_path, f"{remote}/{name}"))
                continue
            if kind != "file":
                continue  # links and specials are never followed
            if exts and not any(name.lower().endswith(e) for e in exts):
                continue
            seen += 1
            local = os.path.join(local_root, rel_path)
            size, mtime = int(item.get("size") or 0), int(item.get("mtime") or 0)
            try:
                st = os.stat(local)
                if st.st_size == size and int(st.st_mtime) == mtime:
                    continue
            except FileNotFoundError:
                pass
            todo.append((f"{remote}/{name}", local, size, mtime))
    total = sum(t[2] for t in todo)
    print(f"  {remote_root}: {seen} files, {len(todo)} to copy ({total / 1048576:,.1f} MB)", file=sys.stderr)

    failures = 0

    def fetch(job: tuple[str, str, int, int]) -> None:
        remote, local, _size, mtime = job
        os.makedirs(os.path.dirname(local) or ".", exist_ok=True)
        path = "/download?" + urllib.parse.urlencode({"skipencode": "1", "file": remote})
        cp.download(path, local)
        os.utime(local, (mtime, mtime))

    done = 0
    with concurrent.futures.ThreadPoolExecutor(max_workers=int(os.environ.get("CPANEL_PARALLEL", "4"))) as pool:
        futures = {pool.submit(fetch, job): job for job in todo}
        for fut in concurrent.futures.as_completed(futures):
            done += 1
            try:
                fut.result()
            except Exception as exc:  # keep going; a re-run picks up what failed
                failures += 1
                print(f"  ! {futures[fut][0]}: {exc}", file=sys.stderr)
            if done % 200 == 0:
                print(f"  {done}/{len(todo)} files", file=sys.stderr)
    print(f"  copied {len(todo) - failures}, failed {failures}", file=sys.stderr)
    return 1 if failures or list_failures else 0


COMMANDS = {"check": (cmd_check, 0), "sqldump": (cmd_sqldump, 2), "cert": (cmd_cert, 2),
            "getfile": (cmd_getfile, 2), "mirror": (cmd_mirror, -1)}


def main() -> int:
    if len(sys.argv) < 2 or sys.argv[1] not in COMMANDS:
        print(__doc__, file=sys.stderr)
        return 2
    func, nargs = COMMANDS[sys.argv[1]]
    args = sys.argv[2:]
    if nargs >= 0 and len(args) != nargs:
        print(__doc__, file=sys.stderr)
        return 2
    cp = CPanel()
    try:
        return func(cp, args)
    except SystemExit as exc:
        if isinstance(exc.code, str):
            print(exc.code, file=sys.stderr)
            return 3 if exc.code.startswith("TFA:") else 1
        raise
    except Exception as exc:
        print(f"error: {exc}", file=sys.stderr)
        return 1
    finally:
        cp.logout()


if __name__ == "__main__":
    sys.exit(main())
