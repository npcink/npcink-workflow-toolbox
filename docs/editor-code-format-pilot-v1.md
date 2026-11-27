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
