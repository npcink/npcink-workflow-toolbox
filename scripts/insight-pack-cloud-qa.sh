#!/bin/sh
# MVP wrapper: load site_ops insight pack and ask Cloud one question via Addon.
#
# Usage:
#   WP_PATH="/path/to/wordpress" scripts/insight-pack-cloud-qa.sh
#   scripts/insight-pack-cloud-qa.sh "站点优先该处理什么？"
#   scripts/insight-pack-cloud-qa.sh "问题" "/path/to/wordpress"
#
# Env:
#   WP_PATH / WP_CLI_BIN / WP_CLI_PHP / WP_DB_SOCKET / NPCINK_INSIGHT_QA_USER
#   NPCINK_INSIGHT_QA_QUESTION / NPCINK_INSIGHT_QA_OUT
#
# Docs (humans + other AIs): docs/insight-pack-cloud-qa-cli.md
# MVP boundary: docs/deidentified-assistant-boundary-draft-v1.md
# See scripts/insight-pack-cloud-qa.php header for contract details.
set -eu

ROOT="$(CDPATH= cd -- "$(dirname "$0")/.." && pwd)"
QUESTION="${1:-${NPCINK_INSIGHT_QA_QUESTION:-}}"
. "$ROOT/scripts/insight-pack-cloud-qa-wp-cli.sh"
insight_qa_require_wordpress "${2:-}"
USER_ID="${NPCINK_INSIGHT_QA_USER:-178}"
OUT="${NPCINK_INSIGHT_QA_OUT:-$ROOT/tmp/insight-pack-cloud-qa-result.json}"

if [ ! -f "$ROOT/scripts/insight-pack-cloud-qa.php" ]; then
	echo "Missing $ROOT/scripts/insight-pack-cloud-qa.php" >&2
	exit 1
fi

mkdir -p "$(dirname "$OUT")"

export NPCINK_INSIGHT_QA_OUT="$OUT"
if [ -n "$QUESTION" ]; then
	export NPCINK_INSIGHT_QA_QUESTION="$QUESTION"
	wp_cli eval-file "$ROOT/scripts/insight-pack-cloud-qa.php" "$QUESTION"
else
	wp_cli eval-file "$ROOT/scripts/insight-pack-cloud-qa.php"
fi

echo "full JSON: $OUT" >&2
