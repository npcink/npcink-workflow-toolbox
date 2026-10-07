# AI Code Review Standard v1

Status: active.

Purpose: adopt `alibaba/open-code-review` (OpenCodeReview, Apache-2.0) as the
shared advisory AI reviewer across Npcink repositories, with one machine-level
local install, a single canonical workflow template, and per-repository CI
enrollment. It complements, and never replaces, deterministic gates.

## Rollout Closeout - 2026-09-29

All enrollments landed the same day; every PR below is merged and its topic
branches (local and remote) were cleaned up.

| Repository | PRs | Delivered |
| --- | --- | --- |
| `npcink-abilities-toolkit` | #141, #142, #146 | workflow, AGENTS.md gate, first closed-loop defect fix |
| `npcink-workflow-toolbox` | #151, #152 | this standard + template, AGENTS.md gate |
| `npcink-governance-core` | #83 | workflow + AGENTS.md gate |
| `npcink-ai-client-adapter` | #43 | workflow + AGENTS.md gate |
| `npcink-cloud-addon` | #189 | workflow + AGENTS.md gate |
| `npcink-ai-cloud` | #1049 | workflow + AGENTS.md gate |
| `npcink-eval-lab` | #80 | workflow (default branch `main`) |
| `npcink-device-inventory` | #6 | workflow |

First real-world catch and closed loop: the CI reviewer flagged on
`npcink-abilities-toolkit` #143 that a countable admin string used `__()`
with `%d` and no plural forms — a WordPress.org translation-readiness defect
outside the reach of PHPStan, `test:all`, and `check:wporg`. Because that PR
was already in auto-merge, the finding landed after merge; the pre-publish
local gate added the same day exists to catch this class earlier. The defect
was fixed in #146, which also added the missing zh_CN `Plural-Forms:
nplurals=1` header — `msgfmt --check-header` then surfaced and removed a
pre-existing invalid `msgstr[1]` on an older plural entry. The fix branch
re-ran the local gate and reviewed clean with the reviewer explicitly
verifying `msgid_plural` coverage. Cost observed: a 3-file/14-line review
used ~51K tokens in 77 seconds, versus ~1.43M tokens (91% cached) and 8.5
minutes for a two-file feature-sized diff.

Operational lessons recorded for future rollouts:

- Advisory review plus immediate auto-merge means CI comments can arrive after
  merge. The pre-publish local gate is the mitigation; do not make the LLM
  review a required check to "fix" this.
- The default 300s provider timeout is too tight for long agentic rounds on
  GLM-5.3; `providers.<name>.timeout_sec = 600` resolved intermittent
  `context deadline exceeded` failures.
- github.com git-over-HTTPS failed intermittently all day while
  `api.github.com` stayed reachable. The publisher's built-in retry plus
  patience loops (25-45s pauses, up to ~10 attempts) always recovered;
  SSH-remote repositories were unaffected.
- Repositories differ in GitHub's auto-delete-head-branch setting; verify
  remote branch state after merge instead of assuming either behavior.
- With parallel AI sessions active, all enrollment work was done in
  throwaway `/tmp` worktrees off freshly fetched `origin/<default>`; shared
  main worktrees were never switched. Watch for the default branch name
  (`main` on `npcink-eval-lab`).
- `pnpm run pr:publish` refuses in a worktree without `node_modules`; invoke
  the same `scripts/publish-pr.sh` directly there.

Deliberately deferred: Gitee-hosted repositories (GitHub Actions cannot run;
the machine-level CLI still serves them), lower-activity GitHub repositories
(`npcink-ad`, `npcink-pay-refund`, `npcink-site-toolbox`, and similar), and
the optional eval-lab triad comparison in Layer 3.

## Verification Record - 2026-09-29

Local machine verification completed before this standard was written:

- CLI installed globally (`npm install -g @alibaba-group/open-code-review`),
  version `v1.12.10` (579b931), darwin/arm64.
- Prerequisites confirmed: node v22.22.3, git 2.54.0 (tool requires git >= 2.41).
- Delegation-mode mechanics verified keylessly against
  `npcink-abilities-toolkit`:
  - `ocr delegate preview` on the docs-only PR #135 range correctly excluded
    all three Markdown files (`unsupported_ext`, 0 reviewable).
  - `ocr delegate preview` on the code PR #134 range selected the two changed
    PHP files, and `ocr delegate rule` resolved a PHP ruleset that explicitly
    defers to PHPStan/PHPCS-enforced findings.
- Model-backed trial completed 2026-09-29 on the operator-configured
  `z-ai-coding` provider (`glm-5.3`, BigModel coding endpoint); `ocr llm test`
  passed including the tool-call round trip.
- Precision check: `ocr review` on the merged, CI-clean PR #134 range
  (2 PHP files, 113 changed lines) produced 0 comments after ~50 tool calls,
  in 8m32s, ~1.43M input tokens (~91% cache reads). No false positives.
- Recall check: a local-only throwaway branch with one probe file planting
  five WordPress defect classes (wrong capability, loose-`==` guard bypass,
  unvalidated request key with PHP 8.1 deprecation, dead try/catch that
  conflated DB failure with an empty result, unparameterized SQL, unescaped
  admin echo) was reviewed and the tool reported: the per-ID
  `current_user_can( 'delete_post', ... )` capability correction, a
  high-severity loose-comparison guard-bypass explanation, the missing-key /
  PHP 8.1 finding, and the dead try/catch finding whose suggested fix embeds
  `$wpdb->prepare` (covering the SQL-injection plant). The unescaped-echo
  finding is inconclusive: the round-2 request for that file failed with
  `context deadline exceeded` under the then-default 300s timeout.
- Tuning applied after the trial: `providers.z-ai-coding.timeout_sec = 600`
  to absorb long agentic rounds. Per-review cost on the coding plan is
  roughly 1.4M input tokens (mostly cached) and 8-10 minutes wall clock, so
  CI rollout keeps PR-event-only triggering and the docs-only auto-skip.

## Template Update - 2026-10-04

Incident: on `npcink-abilities-toolkit` PR #185 the OpenCodeReview run failed
provider-side and the failure was silent - no comments reached the pull
request, the workflow is advisory and never required, and the pull request
merged with no AI review delivered at all. The gap was only noticed during a
2026-10-04 usage audit. The run artifact (`ocr-review-result-*`) recorded
`status: failed`, `comments: 0`, and "all 3 file review(s) failed - check
your LLM configuration and API key": nothing was computed and lost, the LLM
calls themselves failed. Runs before and after the same window succeeded, so
the failure class is transient provider error, not configuration drift.

Template changes in this update (enrolled repositories re-sync their
`.github/workflows/ocr-review.yml` copies from the template):

- A failure-notification step (`if: failure()`) now posts one marker comment
  on the pull request when the review run fails, stating that no AI review
  was delivered and how to retry. Bot comments cannot re-trigger the
  workflow, so the step cannot loop.
- The runner is pinned to `ubuntu-24.04` ahead of the 2026-10-19
  `ubuntu-latest` -> Ubuntu 26 migration (actions/runner-images#14748);
  bump deliberately after revalidating the review action there.

Also recorded: the `actions/upload-artifact` Node.js 20 deprecation warning
comes from inside `alibaba/open-code-review@v1.12.10` and is not fixable in
this template; bump the action pin when upstream ships the fix.

Follow-up (same day): the pilot re-sync pull request
(`npcink-abilities-toolkit` #188) was itself reviewed by OpenCodeReview,
which caught that posting the marker comment requires `issues: write` —
the template originally granted only `pull-requests: write`, so the
notification step itself would have failed with 403 exactly when needed.
The template now grants `issues: write` and updates a single tagged marker
comment per pull request instead of adding one comment per failed attempt.

## Sync And Revalidation Round - 2026-10-04

Enrolled-copy re-sync to the updated template (same-day round):

| Repository | Pull request | Status |
| --- | --- | --- |
| `npcink-abilities-toolkit` | #188 | merged |
| `npcink-governance-core` | #86 | merged |
| `npcink-ai-client-adapter` | #65 | merged |
| `npcink-cloud-addon` | #210 | merged |
| `npcink-eval-lab` | #102 | merged |
| `npcink-ai-cloud` | #1060 | open: pre-existing `backend-targeted (contract-3)` failure on master since 2026-10-02's last green full CI (diagnosis in the pull request; unrelated to the one-file workflow diff) |
| `npcink-device-inventory` | #7 | open: `npm audit --audit-level=high` turned red on the unchanged dependency tree after a new advisory (green at enrollment #6 on 2026-09-29; diagnosis in the pull request) |

Every delivered review round was triaged - fixed, or declined with the
rationale recorded in the thread. Declines that will recur on future
rounds, recorded once here so they can be cited:

- **Workflow-level `issues: write` (least privilege).** GitHub Actions has
  no per-step permissions; splitting the notification steps into their own
  job is the only complete fix and was declined as doubling the workflow
  surface for a marginal delta: the pinned action already holds
  `pull-requests: write`, which by itself allows posting pull-request
  review comments. Declined on `npcink-governance-core` #86; the same
  finding recurred on `npcink-ai-client-adapter` #65,
  `npcink-cloud-addon` #210, and the pilot canary #189 and was declined
  there. The split-job hardening remains a valid future option.
- **Marker on `cancelled()` runs.** A cancelled run is always superseded
  by a newer run in the same per-PR concurrency group, which either
  delivers the review or posts the marker itself. Declined on the pilot
  #188 (round 3) and `npcink-cloud-addon` #210.
- **Hardcoded `github-actions[bot]` match.** The template posts with the
  default GITHUB_TOKEN, so the identity is fixed by construction;
  relaxing the match to any Bot would let other bots' echoes of the
  marker string be selected. Declined on `npcink-cloud-addon` #210.
- **Canary round nits** (concurrency group, docs-only preflight,
  `pr_number`-versus-refs precedence): declined on the pilot #190 with
  per-finding rationale in the threads.

Action pin style: the review action receives the LLM auth token, and the
tag pin was flagged twice in this round (`npcink-ai-client-adapter` #65,
low; pilot canary #189, high). Decision: the template adopts the
commit-SHA pin (`v1.12.10` resolves to tag object `b465046`, commit
`579b931` — matching this standard's verification record) together with
the next template revision, which is the planned runner-image bump; the
pilot's runner canary already pins the SHA and proves it works. Enrolled
copies move to the SHA pin in the same re-sync.

Ubuntu 26.04 revalidation (evidence, not projection): the `ubuntu-26.04`
label is selectable as of 2026-10-04 (GA image `20260927.149`,
Ubuntu 26.04.1 LTS; runner-images#14748 schedules the `ubuntu-latest`
flip for 2026-10-19 through 2026-11-19). Pilot canary runs
`37206797217` and `37222950038` show the SHA-pinned action downloading
and executing end-to-end on that image, including the
`workflow_dispatch` pr-number requirement, the range resolver, and the
review engine's zero-selection path (`files_reviewed: 0`, 0 tokens); the
second run failed only at its comment-upsert step for want of
`issues: write` in the canary itself (fixed by pilot #191). The canary
workflow (`.github/workflows/ocr-runner-canary.yml` in the pilot,
merged via #189/#190) is the standing tool for revalidating any runner
image before the template's `runs-on` pin is bumped: dispatch it with
the number of a freshly merged docs-only pull request.

## Template Update - 2026-10-05

Adopted per the recorded plan, one revision after 2026-10-04: the
template's runner pin moves from `ubuntu-24.04` to `ubuntu-26.04`
(closed green by pilot canary run `37258146682`), and the action pin
moves from the `v1.12.10` tag to its exact commit
`579b9319151aa734f2d25df6e59efa59eec5a577` (tag object `b465046`),
closing the supply-chain finding raised twice on 2026-10-04. Enrolled
repositories re-sync their `.github/workflows/ocr-review.yml` copies
the same day. Future action bumps record the new tag-to-commit pair in
the template comment and re-run the runner canary when the image
changes.

## Template Update - 2026-10-06

Two gaps closed after the 2026-10-05 usage audit on
`npcink-ai-client-adapter` found pull requests merged with no review
delivered (#66, #71) and delivered findings left untriaged (#74's five
findings, including a still-unaddressed `tee /dev/stderr` portability
flag):

- In-workflow retry. A transient run failure cost the whole round: run
  `37268559643` (PR #71) died in 12s on git transport (exit 128), the
  failure marker posted correctly, and the pull request still merged
  unreviewed with no recorded exception. The template now runs the
  review action up to three times per run: attempts 1-2
  continue-on-error with 45s/90s pauses, attempt 3 is allowed to fail
  the job so the failure-marker step still fires. GitHub Actions has no
  native step retry and no YAML anchors, so the duplicated steps are
  deliberate.
- Publisher delivery + triage gate. `npcink-ai-client-adapter`'s
  `scripts/publish-pr.sh` now calls `scripts/verify-ai-review.sh` after
  creating (or reusing) the pull request and before requesting squash
  auto-merge, mechanizing this standard's delivery-confirmation and
  triage rules on the exact head SHA. The gate waits for the successful
  `pull_request_target` review run and re-runs that same run once on
  failure (comment-triggered rounds cannot be correlated to a head by
  `head_sha`, so the run-id-preserving re-run is the machine retry).
  Every inline finding of the delivered attempt must then have one line
  in the pull request body's `## AI Review Triage` section:
  `<finding-id> fix: <what changed>` or
  `<finding-id> accept: <reason>`. The only path past an undelivered
  review is `--no-review-because "<reason>"`, which the gate appends to
  the PR body. Requesting auto-merge after delivery also removes the
  earlier "comments arrive after the merge" hazard that motivated the
  pre-publish local pass; see the cadence note below. Other enrolled
  repositories adopt the same publisher pattern at their own pace; until
  then their AGENTS.md local-gate wording stands.

## Platform Sync Round - 2026-10-08

The re-port and template re-sync promised above landed, plus an
unplanned review loop on the re-ported scripts:

| Repository | Pull request | Status |
| --- | --- | --- |
| `npcink-ai-client-adapter` | #90 | re-port + three delivered review rounds (6, 4, and 3 findings; every finding fixed or accepted with a recorded triage line) |
| `npcink-workflow-toolbox` | #213, #214 | composer process-timeout 900s; the same review-loop fixes as the canonical copy |
| `npcink-abilities-toolkit` | #211 | merged |
| `npcink-governance-core` | #93 | merged |
| `npcink-cloud-addon` | #242 | merged |
| `npcink-eval-lab` | #104 | merged (copy also moved to `ubuntu-26.04` from the lagged `ubuntu-latest`) |
| `npcink-device-inventory` | #11 | open: pre-existing `npm audit --audit-level=high` red in the `ele-rs` desktop tree (unchanged dependencies, documented since 2026-10-04); diagnosis on the pull request |
| `npcink-ai-cloud` | #1078 | open: branch protection requires a literal `backend` check name that never reports on PR events (the sharded `backend-targeted` checks all pass); same protection mismatch as the 2026-10-04 round (#1060); repo-owner decision |

Hardening deltas from the re-port review loop (both gated copies now
carry them): the disarm state read captures stdout separately so a
failed read preserves the unknown sentinel (an assignment substitution
overwrote it), retries four times with growing delay, fails closed on
unreadable state for production bases, and fails outright when an armed
merge cannot be disabled; every `ocr-summary-run` tag in the rolling
summary must match the pinned run-attempt; heading checks use the
pr-body-contract word-boundary form (anchoring at the heading start
would reject the family's own "## Toolbox Boundary" templates); the
triage-section closing heading tolerates leading whitespace; the jq
guard genuinely precedes the `--self-test` dispatch; the rerun flag
uses a bash-3.2-safe guarded array; the observed `ocr-summary-run`
producer version is recorded with the pinned-action contract notes.

Operational notes: github.com HTTPS was down for the whole round, so
every push went through the Git Data API replay path (the playbook
addendum's composition); the fallback publisher used for the round
respects the gate's exit contract (disarms instead of arming auto-merge
past a failed gate); API brownouts (HTTP 500 on pulls PATCH) recovered
within minutes on both occurrences.

## Enrollment And Publisher Gate - 2026-10-07

`npcink-workflow-toolbox` (this repository) enrolled in Layer 2 and adopted
the publisher delivery + triage gate, the second repository after the pilot
`npcink-ai-client-adapter`:

- `.github/workflows/ocr-review.yml` copied verbatim from the canonical
  template (ubuntu-26.04, commit-SHA action pin, in-run triple retry,
  failure marker, `issues: write`).
- `scripts/verify-ai-review.sh` ported verbatim from
  `npcink-ai-client-adapter`; its producer-contract self-test joined
  `composer test:all` as `test:ai-review-gate`.
- `scripts/publish-pr.sh` now invokes the gate after pull-request creation
  and before requesting squash auto-merge, adds `--no-review-because`, and
  reuses an open pull request for the same branch instead of failing (the
  triage loop re-runs the publisher after fixes or body edits). The
  `publisher_sha256` in `pr-publishing-repositories.json` was regenerated
  for that change, and the PR template gained the `## AI Review Triage`
  section.
- The 2026-09-29 rollout delivered this standard and template here with the
  AGENTS.md local gate only; from 2026-10-07 the delivery-confirmation rule
  is mechanized at this repository's publisher too.

The enrollment pull request itself merges under `--no-review-because`:
`pull_request_target` executes the workflow file from the base branch,
where it does not exist yet, so no CI round can register for its own
enabling PR. The compensating local pre-publish review round covered it,
and the first ordinary pull request after the merge runs the full CI loop.

Two local rounds raised 23 findings on the new scripts; the load-bearing
ones were fixed in the same PR and the fixes are deltas against the
`npcink-ai-client-adapter` originals, which should re-port them:

- the publisher's open-PR reuse is scoped to the requested `--base`
  (one head can feed open PRs to different bases);
- the publisher disarms a previously armed auto-merge BEFORE the gate
  waits (a stale armed merge could otherwise merge the newer, untriaged
  head during the gate's polling, since GitHub keeps auto-merge enabled
  across head pushes, `--match-head-commit` is only checked at request
  time, and the review workflow is advisory and never a required check);
  the disarm is state-aware (`gh pr view --json autoMergeRequest`), retries
  once, and reports an undisableable armed merge loudly;
- the reuse path re-validates the four shared headings (and the
  production approval line) against the live pull request body, which can
  drift from the local `--body-file` between runs;
- the gate's summary reconciliation now fails closed when the rolling
  summary's `ocr-summary-run:` tag names a run other than the pinned one
  (an interleaved comment-triggered round edited it); an absent tag stays
  allowed because the observed skipped shape ships without one;
- the gate's completion budget is 50 minutes (150 x 20s), past the
  45-minute workflow timeout plus queue time;
- a cancelled review run is re-run in full (`gh run rerun` without
  `--failed`), because a cancelled run has no failed jobs to select;
- `--head-sha` accepts 40- or 64-character shas (SHA-256 object format);
- the jq dependency guard sits above the `--self-test` dispatch, and the
  exception-recording PR-body reads fail with the auditable message
  instead of a raw `set -e` abort;
- the self-test pins the gate-owned string surfaces too (28 assertions):
  the triage slice is scoped to its section, and the exception writer is
  idempotent, inserts under the existing header, and appends a new
  section.

The template's `timeout-minutes` also rose from 30 to 45 the same day: the
in-run triple retry can legitimately need ~33 minutes, and a timeout-killed
job ends cancelled, which skips the `if: failure()` marker step. The other
enrolled repositories re-sync their `.github/workflows/ocr-review.yml`
copies to the updated template at their own pace (their 30-minute copies
keep today's behavior until then).

The [PR publishing standard](pr-publishing-standard-v1.md) was updated the
same day: the publisher's pre-push checklist now records the open-PR reuse
behavior and the gate wait before the auto-merge request.

## Effectiveness Metrics - 2026-10-07

The deferred work from the pilot's hardening closeout ("OCR zero-findings
summary comment and the three monthly OCR metrics") resolved differently
than expected for its first half: the action already posts a sticky
`<!-- ocr-summary -->` conversation comment on every finished round,
including zero-findings completions ("Review complete: 0 finding(s)
across N selected item(s)") and selection skips ("Review skipped: no
items were selected" on docs-only diffs). No template change is needed
for zero-findings visibility - the summary comment is the mechanical
delivery signal in all finished outcomes, next to the
`<!-- ocr-review-failed -->` marker for failed runs.

On that basis this standard now carries a light monthly effectiveness
record: three numbers per enrolled repository, collected by hand, no
dashboard. Delivery rate (merged pull requests with at least one
delivered round over all merged pull requests), findings by severity
(delivered inline findings grouped by badge category and severity), and
the triage ratio ("Fixed in <sha>" versus "Declined" maintainer replies).
The numbers, the collection recipe, and the pilot baseline live in
[`ai-code-review-metrics.md`](ai-code-review-metrics.md); headline of the
pilot baseline (npcink-abilities-toolkit, pull requests #141-#210,
2026-09-29 through 2026-10-07): 65/68 delivered (96%; all three gaps
accounted for by record), 146 inline findings, 52 fixes against
9 declines.

## Scope

This standard covers the same repositories as the PR publishing standard
(`npcink-abilities-toolkit`, `npcink-governance-core`,
`npcink-ai-client-adapter`, `npcink-workflow-toolbox`, `npcink-cloud-addon`,
`npcink-ai-cloud`). Repositories enroll one at a time; `npcink-abilities-toolkit`
is the pilot and enrolled first on 2026-09-29 after the operator confirmed the
model-backed trial. Independent repositories adopt it only after their own
review.

## Deployment Layers

### Layer 1 - Local CLI (one machine-level install, all repositories)

```bash
npm install -g @alibaba-group/open-code-review
ocr config provider   # interactive; operator types the API key
ocr config model      # interactive; includes a connectivity test
```

- The npm global prefix on the maintainer machine is `~/.hermes/node`; its
  `bin/ocr` is symlinked into `~/.local/bin`, matching how `npm` itself is
  already linked there.
- Configuration may instead be driven purely by environment variables
  (`OCR_LLM_URL`, `OCR_LLM_TOKEN`, `OCR_LLM_MODEL`, `OCR_USE_ANTHROPIC`) or
  non-interactively via `ocr config set`, for example a custom OpenAI-compatible
  provider with `custom_providers.<name>.url` and `.protocol openai`.
- Keys live in the tool's machine-level config or the operator's environment,
  never in any repository, prompt, or generated artifact.

### Layer 2 - Per-repository GitHub Action

The canonical workflow template is
[`ai-code-review-workflow.yml`](ai-code-review-workflow.yml) next to this
document. Enrolling a repository means:

1. Copy the template to `.github/workflows/ocr-review.yml` on a topic branch.
2. Add repository secrets `OCR_LLM_URL` and `OCR_LLM_AUTH_TOKEN` (operator
   types them into GitHub; the account is a personal user, so there are no
   organization-level secrets).
3. Add repository variables `OCR_LLM_MODEL` and `OCR_LLM_USE_ANTHROPIC`
   (OpenAI-compatible providers such as GLM use `false`).
4. Merge only after the secrets exist, so the first real PR run succeeds.

The template pins `alibaba/open-code-review@v1.12.10`. Bump the pin
deliberately, the same way any other CI dependency is bumped.

### Layer 3 - Evaluation evidence (optional)

A pilot comparison against the existing `npcink-eval-lab` `project-review`
triad may be recorded there, following its decision-driven evaluation rules.
That evidence is optional for rollout; this platform standard is the
adoption decision record.

## Rules

- Advisory only. OpenCodeReview must never be added to a repository's
  required checks; required CI plus conversation resolution remain the merge
  gates. AI review comments are inputs to the operator's judgement, not
  blockers.
- No gate replacement. `composer test:all`, `phpstan`, `check:boundary`,
  `check:wporg`, release verification, and eval-lab evidence flows keep their
  existing authority. OpenCodeReview adds a second opinion on diffs; it does
  not certify contracts, boundaries, or release safety.
- Key discipline. Provider keys are typed by the operator only, per repository
  in GitHub secrets, and never copied into repositories, docs, eval artifacts,
  or PR bodies.
- Cost control. Reviews run on PR events and manual `/open-code-review`
  comments by MEMBER/OWNER/COLLABORATOR users only (built into the template).
  Docs-only diffs produce no LLM calls because unsupported extensions are
  excluded during file selection.
- No runtime ownership. OpenCodeReview is an external reviewer. Enrolling it
  grants no workflow runtime, scheduling, approval, audit, prompt, or provider
  routing authority to any Npcink repository, and does not create a second
  registry of any kind.
- Delivery confirmation. Advisory means a failed run blocks nothing, and a
  silent failure equals no review at all. Every merged pull request must have
  had at least one delivered review round (posted review comments, not merely
  a green or missing check). A finished round always leaves the sticky
  `<!-- ocr-summary -->` bot comment - on findings, zero-findings, and
  selection-skip rounds alike - so that comment is the mechanical delivery
  signal (see Effectiveness Metrics - 2026-10-07). A failed run leaves the
  `<!-- ocr-review-failed -->` marker comment; retry with
  a `/open-code-review` comment or record in the pull request why the change
  merges unreviewed. On `npcink-ai-client-adapter` (since 2026-10-06) and
  `npcink-workflow-toolbox` (since 2026-10-07) this rule is mechanized at
  the publisher (see Template Update - 2026-10-06 and Enrollment And
  Publisher Gate - 2026-10-07).
- Effectiveness metrics. Monthly, per enrolled repository: collect the
  delivery rate, findings by severity, and the fix/decline triage ratio
  with the recipe in [`ai-code-review-metrics.md`](ai-code-review-metrics.md)
  and append a dated record there. Three numbers, collected by hand - no
  dashboard, no automation - so "is it worth it" stays answerable from
  evidence instead of anecdote.
- Rollback. Remove the repository's workflow file and delete its secrets; the
  local CLI is independent (`npm uninstall -g @alibaba-group/open-code-review`).

## Local Usage Cheat Sheet

Run from any repository root after Layer 1:

```bash
ocr review                                  # uncommitted workspace changes
ocr review --from origin/master --to HEAD   # whole branch vs master
ocr review --commit <sha>                   # one commit
ocr scan                                    # whole-file audit, diff-free
ocr scan --path <dir>                       # one subtree
ocr review --format json --output result.json
ocr session list                            # resume long reviews with --resume
```

Cadence for solo AI-assisted development: on repositories with the
publisher delivery + triage gate (`npcink-ai-client-adapter` since
2026-10-06, `npcink-workflow-toolbox` since 2026-10-07), the mandated
check happens at `composer pr:publish` - the publisher waits for the
delivered review and verified triage before requesting auto-merge, so no
separate local pass is required. A local `ocr review --from origin/master
--to HEAD` round before opening the pull request remains optional extra
signal there, `ocr review` on uncommitted work stays useful before
staging, and repositories without the publisher gate keep the pre-publish
local pass mandated in their AGENTS.md.
