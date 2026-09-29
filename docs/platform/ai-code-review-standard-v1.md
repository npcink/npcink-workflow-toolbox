# AI Code Review Standard v1

Status: active.

Purpose: adopt `alibaba/open-code-review` (OpenCodeReview, Apache-2.0) as the
shared advisory AI reviewer across Npcink repositories, with one machine-level
local install, a single canonical workflow template, and per-repository CI
enrollment. It complements, and never replaces, deterministic gates.

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

Recommended cadence for solo AI-assisted development: run `ocr review` before
staging AI-generated changes, and
`ocr review --from origin/master --to HEAD` before `composer pr:publish`.
