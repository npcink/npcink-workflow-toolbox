#!/bin/sh
set -eu

ROOT="$(CDPATH= cd -- "$(dirname -- "$0")/.." && pwd)"
TMPDIR="$(mktemp -d "${TMPDIR:-/tmp}/toolbox-wp-cli-target.XXXXXXXX")"
export TMPDIR
trap 'rm -rf "$TMPDIR"' 0
trap 'exit 1' 1 2 3 15

fail() {
	printf 'FAIL: %s\n' "$*" >&2
	printf '%s\n' "$output" >&2
	exit 1
}

WP_CLI_PHP="$TMPDIR/fake-wp"
WP_CLI_BIN="$WP_CLI_PHP"
FAKE_WP_LOG="$TMPDIR/calls"
FAKE_WP_ROOT="$TMPDIR/valid WordPress root"
NPCINK_CLOUD_ADDON_PLUGIN=npcink-cloud-addon
NPCINK_TOOLBOX_PLUGIN=npcink-workflow-toolbox
export WP_CLI_PHP WP_CLI_BIN FAKE_WP_LOG FAKE_WP_ROOT NPCINK_CLOUD_ADDON_PLUGIN NPCINK_TOOLBOX_PLUGIN
mkdir "$FAKE_WP_ROOT" "$TMPDIR/empty"
touch "$FAKE_WP_ROOT/wp-load.php"

# Replace the PHP runner itself so no real WP-CLI can be reached.
cat > "$WP_CLI_PHP" <<'FIXTURE'
#!/bin/sh
printf 'called\n' >> "$FAKE_WP_LOG"
while [ "$1" = -d ]; do shift 2; done
[ "$2" = "--path=$FAKE_WP_ROOT" ] || exit 72
shift 3
case "$1" in --require=*) shift ;; esac
case "$1 $2" in
	'--skip-plugins --skip-themes') exit 73 ;;
	'plugin is-installed'|'plugin is-active') exit 0 ;;
	'eval-file tests/smoke-site-knowledge-cloud-addon-bridge.php') exit 74 ;;
	*) exit 75 ;;
esac
FIXTURE
chmod +x "$WP_CLI_PHP"

reject_target() {
	expected_message="$1"
	shift
	if output="$("$@" 2>&1)"; then
		fail "$script accepted an invalid WordPress target"
	fi
	case "$output" in
		*"$expected_message"*) ;;
		*) fail "$script did not explain why it rejected the target" ;;
	esac
	[ ! -e "$FAKE_WP_LOG" ] || fail "$script called WP-CLI before validating the target"
	[ ! -e "$TMPDIR/npcink-five-plugin-acceptance.lock" ] || fail "$script left an acceptance lock"
}

cd "$ROOT"
for script in scripts/local-five-plugin-acceptance.sh scripts/smoke-site-knowledge-cloud-addon-bridge.sh; do
	reject_target 'WP_PATH must be set' env -u WP_PATH sh "$script"
	reject_target 'WP_PATH must be set' env WP_PATH= sh "$script"
	reject_target 'WP_PATH must contain wp-load.php' env WP_PATH="$TMPDIR/missing" sh "$script"
	reject_target 'WP_PATH must contain wp-load.php' env WP_PATH="$TMPDIR/empty" sh "$script"

	case "$script" in
		scripts/local-five-plugin-acceptance.sh) expected_status=73 ;;
		*) expected_status=74 ;;
	esac
	status=0
	output="$(WP_PATH="$FAKE_WP_ROOT" sh "$script" 2>&1)" || status=$?
	[ "$status" -eq "$expected_status" ] && [ -s "$FAKE_WP_LOG" ] || fail "$script did not forward the explicit root to the expected fake WP-CLI command"
	[ ! -e "$TMPDIR/npcink-five-plugin-acceptance.lock" ] || fail "$script left an acceptance lock after WP-CLI failed"
	rm "$FAKE_WP_LOG"
done

printf '%s\n' 'PASS: local acceptance scripts require an explicit WordPress root before WP-CLI.'
