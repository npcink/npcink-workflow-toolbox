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

- **Five-session follow-up ownership (2026-10-26).** The
  [closeout audit](session-closeout-audit-2026-10-26.md) supersedes historical
  branch states and already-resolved Cloud/Apply blockers. The focused next
  step, updated by the operator on 2026-10-26, is
  [AI-led evaluation through Eval Lab](https://github.com/npcink/npcink-eval-lab/blob/main/docs/link-recommendation-eval.md#ai-led-development-and-usage-feedback).
  AI handles evidence, disagreements, scoped fixes and regression on named
  source/runtime revisions. The operator uses the product, provides ordinary
  feedback and makes final decisions; no five-case human worksheet is required
  to continue development. Check both wrong suggestions and useful positives.
  Preserve the frozen-v1 hold, exclude developer traffic, and keep candidate-review
  worksheets separate from independent gold. No production rollout or ranking
  campaign follows from this handoff.
- **Article assistance follow-up (Toolbox editor / Cloud analysis).** Formatting
  is merged (#141); fresh browser preservation/Undo evidence and unified
  publish-preflight issue locations remain follow-up work. Generic cross-
  paragraph logic and body risk classification are not delivered. Reuse the
  current preflight/paragraph-review surface after a scoped contract; no new
  editor intent, hidden save or automatic publishing block is authorized.
  Source: [five-session audit](session-closeout-audit-2026-10-26.md).
- **Site-effect verification remains a deferred design (Toolbox / Cloud).**
  Existing Smart Ask consumes bounded deidentified packs. Deterministic
  before/after measurement, reviewed method references and comparable black-box
  rechecks still need their own evidence contracts. Environment inventory is
  not a speed measurement. Keep Cloud inspection/optimization pauses and the
  operator's no-new-pack scope; do not infer expansion authority from retained
  code. Source: [original plan](ai-site-check-effect-verification-plan-2026-10-14.md).

- **Next-phase actions for the following session** are consolidated in the
  [Session Handoff 2026-10-11](session-handoff-2026-10-11-hardening-release-next-phase.md):
  real hold-comment calibration feeding Cloud intent scheduling (historical priority;
  framework ready in eval-lab #110), the ③ media-ALT adoption gate (needs
  ~1 week of production use), the PHPStan ratchet, and the remaining cluster
  splits. Site Check browser verification is green. The former Cloud HTTP 502
  internal-link blocker was resolved during recommendation stage 0; real
  candidate retrieval, Apply/Undo, and disposable-draft native save now pass.
  See [Ranking Baseline v1](recommendation-ranking-baseline-v1.md). These older
  actions are conditional on current feature visibility/pauses; the focused
  next action is recorded above.
- **Refresh replacement safety and automatic delivery (Cloud / Addon).**
  The original 100/1,999 corpus gap is resolved by the
  [2026-10-28 bounded recovery](recommendation-corpus-recovery-2026-10-28.md):
  1,999/1,999 public sources, 2,217 public chunks, empty source-set differences,
  original 233 documents / 266 chunks and WordPress source fingerprints
  preserved. Historical Free plan decisions limit vectors to 100; the operator
  upgraded to Agency's 10,000. The earlier site-only `quota=ok` conclusion did
  not exclude account quota. The follow-on [refresh safety slice](recommendation-refresh-safety-2026-10-28.md)
  removes delete-before-quota behavior for refresh and adds explicit legitimate
  withdrawals; malformed/conflicting markers fail before mutation. 267 focused
  regressions and 23 M4 safety tests passed. Real single-article refresh and
  named Site Knowledge Cron each delivered one source with zero deletions;
  unrelated rows/chunks and WordPress source fingerprints were unchanged.
  Exact merge/runtime states are in the [receipt](recommendation-refresh-safety-evidence-2026-10-28.json).
  Expired historical inputs still limit the earlier root-cause claim. Explicit
  rebuild and SQL/vector partial-failure risks remain separate.
  Docker outage is resolved by an authorized restart with no deployment or
  index/volume deletion. The old run committed 11/25 documents, was repaired
  through the existing audited Cloud action, and only missing keys were sent.
  The [checkpoint](recommendation-corpus-recovery-evidence-2026-10-28.json)
  remains historical; [final evidence](recommendation-corpus-recovery-final-evidence-2026-10-28.json)
  records completion. The [knowledge-only Cron recovery](recommendation-knowledge-cron-recovery-2026-10-28.md)
  replaced the blanket local pause with a scope for the two existing knowledge
  hooks. Ordinary HTTP traffic ran overdue reconciliation and a post/page flush
  after the default 180-second debounce: two documents/chunks, zero deletions,
  empty buffer. Other 16 scheduled hook buckets remain paused and unchanged;
  deferred cleanup/monitoring is still a local environment concern. The advanced
  cursor remains intact with no currently eligible sources beyond it; a nonempty
  full-corpus sweep is unproven. Pseudo-Cron needs site traffic and running local
  services. Do not remove the scope or run all Cron to test knowledge.
  The corpus recovery changed indexes; the follow-on safety slice changes Cloud/
  Addon code. Neither operation writes WordPress article content. Owner: Cloud index/repair and Addon delivery; Eval Lab
  measures comparable relevance, Toolbox retains editor regression. No
  per-article human labels are required.
- **Site Knowledge status comparison truncation (Toolbox).** The public status
  producer allows 1,000 local IDs, but the generic recursive sanitizer caps
  request arrays at 80; the live reply confirms `indexed_post_ids_requested=80`.
  Coverage response arrays use the same sanitizer. The complete-comparison
  guard correctly suppresses mismatched article rows. Fix both directions with
  contract-specific bounds and regression evidence, preserving generic limits
  and the incomplete-receipt guard. This explains missing status rows, not the
  Cloud corpus loss. Owner: Toolbox provider/status module; see the diagnosis.
- **Recommendation relevance and anchor naturalness (Cloud ranking owner).**
  Independent human gold remains absent; AI-led development continues under
  the operator's 2026-10-26 decision above. The 2026-10-17 twelve-article developer
  regression found weak related results for the short promotion and broad
  CMS/theme matches, plus exact but unnatural anchors such as `的HTML`,
  `有一定的`, and `售价：49美元`. Physical Apply safety does not establish
  editorial quality. Keep `related_content.v1` / `internal_links.v1` frozen;
  compare source/target/anchor evidence through Eval Lab before proposing a
  versioned ranking change. Developer events, including earlier unmarked smoke
  traffic, must be excluded from human adoption metrics; the current Cloud summary still
  includes developer traffic. Owner: Cloud evaluation, with Toolbox real
  editor regression. Source: [Real Article Regression 2026-10-17](archive/2026-10/recommendation-real-article-regression-2026-10-17.md).
  On 2026-10-27 the current shared M4 candidate contained only 100 public
  documents / 119 chunks and a dirty source receipt; most selected historical
  sources/targets were absent. The next semantic comparison requires a stable
  named runtime and corpus through the Cloud owner's existing process. Do not
  lower thresholds or add Toolbox indexing to compensate. The focused preview
  occurrence fix and unchanged 95-anchor/45-control replay are recorded in
  [Eval Lab's cycle](https://github.com/npcink/npcink-eval-lab/blob/main/docs/link-recommendation-preview-occurrence-2026-10-27.md);
  they establish review-context correctness, not relevance improvement.
  The next bounded cycle adds the [v3 caption guard](internal-link-anchor-eligibility-v3.md):
  7201 → 5280 `解决办法` at an existing caption position becomes manual review; 118 of
  119 development eligibility outcomes stay unchanged. Exact but generic
  `你也可以` and `管理工具` within `管理工具栏` remain unresolved; assess
  source task, destination task and useful-positive retention through Eval Lab
  before another product change. AI opinions are evidence, not human gold.
  The original 10-source/full-corpus holdout completed after the initial
  embedding timeout: all 80 eligibility outcomes/reasons unchanged, 26 eligible
  under v2 and v3. No new-caption case occurred; semantic quality remains open.
  The [cycle record](https://github.com/npcink/npcink-eval-lab/blob/59b31d6b38428c2123d6450940d8d64e570afba9/docs/link-recommendation-solution-caption-2026-10-27.md#服务恢复后完成留验)
  documents the isolated runtime difference and unchanged source/index/postmeta
  checks. No human adoption or release acceptance follows from this regression.
- **Editor intent convergence accepted 2026-10-07** and moved to Recently
  Closed; see [Editor Intent Convergence Decision](editor-intent-convergence-decision.md).
- **User-facing UX audit (2026-10-12) is in closeout.** The audit found four
  systemic patterns (dependency failures, browser-bound batch feedback,
  silent truncation/clamping, and mixed-language UI) plus the Site Check
  visibility decision. PRs #257–#263 delivered the dependency readiness and
  actionable copy, Site Check navigation, batch failure/cancellation feedback,
  internal-link/editor translation fixes, and editor image-sidebar feedback.
  Remaining work is browser verification, residual i18n cleanup, and the
  low-priority interaction polish recorded in the
  [Session Handoff 2026-10-12](session-handoff-2026-10-12-ux-audit-findings.md).
- **Cross-repo: Cloud Addon quota/attribution follow-ups for editor image
  access.** Tracked with the audit trail in
  [Scoped Editor Permissions Lessons 2026-09](scoped-editor-permissions-lessons-2026-09.md);
  the object-level audits live there, not here.
- **Guided fresh-install onboarding tour is implemented and awaiting
  operator-trial answers** ([Onboarding Tour Design
  v1](onboarding-tour-design-v1.md), shipped 2026-10-09): text-link entry
  on the getting-started card, five anchored notice steps, client-only
  `localStorage` state, no routes/writes/dependencies. Open: the trial
  questions (did operators start/finish, which copy was misread, can they
  name one suggestion-only guarantee) still run in the next operator
  trial. Source: [UX Hardening Development Lessons
  2026-10](ux-hardening-development-lessons-2026-10.md).
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
  advisory review. (Superseded: the 2026-10-08 acceleration batch landed
  the DOMParser extraction — see Recently Closed.)
- **Security/performance review deferred findings (2026-10-11) — closed
  same day by PR #247.** Seven of the nine accepted low-severity items
  shipped (route allow-listing and spend coalescing, flow-cache user
  dimension, evidence `read_post` gates, dead helpers removed and the
  error sanitizer wired, version constant, Site Knowledge row caching,
  fingerprint memoization); the remaining two (sequential Cloud chains,
  bootstrap autoload weight) are analyzed-accepted with recorded reasons —
  the local chain lever belongs to npcink-cloud-addon, and lazy bootstrap
  wiring is deferred to the just-in-time cluster splits. Full record:
  [Security And Performance Hardening Closeout 2026-10](security-performance-hardening-2026-10.md).
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
  the shared flow-cache base (Rest_Editor_Flow_Cache, chained through
  Rest_Controller_Support, carrying the transient cache, the
  cross-cluster input-scope/related-items/recommendation-candidate
  statics) plus the taxonomy-shaping cluster (session 2), summary-terms
  (session 3: strategy/metrics tables, Core-handoff candidates, term
  evidence, Toolkit taxonomy adapters, the related-post-terms context
  helper - Rest_Editor_Summary_Terms), writing-pack shaping (session 4:
  required fields, hosted output, field merging, payload/list
  normalization, related articles, request brief -
  Rest_Editor_Writing_Pack_Shaping), and the progressive recommendation
  cluster (session 5: recommendation-set envelope, content
  fingerprint/context contracts, local taxonomy profile, pure
  media/preflight candidate shapers, weighted token-match scoring -
  Rest_Editor_Progressive_Recommendations). Landed 2026-10-09: the
  media/ALT cluster (session 6) as the first instance service extending
  the cache base (Rest_Editor_Media_Alt: request media-items snapshot,
  attachment metadata + sanitization, contextual image ALT flow with
  explicit-consent visual evidence, visual context normalization,
  bounded image-source support query, Toolkit image-candidate review
  projection, and the media brief handler behind a one-line facade
  delegate; editor_media_library_candidates intentionally stays in the
  service per session 5's boundary). The service sits at ~3.0k lines.
  Remaining: the summary and taxonomy cached-flow orchestrators,
  inheriting the cache base and movable with the same pattern.
- **PHPStan baseline ratchet** (2026-10-06; tooling refreshed in PR #262): the
  121 remaining level-5 findings across 86 baseline patterns live in
  `phpstan-baseline.neon` after the phpcbf pass and
  promotion to required. The baseline exists to shrink: when a cluster
  split or cleanup removes a finding class, regenerate the baseline and
  the ratchet holds. The 16 unused-method candidates remain in the
  baseline (mostly `Admin_Page` media-derivative render helpers).

## Recently Closed

- **Internal-link candidate cluster and S2 URL fallback — closed 2026-10-14.**
  Candidate shaping, Toolkit delegation, graph evidence, and exact-anchor
  validation now live in `Rest_Editor_Internal_Links`; server-side URL
  normalization accepts the Toolkit artifact `target_ref.url`,
  `source_candidate_ref`, and nested `source.target_url`/`source.url` shapes
  before local published-post verification.

- **UX audit primary fixes — closed 2026-10-13 (PRs #257–#263).** Cloud
  readiness and dependency copy, Site Check default visibility, batch failure
  details and cancellation, internal-link/editor translation fixes, editor
  image-sidebar feedback, and the Dependabot/PHPStan maintenance refresh are
  merged. Browser smoke verification and the residual debt remain tracked in
  the open entries above.

- **The editor `整理` (format content) button label — closed as already
  fixed by PR #170's i18n pass.** The 2026-09-30 Open entry predated that
  pass; verified 2026-10-12 that no `整理` occurrence remains in any
  PHP/JS source (the label lives only in the zh_CN catalog, so the
  English UI renders English; `tests/run.php` already asserts the
  source-clean state). Retired from Open during the UX audit handoff
  session.

- **`plainTextFromHtml` innerHTML hardening — closed 2026-10-08 (PR
  #222)**, recorded here because the open entry above had not been
  retired. The helper now extracts text through
  `window.DOMParser().parseFromString(source, 'text/html')` (inert by
  specification: no script execution, no resource fetches), with a
  tag-stripping fallback for environments without DOMParser; the
  node behavior test `test:text-utils-inert-extraction` in `test:all`
  fails loudly if `innerHTML` extraction ever returns.

- **Security/performance review priority items — closed 2026-10-11 (PRs
  #242-#245, master 68ab2c4d).** Object-level `edit_post` guards on every
  post-context read; Cloud URL scheme normalization at the provider
  boundary plus JS `isHttpUrl` gates on every dynamic href; editor bundle
  post-type gating with minified release assets; and per-request
  maintenance/batching cost bounds. Record with standing rules:
  [Security And Performance Hardening Closeout 2026-10](security-performance-hardening-2026-10.md).

- **Master-history anomaly on `ec6a2a4f` (PR #238, 2026-10-09) — recorded,
  not actionable.** The squash merge of the docs-only triad PR #238 carries
  the wrong commit message ("refactor: extract the media/ALT cluster into
  Rest_Editor_Media_Alt (#238)", another concurrent session's branch message)
  after a shared-worktree publish; the merged content is correct and the
  real media/ALT split is PR #239 (`b7735c2b`). Rewriting pushed master
  history was rejected; the publishing standard now pins the squash subject
  to the PR title and requires publishing from the topic branch's own
  worktree (see
  [Pull Request Publishing Standard v1](platform/pr-publishing-standard-v1.md),
  Multi-Worktree Safety).

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
