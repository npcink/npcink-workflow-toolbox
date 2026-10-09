# Current State

Status: the session orientation page. Read this plus `AGENTS.md` at session
start; every deep document below is read on demand, not up front. This page
records where things are, not the contracts themselves — when this page and
a canonical contract doc disagree, the contract doc wins.

Last reviewed: 2026-10-07.

## Product Snapshot

- **0.4.0 is live on WordPress.org** (slug `npcink-workflow-toolbox`, SVN
  revision 3730454).
- The five-review-set arc is complete and shipped: Media ALT (0.2.0),
  Comment Moderation and Flagged Media (0.3.0), Taxonomy/Tag and
  Internal-Link (0.4.0). All share one pattern: bounded sampling (<=50),
  Cloud suggestion, review-only admin panel, fail-closed, pii no-store.
- Default surfaces: editor **Npcink Content Support** sidebar (writing
  pack, preflight, category/tag, internal links, contextual ALT, image
  candidates), hidden **Site Check** compatibility route, admin **Image
  Handling** (Batch Optimize first), Overview + Site Profile tabs.
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
  service (~3.0k lines) plus seven static sibling clusters and the
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
- Environment-dependent smokes (browser/Cloud/local site) live outside
  `test:all`; see `docs/development-workflow.md` before running them.
  Never pipe a gate through `grep`/`tail` — check the composer exit code.

## Open Debt (top items)

1. Editor JS clusters (image candidates, audio, preflight, progressive,
   draft) still inside the 10.6k main bundle.
2. `Rest_Editor_Content_Support.php` sub-division (~3.0k facade lines;
   only the summary/taxonomy cached-flow orchestrators remain).
3. `assets/admin.js` (8.2k) and remaining `Admin_Page.php` clusters
   (review-set tools, media derivative controls, content context form).
4. Editor intent convergence shipped 2026-10-07: twelve-intent editor
   allowlist; see `docs/editor-intent-convergence-decision.md` before
   adding any new editor intent.
5. PHPStan baseline ratchet (136 findings; shrinks with cluster splits).
6. `plainTextFromHtml` innerHTML hardening; guided onboarding tour
   (design proposed, awaiting the operator trial —
   `docs/onboarding-tour-design-v1.md`); "Core proposal" terminology
   standard cross-repo rollout.

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
