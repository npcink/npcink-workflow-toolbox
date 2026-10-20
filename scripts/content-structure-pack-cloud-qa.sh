#!/bin/sh
# Pack 2 wrapper: content_structure insight pack + Cloud question via Addon.
#
# Usage:
#   scripts/content-structure-pack-cloud-qa.sh
#   scripts/content-structure-pack-cloud-qa.sh "内容结构优先看什么？"
#   scripts/content-structure-pack-cloud-qa.sh "问题" "/Users/muze/Local Sites/magick-ai/app/public"
#
# Docs: docs/content-structure-pack-cloud-qa-cli.md
# Boundary: docs/deidentified-assistant-boundary-v1.md
set -eu

ROOT="$(CDPATH= cd -- "$(dirname "$0")/.." && pwd)"
QUESTION="${1:-${NPCINK_CONTENT_STRUCTURE_QA_QUESTION:-}}"
WP_PATH="${2:-${WP_PATH:-/Users/muze/Local Sites/magick-ai/app/public}}"
WP_CLI_BIN="${WP_CLI_BIN:-/opt/homebrew/bin/wp}"
WP_CLI_PHP="${WP_CLI_PHP:-$HOME/Library/Application Support/Local/lightning-services/php-8.2.30+1/bin/darwin-arm64/bin/php}"
SOCKET="${WP_DB_SOCKET:-$HOME/Library/Application Support/Local/run/s63K4c8XP/mysql/mysqld.sock}"
USER_ID="${NPCINK_CONTENT_STRUCTURE_QA_USER:-${NPCINK_INSIGHT_QA_USER:-178}}"
OUT="${NPCINK_CONTENT_STRUCTURE_QA_OUT:-$ROOT/tmp/content-structure-pack-cloud-qa-result.json}"

if [ ! -d "$WP_PATH" ]; then
	echo "WP_PATH not found: $WP_PATH" >&2
	exit 1
fi

if [ ! -f "$ROOT/scripts/content-structure-pack-cloud-qa.php" ]; then
	echo "Missing $ROOT/scripts/content-structure-pack-cloud-qa.php" >&2
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

export NPCINK_CONTENT_STRUCTURE_QA_OUT="$OUT"
if [ -n "$QUESTION" ]; then
	export NPCINK_CONTENT_STRUCTURE_QA_QUESTION="$QUESTION"
	wp_cli eval-file "$ROOT/scripts/content-structure-pack-cloud-qa.php" "$QUESTION"
else
	wp_cli eval-file "$ROOT/scripts/content-structure-pack-cloud-qa.php"
fi
