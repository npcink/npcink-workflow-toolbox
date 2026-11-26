#!/bin/sh
# Pack 2 wrapper: content_structure insight pack + Cloud question via Addon.
#
# Usage:
#   WP_PATH="/path/to/wordpress" scripts/content-structure-pack-cloud-qa.sh
#   scripts/content-structure-pack-cloud-qa.sh "内容结构优先看什么？"
#   scripts/content-structure-pack-cloud-qa.sh "问题" "/path/to/wordpress"
#
# Docs: docs/content-structure-pack-cloud-qa-cli.md
# Boundary: docs/deidentified-assistant-boundary-v1.md
set -eu

ROOT="$(CDPATH= cd -- "$(dirname "$0")/.." && pwd)"
QUESTION="${1:-${NPCINK_CONTENT_STRUCTURE_QA_QUESTION:-}}"
. "$ROOT/scripts/insight-pack-cloud-qa-wp-cli.sh"
insight_qa_require_wordpress "${2:-}"
USER_ID="${NPCINK_CONTENT_STRUCTURE_QA_USER:-${NPCINK_INSIGHT_QA_USER:-178}}"
OUT="${NPCINK_CONTENT_STRUCTURE_QA_OUT:-$ROOT/tmp/content-structure-pack-cloud-qa-result.json}"

if [ ! -f "$ROOT/scripts/content-structure-pack-cloud-qa.php" ]; then
	echo "Missing $ROOT/scripts/content-structure-pack-cloud-qa.php" >&2
	exit 1
fi

mkdir -p "$(dirname "$OUT")"

export NPCINK_CONTENT_STRUCTURE_QA_OUT="$OUT"
if [ -n "$QUESTION" ]; then
	export NPCINK_CONTENT_STRUCTURE_QA_QUESTION="$QUESTION"
	wp_cli eval-file "$ROOT/scripts/content-structure-pack-cloud-qa.php" "$QUESTION"
else
	wp_cli eval-file "$ROOT/scripts/content-structure-pack-cloud-qa.php"
fi
