# Related reading in the current editor v1

Status: accepted by the operator on 2026-10-28; implementation in this change.

## Two jobs

- Internal links find a phrase in the current draft and suggest its destination.
- Related reading finds useful articles to read next. The author reviews up to
  eight candidates and inserts selected article titles as a reading list.

The existing sidebar has one recommendation area with two explicit actions.
`related_content` is restored as an editor intent for this named operator
surface. It uses the existing Cloud intent and its ranking, independently of
`internal_links`; it does not restore other retired editor intents.

## Retrieval and target checks

Use the current article's title, excerpt and bounded body projection, ignoring
selected-text scope. Reuse Cloud Site Knowledge and its cache, version and
metadata-only open/copy feedback. No indexing, model routing or ranking changes.
Only ready Cloud results with verified readable, published, password-free local
posts/pages are selectable. Exclude self, duplicates, weak relevance; canonical URLs and titles come from WordPress. Missing/error evidence
stays distinguishable from a healthy empty result. No local search fallback.

## Native editor transaction

Under ADR-006/ADR-014, one explicit author action inserts a single ordinary
`core/group` containing a level-three `core/heading` and `core/list` of at most
eight `core/list-item` links, at the end of the current article. The complete
list is visible and editable. No custom frontend block, render-time recommendation,
REST content patch, hidden save executor, automatic save or publish is added.

Recheck the current editor post identity, editable/unlocked block context,
selected candidates and already-linked destinations at click time. Reject stale
article identity, missing evidence, invalid URLs and invalid selections. Do not
replace existing content. Escape titles and URLs before constructing rich text.

Undo removes only the inserted group and only while its complete block snapshot
is unchanged and the current editor permits removal. If the author edits the
inserted list, preserve those edits and direct them to ordinary editor Undo.
WordPress native save alone persists changes. Open/copy events remain distinct
from insertion or saved adoption; this change adds no invented Cloud action ids
or adoption claims.

## Verification and stop condition

Test REST context/target filtering, safe block construction, deduplication,
selection bounds, changed post identity, editor locks and edited-list Undo.
Verify the actual editor with a disposable draft: insert, unchanged database,
Undo, insert again and native save, then remove the fixture. Record whether
candidates were real Cloud retrieval or deterministic browser fixtures; fixtures
prove editing behavior, not semantic quality.

Stop after the operator can separately add body citations and a reading list,
with focused regression and normal CI. Do not start a new ranking experiment.

## Implementation evidence, 2026-10-28

- PHP behavior tests exercise full-article context, canonical local target
  projection, publication/password/type/read permission, weak/self/duplicate
  exclusion, Cloud failure and no WordPress writes.
- `tests/related-reading-editor-behavior.mjs` covers selection bounds, URL and
  identity rejection, escaped native blocks, unchanged/edited-list Undo and the
  actual candidate checkbox renderer. It is part of `composer test:all`.
- `tests/smoke-related-reading-browser.mjs` passed on isolated WordPress 7.1.3
  using this checkout's activated plugin. Ten checks include real selection,
  locked-root and unpublished-target rejection, insertion, database unchanged
  before save, Undo, duplicate rejection, edited-list preservation, native draft
  save and valid Gutenberg serialization; zero JavaScript page errors.
- Browser retrieval was a deterministic fixture; fresh target REST reads and
  native editing/save were real. `magick-ai.local` returned HTTP 502, so this is
  not original-site acceptance, ranking-quality evidence or a deployment receipt.
- Local raw evidence is ignored under `tests/artifacts/related-reading-*`;
  essential results above are preserved in Git. The temporary site/database and
  its draft/target fixtures are removed at closeout.

Reproduce on a disposable local installation with this plugin activated:

```sh
NPCINK_RELATED_READING_SANDBOX=1 WP_PATH=/absolute/temporary-wordpress \
WP_BASE_URL=http://127.0.0.1:8936 WP_CLI_PHP=/absolute/php \
NODE_PATH=/absolute/node_modules composer smoke:related-reading-browser
```

The script creates only named temporary draft/target posts, uses a short-lived
random login helper, and removes both helper and posts in `finally`. Never aim
this fixture smoke at a public production site. Original-site real-content use
remains the operator's normal-use feedback step, without per-article labeling.
