# Known Issues

Status: single tracking point for accepted-but-unfinished items.

This file is the one place future sessions should check for open debt.
Items graduate out of narrative closeout records into this list, and a
closeout may not be archived until each of its unresolved items either
appears here or is explicitly closed with a resolution pointer.

Rule: every entry names its owner surface and its source record. Do not
resolve an entry by deleting it silently; move it to "Recently Closed"
with the closing commit or PR for one release cycle, then prune.

## Open

- **Sixteen non-default editor intents remain callable** (title, summary,
  category, tag, outline, checkup, discoverability, comment-reply, and
  related compatibility paths). Converging or retiring them is a mid-term
  product decision, not a cleanup. Source:
  [Pre-Release Hardening Closeout 2026-09-30](pre-release-hardening-closeout-2026-09-30.md).
- **The editor `整理` (format content) button label is Chinese inside an
  otherwise English UI**; unify label language in a scoped i18n pass.
  Source: [Pre-Release Hardening Closeout 2026-09-30](pre-release-hardening-closeout-2026-09-30.md).
- **Cross-repo: Cloud Addon quota/attribution follow-ups for editor image
  access.** Tracked with the audit trail in
  [Scoped Editor Permissions Lessons 2026-09](scoped-editor-permissions-lessons-2026-09.md);
  the object-level audits live there, not here.
- **Full guided fresh-install onboarding tour needs product design**; the
  shipped surface is notices plus a three-step card. Source:
  [UX Hardening Development Lessons 2026-10](ux-hardening-development-lessons-2026-10.md).
- **Site Knowledge status owner-matrix rows and the author/admin error
  audience split remain follow-ups.** The acceptance loop is the
  [UX Hardening Operator Trial 2026-10](ux-hardening-operator-trial-2026-10.md).
- **Cross-plugin "Core proposal" terminology** waits on the Operator
  Terminology Standard being accepted in all five repos. Source:
  [UX Hardening Development Lessons 2026-10](ux-hardening-development-lessons-2026-10.md).
- **Legacy Chinese-source msgids migrate file-by-file** under the
  [Translation Source Language Policy](translation-source-language-policy.md);
  bulk regeneration of the JED catalogs is forbidden by the same policy.
- **Structure: staged splits are still owed** for
  `assets/editor-content-support.js` (~11k lines), `includes/Rest_Controller.php`
  (~8.5k), `assets/admin.js` (~8k), and `includes/Admin_Page.php` (~4.5k),
  following [Provider Split Refactor Standard v1](platform/provider-split-refactor-standard-v1.md)
  (behavior tests first, string contracts untouched). Source: the
  2026-10-03 systematic review.
- **`Rest_Controller::rest_route_scope()` coverage is asserted one-way**:
  a route registered but missing from the scope map silently degrades to
  coarse `manage_options` instead of failing the gate. A static contract
  asserting full scope-map coverage for every registered route is owed.
  Source: the 2026-10-03 systematic review.
- **Dated closeout records at the `docs/` root** (June-July 2026 records
  such as the WordPress.org release readiness, site-check, operator-path,
  cross-repo 2026-07-08 series, and five-plugin hardening closeouts)
  qualify for archival under the archive policy in
  [the documentation index](README.md); move them only together with the
  links that reference them.

## Recently Closed

- **Roadmap had no release anchors** — resolved 2026-10-03 by the Release
  Anchors section in [roadmap.md](roadmap.md).
- **ADR number collision (two ADR-016 drafts)** — resolved 2026-10-03:
  the media backup cleanup boundary keeps
  [ADR-016](decisions/ADR-016-media-backup-cleanup-boundary.md), media
  recognition continuation was renumbered to
  [ADR-019](decisions/ADR-019-media-recognition-continuation-owner.md).
- **Twenty index-invisible documents (including ADR-015/016 and five
  platform standards)** — resolved 2026-10-03 by the documentation index
  update.
- **Weekly media fingerprint cron not named in scheduling notes** —
  resolved by PR #169 and the recurring WP-Cron exception section in the
  root `README.md`.
- **`load_plugin_textdomain` is intentionally not called**: WordPress.org
  hosting loads the bundled `languages/` catalogs for this plugin, and
  `tests/run.php` enforces that posture. The standing caveat is that a
  self-distributed (non-.org) package would need an explicit loader; no
  such distribution exists today.
