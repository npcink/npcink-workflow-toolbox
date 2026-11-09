# WordPress.org Release Gate

Status: active release gate.

Before uploading this plugin to WordPress.org, run:

```sh
composer release:verify
```

This release gate exists because functional tests and local smoke tests can pass
while WordPress.org rejects the package for review-policy issues.

The local `check:wporg` guard blocks recurring review problems:

- direct `wp-admin/includes/*` path construction, except the common
  `upgrade.php` activation helper for `dbDelta()`;
- admin request parameters read directly from `$_GET`;
- unsanitized request reads such as `FILTER_UNSAFE_RAW`;
- inline admin CSS or JS emitted from PHP;
- raw `<script>` or `<style>` tags in PHP admin views.
- a WordPress.org-facing `readme.txt` Contributors line that omits the
  submitting account `muze233`.

The Plugin Check gate must run with a working wp-cli and a booted WordPress
site. On this workstation the `wp plugin check` subcommand is registered by the
WordPress Plugin Check plugin active in the magick-ai Local site, and the
Homebrew PHP curl CA path is broken (`@@HOMEBREW_PREFIX@@` placeholder), so use
the official wp-cli.phar plus the Local MySQL socket:

```sh
SOCK="$HOME/Library/Application Support/Local/run/s63K4c8XP/mysql/mysqld.sock"
WP_CLI=/tmp/wp-cli.phar WP_CLI_PHP=/opt/homebrew/bin/php \
WP_PATH="/Users/muze/Local Sites/magick-ai/app/public" \
WP_DB_SOCKET="$SOCK" composer plugin-check:release
```

When `/tmp/wp-cli.phar` is missing, fetch it over git (raw downloads may be
unreachable): `git clone --depth 1 -b gh-pages https://github.com/wp-cli/builds`
and copy `phar/wp-cli.phar`. Full environment notes are recorded in the
[0.3.0 publication closeout](archive/2026-10/wordpress-org-publication-0-3-0-closeout-2026-10-02.md).

The command builds an isolated distribution directory using `.distignore` and
runs Plugin Check in update mode with strict JSON output. It does not inspect
whatever copy happens to be installed in the local WordPress site. Any
reported error produces a nonzero exit code.

Do not rely on a passing `composer test:all` or `composer check:wporg` alone
for WordPress.org review readiness. `composer test:all` protects the internal
Toolbox contracts; `composer check:wporg` protects local recurring static
patterns; Plugin Check is the external review-policy mirror.

## Brand And Submitter Gate

The public plugin name intentionally remains `Npcink Workflow Toolbox` because
this repository is the Npcink product surface, and the first-version runtime
contracts keep `npcink-toolbox` route and ability ids for compatibility.

Before replying to a WordPress.org trademark/name review question, confirm one
of these non-code facts and state it briefly in the reviewer reply:

- the submitting WordPress.org account is an official Npcink owner account; or
- the plugin should be transferred to an official Npcink owner account; or
- the display name and requested slug must be changed before resubmission.

Do not treat a code-only edit as sufficient for a brand ownership question.
Adding the submitter to `readme.txt` resolves the contributor mismatch only; it
does not prove brand ownership.

## 2026-07-02 Review Feedback Retrospective

WordPress.org pre-review feedback for the 0.1.1 submission identified three
classes of issues:

1. potential confusion around the `Npcink` public name when submitted from
   `muze233`;
2. `filter_input( INPUT_GET, ..., FILTER_UNSAFE_RAW )` request reads, including
   nonce reads before `wp_verify_nonce()`;
3. `readme.txt` listing `npcink` but not the submitting account `muze233`.

The immediate cause was that the release closeout treated
`composer test:all` and the earlier `check:wporg` guard as sufficient
evidence. They were not. The local guard did not include the exact review-email
pattern classes, and the brand ownership question is not statically decidable
from source code.

The correction is:

- fix the current code pattern, not only the cited line numbers;
- add every statically checkable review pattern to `scripts/check-wordpress-org-review-rules.php`;
- run Plugin Check with the real local WP-CLI path before every upload;
- keep brand ownership and submitter identity as an explicit manual release
  checklist item.

When WordPress.org sends a review email, decode the current top-level message,
extract every cited file and line, fix the whole pattern class, and add a local
guard when the pattern is statically checkable.

## Plugin Check query-policy repair — 2026-11-09

The baseline `859bf9b0` reproduces two errors and five warnings in an isolated
WORKTREE distribution. The focused source repair is
[`db02bd1b`](https://github.com/npcink/npcink-workflow-toolbox/commit/db02bd1b216152ed233dc71fe0abb23e1da1f561).

| Finding | Diagnosis and disposition |
| --- | --- |
| Two `SuppressFilters_suppress_filters` errors | The content-structure/page-quality population queries explicitly set `suppress_filters=true`; their sample fetches also inherited the `get_posts()` default of true. Both queries now explicitly use false, preserving published post/page constraints, ID-only population enumeration, chronological strata and bounded sample fetches while respecting host filters. |
| Three `DynamicHooknameFound` warnings | The checker cannot resolve the public hook-name constants. The calls now use the same prefixed literal names; constants remain public for compatibility. Existing sample/plugin-list hooks and their clamp limits are tested. |
| Uninstall `DirectQuery` / `NoCaching` warnings | Dynamic transient-name enumeration needs a prefix-bounded prepared SELECT, followed by `delete_option()` so WordPress manages deletion/cache cleanup. The existing narrowly justified annotation was attached to `array_merge`, not `get_col`; it is moved to the actual call. SQL, prefixes, cleanup APIs and ownership remain unchanged. No global sniff exclusions are added. |

Host filters may narrow a population, for example to a language or visible
subset. `sampling.total_published` describes that eligible queried population;
`post_counts` remains the separate site-wide aggregate. Sample notes therefore
say **本次范围内已发布**, not **全站已发布**. No new field, scope selector,
provider, pack or REST permission is introduced. Custom filters can change the
selected population; the plugin does not bypass them to claim a full-site census.

Because result filters can replace rows, both samplers recheck published status,
post/page type and planned-ID membership before reading output fields; duplicates
are discarded. Empty populations skip the sample query rather than falling back
to recent posts. No new body/title storage, Cloud calls or WordPress writes.

Verification in this slice:

- `composer test:all`: passes, including 4,577 static contracts and the focused
  sampling/hook compatibility/uninstall/privacy behavior checks.
- Composer validation, `composer check:wporg`, PHPCS and PHPStan: pass.
- Installed `composer plugin-check:release`: passes with no reported errors or
  warnings, versus the recorded baseline's two errors/five warnings. This uses
  the isolated distribution builder and the running Local WordPress site.
- Installed read-only verification exercises both builders with real
  `posts_where` / `posts_results` filters: two eligible posts yield two samples;
  injected private/draft/unplanned/other-type/duplicate rows leave one allowed
  sample; an empty population yields no samples and no fallback query. Six
  cases pass, zero upstream HTTP, zero observed database mutations and unchanged
  existing post records. Filter callbacks are process-scoped and removed in
  `finally`; no fixtures, sessions or persistent observer are created.

This clears the recorded Plugin Check findings only. Final ZIP installation,
upgrade/final candidate acceptance, versioning, WordPress.org publication and
production deployment remain separate work; none is claimed here.
