# Current State

Status: the session orientation page. Read this plus `AGENTS.md` at session
start; every deep document below is read on demand, not up front. This page
records where things are, not the contracts themselves — when this page and
a canonical contract doc disagree, the contract doc wins.

Last reviewed: 2026-11-01.

**Article review follow-up delivered:** formatting compatibility (#356), language
quality evidence (#358), and the [logic/context-risk extension](article-review-context-v1.md)
(#359 / Addon #339 / Cloud #1335) are merged. See the
[2026-11-01 acceptance](article-review-context-acceptance-2026-11-01.md): 9/9 positive
hits, 14 completed zero-hint controls, one timed-out news control retained as
unavailable; real installed editor and 25-run no-store audit passed. Addon #340
adds timeout-receipt transport margin. Broader accuracy, author feedback, OSS
real writes/backup/restore and production release remain separate.

**Language quality follow-up:** the [2026-11-01 evaluation](article-language-quality-evaluation-2026-11-01.md)
records 56 explicit synthetic calls: 20/20 basic positive hits, one false alarm
among 20 basic controls, separate context-label errors and fresh controls.
General accuracy is not certified; the later Cloud extension suppresses style-only
warnings and its original/fresh style controls passed; see current acceptance.

**Formatting compatibility follow-up:** the two known validator refusals now
pass preserved-block and fixed-layout spacing checks; see the
[2026-10-31 verification](article-format-compatibility-2026-10-31.md).
Unsupported legacy HTML remains opaque, and all corrections stay manual.

**Article review closeout:** start with the
[2026-10-31 audit](article-review-session-closeout-2026-10-31.md) for merged PRs,
source/worktree cleanup, intentional local Addon overlay and remaining owners.
Future editor review work follows the
[delivery rules and lessons](article-review-delivery-lessons-v1.md).

**Existing content loop, 2026-10-30:** the real WordPress AI plugin's three
summaries, two titles, selected-paragraph rewrite, native-save readback and
browser-only title failure/real retry passed; originals were unchanged and
disposable drafts were trashed. See [acceptance and dispositions](existing-content-loop-acceptance-2026-10-30.md)
for the separate CMS handoff, image limitations, optional site-answer scope,
human visual acceptance and conservative worktree retention. This is developer
evidence, not natural usage, production release or new upload/CMS support.

**Recommendation/research chat closeout:** the
[2026-10-29 audit](session-research-closeout-2026-10-29.md) verifies 48 attached
PRs merged and records evidence preservation, stopped trials and remaining owners.
Use its current handoff before replaying historical plans. Model-method limits,
article-review/effect-verification design and CMS adapters remain conditional or
deferred work; the frozen 0.6B trial does not describe the concurrent Cloud 8B index.

SEO/AEO/GEO specialist product entrypoints are paused in the current candidate;
generic content assistance, original configuration and historical data remain.
See [pause implementation](seo-specialist-pause-implementation-2026-10-18.md)
for source, gates and runtime evidence states.

**Next session:** read the
[five-session closeout audit](session-closeout-audit-2026-10-26.md) for merged
work, retired experiments, preserved Git refs and the remaining owners.
Admin work also starts from the
[current three-tab IA](admin-ia-site-and-ask-v1.md) and its
[open TODOs](admin-ia-open-todos-2026-10-23.md).
The October 11/12 handoffs remain historical environment and UX evidence;
their old priorities and branch states are not today's task queue.

Cloud inspection/site analysis, new audio and media optimization are also
paused in the current source candidate. Local checks, playback, backup recovery,
ordinary generation and Site Knowledge remain available. See
[reversible pause scope](reversible-feature-pauses-v1.md).

## Product Snapshot

- **0.6.0 is live on WordPress.org** (slug `npcink-workflow-toolbox`, SVN
  revision 3739544); the API reports stable 0.6.0 and the live zip carries
  the nine minified asset siblings. 0.6.0 shipped the 2026-10-11
  security/performance hardening arc (PRs #242–#247).
- The five-review-set arc is complete and shipped: Media ALT (0.2.0),
  Comment Moderation and Flagged Media (0.3.0), Taxonomy/Tag and
  Internal-Link (0.4.0). All share one pattern: bounded sampling (<=50),
  Cloud suggestion, review-only admin panel, fail-closed, pii no-store.
- Default surfaces: editor **Npcink Content Support** sidebar (writing
  pack, preflight, category/tag, internal links, contextual ALT, image
  candidates) on `post`/`page` editors only (extend via the
  `npcink_toolbox_editor_supported_post_types` filter). Admin has three top
  tabs: **Start**, **Site & Ask**, and **Image Handling** (ALT review first;
  optimization paused). Site Profile, Smart Ask and the folded Site Check
  live under Site & Ask; legacy Site Check URLs remain compatible. This is
  the source layout, not a new WordPress.org release receipt. Release packages
  ship `.min` asset siblings (`composer package:release` runs
  `scripts/minify-assets.sh`); checkouts without them enqueue the
  readable sources.
- Sibling family: `npcink-governance-core`, `npcink-abilities-toolkit`,
  `npcink-ai-client-adapter`, `npcink-cloud-addon`, `npcink-ai-cloud`,
  dev-only `npcink-eval-lab`.

## Repo Map

- `includes/Rest_Controller.php` — REST facade (route registration, scope
  map, permission); cluster services in `Rest_*_Bridges.php` and
  `Rest_Editor_Content_Support.php`.
- `includes/Provider_Client.php` — provider facade; cluster services in
  `Provider_*_Service.php` over abstract `Provider_Client_Support.php`.
- `includes/Admin_Page.php` — admin page facade (2.7k lines);
  `Admin_Page_Site_Ops_Panel.php` holds the extracted Site Check render
  cluster (parent class).
- `includes/Rest_Editor_Content_Support.php` — editor content-support
  service (~3.0k lines) plus eight static sibling clusters and the
  `Rest_Editor_Media_Alt` instance service over the shared
  `Rest_Editor_Flow_Cache` base; only the summary/taxonomy cached-flow
  orchestrators remain inside the facade.
- `assets/editor-content-support.js` — editor bundle (10.6k lines) plus
  pure part files under `assets/editor-content-support/` behind frozen
  `window.NpcinkToolbox*` namespaces.
- `assets/admin.js` — admin bundle (8.2k lines; split owed).
- `tests/run.php` — static contract suite (4,424 assertions on 2026-10-26, needles pin
  exact source strings; aggregation helpers keep them file-portable).
- `docs/route-boundary-table.json`, `ability-boundary-table.json`,
  `cloud-bridge-contract-table.json`, `fixed-button-contract-table.json` —
  machine-readable contract truth.
- `docs/known-issues.md` — the single open-debt ledger.
- [AI Site Check and Effect Verification Plan](ai-site-check-effect-verification-plan-2026-10-14.md)
  — discussion record and proposed phased plan for read-only analysis,
  reviewed methods, and black-box rechecks; no new runtime or write authority.

## Verification

- Fast contract gate (run while iterating):
  `php tests/run.php --quiet`
- Full default gate (before commit/PR): `composer test:all`
- Static analysis (required in CI): `composer lint:standards`,
  `composer analyse:php` (level 5 + baseline; regenerate the baseline only
  when a split moves findings, counts must not grow).
- Advisory AI review: local Qwen only, per the 2026-10-27 operator decision.
  Run `scripts/local-qwen-review.py` against the committed diff and pass its
  receipt to `composer pr:publish -- --local-review <path>`. The publisher
  verifies exact Git identity and finding triage; cloud review is retired.
  See the active policy in `docs/platform/ai-code-review-standard-v1.md`.
- Browser closeout on 2026-10-14: `smoke-site-ops-insights-browser` passed
  against `https://magick-ai.local` with the fourth Site Check tab, all
  read-only sub-tabs, narrow-layout checks, and no Cloud/Core execution calls.
  The subsequent recommendation stage-0 verification resolved the Cloud anchor
  matcher CPU bottleneck on the actual M4-backed Addon route. Related-content
  and internal-link queries passed three consecutive rounds; editor
  review/copy, disposable-draft Apply/Undo, and native-save feedback passed.
  Toolkit now consumes verified Cloud anchor hints (Toolkit PR #262).
  The editor projects live Apply safety, including existing links and stale text,
  into readiness without changing the frozen ranking baseline. See
  [Ranking Baseline v1](recommendation-ranking-baseline-v1.md) for the separate
  ranking versions, bounded metadata, evidence, and current limitations.
  The [12-article developer regression](archive/2026-10/recommendation-real-article-regression-2026-10-17.md)
  extends both recommendation families, fixes related-content status/source
  feedback projection, and records relevance/anchor-quality debt without
  changing ranking. Developer receipts are not human adoption evidence.
- Environment-dependent smokes (browser/Cloud/local site) live outside
  `test:all`; see `docs/development-workflow.md` before running them.
  Never pipe a gate through `grep`/`tail` — check the composer exit code.

## Open Debt (top items)

- **2026-10-28 original-site acceptance and corpus recovery:** both body-link
  Apply/Undo/native draft save and related-reading insert/Undo/save/reload passed
  on restored `magick-ai.local`; see [acceptance](recommendation-original-site-acceptance-2026-10-28.md).
  After the operator upgraded Free (100 documents) to Agency (10,000), bounded
  Addon delivery restored all **1,999/1,999 public post/page documents** and
  2,217 public chunks. Final source-set differences are empty; all original
  233 documents / 266 chunks and WordPress source fingerprints are preserved.
  The operator-authorized Docker restart recovered the same Cloud revision;
  one interrupted run committed 11 documents before audited failure repair,
  then only the remaining 383 keys were delivered. There were 77 successful
  runs plus that interrupted run, not zero failed runs. See
  [recovery and lessons](recommendation-corpus-recovery-2026-10-28.md) and
  [final receipt](recommendation-corpus-recovery-final-evidence-2026-10-28.json);
  the earlier [checkpoint](recommendation-corpus-recovery-evidence-2026-10-28.json)
  remains historical. Account quota and delete-before-quota order strongly
  explain the earlier 100-document reduction; expired historical inputs limit
  certainty. The follow-on [refresh safety slice](recommendation-refresh-safety-2026-10-28.md)
  preserves old sources on omitted/failed/quota-skipped refresh and adds explicit
  Addon withdrawal markers. 267 focused Cloud regressions, 23 M4 safety tests,
  real single refresh and named knowledge-Cron delivery passed with unchanged
  unrelated indexes and WordPress sources. See its
  [delivery receipt](recommendation-refresh-safety-evidence-2026-10-28.json) for
  exact merged/runtime states. The [local knowledge-only Cron recovery](recommendation-knowledge-cron-recovery-2026-10-28.md)
  retired the blanket pause: ordinary HTTP requests ran overdue reconciliation
  and delivered a post/page pair after the normal 180-second debounce (two
  documents, two chunks, zero deletions; buffer empty). Other 16 scheduled hook
  buckets remain stored and paused. The cursor was preserved; no eligible
  candidates were found beyond it, so a nonempty full-corpus sweep is unproven.
  The [Toolbox status bounds fix](recommendation-status-bounds-2026-10-28.md)
  preserves the intended 1,000-ID request/reply lists without widening generic
  limits. Real REST comparison improved from 80 IDs / zero article rows to
  1,000 IDs / 1,000 indexed rows; password-protected sources are excluded.
  Malformed/incomplete receipts still suppress article verdicts. Next:
  comparable AI-led Eval Lab relevance evidence. Coverage restoration is
  not semantic-quality improvement or human adoption. Typecho/Astro remain
  unimplemented; see [cross-platform diagnosis](recommendation-coverage-diagnosis-2026-10-28.md).
- **2026-10-28 recommendation priority:** deliver useful ranked articles for
  manual choice. The editor now shows up to eight non-weak candidates in their
  existing order, with open/copy actions regardless of anchor eligibility.
  A separate Related reading action now retrieves full-article candidates for
  author-selected native Gutenberg reading-list insertion; see
  [the editor contract](related-reading-editor-v1.md).
  Apply remains optional and uses the existing live safety checks; its source
  preview stays visible. Perfect anchors, exact resource identity for every
  candidate and model unanimity are not prerequisites for manual recommendation
  delivery. See the [active recommendation standard](related-article-and-internal-link-recommendation-standard-v1.md#3-当前目标相关候选优先用户自行挑选).
  This UI change does not establish retrieval-quality improvement or deployment.
- The operator selected AI-led recommendation development on 2026-10-26:
  Eval Lab reuses real samples and model evidence, the development agent handles
  disagreements, fixes and regression, and the operator uses the product,
  provides ordinary feedback and makes final decisions. Human worksheets are
  not the next development gate. Follow
  [Eval Lab's existing loop](https://github.com/npcink/npcink-eval-lab/blob/main/docs/link-recommendation-eval.md#ai-led-development-and-usage-feedback)
  on named source/runtime revisions; test useful positives as well as rejection.
  Ranking versions and bounded telemetry already shipped in #271;
  v2 eligibility/abstention and the recorded 12-article editor smoke are
  development evidence, not independent human gold or production acceptance.
  See the [closeout audit](session-closeout-audit-2026-10-26.md).
  The first bounded cycle fixed repeated-anchor preview selection after emoji;
  see the [occurrence contract](internal-link-anchor-eligibility-v2.md#review-preview-occurrence)
  and [Eval Lab evidence](https://github.com/npcink/npcink-eval-lab/blob/main/docs/link-recommendation-preview-occurrence-2026-10-27.md).
  The earlier 2026-10-26 shared Cloud receipt recorded 100 public documents /
  119 chunks and a dirty source receipt; it is historical, not a fresh status
  check. Further retrieval comparison needs a named runtime/corpus from its owner; old ranking outcomes are not comparable
  on that reduced corpus. No shared runtime or index was changed this cycle.
- The [article review report](article-review-report-v1.md) keeps formatting and
  read-only language/logic/context-risk hints separate. Two historical formatting
  refusals now pass protected compatibility checks (#356). The 56-call language
  screen (#358) retains its original style-only false alarm and label mistakes;
  that sentence and fresh style controls passed the later extension screen.
  Toolbox #359, Addon #339 and Cloud #1335 deliver the compatible three-check
  contract; Addon #340 adds HTTP receipt margin while keeping a 30-second model
  budget and no retries. See [current acceptance](article-review-context-acceptance-2026-11-01.md)
  for exact source/runtime, 24 initial model calls (one timeout), installed editor,
  no-store audit and cleanup. Adult-context hints are not comprehensive content
  risk, external fact checking or publication approval. Independent author value,
  general accuracy, real OSS writes/backup/restore and production remain open.

- Recommendation anchor eligibility now has an active
  [v3 contract](internal-link-anchor-eligibility-v3.md) extending the historical
  [v2 contract](internal-link-anchor-eligibility-v2.md) with a bounded
  `解决办法` caption guard. The 20-source / 119-candidate development replay
  changes one eligibility outcome; ordinary prose and useful positives remain.
  The original 10-source/full-corpus holdout then completed: 80 unchanged
  eligibility outcomes/reasons, 26 eligible in both v2 and v3; no new-caption
  candidate occurred. This closes the holdout eligibility regression task,
  while semantic quality and release acceptance remain open.
  The policy remains bounded to the current editor. The frozen ranking v1 and its old editor
  implementation remain the comparison; consult Eval Lab release acceptance
  evidence before treating the recommendation feature as ready to release.

0. The UX delivery arc in PRs #257–#263 is merged. Browser verification is
   complete for Site Check. Recommendation stage 0 now passes through the
   actual M4-backed Cloud route. Toolkit anchor-hint consumption is fixed by
   Toolkit PR #262; live editor
   readiness now reuses Apply checks as documented in Ranking Baseline v1.
   Other carried actions are real hold-comment calibration, the media-ALT
   adoption gate, the PHPStan ratchet, and the carried cluster splits. These
   are conditional backlog under the current UI/feature pauses; they do not
   authorize production observation, hidden surfaces or Cloud scheduling.
1. Editor JS clusters (image candidates, audio, preflight, progressive,
   draft) still inside the 10.6k main bundle.
2. `Rest_Editor_Content_Support.php` sub-division (~3.0k facade lines;
   only the summary/taxonomy cached-flow orchestrators remain).
3. `assets/admin.js` (8.2k) and remaining `Admin_Page.php` clusters
   (review-set tools, media derivative controls, content context form).
4. Editor intent convergence shipped 2026-10-07: thirteen-intent editor
   allowlist; see `docs/editor-intent-convergence-decision.md` before
   adding any new editor intent.
5. PHPStan baseline ratchet (121 findings across 86 baseline patterns; shrinks with cluster splits).
6. Guided onboarding tour awaiting the operator trial answers
   (`docs/onboarding-tour-design-v1.md`); "Core proposal" terminology
   standard cross-repo rollout. (The 2026-10-11 security/performance
   review items all closed — see
   `docs/security-performance-hardening-2026-10.md` before touching the
   image-source routes, the flow cache, or the release asset pipeline.)
7. The 2026-10-12 user-facing UX audit is now in closeout: dependency
   readiness/copy, Site Check visibility, batch failure/cancellation feedback,
   internal-link/editor translation fixes, and editor image-sidebar feedback
   shipped in PRs #257–#263. The handoff still records the browser smokes,
   residual i18n cleanup, and low-priority interaction polish.

Full list with sources: `docs/known-issues.md`.

## Session Rules That Are Easy To Forget

- Boundary and hard blocks live in `AGENTS.md` and
  `docs/boundary.md`; REST scope truth is
  `docs/route-boundary-table.json` aligned with
  `Rest_Controller::rest_route_scope()` (full-coverage contract enforced).
- Split work follows `docs/platform/provider-split-refactor-standard-v1.md`
  (portable assertion sources first; one cluster per commit).
- Commit scope discipline: no `git add -A`; stage per file/hunk and verify
  `git diff --cached --stat` before committing.
- PRs publish through `composer pr:publish` (or
  `bash scripts/publish-pr.sh`) with the Scope/Boundary/Verification/Risk
  template; never bypass required checks.
- WordPress.org releases follow the SVN process in
  `docs/wordpress-org-submission.md`.

## Maintenance Rule For This Page

Update this page when the module map, the debt list, or the default gates
change. It intentionally contains no contract language of its own, so it
never needs an ADR to stay current.
