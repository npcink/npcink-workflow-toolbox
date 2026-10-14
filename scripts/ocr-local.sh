#!/usr/bin/env bash

set -euo pipefail

fail() {
	echo "[ocr-local] error: $*" >&2
	exit 1
}

command -v git >/dev/null 2>&1 || fail 'git is required'
command -v ocr >/dev/null 2>&1 || fail 'open-code-review CLI (ocr) is required'
command -v jq >/dev/null 2>&1 || fail 'jq is required to validate the local OCR configuration'

root="$(git rev-parse --show-toplevel 2>/dev/null)" || fail 'run inside a Git worktree'
cd "$root"

config_path="${OCR_CONFIG_PATH:-${HOME}/.opencodereview/config.json}"
[ -f "$config_path" ] || fail "local OCR config not found: $config_path"

provider="$(jq -r '.provider // empty' "$config_path")"
model="$(jq -r --arg provider "$provider" 'if ($provider != "" and .custom_providers[$provider] != null) then (.custom_providers[$provider].model // .model // empty) else (.model // empty) end' "$config_path")"
url="$(jq -r --arg provider "$provider" 'if ($provider != "" and .custom_providers[$provider] != null) then (.custom_providers[$provider].url // empty) else (.url // empty) end' "$config_path")"

[ -n "$provider" ] || fail "local OCR config has no active provider: $config_path"
[ -n "$model" ] || fail "local OCR config has no model for provider $provider"
[ -n "$url" ] || fail "local OCR config has no URL for provider $provider"

echo "[ocr-local] provider: $provider"
echo "[ocr-local] model: $model"
echo "[ocr-local] endpoint: configured"
echo "[ocr-local] source: $config_path"

if [ "$#" -eq 0 ]; then
	set -- --from origin/master --to HEAD --effort low --concurrency 1 --timeout 8 --max-tokens-budget 80000
fi

echo '[ocr-local] running local review; GitHub CI remains the publish gate'
exec ocr review "$@"
