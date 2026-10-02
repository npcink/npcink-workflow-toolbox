# WordPress.org 0.3.0 Publication Closeout - 2026-10-02

## Status

Accepted as the publication record for `Npcink Workflow Toolbox` 0.3.0. This
was a routine update publication under the already-approved slug, not a new
directory review. The first-approval and 0.1.1 publication history remains in
[WordPress.org Publication And Translation Closeout - 2026-07-03](../2026-07/wordpress-org-publication-translation-closeout-2026-07-03.md).

## SVN Publication

The release was published under the intended public slug:

```text
https://plugins.svn.wordpress.org/npcink-workflow-toolbox/
Revision: 3723781
Author: muze233
Date: 2026-10-02 03:49:25 +0800
```

Published SVN areas:

- `trunk/` updated to 0.3.0;
- `tags/0.3.0/` added from trunk;
- `assets/` unchanged (icons, banners, and screenshots retained).

Pre-publication SVN state was `trunk` and `tags/0.1.1` only. Version 0.2.0 was
never published to WordPress.org, so the 0.3.0 readme changelog carries the
0.2.0 and 0.3.0 sections together to stable readers.

Staged change shape (reviewed before commit): 26 added files (provider service
split classes, media fingerprint/optimization/recognition classes,
`Editor_Content_Format`, the editor format script, the new translation JSON,
and the `.pot`) and 23 modified files; no deletions; no dev-only paths leaked
into the package.

## What Shipped

- Scoped editor permissions for the post editor Content Support sidebar
  (ADR-018; #157).
- The read-only comment moderation review set with its Site Check review
  section (#164, #165, #166).
- The read-only flagged media review set in Image Handling (#167).
- Retirement of the pre-release compatibility surface: legacy
  vector/knowledge/article-brief/article-assistant routes and admin URL
  aliases (#154), plus the Provider_Client split into per-cluster services
  behind the same facade (#156, #161, #162).
- Documentation alignment: the weekly media fingerprint scan named as the
  recurring WP-Cron exception (#169).
- The i18n completion pass for the editor content-format feature and remaining
  admin UI copy leaks, with zh_CN catalogs (#170).
- 0.3.0 release metadata, WordPress.org readme disclosures for the new review
  sets, the Site Check visibility decision record, and the `.pot` template
  (#171).

## Verification

All gates ran on the master merge commit (`45619bf`) that produced the
release package:

```text
composer test:all                 PASS (4349+ contracts)
composer check:wporg              PASS
composer validate --no-check-publish PASS
composer plugin-check:release     PASS (0 ERROR / 0 WARNING, strict-json)
```

Post-publication verification:

- SVN remote shows `tags/0.3.0/` and `trunk` at version 0.3.0 with
  `Stable tag: 0.3.0`;
- `https://downloads.wordpress.org/plugin/npcink-workflow-toolbox.zip`
  returns HTTP 200 and the package plugin header is `Version: 0.3.0`.

## Environment Notes Worth Reusing

1. **Homebrew PHP curl CA defect.** `/opt/homebrew/bin/php` on this
   workstation has an unsubstituted `@@HOMEBREW_PREFIX@@` OpenSSL CA path, so
   any wp-cli HTTPS package lookup fails with cURL error 77. Workaround: use
   the official wp-cli.phar (fetchable by cloning `wp-cli/builds` branch
   `gh-pages` over git when raw.githubusercontent.com is unreachable) and pass
   `-d curl.cainfo=/opt/homebrew/etc/openssl@3/cert.pem` when a network
   package step is required.
2. **`wp plugin check` comes from the Plugin Check plugin, not a wp-cli
   package.** The command is registered by the WordPress Plugin Check plugin
   being active in the target site. The magick-ai local site already has it
   active.
3. **Working plugin-check invocation for this workstation:**

   ```bash
   SOCK="$HOME/Library/Application Support/Local/run/s63K4c8XP/mysql/mysqld.sock"
   WP_CLI=/tmp/wp-cli.phar WP_CLI_PHP=/opt/homebrew/bin/php \
   WP_PATH="/Users/muze/Local Sites/magick-ai/app/public" \
   WP_DB_SOCKET="$SOCK" composer plugin-check:release
   ```

4. **SVN update publication procedure.** Sparse checkout
   (`svn co --depth=immediates`, then `svn up --set-depth=infinity trunk`),
   wipe trunk except `.svn`, copy the verified release package in,
   `svn add --force`, `svn rm` the reported missing paths, review
   `svn status`, `svn cp trunk tags/<version>`, then one commit. Anonymous
   reads work without credentials; commits use the cached WordPress.org
   credentials.

## Translation Status

zh_CN ships bundled with the plugin and works without GlotPress: the release
adds 67 new `.po` entries, a recompiled `.mo`, and a new
`npcink-toolbox-editor-content-format.json` script catalog. At closeout time,
translate.wordpress.org had not yet created a 0.3.0 version project (the
platform rescans stable tags automatically, typically within hours). A manual
import remains optional per the 2026-07-03 closeout precedent and PTE status
is unchanged.

## Remaining Known Items

Post-publication follow-ups were executed the same day; the current state is:

- the authenticated REST performance baseline batches required by
  [Security And Performance Release Gate](../../security-performance-release-gate.md)
  were captured on 2026-10-02 against `https://magick-ai.local`
  (WP 7.1.1, zh_CN, plugin active, authenticated `GET /status`, 1 warmup plus
  10 samples per batch, three batches): medians 23.4 / 24.0 / 23.3 ms,
  P95 31.7 / 31.0 / 30.2 ms, all HTTP 200, consistent statuses, valid JSON,
  identical probe signature. `build/perf/toolbox-baseline-2.jsonl` is the
  designated reference (`build/perf/toolbox-reference.jsonl`); timing remains
  observation-only with no enforced threshold;
- the zh_CN script translation load was verified at runtime on the same site:
  `load_script_textdomain()` resolved all three bundled catalogs
  (`npcink-toolbox-editor-content-format`, `npcink-toolbox-editor-content-support`,
  `npcink-toolbox-admin`) and returned the expected entries, including
  `Format text` -> 整理 for the new format catalog. The legacy
  `locale_data.messages` Jed envelope is accepted by core, so no catalog
  regeneration is needed;
- the GlotPress 0.3.0 version project was still not present at the final
  session check; the platform rescans stable tags automatically and a manual
  import stays optional because zh_CN ships bundled with the plugin;
- the sixteen non-default editor intents remain callable as documented
  mid-term convergence work, unchanged from the pre-release hardening
  closeout.

## Boundary

This closeout records release operations only. The public slug stays
`npcink-workflow-toolbox`; runtime contracts keep the `npcink-toolbox` REST
namespace, `npcink-toolbox/*` ability ids, and `npcink_toolbox_*` option and
hook names. Toolbox remains a review-only operator surface and does not own
final WordPress write approval, provider billing, queues, approval truth, or
long-term runtime state.
