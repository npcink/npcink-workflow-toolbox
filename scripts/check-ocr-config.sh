#!/usr/bin/env bash

set -euo pipefail

usage() {
	cat <<'EOF'
Usage:
  scripts/check-ocr-config.sh [--local-only]

Checks the local OpenCodeReview model/provider metadata against the GitHub
Actions model variables. GitHub secret values are never readable; the check
reports only whether the two required secrets exist and when they were last
updated.
EOF
}

fail() {
	echo "[ocr-config] error: $*" >&2
	exit 1
}

local_only=0
case "${1:-}" in
	'') ;;
	--local-only) local_only=1 ;;
	--help|-h) usage; exit 0 ;;
	*) usage >&2; exit 2 ;;
esac

command -v jq >/dev/null 2>&1 || fail 'jq is required'
command -v git >/dev/null 2>&1 || fail 'git is required'

root="$(git rev-parse --show-toplevel 2>/dev/null)" || fail 'run inside a Git worktree'
cd "$root"

config_path="${OCR_CONFIG_PATH:-${HOME}/.opencodereview/config.json}"
[ -f "$config_path" ] || fail "local OCR config not found: $config_path"

provider="$(jq -r '.provider // empty' "$config_path")"
model="$(jq -r --arg provider "$provider" 'if ($provider != "" and .custom_providers[$provider] != null) then (.custom_providers[$provider].model // .model // empty) else (.model // empty) end' "$config_path")"
url="$(jq -r --arg provider "$provider" 'if ($provider != "" and .custom_providers[$provider] != null) then (.custom_providers[$provider].url // empty) else (.url // empty) end' "$config_path")"
protocol="$(jq -r --arg provider "$provider" 'if ($provider != "" and .custom_providers[$provider] != null) then (.custom_providers[$provider].protocol // empty) else empty end' "$config_path")"

[ -n "$provider" ] || fail 'local config has no active provider'
[ -n "$model" ] || fail "local config has no model for provider $provider"
[ -n "$url" ] || fail "local config has no URL for provider $provider"

echo '[ocr-config] Local configuration'
echo "  provider: $provider"
echo "  model: $model"
echo "  protocol: ${protocol:-default}"
echo '  endpoint: configured (value withheld from comparison output)'

if [ "$local_only" -eq 1 ]; then
	echo '[ocr-config] local-only check passed; no GitHub metadata queried'
	exit 0
fi

command -v gh >/dev/null 2>&1 || fail 'gh is required unless --local-only is used'
repo="$(gh repo view --json nameWithOwner --jq .nameWithOwner 2>/dev/null)" || fail 'unable to resolve the GitHub repository'

github_model="$(gh api "repos/${repo}/actions/variables/OCR_LLM_MODEL" --jq '.value' 2>/dev/null || true)"
github_anthropic="$(gh api "repos/${repo}/actions/variables/OCR_LLM_USE_ANTHROPIC" --jq '.value' 2>/dev/null || true)"
secret_json="$(gh api "repos/${repo}/actions/secrets" --jq '[.secrets[] | select(.name == "OCR_LLM_URL" or .name == "OCR_LLM_AUTH_TOKEN") | {name,updated_at}]' 2>/dev/null || true)"

echo '[ocr-config] GitHub Actions metadata'
echo "  repository: $repo"
echo "  model: ${github_model:-missing}"
echo "  use_anthropic: ${github_anthropic:-missing}"
printf '%s\n' "$secret_json" | jq -r '.[] | "  secret: \(.name) present (updated \(.updated_at); value withheld)"'

status=0
if [ "$github_model" != "$model" ]; then
	echo "[ocr-config] mismatch: local model '$model' != GitHub model '${github_model:-missing}'" >&2
	status=1
fi
if [ "$github_anthropic" != 'false' ]; then
	echo '[ocr-config] mismatch: OCR_LLM_USE_ANTHROPIC must be false for the OpenAI-compatible local provider' >&2
	status=1
fi
for secret_name in OCR_LLM_URL OCR_LLM_AUTH_TOKEN; do
	if ! printf '%s\n' "$secret_json" | jq -e --arg name "$secret_name" 'any(.[]; .name == $name)' >/dev/null; then
		echo "[ocr-config] missing GitHub secret: $secret_name" >&2
		status=1
	fi
done

echo '[ocr-config] Secret values are opaque in GitHub; compare only presence and update timestamps.'
if [ "$status" -eq 0 ]; then
	echo '[ocr-config] model/flag/secret-presence check passed'
else
	echo '[ocr-config] synchronization required before relying on the GitHub review gate' >&2
fi
exit "$status"
