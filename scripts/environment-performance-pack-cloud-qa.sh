#!/bin/sh
# Pack 4 wrapper: environment_performance insight pack + Cloud question via Addon.
#
# Usage:
#   scripts/environment-performance-pack-cloud-qa.sh
#   scripts/environment-performance-pack-cloud-qa.sh "环境与性能优先补什么？"
#   scripts/environment-performance-pack-cloud-qa.sh "问题" "/Users/muze/Local Sites/magick-ai/app/public"
#
# Docs: docs/environment-performance-pack-cloud-qa-cli.md
# Boundary: docs/deidentified-assistant-boundary-v1.md
set -eu

ROOT="$(CDPATH= cd -- "$(dirname "$0")/.." && pwd)"
QUESTION="${1:-${NPCINK_ENVIRONMENT_PERFORMANCE_QA_QUESTION:-}}"
WP_PATH="${2:-${WP_PATH:-/Users/muze/Local Sites/magick-ai/app/public}}"
WP_CLI_BIN="${WP_CLI_BIN:-/opt/homebrew/bin/wp}"
WP_CLI_PHP="${WP_CLI_PHP:-$HOME/Library/Application Support/Local/lightning-services/php-8.2.30+1/bin/darwin-arm64/bin/php}"
SOCKET="${WP_DB_SOCKET:-$HOME/Library/Application Support/Local/run/s63K4c8XP/mysql/mysqld.sock}"
USER_ID="${NPCINK_ENVIRONMENT_PERFORMANCE_QA_USER:-${NPCINK_INSIGHT_QA_USER:-178}}"
OUT="${NPCINK_ENVIRONMENT_PERFORMANCE_QA_OUT:-$ROOT/tmp/environment-performance-pack-cloud-qa-result.json}"

if [ ! -d "$WP_PATH" ]; then
	echo "WP_PATH not found: $WP_PATH" >&2
	exit 1
fi

if [ ! -f "$ROOT/scripts/environment-performance-pack-cloud-qa.php" ]; then
	echo "Missing $ROOT/scripts/environment-performance-pack-cloud-qa.php" >&2
	exit 1
fi

mkdir -p "$(dirname "$OUT")"

wp_cli() {
	if [ -n "${WP_CLI_PHP:-}" ] && [ -x "$WP_CLI_PHP" ]; then
		"$WP_CLI_PHP" \
			-d display_errors=0 \
			-d error_reporting=8191 \
			-d mysqli.default_socket="$SOCKET" \
			-d pdo_mysql.default_socket="$SOCKET" \
			"$WP_CLI_BIN" \
			--path="$WP_PATH" \
			--user="$USER_ID" \
			--no-color \
			"$@"
		return
	fi

	"$WP_CLI_BIN" \
		--path="$WP_PATH" \
		--user="$USER_ID" \
		--no-color \
		"$@"
}

export NPCINK_ENVIRONMENT_PERFORMANCE_QA_OUT="$OUT"
if [ -n "$QUESTION" ]; then
	export NPCINK_ENVIRONMENT_PERFORMANCE_QA_QUESTION="$QUESTION"
	wp_cli eval-file "$ROOT/scripts/environment-performance-pack-cloud-qa.php" "$QUESTION"
else
	wp_cli eval-file "$ROOT/scripts/environment-performance-pack-cloud-qa.php"
fi
