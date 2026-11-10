# AGENTS.md - Npcink Toolbox

## Session Startup Protocol

Every new AI development session should start with:

1. Run `git status --short --branch`.
2. Read `docs/current-state.md` (the orientation page: repo map, gates,
   open debt, pointers).
3. Read `docs/boundary.md` before editing; it is the hard-block authority.
   Read the module-specific contract doc that `docs/current-state.md`
   points at for the area you touch (`docs/product-positioning.md`,
   `docs/architecture.md`, `docs/roadmap.md`,
   `docs/development-workflow.md`, or
   `docs/decisions/ADR-001-toolbox-as-product-surface.md`) rather than all
   of them up front.
4. Briefly report the current module, relevant boundary, and intended focused
   gate before editing.

**Active development freeze — 2026-11-09:** follow
`docs/development-freeze-2026-11-09.md` before selecting work. Maintain the
existing article editor loop and necessary safety/compatibility fixes; freeze
feature expansion and repeated evaluation without new evidence. Existing
runtime pauses and default-hidden entries remain effective. Old TODOs do not
authorize new work; bounded repairs need no additional unfreeze approval.

Cross-repo compatibility is governed by
https://github.com/npcink/npcink-ai-cloud/blob/master/docs/compatibility-matrix.md;
do not add Addon `version_compare` checks.

When debugging cross-repo or Cloud integration, prefer the Cloud `run_id`;
do not expect local prefix traces to grep Cloud logs directly.

**脱密 pack → Cloud 验证（其它 AI 必读）：** 边界
`docs/deidentified-assistant-boundary-v1.md`。
- **巡检摘要：** `docs/insight-pack-cloud-qa-cli.md`；`scripts/insight-pack-cloud-qa.sh`；
  CLI 直调 `POST …/site-ops/insight-pack-qa`；后台智能提问直接描述站点检查问题。
- **内容结构摘要（第二包）：** `docs/content-structure-pack-cloud-qa-cli.md`；
  `scripts/content-structure-pack-cloud-qa.sh`；后台智能提问直接描述内容结构问题
  （`POST …/content-structure/insight-pack-qa`；不要去「图片处理」）。
源码已包含巡检、内容结构、页面质量、环境清单四包，以及独立的「本站公开内容」问答。
冻结新增包与编排；原五类接口保留。2026-11-10 用户同意取消后台资料选择器，
只允许一次问题判断与至多一个既有回答路径，见
`docs/admin-operator-focus-2026-11-10.md`；不恢复其他冻结功能。
勿走已暂停的 `cloud_inspection`；排障优先带 Cloud `run_id`。

## Product Boundary

Npcink Toolbox is the WordPress operator-facing AI tool surface.

Toolbox owns:

- Tavily external research tool UX;
- Unsplash image-source candidate tool UX;
- SiliconFlow or Jina query embedding calls for vector search;
- Qdrant vector search tool UX;
- fixed workflow buttons for repeatable operator actions;
- planning artifacts and handoff suggestions;
- lightweight provider action calls for current MVP.

Toolbox does not own:

- Core governance truth, approval records, or audit logs;
- final WordPress write authorization;
- reusable first-party WordPress ability definitions already owned by
  `npcink-abilities-toolkit`;
- reusable static workflow definitions already owned by
  `npcink-abilities-toolkit`; Toolbox buttons may consume them but must not
  become a parallel definition registry;
- workflow runtime, queues, schedulers, retries, or leases;
- MCP, Agent Gateway, Open API, or OpenClaw control-plane state;
- long-term provider billing, quota, key rotation, or request log ownership;
- content indexing jobs, re-index jobs, stale index detection, or vector
  collection lifecycle in the current stage;
- direct publish, direct media mutation, or direct SEO meta mutation without a
  governed WordPress ability handoff.

## Hard Blocks

Do not introduce:

- `confirm_token` or `write_confirmed` behavior;
- final WordPress writes from fixed-flow buttons;
- direct post publishing, media upload, featured-image setting, or SEO writes
  that bypass WordPress abilities and Core governance;
- a second ability registry, second workflow registry, or second approval store;
- provider key leakage into logs, proposals, REST responses, or docs;
- queue/runtime ownership inside this plugin for the current stage.
- treating Unsplash image-source search as AI image generation;
- treating query embedding as content indexing ownership;
- treating Qdrant vector query as full RAG, indexing, or collection lifecycle
  ownership.
- treating Jina Reader or Jina Reranker as active runtime features before a
  workflow-level contract exists.

If a feature needs any of the above, stop and write a boundary note instead of
implementing it inside Toolbox.

## Development Rules

- New work branches must start from the latest `master`; never branch off an
  unmerged branch. Keep at most 1 unmerged branch per repo (exception: changes
  with zero file overlap may each branch from `master`).
- Check `git status --short --branch` before edits.
- Use command-line `git` for all ordinary Git work: status, diff, branch,
  fetch, merge/rebase, staging, commit, push, pruning, and local sync. Use
  `gh` only for GitHub-specific PR metadata, check inspection, or PR operations
  that plain `git` cannot perform.
- Keep changes scoped to one module per session.
- Prefer server-rendered WordPress admin UI and vanilla JS unless a real build
  requirement appears.
- Keep REST routes capability-gated with `manage_options` until a scoped host
  auth model is intentionally designed.
- Treat provider outputs as suggestions, not committed WordPress changes.
- Treat an author-reviewed value added to the current article's visible editor
  state as `native_editor_commit` only when it is persisted solely by the
  author's normal WordPress Publish or Update action. Toolbox must not attach a
  hidden post-save executor or use this rule for media, cross-object, global,
  external, background, or batch writes; see ADR-006.
- Preserve Unsplash attribution and `download_location` metadata.
- Keep vector search limited to synchronous text query embeddings, supplied
  vector JSON, or Qdrant query objects. Do not add indexing/re-indexing without
  a separate contract.
- Keep `cap.toolbox.*` as the stable first-version scope naming unless Core
  explicitly changes the contract.
- Relaxing any REST capability or adding a lower-capability scope requires an
  object-level authorization audit of every server-side object read on the
  affected routes in the same change; rule by the transport routes the client
  actually calls, not by intent names. Record the audit in the PR body. See
  `docs/scoped-editor-permissions-lessons-2026-09.md`.
- Update docs when public REST, ability ids, workflow shape, lifecycle, or
  product boundary changes.
- For cross-repository Cloud closeout, use the exact-SHA GitHub source gate in
  `composer quality:matrix:run`. Use `composer quality:matrix:m4` only when
  explicit revision-bound M4 runtime evidence is required. Never restore an
  implicit MBA Docker requirement or let the matrix deploy/synchronize M4.
- Add or update `tests/run.php` static contracts for public behavior.
- Stage only files changed for the current task. Do not use `git add -A`.
- Before staging, inspect `git status --short` and `git diff --stat`. If
  unrelated edits already exist, name them and leave them unstaged.
- When one file contains unrelated hunks, stage with `git add -p` or
  `git apply --cached` so the commit contains only the intended scope.
- Before committing, run `git diff --cached --stat` and
  `git diff --cached --name-only`; after committing, check
  `git show --name-status --stat HEAD`. If unexpected files entered the commit,
  reset that commit with `git reset --mixed HEAD~1` and recommit the correct
  scope while preserving the working tree.
- Publish a completed clean topic branch with
  `composer pr:publish -- --title "<title>" --body-file <path>`. Start the body
  from `.github/pull_request_template.md`; do not replace it with ad hoc
  `gh pr create --body` text that omits `Scope`, `Boundary`, `Verification`, or
  `Risk`.
- The publisher checks the current `origin/master` baseline, creates the PR,
  and requests protected squash auto-merge. It never bypasses required checks
  and never deletes local or remote branches, because this repository commonly
  uses multiple worktrees.
- The authoritative cross-repository contract is
  `docs/platform/pr-publishing-standard-v1.md`.

## Verification Gates

Default gate:

```bash
composer test:all
```

Local AI review uses the configured Qwen or DeepSeek model under the
2026-11-03 operator decision. The legacy script path remains compatible.
Do not wait for or rerun cloud AI review. Use
`python3 scripts/local-qwen-review.py --base origin/master --head HEAD --output <new-local-receipt.json>`
and publish with `--local-review <receipt.json>`. Bind the exact committed diff,
triage every finding, and keep required deterministic CI checks. See the active
policy at the top of `docs/platform/ai-code-review-standard-v1.md`.

Composer metadata:

```bash
composer validate --no-check-publish
```

WordPress activation smoke, when a local site and WP-CLI are available:

```bash
wp --path="/path/to/wordpress" plugin activate npcink-workflow-toolbox
```

Before finishing a code session, run the narrowest useful gate and report
exactly what passed or failed.

## Session Closeout

Before final response:

- run the relevant verification gate;
- commit only if explicitly requested or if the session is clearly a complete
  local milestone and the user expects commits;
- report whether the branch is ahead/behind its upstream and whether any
  worktree files remain modified or untracked;
- if the branch is ahead after a completed milestone, either push/open the
  expected PR or explicitly state why the commits are intentionally local-only;
- report changed files and verification results.
