# Installed Editor Role Acceptance — 2026-11-09

Status: local development acceptance; no production or WordPress.org release.

The operator accepted a bounded follow-up to the action-guidance maintenance:
verify standard author, editor and administrator accounts on the installed
WordPress editor, including native save/readback. No role capability or REST
permission was changed.

## Results

Three disposable accounts kept their standard WordPress roles. Each edited
its own disposable draft on `magick-ai.local` (WordPress 7.1.4). Real WordPress
session cookies authenticated the browser; no capability flag or installed
asset was injected. Loaded editor asset hashes matched this checkout.

| Check | Author | Editor | Administrator |
| --- | --- | --- | --- |
| Existing category/tag suggestions through real REST | 200 | 200 | 200 |
| Metadata result action | Manual-selection guidance | Manual-selection guidance | Existing Core form enabled after selection |
| Metadata apply-plan through real REST | 403 | 403 | 200, read-only plan |
| Another temporary user's draft as suggestion context | 403, object denial | 200 | 200 |
| Site-media search route, with server retrieval fixture | 200 | 200 | 200 |
| Other user's attachment in that fixture | Filtered out | Visible | Visible |
| External-image candidate route, with server provider fixture | 200 | 200 | 200 |
| Native Categories checkbox and existing Tags autocomplete | Passed | Passed | Passed |
| Contextual ALT preview, reviewed edit, confirmation and Apply | Passed | Passed | Passed |
| Normal WordPress draft save and editor reload | Passed | Passed | Passed |

Preview and Apply left database content/taxonomy unchanged. Only the explicit
native save persisted the visible block ALT and selected existing terms; every
post remained a draft. Attachment-global ALT was unchanged. Core proposal and
audit table counts and full-row fingerprints were unchanged. No proposal was
submitted, approved or executed. Administrator acceptance stops at the enabled
form and read-only plan; it does not certify a new Core execution path.

## Defect Repaired

Manual and automatic image search declared `requestSignal` inside `try`, then
used it from `finally`. Successful searches raised `ReferenceError:
requestSignal is not defined`; error/cancel/stale exits had the same scope bug.
The request signal now lives in the enclosing handler scope so cleanup can
release the same request. Search contracts, deadlines and permissions remain
unchanged.

The focused behavior test executes both real handlers for success, provider
failure, cancellation, supersession and stale responses. It fails against the
pre-repair `origin/master` source with the same ReferenceError and passes after
the repair. Installed browser acceptance then completes without page errors.

## Evidence Limits And Upstream Failure

An initial real site-media query reached the existing browser timeout state
without a candidate response. The browser's 8-second search deadline aborted
the request; no Cloud `run_id` was available in the browser result. This does
not establish an authorization failure or diagnose the Cloud root cause.
See [Known Issues](known-issues.md). The role smoke does not retry that live
query or reopen model/retrieval evaluation.

The completed role smoke uses a temporary MU fixture restricted to its random
request header and disposable user metadata. Existing provider filters return
fixed retrieval/image candidates **after real REST authorization**. Toolbox
response shaping, attachment object filtering, Toolkit inventory and local
contextual ALT remain real. Hosted HTTP calls are blocked for those fixture
requests, so local preflight remains usable with the hosted article review
unavailable. Browser interception suppresses only synthetic quality feedback
and unrelated native writes; the current draft's explicit native save is real.
These results prove role/UI/save behavior, not live Cloud availability,
recommendation quality, natural adoption or production readiness.

Every run removes its drafts (including revisions), users, sessions, recorded
transients and MU fixture, including failure paths. It reuses existing terms
and an existing image without changing the vocabulary or attachment. Receipts
and screenshots are ignored local artifacts under `output/playwright/`.

## Reproduce

Set `WP_PATH`, `WP_CLI_PHP`, `WP_CLI_BIN`, `WP_DB_SOCKET` and `NODE_PATH` for a
running local site and installed Playwright. Optionally set `CHROME_BIN`.
The script refuses a non-`.local` site before creating resources.

```bash
node tests/smoke-editor-real-roles-browser.mjs
node tests/operator-action-guidance-behavior.mjs
composer test:all
```

The installed-site smoke remains optional and outside `composer test:all`.
Keep the development freeze and runtime pauses in force.
