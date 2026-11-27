# Single code block formatting pilot

Operator approved the bounded pilot on 2026-11-25. Default sidebar actions and
ordinary body-format protection stay unchanged. Enable `NPCINK_TOOLBOX_CODE_FORMAT_PILOT`
as a boolean `true` in an isolated WordPress site's config and connect an Addon
that exposes `npcink_cloud_addon_execute_toolbox_code_preview_runtime()` to a
Cloud development instance with the matching opt-in contract. Remove the
constant to hide the UI and reject the REST intent. No version hard gate.

Select one top-level, editable WordPress Code block under Body formatting. Choose
JavaScript, CSS or JSON explicitly. The request sends only the selected code to
Cloud, where a pinned parser/formatter returns a candidate; no language guessing,
AI provider, PHP/shell formatting, inline code, batch operation or automatic
fallback. Review before and after, then apply to visible editor state only.
Native Undo remains available and only native Update/Publish commits the edit.

`POST /npcink-toolbox/v1/editor/content-support` accepts exactly
`{intent: "format_code_block", post_id, code, language}`. Before using the
bounded Addon signed transport, Toolbox checks the existing editor scope and
`edit_post` for the one supplied post. That post only needs to exist; no post
data, other objects, or saved block content are collected. Addon sends only
code, language and digest in the Cloud input. WordPress rejects recognized
credential formats before transport. Toolbox validates Cloud's exact
result contract, statuses and digests and returns it with `persisted: false`
and `Cache-Control: no-store`. Neither Toolbox nor Cloud stores the code for
this operation. Send no secrets.

Development candidate only. No production dependency, rollout or natural use
is implied. Trial one or two representative blocks before deciding whether to
make the contextual action generally available.

## Local verification

On 2026-11-25, a disposable WordPress 7.1 site with fresh MySQL and the
candidate Addon reached a loopback Cloud test API over the real signed
transport. The installed browser smoke verified that a paragraph has no code
action, preview does not edit the draft, Apply changes only the chosen block,
native Undo restores the original, and saved post content does not change.
The Cloud run retained empty input and result fields. The narrow browser
fixture separately checked visual wrapping and keyboard focus in the preview.
This is a local smoke, not shared M4, production, or human acceptance.

To repeat the installed smoke, use a disposable English-language WordPress
site on loopback, an administrator test account, and an unmodified draft with
two top-level blocks: a Code block containing `const z=(x)=>x<3&&x>0;`, then
a Paragraph containing `Untouched adjacent paragraph`. Install the three
matching candidates and enable both pilot flags. Provide `CODE_FORMAT_TEST_URL`,
`CODE_FORMAT_TEST_POST_ID`, `CODE_FORMAT_TEST_USER` and
`CODE_FORMAT_TEST_PASSWORD` through the environment, then run
`node tests/smoke-editor-code-format-installed.mjs` with Playwright available.
To smoke-test a different fixture expected to change, set
`CODE_FORMAT_TEST_SOURCE_FILE` to the exact UTF-8 block text and
`CODE_FORMAT_TEST_LANGUAGE` to its language. Keep the adjacent paragraph
unchanged. The smoke checks that the exact input reaches WordPress REST and
that the Cloud candidate matches both the preview and the applied block.

The follow-up candidate was verified on Toolbox master `e67ca35`, preserving
the upstream body FAQ action and translations. The installed smoke passed on
the synthetic fixture and two copied public blog blocks (14401 CSS and 13140
JavaScript), including native Undo and no saved mutation. The blog sources
were not modified. Manual evaluation of the proposed formatting remains open.

## Source handoff and open gates (2026-11-27)

| Repository | Source status | Pilot branches and worktrees |
| --- | --- | --- |
| Toolbox | [#439](https://github.com/npcink/npcink-workflow-toolbox/pull/439) merged at `c7a028668f744e6922b2fee1c0a83b1d89610098`. | Implementation branches and temporary worktrees removed. |
| Cloud Addon | [#387](https://github.com/npcink/npcink-cloud-addon/pull/387) merged at `fc14510be0f06a6b33eae15079ec718850455032`. | Implementation branches and temporary worktrees removed. |
| Cloud | [#1458](https://github.com/npcink/npcink-ai-cloud/pull/1458) committed and pushed at `255b599114418b33c2fe1175de620d51a9121cb3`. Protected auto-merge is enabled, but the PR is blocked. | Keep `codex/article-code-format-pilot` and its clean, locked local worktree until the squash is verified. |

Cloud's exact-head source matrix failed at `backend`. The failed checks point
to existing image-security gates: the `CVE-2026-119170` exception expired on
2026-11-22, and the authoritative verification window had expired. The 150
focused Cloud runtime tests and local installed browser smoke are useful
development evidence, not substitutes for that gate. Do not extend or bypass
the exception in this pilot. Once image security is repaired on `master`,
recheck the PR against that baseline, verify the exact-head matrix and the
squashed changes on GitHub `master`, then remove only the task's Cloud branch
and worktree. Check head identity, worktree contents and installation references
before deletion; preserve recovery evidence outside disposable worktrees.

No shared M4 acceptance, human formatting-quality sign-off, production
deployment, or default enablement was completed by this source handoff.
Before broader availability, also revisit the bounded database transaction
held while the Cloud formatter runs, as recorded in the Cloud PR review.

## Lessons for similar editor tools

- Diagnose display and source separately. Scrolling or wrapping belongs to
  rendering unless there is a separate reason to edit source. Keep ordinary
  body formatting's code protection; when a pinned parser can perform the
  requested normalization, do not introduce a generative model or fallback.
- Keep preview separate from adoption. Verify the selected block and snapshot
  again on Apply, reject stale results, and test Undo, adjacent-block
  preservation, and unchanged saved content through the installed editor and
  real signed transport. Do not treat parser checks as human quality sign-off.
- Whenever PHP references for translatable strings move, check both PO and POT
  against the final branch. Addon's first freshness gate caught stale POT
  references; updating the translation files, not retrying, cleared it.
- Report local behavior, exact-head CI, installed runtime, human acceptance,
  and release as distinct results. An unrelated baseline gate can block source
  merge even when feature-specific tests pass. See the shared
  [article review delivery rules](article-review-delivery-lessons-v1.md).
