#!/usr/bin/env python3
"""Find the MySQL login an old PHP application uses for its database.

    find-db-login.py <application folder> <database name>

Prints "file<TAB>user<TAB>password" for the best match, or nothing. Looks at the
application's own config files (PHP, .ini, .env) that mention the database and
understands the usual styles:

    mysqli_connect('localhost', 'user', 'pass', 'db')   new mysqli($host, $user, $pass, $db)
    new PDO('mysql:...', 'user', "pass")                 define('DB_USER', 'user')
    $db_pass = '...';    'password' => '...'             DB_PASSWORD=... (.env)

migrate.sh shows the result (password masked) and asks before using it.
"""

from __future__ import annotations

import os
import re
import sys

LIT = r"""'((?:[^'\\]|\\.)*)'|"((?:[^"\\]|\\.)*)\""""
USER = r"(?:db_?user(?:name)?|database_?user(?:name)?|dbuser|mysql_?user|user(?:name)?)"
PASS = r"(?:db_?pass(?:word)?|database_?pass(?:word)?|dbpass|mysql_?pass(?:word)?|pass(?:word)?|pwd)"
SKIP_DIRS = {"uploads", "upload", "files", "images", "vendor", "node_modules", "cache"}


def unescape(value: str) -> str:
    return re.sub(r"\\(.)", r"\1", value)


def literal(token: str) -> str | None:
    m = re.fullmatch(r"\s*(?:" + LIT + r")\s*", token, re.S)
    if not m:
        return None
    return unescape(m.group(1) if m.group(1) is not None else m.group(2))


def resolve(token: str, text: str) -> str | None:
    """A call argument: a string, a $variable assigned a string, or a define()d constant."""
    token = token.strip()
    value = literal(token)
    if value is not None:
        return value
    m = re.fullmatch(r"\$(\w+)", token)
    if m:
        a = re.search(r"\$" + m.group(1) + r"\s*=\s*(" + LIT + r")\s*;", text)
        return literal(a.group(1)) if a else None
    if re.fullmatch(r"[A-Za-z_]\w*", token):
        a = re.search(r"define\s*\(\s*['\"]" + token + r"['\"]\s*,\s*(" + LIT + r")", text)
        return literal(a.group(1)) if a else None
    return None


def args_of(call: str, text: str) -> list[str]:
    m = re.search(call + r"\s*\(", text, re.I)
    if not m:
        return []
    parts, buf, quote, depth = [], "", None, 0
    i = m.end()
    while i < len(text):
        ch = text[i]
        if quote:
            buf += ch
            if ch == "\\" and i + 1 < len(text):
                buf += text[i + 1]
                i += 1
            elif ch == quote:
                quote = None
        elif ch in "'\"":
            quote = ch
            buf += ch
        elif ch == "(":
            depth += 1
            buf += ch
        elif ch == ")":
            if depth == 0:
                break
            depth -= 1
            buf += ch
        elif ch == "," and depth == 0:
            parts.append(buf)
            buf = ""
        else:
            buf += ch
        i += 1
    parts.append(buf)
    return parts


def by_key(keys: str, text: str) -> str | None:
    for rx in (rf"define\s*\(\s*['\"]{keys}['\"]\s*,\s*({LIT})",
               rf"\$\w*{keys}\w*\s*=\s*({LIT})\s*;",
               rf"['\"]{keys}['\"]\s*=>\s*({LIT})"):
        m = re.search(rx, text, re.I)
        if m:
            return literal(m.group(1))
    m = re.search(rf"^\s*{keys}\s*=\s*['\"]?([^'\"\s]*)", text, re.I | re.M)
    return m.group(1) if m else None


def login_in(text: str) -> tuple[str, str] | None:
    for call, ui, pi in ((r"(?:mysqli_connect|new\s+mysqli|mysql_connect)", 1, 2),
                         (r"mysqli_real_connect", 2, 3),
                         (r"new\s+PDO", 1, 2)):
        a = args_of(call, text)
        if len(a) > pi:
            user, pw = resolve(a[ui], text), resolve(a[pi], text)
            if user and pw is not None:
                return user, pw
    user, pw = by_key(USER, text), by_key(PASS, text)
    if user and pw is not None and not user.startswith("$"):
        return user, pw
    return None


def main() -> int:
    if len(sys.argv) != 3:
        print(__doc__, file=sys.stderr)
        return 2
    root, db = sys.argv[1:3]
    found = []
    for dp, dirs, files in os.walk(root):
        dirs[:] = [d for d in dirs if d.lower() not in SKIP_DIRS]
        for f in files:
            if not f.lower().endswith((".php", ".inc", ".ini", ".env", ".conf", ".cfg")):
                continue
            p = os.path.join(dp, f)
            try:
                if os.path.getsize(p) > 400_000:
                    continue
                text = open(p, encoding="utf-8", errors="replace").read()
            except OSError:
                continue
            if db not in text:
                continue
            login = login_in(text)
            if login:
                found.append((os.path.relpath(p, root), *login))
    # Prefer a cPanel-style user (spuedu_...) and the shallowest file (the main config).
    found.sort(key=lambda r: (not r[1].startswith("spuedu_"), r[0].count("/"), len(r[0])))
    if found:
        print("\t".join(found[0]))
    return 0


if __name__ == "__main__":
    sys.exit(main())
