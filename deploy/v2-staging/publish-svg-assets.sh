#!/usr/bin/env bash

set -euo pipefail

repository_root="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
source_root="${repository_root}/public/images"
target_root="${1:-/home/spuedu/public_html/spu_v2/public/images}"

if [[ ! -d "${source_root}" ]]; then
    echo "SVG source directory does not exist: ${source_root}" >&2
    exit 1
fi

mkdir -p "${target_root}"

copied=0
while IFS= read -r -d '' source_file; do
    relative_path="${source_file#"${source_root}/"}"
    target_file="${target_root}/${relative_path}"

    mkdir -p "$(dirname "${target_file}")"
    install -m 0644 "${source_file}" "${target_file}"
    copied=$((copied + 1))
done < <(find "${source_root}" -type f -name '*.svg' -print0)

if [[ ${copied} -eq 0 ]]; then
    echo "No SVG assets were found under ${source_root}" >&2
    exit 1
fi

for required_icon in \
    icon-search-outline.svg \
    icon-chevron-right-outline.svg \
    icons/check-circle.svg; do
    if [[ ! -s "${target_root}/${required_icon}" ]]; then
        echo "Required SVG was not published: ${target_root}/${required_icon}" >&2
        exit 1
    fi
done

echo "Published ${copied} SVG assets to ${target_root}"
