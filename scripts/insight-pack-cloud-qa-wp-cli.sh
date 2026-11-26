# Shared by the four insight-pack Cloud QA wrappers. No site is selected by default.
insight_qa_require_wordpress() {
	WP_PATH="${1:-${WP_PATH:-}}"
	if [ -z "$WP_PATH" ]; then
		echo 'WP_PATH must be set to a WordPress root (or passed as the second argument).' >&2
		return 1
	fi
	if [ ! -f "$WP_PATH/wp-load.php" ]; then
		printf 'WordPress root missing wp-load.php: %s\n' "$WP_PATH" >&2
		return 1
	fi
}

wp_cli() {
	set -- --path="$WP_PATH" --user="$USER_ID" --no-color "$@"
	if [ -n "${WP_DB_SOCKET:-}" ] || [ -n "${WP_CLI_PHP:-}" ]; then
		cli="$(command -v "${WP_CLI_BIN:-wp}")" || {
			printf 'WP-CLI not found: %s\n' "${WP_CLI_BIN:-wp}" >&2
			return 1
		}
		if [ -n "${WP_DB_SOCKET:-}" ]; then
			set -- -d "mysqli.default_socket=$WP_DB_SOCKET" -d "pdo_mysql.default_socket=$WP_DB_SOCKET" "$cli" "$@"
		else
			set -- "$cli" "$@"
		fi
		"${WP_CLI_PHP:-php}" -d display_errors=0 -d error_reporting=8191 "$@"
	else
		"${WP_CLI_BIN:-wp}" "$@"
	fi
}
