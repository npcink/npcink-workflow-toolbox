#!/bin/sh
# Pack 3 wrapper: page_quality insight pack + Cloud question via Addon.
#
# Usage:
#   WP_PATH="/path/to/wordpress" scripts/page-quality-pack-cloud-qa.sh
#   scripts/page-quality-pack-cloud-qa.sh "页面质量优先补什么？"
#   scripts/page-quality-pack-cloud-qa.sh "问题" "/path/to/wordpress"
#
# Docs: docs/page-quality-pack-cloud-qa-cli.md
# Boundary: docs/deidentified-assistant-boundary-v1.md
set -eu

ROOT="$(CDPATH= cd -- "$(dirname "$0")/.." && pwd)"
QUESTION="${1:-${NPCINK_PAGE_QUALITY_QA_QUESTION:-}}"
. "$ROOT/scripts/insight-pack-cloud-qa-wp-cli.sh"
insight_qa_require_wordpress "${2:-}"
USER_ID="${NPCINK_PAGE_QUALITY_QA_USER:-${NPCINK_INSIGHT_QA_USER:-178}}"
OUT="${NPCINK_PAGE_QUALITY_QA_OUT:-$ROOT/tmp/page-quality-pack-cloud-qa-result.json}"

if [ ! -f "$ROOT/scripts/page-quality-pack-cloud-qa.php" ]; then
	echo "Missing $ROOT/scripts/page-quality-pack-cloud-qa.php" >&2
	exit 1
fi

mkdir -p "$(dirname "$OUT")"

export NPCINK_PAGE_QUALITY_QA_OUT="$OUT"
if [ -n "$QUESTION" ]; then
	export NPCINK_PAGE_QUALITY_QA_QUESTION="$QUESTION"
	wp_cli eval-file "$ROOT/scripts/page-quality-pack-cloud-qa.php" "$QUESTION"
else
	wp_cli eval-file "$ROOT/scripts/page-quality-pack-cloud-qa.php"
fi
