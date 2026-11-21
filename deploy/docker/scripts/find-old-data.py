#!/usr/bin/env python3
"""Find the old server's data in a folder copied by hand (e.g. SPU-Old-Server-Data).

    find-old-data.py <folder>

Prints one line per item found, "item<TAB>path", best match first; other
candidates follow as "alt<TAB>item<TAB>path". migrate.sh shows them and asks.
Each item may be a folder, a .zip, a .tar.gz/.tgz/.tar, or (dumps) .sql/.sql.gz.
The folder can be laid out any way: items are recognised by what they hold and
by names the old server uses (public_html, spu_v2_app, downloads, ...).

Items:
    env                      v2's .env (a file with APP_KEY= and DB_DATABASE=)
    storage                  v2's storage/ (holds app/)
    web                      v2's document root (public_html/spu_v2/public)
    app:forms app:Medicine app:spuh
    db:<name>                spuedu_v2, spuedu_forms, spuedu_medical, spuedu_spuh
    media:<path>             downloads/files, downloads/files2, downloads/root (the
                             loose files of public_html/downloads), images, FCKBIH,
                             <faculty>/images
Only the standard library is used; nothing is changed.
"""

from __future__ import annotations

import os
import re
import sys

DBS = ("spuedu_v2", "spuedu_forms", "spuedu_medical", "spuedu_spuh")
APPS = ("forms", "Medicine", "spuh")
SUBSITES = ("med", "dent", "pharm", "info", "petrol", "admin", "research", "hospital",
            "dent_clinic", "alumni", "clubs")
ARCHIVE = re.compile(r"\.(zip|tar\.gz|tgz|tar)$", re.I)
LOOSE = re.compile(r"\.(pdf|docx?|xlsx?|pptx?|zip|rar|jpe?g|png|gif)$", re.I)
SKIP = {"vendor", "node_modules", ".git", "cache", "__MACOSX"}

found: dict[str, list[tuple[int, str]]] = {}


def add(item: str, path: str, score: int) -> None:
    found.setdefault(item, []).append((score, path))


def parts(path: str) -> list[str]:
    return [p.lower() for p in path.replace("\\", "/").split("/") if p]


def stem(name: str) -> str:
    return ARCHIVE.sub("", name).lower()


def has_php(path: str) -> bool:
    for _root, _dirs, files in os.walk(path):
        if any(f.lower().endswith(".php") for f in files):
            return True
        if _root.count(os.sep) - path.count(os.sep) >= 2:
            break
    return False


def env_file(path: str) -> bool:
    try:
        if os.path.getsize(path) > 200_000:
            return False
        with open(path, encoding="utf-8", errors="replace") as fh:
            text = fh.read()
    except OSError:
        return False
    return "APP_KEY=" in text and "DB_DATABASE=" in text


def look_at_dir(path: str, name: str, up: list[str]) -> bool:
    """Record what this folder is; True when its inside need not be searched."""
    low = name.lower()
    parent = up[-1] if up else ""
    if low == "storage" and os.path.isdir(os.path.join(path, "app")):
        add("storage", path, 10 + (5 if "spu_v2_app" in up else 0))
        return True
    if low == "public" and os.path.isfile(os.path.join(path, "index.php")):
        add("web", path, 10 if "spu_v2" in up else 1)
        return True
    for app in APPS:
        if name == app or (low == app.lower() and parent != "downloads"):
            if has_php(path):
                add(f"app:{app}", path, 10 if parent == "public_html" else 5)
                return True
    if parent == "downloads" and low in ("files", "files2"):
        add(f"media:downloads/{low}", path, 10)
        return True
    if low == "fckbih":
        add("media:FCKBIH", path, 10)
        return True
    if low == "images":
        if parent in SUBSITES:
            add(f"media:{parent}/images", path, 10)
            return True
        if parent not in ("public", "spu_v2", "storage", "app") and "spu_v2" not in up and "spu_v2_app" not in up:
            add("media:images", path, 10 if parent == "public_html" else 3)
            return True
    if low == "downloads":
        try:
            if any(LOOSE.search(f) and os.path.isfile(os.path.join(path, f)) for f in os.listdir(path)):
                add("media:downloads/root", path, 10)
        except OSError:
            pass
    return False


def look_at_file(path: str, name: str, up: list[str]) -> None:
    low = name.lower()
    for db in DBS:
        if db in low and re.search(r"\.(sql|sql\.gz|gz|zip)$", low):
            score = 10 if re.search(r"\.sql(\.gz)?$", low) else 5
            # spuedu_v2 must not match a longer name such as spuedu_v2_old
            if re.search(re.escape(db) + r"(?![A-Za-z0-9]|_[A-Za-z])", low) or re.search(re.escape(db) + r"_?\d{6,}", low):
                add(f"db:{db}", path, score)
    if name in (".env", "env", "cpanel.env", ".env.txt", "env.txt") or low.endswith(".env"):
        if env_file(path):
            add("env", path, 10 if "spu_v2_app" in up or name in (".env", "env") else 5)
    if ARCHIVE.search(name):
        s = stem(name)
        if s in ("storage", "spu_v2_app-storage") or s.startswith("storage"):
            add("storage", path, 4)
        elif s in ("web", "public", "spu_v2-public"):
            add("web", path, 4)
        for app in APPS:
            if s in (app.lower(), f"app-{app.lower()}"):
                add(f"app:{app}", path, 4)
        for key, names in (("media:downloads/files", ("files", "downloads-files")),
                           ("media:downloads/files2", ("files2", "downloads-files2")),
                           ("media:FCKBIH", ("fckbih",)), ("media:images", ("images",))):
            if s in names:
                add(key, path, 4)


def main() -> int:
    if len(sys.argv) != 2 or not os.path.isdir(sys.argv[1]):
        print(__doc__)
        return 2
    top = os.path.abspath(sys.argv[1])
    for root, dirs, files in os.walk(top):
        rel = parts(os.path.relpath(root, top)) if root != top else []
        if len(rel) > 8:
            dirs[:] = []
            continue
        keep = []
        for d in sorted(dirs):
            if d in SKIP:
                continue
            if not look_at_dir(os.path.join(root, d), d, rel):
                keep.append(d)
        dirs[:] = keep
        for f in sorted(files):
            look_at_file(os.path.join(root, f), f, rel)
    order = ["env", "storage", "web", *[f"app:{a}" for a in APPS], *[f"db:{d}" for d in DBS],
             "media:downloads/files", "media:downloads/files2", "media:downloads/root", "media:images",
             "media:FCKBIH", *[f"media:{s}/images" for s in SUBSITES]]
    for item in order:
        cands = sorted(found.get(item, []), key=lambda c: (-c[0], len(c[1])))
        if not cands:
            continue
        print(f"{item}\t{cands[0][1]}")
        for _score, path in cands[1:4]:
            print(f"alt\t{item}\t{path}")
    return 0


if __name__ == "__main__":
    sys.exit(main())
