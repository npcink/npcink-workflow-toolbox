# Git Transport Fallback Playbook v1

Status: active.

Purpose: how to keep publishing branches, pull requests, and merges
across the Npcink repositories when `github.com` git transports fail
while `api.github.com` stays reachable. All techniques below were
exercised end-to-end during the 2026-10-04/05 advisory-review family
round (github.com HTTPS was unreachable for hours while the REST API
stayed up).

## Diagnosis Matrix

Test all three paths before choosing a route:

| Path | Test | Meaning |
| --- | --- | --- |
| `https://github.com` | `curl -m 10 -o /dev/null -w "%{http_code}" https://github.com` | Git smart-HTTP fetch/push for HTTPS remotes, and the `publish-pr.sh` baseline fetch. |
| `https://api.github.com` | `curl` the same way, or any `gh api` call | Pull requests, refs, Git Data API, server-side merges. |
| `ssh -T git@github.com` | greeting text | Git over SSH. Note which identity it greets - deploy keys are per-repository. |

## Transport Facts (observed 2026-10-04/05)

- The SSH key configured for `npcink-abilities-toolkit` greets as that
  repository only: it can **read public repositories** and **write its
  own**, but cannot read private repositories or write other repositories.
- Private repositories (`npcink-eval-lab`) expose no usable git transport
  when HTTPS is down and the deploy key cannot read them; the REST API
  (with the `npcink` account token) remains fully capable.
- Per-repository SSH alias remotes (for example
  `github-magick-ai-cloud:npcink/npcink-ai-cloud.git` in
  `npcink-ai-cloud`) carry their own deploy key with write access to
  that repository; direct `git push` keeps working for those remotes.
- Outages are intermittent across hours; retries with 15-90 second
  pauses recovered pushes several times. Prefer retrying before
  switching techniques.

## Techniques

1. **One-off fetch through an explicit URL** (works for public
   repositories over SSH regardless of the configured remote):
   `git fetch git@github.com:npcink/<repo>.git master`, then compare
   `FETCH_HEAD` against `gh api repos/npcink/<repo>/branches/master`.
2. **Refresh stale remote refs deliberately.** Local
   `refs/remotes/origin/<branch>` refs go stale silently; before basing
   work, compare with the API head and `git update-ref` when they lag.
   Scripts that assert `origin/master == API head` prevent accidental
   stale-baseline branches.
3. **Git Data API replay** (when no push transport exists): create the
   file blobs (`POST /git/blobs`, base64), a tree
   (`POST /git/trees` with `base_tree` = parent commit's tree plus the
   changed entries), commits (`POST /git/commits` with exact author,
   committer, dates, and message), then the branch ref (`POST /git/refs`
   for a new branch, `PATCH` for an existing one). Reference
   implementation: `sync-repo.py` used in the 2026-10-05 family round.
   - Timestamps: the API normalizes `+08:00` dates to UTC, so replayed
     commits differ in SHA from their local originals even with
     identical trees. Content is identical; verify with a byte-level
     file comparison and continue from the remote SHA.
   - `gh pr merge --auto --squash --match-head-commit` requires the
     full 40-character SHA.
4. **Server-side merges for BEHIND pull requests**:
   `POST /repos/{repo}/merges` with `base` = the pull request branch,
   `head` = master, merges master into the branch without any local
   transport. Re-request auto-merge with the new head SHA afterwards.
5. **Pure-API branch creation for private repositories**: parent =
   remote default-branch head (from the API), tree from `base_tree` +
   uploaded blobs - no local git objects required at all.
6. **Conversation-thread triage without a browser**: reply with
   `POST /repos/{repo}/pulls/{n}/comments/{id}/replies`, resolve with
   the GraphQL `resolveReviewThread` mutation using a freshly fetched
   thread id (reused ids silently resolve the wrong thread).

## Addendum - 2026-10-07 (PR #210 outage round)

Three additions proven while publishing through the new AI-review
publisher gate with github.com HTTPS down (SSH fetch worked, but this
machine's deploy key is read-only for this repository, and the
publisher's `git fetch` retry budget does not survive a full outage):

- **Remote branch deletion during an outage**: the deploy key cannot
  push deletes, but the REST API can:
  `gh api -X DELETE repos/{repo}/git/refs/heads/{branch}`.
- **Master sync without HTTPS**: fetch over SSH and fast-forward
  (`git fetch git@github.com:{repo}.git master && git merge --ff-only
  FETCH_HEAD`). The `origin/master` tracking ref stays stale (status
  shows `[ahead 1]`) until HTTPS recovers; that is cosmetic, and a
  plain `git fetch origin` reconciles it.
- **Composing the fallback with the AI-review gate**: after an API
  replay push, create the PR with `gh pr create`, then run
  `bash scripts/verify-ai-review.sh --pr {N} --head-sha {remote-sha}`
  directly with the REMOTE head sha (the replayed commit SHA may differ
  from the local one), and request auto-merge with the same remote sha
  in `--match-head-commit`. The gate's run discovery matches on the
  remote head, so this path verifies delivery exactly like the
  publisher's own invocation.

## Local Worktree Protocol

- Use sibling directories under `/Users/muze/gitee/` (for example
  `sync-<repo>`) for multi-repository work, not `/tmp`: several
   repositories' tests resolve sibling paths such as
  `../npcink-abilities-toolkit`, which do not exist under `/tmp`.
- Repositories differ in whether `composer.lock` is tracked (toolkit:
  no; governance-core, ai-cloud: yes). Check before deleting
  build artifacts in a worktree; `git restore <file>` repairs an
  accidental deletion of a tracked lock file.
- Do not switch or clean a main worktree that another session is using;
  create a worktree instead, and remove it (keeping or deleting its
  branch per merge state) when done.

## Shell Notes

macOS zsh does not word-split unquoted variables (`set -- $var` yields
one element), treats `=word` as a command expansion, and has no
`timeout`. Multi-step git/API operations belong in a Python or bash
script file, not an inline zsh loop.
