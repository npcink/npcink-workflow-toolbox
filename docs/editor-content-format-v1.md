# Cloud Content Formatting In The Native Editor

Status: development candidate; no merge or production claim.

## Current Structure Contract v2

Addon now requests `content_format_request.v2`; Cloud retains v1 spacing-only
compatibility. The full `content_format_candidate.v2` response adds
`structural_changes`. `inserted_spaces` counts spacing insertions, not total
serialized byte delta. This section supersedes the v1 limits below.

Plain top-level paragraphs may split a contiguous labeled link/scalar tail
into a native list or restore missing BR before Chinese star-marked items
inside an existing repeated-BR list context. Feature stars remain visible.
Titles, words, numbers, links, images and galleries are not rewritten.
Attributed/nested paragraphs, uncertain prose, code and custom blocks are not
structurally transformed. No model, billing, theme or CSS changes are included.

Long-paragraph splitting now supports balanced inline emphasis, links and
opaque inline code, but only cuts outside all open tags. It never duplicates
tags or changes link/code text. The 180-character inspection threshold does
not force a split; original paragraphs never merge. PHP and browser validators
accept unchanged inline code alongside structural repairs and reject code
text or inline-attribute mutations. `FORMAT_PRESERVATION_FIXTURE=1` exercises
rich paragraphs, a table and code in a disposable draft with native undo.
Semantic embedding/model comparison remains eval-only, not a WordPress option
or default request. The small initial M4 model pilot did not justify adoption.

PHP validates digests, exact result keys, ordered text, inline attributes,
link labels and protected blocks. Only plain paragraph/list/BR boundaries may
differ. Browser checks native block validity and equivalent content before a
single `replaceBlocks` action. Unchanged blocks retain IDs; split blocks may
receive new IDs. Undo includes block identity. No backend saving is invoked.

Browser smoke copies the selected post using `FORMAT_SOURCE_POST_ID`, blocks
post writes, locks test autosave, and verifies original content at cleanup.
For article 2701 it requires three metadata list items, four restored BRs,
idempotence, undo/redo and failure preservation. Its ignored
`build/smoke/content-format-eval.json` feeds eval-lab task
`content_format_offline`; all six independent outcome checks must pass.
Screenshots must also match the operator's structural reference. Scores are
development evidence, never user acceptance or write authority.

### Structure Candidate Evidence, 2026-09-08

M4 bundle `fa41e2aa60ecfb82f1a2e07cb273c81f86fe88742fde8ab5bb6641364f727d67`
at base `735b422a11b519f934786e922e0d3c6b33da3cb2` passed 50 focused tests
(pytest execution 6.91 seconds), with healthy API/frontend/proxy. One source
sync, no image rebuild, merge or promotion. Local pytest, Ruff and mypy passed.

Site A copied article 2701 now yields three metadata items and four restored
feature breaks (five structural repairs). Native validity, protected block
IDs, repeat no-op, undo/redo, one-shot restore, delayed-result rejection and
no post writes passed. Browser adversarial fixtures reject text/link/attribute
tampering. Final Site A draft 281067 and Site B draft 20 were deleted; earlier
failed diagnostic drafts and all temporary sessions were also cleaned up.
The saved original remained unchanged. Desktop/mobile screenshots were checked.

Eval-lab's independent mixed-article checks passed 6/6 (descriptive score 100,
not human acceptance). Its own tests reject the unrepaired fixture and
simulated text/link/media loss; registry self-check passed 927 checks.
The five WordPress matrix gates passed. Cloud exact-clean-SHA CI remains
`needs_validation`; final PHP no-op and browser-identity changes were checked
with focused WP/browser tests rather than repeating the whole matrix.

## Decision

The present editor clicks `整理` in Npcink Content Support. Cloud returns the
complete formatted body, not an operation plan for WordPress to execute. After
local validation it appears directly in the current Gutenberg editor. There is
no separate candidate page or second Apply action. This is ADR-006
`native_editor_commit`: the author reviews the visible body and uses native
WordPress save. The tool never invokes save, publish, Core, Adapter or a
backend article write. Native WordPress autosave behavior is not redefined.

Cloud owns formatting rules and execution. Addon owns the bounded signed
transport. Toolbox owns request validation, editor-state application and
request-scoped undo, not a second formatting engine or workflow registry.
Gemini Nano and paid model calls are outside this candidate.

## Initial v1 Contract (Historical)

Reuse `POST /npcink-toolbox/v1/editor/content-support` with exactly:

```json
{"intent":"format_content","post_id":123,"content":"exact current Gutenberg serialization"}
```

Cookie/REST-nonce authentication, `manage_options`, and the target `edit_post`
capability are required. Text must be valid UTF-8, nonempty, at most 100000
bytes. This intent bypasses the ordinary sanitized/truncated context builder
and transient cache. No draft body or result is persisted by Toolbox.

The Addon facade `npcink_cloud_addon_execute_toolbox_content_format_runtime`
accepts exactly `content`, `format=html`, and `source_sha256`. The fixed Cloud
runtime marker is `npcink-toolbox/format-content`, request contract
`content_format_request.v1`, profile `content-format.managed`, execution kind
`content_format`. It is inline, `no_store`, `pii`, no callbacks/retries/retention,
with a fresh idempotency key on each explicit request. This marker is not a new
registered local ability or external-client entry.

Toolbox accepts only the exact `content_format_candidate.v1` shape. Source and
candidate SHA-256 values, status and inserted-space count must match. Non-space
text and original whitespace cannot be deleted or replaced. WordPress's HTML
tokenizer masks editable text to prove that raw tags, attributes, comments,
links, code, preformatted text and tables remain unchanged. The result adds
only `post_id` and `persisted=false`, with HTTP `Cache-Control: no-store`.

The browser verifies supported valid blocks, stable names, non-content
attributes and exact serialization before applying all changed text attributes
in one native block-editor action. Block client IDs are retained. Any article,
body or block-identity change during the request invalidates it, including an
edit followed by undo. Leaving the surface aborts the request. One-shot undo
rejects conflicts with subsequent edits; normal editor undo remains available.

## Initial v1 Limits (Historical)

This candidate only adds CJK/Latin/digit spacing. It is not paragraph splitting,
rewriting, title generation, or general typographic cleanup. Supported editor
blocks are paragraph, heading, list, list-item, quote, code and preformatted.
Image/gallery/custom/dynamic blocks are preserved exactly while supported
siblings can change (`PARTIAL`). `REVIEW` is accepted only as an unchanged
candidate. Structural changes, changed protected blocks, invalid serialization,
absent candidates and transport errors leave the original body unchanged.
Classic Editor and Markdown editor conversion are not exposed.

## Verification And Rollback

- `composer test:editor-content-format`: pure JS preflight and no-write guards.
- `wp eval-file tests/smoke-editor-content-format.php`: actual WordPress HTML
  tokenizer, digest/markup/protected-content checks and permission rejection.
- `node tests/smoke-editor-content-format-browser.mjs`: disposable Site B draft,
  actual signed Cloud success, stable IDs, undo, delayed response rejection,
  mocked failure, no tool-triggered post writes, PC/mobile screenshots.
- Both plugin `composer test:all` gates, Addon Playground public API gate and
  central quality matrix. Dirty/unmerged Cloud candidates remain
  `needs_validation` for exact-SHA GitHub acceptance even when runtime passes.

Rollback removes this intent, its enqueued module and named Addon facade as one
source change. No migration or article rollback is needed. Revert only task
hunks; preserve unrelated checkout changes and existing Cloud credentials.

## Candidate Evidence, 2026-09-08

Site B (`magick-toolbox.local`) now mounts and activates the existing Toolbox
checkout alongside its existing AI and Cloud Addon plugins. Connection settings
and existing articles were preserved. The temporary smoke drafts and test login
sessions were removed; the operator's existing browser sessions were retained.

Passed: both plugin `composer test:all` gates, Addon Playground (WordPress
7.0.4/PHP 8.2), Composer metadata, actual Site B WordPress tokenizer/permission
smoke, and the browser smoke on WordPress 7.1. Browser evidence proves a real
signed Cloud response, exact visible two-paragraph result, stable block IDs,
one native undo/redo transaction, one-shot undo, stale-result rejection,
failure preservation, no post-write requests/page errors, and a 390px viewport.
Screenshots are ignored local evidence under `build/smoke/content-format-*`.

The central quality matrix passed all five WordPress repositories. Its Cloud
entry remains `needs_validation` because the explicit formatting worktree is
dirty and lacks exact-clean-SHA GitHub acceptance. M4 status still reports the
existing candidate at base `735b422a11b519f934786e922e0d3c6b33da3cb2`, bundle
`471dd5e0b47ce844efb241fb6aef39137506db66bb6a8ea548e12d4b24e089f0`, with healthy
services. This editor integration did not sync, deploy, merge or promote Cloud.
No paid provider call or production change was made. Both plugin branches
remain at their upstream master revisions with uncommitted candidate changes.

## Protected-Block Repair Evidence, 2026-09-08

The saved article 2701 on Site A contains galleries, images and links. The old
Cloud parser rejected its media blocks, and Toolbox misreported the resulting
unchanged `REVIEW` as a failed integrity check. Cloud now skips balanced
protected blocks and returns `PARTIAL` when supported siblings change; Toolbox
independently verifies protected subtree equality before editor application.
The saved-source reproduction inserted 12 spaces and passed real WP validation.

M4 received one source sync, without an image build, using the existing direct
Pgy connection. Candidate bundle is
`778243c2bc64e3c69b59e8b44d25a4ed8ad079b408045af957ec9034583dae48`.
All 41 focused Cloud tests passed on M4 (pytest execution: 6.17 seconds).
Browser smoke passed on an HTTPS Site A disposable copy of article 2701 and
on the ordinary Site B fixture: direct visible application, stable IDs,
native undo/redo, one-shot undo, stale-response rejection, error preservation,
no post writes and mobile fit. Original article 2701 remained byte-identical;
drafts 281060 and 19 and their temporary sessions were removed. Screenshots
were inspected. JS protection regressions and all five WordPress repository
gates passed. Cloud exact-clean-SHA acceptance remains pending, not green.
No paid model call, account change, merge, promotion or production action occurred.
