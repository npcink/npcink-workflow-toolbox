#!/bin/sh
# Pack 4 wrapper: environment_performance insight pack + Cloud question via Addon.
#
# Usage:
#   WP_PATH="/path/to/wordpress" scripts/environment-performance-pack-cloud-qa.sh
#   scripts/environment-performance-pack-cloud-qa.sh "环境与性能优先补什么？"
#   scripts/environment-performance-pack-cloud-qa.sh "问题" "/path/to/wordpress"
#
# Docs: docs/environment-performance-pack-cloud-qa-cli.md
# Boundary: docs/deidentified-assistant-boundary-v1.md
set -eu

ROOT="$(CDPATH= cd -- "$(dirname "$0")/.." && pwd)"
QUESTION="${1:-${NPCINK_ENVIRONMENT_PERFORMANCE_QA_QUESTION:-}}"
. "$ROOT/scripts/insight-pack-cloud-qa-wp-cli.sh"
insight_qa_require_wordpress "${2:-}"
USER_ID="${NPCINK_ENVIRONMENT_PERFORMANCE_QA_USER:-${NPCINK_INSIGHT_QA_USER:-178}}"
OUT="${NPCINK_ENVIRONMENT_PERFORMANCE_QA_OUT:-$ROOT/tmp/environment-performance-pack-cloud-qa-result.json}"

if [ ! -f "$ROOT/scripts/environment-performance-pack-cloud-qa.php" ]; then
	echo "Missing $ROOT/scripts/environment-performance-pack-cloud-qa.php" >&2
	exit 1
fi

mkdir -p "$(dirname "$OUT")"

export NPCINK_ENVIRONMENT_PERFORMANCE_QA_OUT="$OUT"
if [ -n "$QUESTION" ]; then
	export NPCINK_ENVIRONMENT_PERFORMANCE_QA_QUESTION="$QUESTION"
	wp_cli eval-file "$ROOT/scripts/environment-performance-pack-cloud-qa.php" "$QUESTION"
else
	wp_cli eval-file "$ROOT/scripts/environment-performance-pack-cloud-qa.php"
fi
