#!/usr/bin/env bash
#
# Build /srv/spu/app.env from the cPanel .env.
#
#     make-app-env.sh <cpanel .env> <output> [stack.env]
#
# Copies every key as it is, then applies deploy/docker/env/app.env.overrides
# and the database password from stack.env. Prints only key names, never values.
set -euo pipefail
src="$1"; out="$2"; stack="${3:-/srv/spu/stack.env}"
here="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
[[ -f "${src}" ]] || { echo "No such file: ${src}" >&2; exit 1; }
[[ -f "${stack}" ]] || { echo "No such file: ${stack}" >&2; exit 1; }

umask 077
python3 - "${src}" "${out}" "${here}/env/app.env.overrides" "${stack}" <<'PY'
import re, sys
src, out, overrides_path, stack_path = sys.argv[1:5]

def parse(path):
    pairs = {}
    for line in open(path, encoding="utf-8"):
        m = re.match(r'^\s*([A-Z0-9_]+)=(.*)$', line.rstrip("\n"))
        if m:
            pairs[m.group(1)] = m.group(2)
    return pairs

stack = parse(stack_path)
overrides = parse(overrides_path)
for key in ("DB_DATABASE", "DB_USERNAME", "DB_PASSWORD"):
    if stack.get(key):
        overrides[key] = stack[key]
if not stack.get("DB_PASSWORD"):
    sys.exit("stack.env has no DB_PASSWORD")

lines, seen, changed = [], set(), []
for line in open(src, encoding="utf-8"):
    m = re.match(r'^\s*([A-Z0-9_]+)=(.*)$', line.rstrip("\n"))
    if m and m.group(1) in overrides:
        key = m.group(1)
        seen.add(key)
        if m.group(2) != overrides[key]:
            changed.append(key)
        lines.append(f"{key}={overrides[key]}\n")
    else:
        lines.append(line if line.endswith("\n") else line + "\n")
added = [k for k in overrides if k not in seen]
if added:
    lines.append("\n# Docker VPS (make-app-env.sh)\n")
    lines += [f"{k}={overrides[k]}\n" for k in added]

keys = parse(src)
for required in ("APP_KEY", "ADMIN_PATH"):
    if not keys.get(required):
        sys.exit(f"The cPanel .env has no {required}; refusing to build app.env without it.")

open(out, "w", encoding="utf-8").writelines(lines)
print("changed:", ", ".join(changed) or "nothing")
print("added:  ", ", ".join(added) or "nothing")
print("kept as they were: APP_KEY, ADMIN_PATH and", len(keys) - len(changed) - 2, "other keys")
PY
chmod 600 "${out}"
# PHP runs as www-data (uid 33) in the containers and must read it.
chown 33:33 "${out}" 2>/dev/null || echo "Run: chown 33:33 ${out} (PHP reads it as www-data)" >&2
