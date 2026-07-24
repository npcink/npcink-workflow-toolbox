# Pull Request Publishing Standard v1

Status: active.

Purpose: prevent avoidable PR-body CI failures while preserving repository
boundaries, required checks, protected branches, and multi-worktree safety.

## Scope

This standard applies to the six repositories in the current Npcink platform
delivery chain:

| Repository | Local publish command | Default base |
| --- | --- | --- |
| `npcink-abilities-toolkit` | `composer pr:publish -- ...` | `master` |
| `npcink-governance-core` | `composer pr:publish -- ...` | `master` |
| `npcink-ai-client-adapter` | `composer pr:publish -- ...` | `master` |
| `npcink-workflow-toolbox` | `composer pr:publish -- ...` | `master` |
| `npcink-cloud-addon` | `composer pr:publish -- ...` | `master` |
| `npcink-ai-cloud` | `pnpm run pr:publish -- ...` | `master` |

The machine-readable inventory is
[`pr-publishing-repositories.json`](pr-publishing-repositories.json).
It records the authoritative publisher SHA-256 so an intentional script change
must update the shared version rather than drifting one repository silently.
Independent repositories such as `wp-magick-toolbox`, `npcink-ad`,
`npcink-pay-refund`, and `npcink-site-toolbox` are not automatically enrolled
by this document. They need their own repository review before adopting the
same command.

## Required PR Body Contract

Every PR body must preserve headings that match these four semantic sections:

- `Scope`
- `Boundary`
- `Verification`
- `Risk`

Repository-specific headings such as `Core Boundary`, `Toolbox Boundary`, or
`Cloud Boundary` satisfy the shared `Boundary` section. Additional sections
such as release impact, deployment impact, and notes remain repository-owned.

Start from the target repository's checked-in
`.github/pull_request_template.md`. Complete the real scope, boundary result,
verification evidence, residual risk, and rollback plan. Do not replace the
template with a short ad hoc `gh pr create --body` value.

Cloud promotion PRs targeting `production` have one additional hard gate:

```text
Approved for production validation by operator.
```

The publishing command rejects a production body that does not contain that
exact operator approval sentence. This does not grant approval; it only
preserves the existing human release gate.

## Standard Flow

1. Work in a focused `codex/<topic>` branch created from the current
   `origin/master`.
2. Run the narrowest relevant local gate, then the repository closeout gate
   required by its `AGENTS.md`.
3. Inspect `git status --short --branch` and `git diff --stat`.
4. Stage only intended files, commit, and confirm the worktree is clean.
5. Copy `.github/pull_request_template.md` to a temporary body file outside the
   tracked source tree and complete it.
6. Preview publication:

   ```bash
   composer pr:publish -- \
     --title "fix: describe the focused change" \
     --body-file /absolute/path/to/pr-body.md \
     --dry-run
   ```

   For Cloud, replace `composer pr:publish` with
   `pnpm run pr:publish`.
7. Run the same command without `--dry-run`.

The publisher performs these checks before push:

- `git` and `gh` are available;
- the PR body file exists;
- all four shared headings are present;
- the current branch is neither detached nor the target base branch;
- the worktree is clean;
- the branch includes the latest fetched `origin/<base>`;
- the branch has at least one commit beyond the base;
- no open PR already exists for the same branch.

It then pushes the current branch, creates the PR with `--body-file`, and
requests:

```text
squash auto-merge + exact head commit match
```

Required checks and branch protection remain the merge authority. Auto-merge
waits; it does not bypass checks or conversation resolution.

## Multi-Worktree Safety

The publisher deliberately omits `--delete-branch`. Repository work commonly
uses a separate clean worktree, while `master` may be checked out elsewhere.
Deleting or switching a branch from the PR command can therefore fail after
the PR has otherwise merged successfully.

Post-merge branch and worktree cleanup is a separate, read-before-delete
operation:

1. fetch and prune;
2. verify the PR merged;
3. inspect `git worktree list --porcelain`;
4. remove only clean auxiliary worktrees;
5. delete only fully merged topic branches.

## Failure Recovery

- Missing body heading: complete the checked-in template and rerun; no push
  occurred.
- Dirty worktree: stage/commit only intended changes or move the publication
  to a clean worktree; do not stash or reset user work implicitly.
- Branch behind base: update the topic branch through the repository's normal
  merge or rebase policy, rerun gates, and publish again.
- Existing open PR: update that PR body with `gh pr edit --body-file ...`;
  do not create a duplicate PR.
- Auto-merge unavailable: confirm repository settings and required checks.
  Do not replace it with a direct protected-branch push.
- Failed required check: fix the source or body contract on the same topic
  branch; do not bypass the check.

## Cross-Repository Closeout

For a multi-repository change, each repository gets its own focused commit and
PR. After all repository-local gates pass, run from Workflow Toolbox:

```bash
composer quality:matrix
composer quality:matrix:run
```

Report source commit, PR URL, CI result, merge commit, and any runtime
acceptance separately. A green PR does not by itself prove M4 deployment,
production deployment, or external human acceptance.

## Boundary

This standard owns publishing coordination only. It does not own product
runtime, ability contracts, Core governance, Adapter execution policy, Cloud
transport, hosted runtime, production approval, deployment, or Git history
cleanup. Each repository remains authoritative for its own template, required
checks, release gates, and verification commands.
