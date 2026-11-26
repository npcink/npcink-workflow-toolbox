#!/bin/sh
set -eu

root="$(CDPATH= cd -- "$(dirname "$0")/.." && pwd)"
tmp="$(mktemp -d)"
trap 'rm -rf "$tmp"' EXIT
mkdir -p "$tmp/first site" "$tmp/second site" "$tmp/not wordpress"
touch "$tmp/first site/wp-load.php" "$tmp/second site/wp-load.php"

cat > "$tmp/fake-wp" <<'EOF'
#!/bin/sh
printf '%s\n' "$@" > "$QA_CALL"
EOF
cat > "$tmp/fake-php" <<'EOF'
#!/bin/sh
while [ "$1" = -d ]; do
	printf '%s\n' "$2" >> "$QA_PHP_ARGS"
	shift 2
done
exec "$@"
EOF
chmod +x "$tmp/fake-wp" "$tmp/fake-php"
unset WP_PATH WP_CLI_PHP WP_DB_SOCKET
export WP_CLI_BIN="$tmp/fake-wp" QA_CALL="$tmp/call" QA_PHP_ARGS="$tmp/php-args"
export NPCINK_INSIGHT_QA_OUT="$tmp/out/result.json"
export NPCINK_CONTENT_STRUCTURE_QA_OUT="$NPCINK_INSIGHT_QA_OUT"
export NPCINK_PAGE_QUALITY_QA_OUT="$NPCINK_INSIGHT_QA_OUT"
export NPCINK_ENVIRONMENT_PERFORMANCE_QA_OUT="$NPCINK_INSIGHT_QA_OUT"

for pack in insight-pack content-structure-pack page-quality-pack environment-performance-pack; do
	wrapper="$root/scripts/$pack-cloud-qa.sh"
	rm -f "$QA_CALL" "$QA_PHP_ARGS"
	rmdir "$tmp/out" 2>/dev/null || :
	if sh "$wrapper" 'test question' > "$tmp/error" 2>&1; then
		echo "$pack accepted a missing WordPress root" >&2
		exit 1
	fi
	grep -Fq 'WP_PATH must be set' "$tmp/error"
	if WP_PATH="$tmp/not wordpress" sh "$wrapper" 'test question' > "$tmp/error" 2>&1; then
		echo "$pack accepted a directory without wp-load.php" >&2
		exit 1
	fi
	grep -Fq 'missing wp-load.php' "$tmp/error"
	test ! -e "$QA_CALL"
	test ! -d "$tmp/out"
	WP_PATH="$tmp/first site" sh "$wrapper" 'test question'
	grep -Fxq -- "--path=$tmp/first site" "$QA_CALL"
	grep -Fxq 'test question' "$QA_CALL"
	grep -Fxq "$root/scripts/$pack-cloud-qa.php" "$QA_CALL"
	test ! -e "$QA_PHP_ARGS"
	WP_PATH="$tmp/first site" WP_CLI_PHP="$tmp/fake-php" WP_DB_SOCKET="$tmp/db socket" \
		sh "$wrapper" 'test question' "$tmp/second site"
	grep -Fxq -- "--path=$tmp/second site" "$QA_CALL"
	grep -Fxq "mysqli.default_socket=$tmp/db socket" "$QA_PHP_ARGS"
	grep -Fxq "pdo_mysql.default_socket=$tmp/db socket" "$QA_PHP_ARGS"
done
printf '%s\n' 'Four Cloud QA wrappers require an explicit WordPress root and honor explicit runtime settings.'
