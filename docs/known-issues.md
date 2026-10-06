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
- **`plainTextFromHtml` parses with `innerHTML` on a detached div**, which
  is not inert: inline error handlers and resource fetches inside block
  HTML can execute when the helper runs. The exposure is bounded by the
  fact that the Gutenberg editor already renders the same block HTML, and
  the helper predates the text-utils split (moved verbatim). Hardening
  (DOMParser-based extraction or attribute stripping) is deferred to a
  dedicated behavior-change session; flagged by the 2026-10 split-session
  advisory review.
- **Structure: staged splits are partially landed.** The first
  editor-content-support session extracted the pure `text-utils.js` and
  `internal-links.js` part files behind frozen namespaces (main bundle
  11,191 -> 10,724 lines). Still owed: the remaining
  `editor-content-support.js` clusters (image candidates, audio,
  preflight, progressive, draft flows), `includes/Rest_Controller.php`
  (~8.5k), `assets/admin.js` (~8k), and `includes/Admin_Page.php`
  (~4.5k), following [Provider Split Refactor Standard v1](platform/provider-split-refactor-standard-v1.md)
  (portable assertion sources first; part files must carry no
  translations unless a per-handle JED contract is added). Sources: the
  2026-10-03 systematic review and the first split session.
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
- **Live editor verification reopened and available**: a standalone
  five-plugin site (WP 7.1, plugins symlinked from the sibling repos)
  runs at `http://127.0.0.1:8090` from `~/wp-sites/npcink-five` using the
  Local MySQL socket and lightning PHP 8.2 (`php -S` with
  `wp-sites/npcink-five/router.php`). On 2026-10-06 the progressive
  browser smoke passed in full against the split bundle (sidebar render,
  namespaces loaded, zero console/page errors, prefetch POST with the
  progressive intent, no Cloud/Adapter/Core calls, no writes), and the
  internal-link batch variant passed its 18 behavioral assertions
  including disabled direct writes and Apply gating; its trailing
  no-HTTP-errors gate fails only because this site has no Cloud
  credentials (5xx from the Cloud-required route) and needs a
  Cloud-connected run to close. Environment: WP_PATH, WP_BASE_URL,
  WP_CLI_PHP, WP_DB_SOCKET, NODE_PATH to a playwright install,
  BROWSER_EXECUTABLE to a cached Chromium; pretty permalinks required
  (REST otherwise uses ?rest_route= and URL matchers miss).
- **The standalone site-knowledge review UI smoke is red on master**
  (`composer` script exists but is not in the default gate): it still pins
  Admin_Page copy ("Review handoff", "Evidence first", "Core review only",
  "No direct write") that a prior admin-surface cleanup removed. Update the
  smoke to the current surface or retire it; found during the 2026-10-04
  REST controller split portability pass.
- **The extracted editor content-support service is a 6.5k-line single
  unit** (183 methods, PR for the facade closeout): the Rest_Controller
  split moved it wholesale to end the facade bottleneck. Sub-dividing it
  into cohesive editor sub-services (audio, taxonomy, media/ALT,
  progressive, writing-pack/draft) follows the same standard in later
  sessions, after the editor-content-support.js clusters establish the
  JED translation policy.
- **PHPStan baseline ratchet** (2026-10-06): the 134 remaining level-5
  findings live in `phpstan-baseline.neon` after the phpcbf pass and
  promotion to required. The baseline exists to shrink: when a cluster
  split or cleanup removes a finding class, regenerate the baseline and
  the ratchet holds. The 16 unused-method candidates remain in the
  baseline (mostly `Admin_Page` media-derivative render helpers).

## Recently Closed

- **No live-site smoke against the split editor bundle** — resolved
  2026-10-06: the standalone five-plugin site above now runs the editor
  browser smokes green; the compensating `test:editor-js-undefined`
  audit gate stays as the static net.

- **Toolbox enrolled in Static Analysis Standard v1** — 2026-10-04,
  advisory-first per the promotion rule: dev dependencies, PHPStan level 5
  with WordPress stubs, PHPCS with the 8.0 floor, an advisory CI job on
  PHP 8.4, and five real first-run defects fixed in the introducing PR.
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
