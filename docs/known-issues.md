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

- **Editor intent convergence accepted 2026-10-07** and moved to Recently
  Closed; see [Editor Intent Convergence Decision](editor-intent-convergence-decision.md).
- **The editor `整理` (format content) button label is Chinese inside an
  otherwise English UI**; unify label language in a scoped i18n pass.
  Source: [Pre-Release Hardening Closeout 2026-09-30](pre-release-hardening-closeout-2026-09-30.md).
- **Cross-repo: Cloud Addon quota/attribution follow-ups for editor image
  access.** Tracked with the audit trail in
  [Scoped Editor Permissions Lessons 2026-09](scoped-editor-permissions-lessons-2026-09.md);
  the object-level audits live there, not here.
- **Full guided fresh-install onboarding tour has a proposed design**
  ([Onboarding Tour Design v1](onboarding-tour-design-v1.md)) awaiting the
  operator trial; the shipped surface remains notices plus a three-step
  card until the trial answers the entry questions. Source: [UX Hardening
  Development Lessons 2026-10](ux-hardening-development-lessons-2026-10.md).
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
- **Structure: staged splits; remaining clusters deferred to
  just-in-time.** The editor-content-support split landed three parts
  (text-utils, internal-links, audio-preferences) behind the JED
  translation policy; the Rest_Controller split is complete (520-line
  facade, 9 services); and the 2026-10-07 session extracted the Site
  Check render cluster from `Admin_Page.php` into
  `Admin_Page_Site_Ops_Panel` (4,620 -> 2,762 facade lines) with portable
  admin-page assertion sources. Still owed, deferred until product work
  per the just-in-time refactoring decision as amended 2026-10-08
  (acceleration agreement: a product session may carry one cluster
  extraction, or one dedicated split session may run per week): the remaining
  `editor-content-support.js` clusters (image candidates, preflight,
  progressive, draft flows), `includes/Rest_Editor_Content_Support.php`
  (~6.5k), `assets/admin.js` (~8.2k), and the remaining `Admin_Page.php`
  clusters (review-set tools, media derivative controls, content context
  form), following [Provider Split Refactor Standard v1](platform/provider-split-refactor-standard-v1.md)
  (portable assertion sources first; part files must carry no
  translations unless a per-handle JED contract is added). Sources: the
  2026-10-03 systematic review, the first split session, and the
  2026-10-07 churn-ranked closeout.
- **Dated closeout records at the `docs/` root** (June-July 2026 records):
  the 2026-10-07 archival pass moved fourteen records (WordPress.org
  release readiness, the site-check and operator-path closeouts, the
  cross-repo 2026-07-08 series, media ALT governed closeout, and the
  five-plugin hardening closeout) into `docs/archive/2026-06|07/` with
  inbound links updated. A few dated summaries remain at the root
  (admin-operator-ux-cleanup, toolbox-fixed-button-reference-notes,
  reference-learning-synthesis, and the recent 2026-09/10 records);
  qualify each under the archive policy in
  [the documentation index](README.md) and move them only together with
  the links that reference them.
- **The extracted editor content-support service is being sub-divided**
  under the split standard with the amended 2026-10-08 cadence. Landed
  2026-10-08: paragraph-check and audio-text static clusters (session 1),
  then the shared flow-cache base (Rest_Editor_Flow_Cache, chained
  through Rest_Controller_Support, carrying the transient cache, the
  cross-cluster input-scope/related-items/recommendation-candidate
  statics) plus the taxonomy-shaping cluster (session 2) - the service
  sits at ~4.3k lines after session 3 extracted the summary-terms
  cluster (strategy/metrics tables, Core-handoff candidates, term
  evidence, Toolkit taxonomy adapters, and the related-post-terms
  context helper - Rest_Editor_Summary_Terms). Remaining:
  writing-pack, progressive, and media/ALT clusters plus the summary and
  taxonomy cached-flow orchestrators, all inheriting the cache base and
  movable with the same pattern.
- **PHPStan baseline ratchet** (2026-10-06): the 134 remaining level-5
  findings live in `phpstan-baseline.neon` after the phpcbf pass and
  promotion to required. The baseline exists to shrink: when a cluster
  split or cleanup removes a finding class, regenerate the baseline and
  the ratchet holds. The 16 unused-method candidates remain in the
  baseline (mostly `Admin_Page` media-derivative render helpers).

## Recently Closed

- **Site Knowledge status owner-matrix rows and the author/admin error
  audience split** — resolved 2026-10-07: the admin Site Knowledge status
  renderer now shows every ownership row Cloud reports (adding
  index-lifecycle, freshness-policy, diagnostics-detail, and
  embedding-execution owners) plus the freshness and diagnostics truth
  rows, pinned by a static contract so rows cannot silently drop again;
  both editor REST wrappers normalize permission failures for the acting
  audience (plain "This action needs a site administrator. Nothing was
  written." copy with the original REST denial and http status preserved
  on the error object), keeping the server-side scope boundary
  authoritative per ADR-018. Trial steps 9 and 10 cover both in the
  acceptance loop.

- **Sixteen non-default editor intents converged 2026-10-07**: the accepted
  [Editor Intent Convergence Decision](editor-intent-convergence-decision.md)
  retired the ten route-only intents from `/editor/content-support`
  (writing_support, both editor-route Zhihu aliases, article_checkup,
  title/summary suggestions, article_outline, taxonomy_tags,
  comment_reply_suggestion, discoverability) with zero family callers
  (verified across all five sibling repos), folded the local article-checkup
  prose signals into publish preflight as `local_article_checkup` plus a
  `prose_quality` review row, and removed the dead PHP branches, orphaned
  helper families, JS render paths, and their catalog entries. The editor
  allowlist is pinned to the twelve kept intents by a static contract.
  Source: [Pre-Release Hardening Closeout 2026-09-30](pre-release-hardening-closeout-2026-09-30.md).

- **Site Knowledge review UI smoke repaired and promoted into the default
  gate** — resolved 2026-10-07: the red assertion pinned four Admin_Page
  explainer phrases that an earlier admin-surface cleanup moved into the
  client-side governed-handoff renderer in `assets/admin.js`. The smoke now
  pins the current renderer copy (governed handoff section, prepared-
  locally-only and proposal-candidate-only notices, evidence-first next
  steps, explicit operator buttons), all 23 assertions pass, and
  `@smoke:site-knowledge-review-ui` joined `composer test:all` so the
  source-only smoke cannot drift red unnoticed again.
- **Live editor verification passed for the split bundle** — 2026-10-06:
  the standalone five-plugin site ran the progressive browser smoke in
  full (namespaces loaded, zero console/page errors, no-write assertions
  all green) and the internal-link batch variant passed its 18 behavioral
  assertions. The environment recipe is preserved here for repeat runs:
  `php -S` at 127.0.0.1:8090 with pretty permalinks, WP_PATH/WP_BASE_URL/
  WP_CLI_PHP/WP_DB_SOCKET env vars, and NODE_PATH+BROWSER_EXECUTABLE for
  Playwright.
- **0.4.0 shipped to WordPress.org** — SVN revision 3730454; the
  five-review-set arc is complete (media ALT, taxonomy/tag, internal-link,
  comment moderation, flagged media) alongside the Rest_Controller
  restructure, editor JS part-file split, and required static analysis
  gates.
- **`Rest_Controller::rest_route_scope()` full-coverage contract** —
  resolved 2026-10-07: `tests/run.php` now simulates runtime scope
  resolution (regex branches, literal routes, and the exact map) against a
  representative concrete path for every registered route, fails on any
  silent `cap.toolbox.admin` fallback, and requires the resolved scope to
  match `docs/route-boundary-table.json`. The first run exposed one real
  defect: the local-review route's scope-map key used the registered
  pattern (with the `(?P<artifact_id>...)` named group), which never
  matches the concrete runtime path `WP_REST_Request::get_route()`
  returns, so the route silently ran on the fallback scope; it now
  resolves through a runtime regex branch to its documented
  `cap.toolbox.workflow_suggest` scope (same `manage_options` default
  capability, corrected scope string for the host permission filter).

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
