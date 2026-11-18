#!/usr/bin/env python3
"""Record and compare how the site answers old-site URLs.

Webometrics and search engines know the university through the old site's
URLs, so moving hosts must not change a single redirect: the same status code
and the same target, for every old address. This tool proves that.

    # 1. Before the move: what the current server answers (v2.spu.edu.sy).
    redirect-parity.py record --base https://v2.spu.edu.sy \
        --paths old-sitemap-paths.txt.gz --sample 1500 --out baseline.tsv

    # 2. On the new VPS, before DNS moves: same paths, new server, real hostname.
    redirect-parity.py record --base https://spu.edu.sy --connect-to 203.0.113.10 \
        --insecure --paths baseline.tsv --out vps.tsv

    # 3. Compare. Exit status 1 when anything differs.
    redirect-parity.py compare baseline.tsv vps.tsv

Only the Python standard library is used, so it runs on a bare VPS. Redirects are
never followed: what is recorded is the first answer, which is what a crawler
holding the old URL sees. Locations on any of the site's own hosts
(spu.edu.sy, www.spu.edu.sy, v2.spu.edu.sy) are written as SITE/<path>, so a
baseline taken on v2 compares cleanly with the live domain.
"""

from __future__ import annotations

import argparse
import concurrent.futures
import gzip
import http.client
import random
import socket
import ssl
import sys
import time
from urllib.parse import urlsplit

SITE_HOSTS = {"spu.edu.sy", "www.spu.edu.sy", "v2.spu.edu.sy", "www.v2.spu.edu.sy"}
BOT_CHECK_MARKERS = (b"One moment, please", b"One moment please")
USER_AGENT = "spu-redirect-parity/1.0"


class PinnedHTTPSConnection(http.client.HTTPSConnection):
    """HTTPS to a fixed IP while SNI and Host stay the real hostname (curl --resolve)."""

    def __init__(self, host: str, ip: str | None, **kwargs):
        super().__init__(host, **kwargs)
        self._pinned_ip = ip

    def connect(self) -> None:
        if not self._pinned_ip:
            return super().connect()
        sock = socket.create_connection((self._pinned_ip, self.port or 443), self.timeout)
        self.sock = self._context.wrap_socket(sock, server_hostname=self.host)


def read_paths(path: str) -> list[str]:
    opener = gzip.open if path.endswith(".gz") else open
    out: list[str] = []
    with opener(path, "rt", encoding="utf-8") as fh:
        for line in fh:
            line = line.rstrip("\n")
            if not line or line.startswith("#"):
                continue
            # A TSV written by `record` is also accepted: its first column is the path.
            first = line.split("\t", 1)[0]
            if first == "path":
                continue
            out.append(first if first.startswith("/") else "/" + first)
    return out


def normalise_location(location: str | None) -> str:
    if not location:
        return ""
    parts = urlsplit(location)
    if not parts.scheme and not parts.netloc:
        return "SITE" + location
    if parts.hostname and parts.hostname.lower() in SITE_HOSTS:
        query = "?" + parts.query if parts.query else ""
        return "SITE" + (parts.path or "/") + query
    return location


def fetch(base: str, path: str, ip: str | None, insecure: bool, timeout: float, retries: int) -> tuple[str, str, str]:
    parts = urlsplit(base)
    host = parts.hostname or ""
    port = parts.port
    context = ssl.create_default_context()
    if insecure:
        context.check_hostname = False
        context.verify_mode = ssl.CERT_NONE

    last_error = ""
    for attempt in range(retries + 1):
        try:
            if parts.scheme == "https":
                conn = PinnedHTTPSConnection(host, ip, port=port, timeout=timeout, context=context)
            else:
                conn = http.client.HTTPConnection(ip or host, port=port, timeout=timeout)
            conn.request("GET", path, headers={
                "Host": host if not port else f"{host}:{port}",
                "User-Agent": USER_AGENT,
                "Accept": "text/html,*/*",
                "Accept-Language": "ar",
            })
            resp = conn.getresponse()
            body = resp.read(4096)
            location = resp.getheader("Location")
            conn.close()
            if resp.status in (200, 202, 403, 503) and any(m in body for m in BOT_CHECK_MARKERS):
                # The current host's bot check sometimes answers instead of the
                # application. That is not the site's answer, so wait and retry.
                last_error = "bot-check"
                time.sleep(2 + attempt * 3)
                continue
            return str(resp.status), normalise_location(location), ""
        except Exception as exc:  # network errors are recorded, not fatal
            last_error = type(exc).__name__ + ": " + str(exc)
            time.sleep(1 + attempt * 2)
    return "ERR", "", last_error


def cmd_record(args: argparse.Namespace) -> int:
    paths = list(dict.fromkeys(read_paths(args.paths)))
    if args.sample and args.sample < len(paths):
        rng = random.Random(args.seed)
        chosen = set(rng.sample(paths, args.sample))
        paths = [p for p in paths if p in chosen]
    # Extra paths are always requested in full, never sampled away.
    for extra in args.extra or []:
        paths.extend(read_paths(extra))
    paths = list(dict.fromkeys(paths))

    results: dict[str, tuple[str, str, str]] = {}
    started = time.time()
    with concurrent.futures.ThreadPoolExecutor(max_workers=args.concurrency) as pool:
        futures = {
            pool.submit(fetch, args.base, p, args.connect_to, args.insecure, args.timeout, args.retries): p
            for p in paths
        }
        for done, future in enumerate(concurrent.futures.as_completed(futures), 1):
            results[futures[future]] = future.result()
            if done % 200 == 0:
                print(f"  {done}/{len(paths)} in {time.time() - started:.0f}s", file=sys.stderr)
            if args.delay:
                time.sleep(args.delay)

    with open(args.out, "w", encoding="utf-8") as fh:
        fh.write("path\tstatus\tlocation\terror\n")
        for p in paths:
            status, location, error = results[p]
            fh.write(f"{p}\t{status}\t{location}\t{error}\n")

    counts: dict[str, int] = {}
    for status, _, _ in results.values():
        counts[status] = counts.get(status, 0) + 1
    print(f"Recorded {len(paths)} paths from {args.base} -> {args.out}: "
          + ", ".join(f"{k}={v}" for k, v in sorted(counts.items())), file=sys.stderr)
    return 0


def load(path: str) -> dict[str, tuple[str, str]]:
    rows: dict[str, tuple[str, str]] = {}
    with open(path, encoding="utf-8") as fh:
        next(fh, None)
        for line in fh:
            cols = line.rstrip("\n").split("\t")
            if len(cols) >= 3:
                rows[cols[0]] = (cols[1], cols[2])
    return rows


def cmd_compare(args: argparse.Namespace) -> int:
    before, after = load(args.before), load(args.after)
    common = [p for p in before if p in after]
    differ = [p for p in common if before[p] != after[p] and "ERR" not in (before[p][0], after[p][0])]
    errors = [p for p in common if "ERR" in (before[p][0], after[p][0])]
    missing = [p for p in before if p not in after]

    print(f"Compared {len(common)} paths: {len(common) - len(differ) - len(errors)} identical, "
          f"{len(differ)} different, {len(errors)} unreadable, {len(missing)} not in the second run.")
    for p in differ[: args.show]:
        print(f"DIFF {p}\n     before {before[p][0]} {before[p][1]}\n     after  {after[p][0]} {after[p][1]}")
    for p in errors[: args.show]:
        print(f"ERR  {p}  before={before[p][0]} after={after[p][0]}")
    return 1 if differ or (errors and args.strict) else 0


def main() -> int:
    parser = argparse.ArgumentParser(description=__doc__, formatter_class=argparse.RawDescriptionHelpFormatter)
    sub = parser.add_subparsers(dest="cmd", required=True)

    rec = sub.add_parser("record", help="request every path once and write status + Location")
    rec.add_argument("--base", required=True, help="scheme and host, e.g. https://spu.edu.sy")
    rec.add_argument("--paths", required=True, help="paths file (.txt, .txt.gz, or a TSV from record)")
    rec.add_argument("--extra", action="append", help="another paths file to add")
    rec.add_argument("--out", required=True)
    rec.add_argument("--connect-to", help="IP to connect to instead of resolving the host (before DNS moves)")
    rec.add_argument("--insecure", action="store_true", help="accept any certificate (self-signed test TLS)")
    rec.add_argument("--sample", type=int, default=0, help="random sample of this many paths (0 = all)")
    rec.add_argument("--seed", type=int, default=20261117)
    rec.add_argument("--concurrency", type=int, default=3)
    rec.add_argument("--delay", type=float, default=0.0, help="seconds to pause after each answer")
    rec.add_argument("--timeout", type=float, default=30.0)
    rec.add_argument("--retries", type=int, default=3)
    rec.set_defaults(func=cmd_record)

    cmp_ = sub.add_parser("compare", help="compare two recordings")
    cmp_.add_argument("before")
    cmp_.add_argument("after")
    cmp_.add_argument("--show", type=int, default=50)
    cmp_.add_argument("--strict", action="store_true", help="also fail when a path could not be read")
    cmp_.set_defaults(func=cmd_compare)

    args = parser.parse_args()
    return args.func(args)


if __name__ == "__main__":
    sys.exit(main())
