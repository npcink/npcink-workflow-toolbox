#!/usr/bin/env bash
#
# Release-time bundle minification: writes .min.js/.min.css siblings next to
# the shipped admin and editor bundles inside TARGET_DIR (the packaged plugin
# copy produced by `composer package:release`). Repository sources stay
# unminified and reviewable; the runtime enqueues prefer the .min sibling
# unless SCRIPT_DEBUG is on, and fall back to the readable source when the
# .min file is absent (development checkouts).
#
# Requires node; terser and clean-css-cli are fetched through npx and are not
# committed to the repository.
#
# Usage: scripts/minify-assets.sh [TARGET_DIR]

set -euo pipefail

TARGET_DIR="${1:-$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)}"

command -v node >/dev/null 2>&1 || {
	echo "[minify] node is required to minify release assets" >&2
	exit 127
}

js_bundles=(
	"assets/admin.js"
	"assets/editor-content-format.js"
	"assets/editor-content-support.js"
	"assets/editor-content-support/text-utils.js"
	"assets/editor-content-support/internal-links.js"
	"assets/editor-content-support/audio-preferences.js"
)

css_bundles=(
	"assets/admin.css"
	"assets/editor-content-support.css"
)

total_before=0
total_after=0

for bundle in "${js_bundles[@]}"; do
	src="$TARGET_DIR/$bundle"
	out="$TARGET_DIR/${bundle%.js}.min.js"
	if [[ ! -f "$src" ]]; then
		echo "[minify] missing bundle: $src" >&2
		exit 1
	fi
	npx --yes terser@5 --compress --mangle --ecma 2022 -o "$out" -- "$src"
	before=$(wc -c <"$src" | tr -d ' ')
	after=$(wc -c <"$out" | tr -d ' ')
	total_before=$((total_before + before))
	total_after=$((total_after + after))
	echo "[minify] $bundle: ${before} -> ${after} bytes"
done

for bundle in "${css_bundles[@]}"; do
	src="$TARGET_DIR/$bundle"
	out="$TARGET_DIR/${bundle%.css}.min.css"
	if [[ ! -f "$src" ]]; then
		echo "[minify] missing bundle: $src" >&2
		exit 1
	fi
	npx --yes clean-css-cli@5 -o "$out" "$src"
	before=$(wc -c <"$src" | tr -d ' ')
	after=$(wc -c <"$out" | tr -d ' ')
	total_before=$((total_before + before))
	total_after=$((total_after + after))
	echo "[minify] $bundle: ${before} -> ${after} bytes"
done

if (( total_after >= total_before )); then
	echo "[minify] minified output is not smaller than the sources; refusing" >&2
	exit 1
fi

echo "[minify] total: ${total_before} -> ${total_after} bytes"
