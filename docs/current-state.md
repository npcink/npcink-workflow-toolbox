# Current State

Status: the session orientation page. Read this plus `AGENTS.md` at session
start; every deep document below is read on demand, not up front. This page
records where things are, not the contracts themselves — when this page and
a canonical contract doc disagree, the contract doc wins.

Last reviewed: 2026-10-13.

**Next session: read
[Session Handoff 2026-10-11](session-handoff-2026-10-11-hardening-release-next-phase.md)
and
[Session Handoff 2026-10-12 — UX Audit Findings](session-handoff-2026-10-12-ux-audit-findings.md)
after this page** — the first records what just shipped, the next actions
in priority order, and the environment facts (sockets, test site,
gotchas); the second carries the user-perspective UX audit findings and
the fix TODO order (uncommitted working-tree docs at the time of
writing).

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
  `npcink_toolbox_editor_supported_post_types` filter), visible **Site Check**
  tab (Overview remains the default; compatibility route retained), admin **Image Handling**
  (Batch Optimize first), Overview + Site Profile + Site Check tabs. Release packages
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
- `tests/run.php` — static contract suite (~4.1k assertions, needles pin
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
- Advisory AI review (mechanized at the publisher since 2026-10-07):
  `composer pr:publish` waits for the delivered OpenCodeReview CI round
  and a `fix:`/`accept:` triage line per finding in the PR body's
  `## AI Review Triage` section; exceptions go through
  `-- --no-review-because`. A local `ocr review --from origin/master
  --to HEAD` round is optional extra signal.
- Local OCR wrappers: `composer ocr:config-check` compares local provider/model
  metadata with the GitHub Actions mirror without exposing secret values;
  `composer ocr:local` runs the bounded local review. CI remains the exact-head
  publish gate. On 2026-10-14 the local preview passed, but the Qwen review
  timed out after 13m43s with zero tokens and zero findings; this is recorded
  as provider timeout evidence rather than a code finding.
- Browser closeout on 2026-10-14: `smoke-site-ops-insights-browser` passed
  against `https://magick-ai.local` with the fourth Site Check tab, all
  read-only sub-tabs, narrow-layout checks, and no Cloud/Core execution calls.
  The subsequent recommendation stage-0 verification resolved the Cloud anchor
  matcher CPU bottleneck on the actual M4-backed Addon route. Related-content
  and internal-link queries passed three consecutive rounds; editor
  review/copy, disposable-draft Apply/Undo, and native-save feedback passed.
  Natural samples can still be review-only because Toolkit currently matches
  target titles or author selections, not Cloud anchor hints. See
  [Ranking Baseline v1](recommendation-ranking-baseline-v1.md) for the separate
  ranking versions, bounded metadata, evidence, and remaining contract gap.
- Environment-dependent smokes (browser/Cloud/local site) live outside
  `test:all`; see `docs/development-workflow.md` before running them.
  Never pipe a gate through `grep`/`tail` — check the composer exit code.

## Open Debt (top items)

0. The UX delivery arc in PRs #257–#263 is merged. Browser verification is
   complete for Site Check. Recommendation stage 0 now passes through the
   actual M4-backed Cloud route; the remaining natural-article Apply limitation
   is Toolkit anchor-hint consumption, documented in Ranking Baseline v1.
   Remaining next-phase actions are real hold-comment calibration for Cloud
   scheduling (highest value), the ③ media-ALT adoption gate (needs ~1 week
   of production use), the PHPStan ratchet, and the carried cluster splits.
1. Editor JS clusters (image candidates, audio, preflight, progressive,
   draft) still inside the 10.6k main bundle.
2. `Rest_Editor_Content_Support.php` sub-division (~3.0k facade lines;
   only the summary/taxonomy cached-flow orchestrators remain).
3. `assets/admin.js` (8.2k) and remaining `Admin_Page.php` clusters
   (review-set tools, media derivative controls, content context form).
4. Editor intent convergence shipped 2026-10-07: twelve-intent editor
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
