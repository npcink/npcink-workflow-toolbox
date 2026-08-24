# ADR-014: Current-Article Multi-Link Editor Transaction

## Status

Accepted

## Date

2026-08-24

## Context

ADR-006 separates visible changes to the current editor from governed writes
to WordPress objects. Internal-link review now needs a more precise answer for
one interaction: an editor selects several recommendations, reviews their
anchors and source phrases, and applies them to the one article already open in
Gutenberg.

The number of selected suggestions does not by itself determine write
authority. Applying several links to visible editor state can still be one
native editing transaction, while a single hidden backend patch can already be
a governed write. The boundary must instead be based on target scope, evidence,
operator review, mutation location, and persistence.

This decision refines ADR-006. It does not supersede ADR-006 or create a general
Toolbox batch-write exemption.

## Decision

### Eligible current-article transaction

A current-article multi-link editor transaction may use
`write_posture=native_editor_commit` only when every condition below holds:

- the present editor explicitly selects at most eight suggestions;
- every selection targets the one article currently open in Gutenberg;
- the final anchor text, target title, target URL, and matching source phrase
  are visible for review before Apply;
- each target is a confirmed public post or page on the same WordPress site and
  has a valid HTTP or HTTPS URL;
- each anchor is specific and is backed by an exact, unchanged source match in
  the current paragraph;
- Apply reruns the product-owned fail-closed preflight against current editor
  state instead of trusting stale selection-time evidence;
- accepted ranges in the same block are applied from the highest offset to the
  lowest offset so earlier mutations cannot invalidate later positions;
- an invalid item is rejected with a per-item reason while other independently
  valid items may still apply;
- the action captures one request-scoped undo snapshot, and Undo fails closed
  after any subsequent editor change makes that snapshot stale; and
- persistence occurs only if the author later uses native WordPress Update or
  Publish.

The editor must show the final transaction summary before Apply. Selection is
not approval to save, and Apply is not approval to publish.

### Apply-time result contract

The future UI and editor adapter should return
`current_article_multi_link_result.v1` with at least:

```text
schema=current_article_multi_link_result.v1
write_posture=native_editor_commit
direct_wordpress_write=false
persisted=false
selected_count=<integer>
applied_count=<integer>
rejected_count=<integer>
items=<per-item outcome and reason code>
undo_available=<boolean>
```

Per-item outcomes must distinguish applied, stale source match, unsafe anchor,
invalid target, overlap/conflict, unsupported block, and other fail-closed
rejections. Partial success must never be reported as complete success.

`persisted=false` describes the Apply response: content has changed only in
visible editor state. A later native WordPress save is outside this Toolbox
result and remains attributable to the author and WordPress.

### Governed batch boundary

For this decision, processing multiple reviewed suggestions is not a governed
batch merely because one button applies them. It remains one current-article
editor transaction only while all eligibility conditions hold.

Any cross-object, cross-post, backend, hidden, external, background, directly
persisted, durable, queued, or retry-based multi-write operation is a governed
batch and remains `core_proposal_required`. The same applies when the source
text is not visible and unchanged at Apply time.

### Prohibited implementation paths

This decision does not authorize:

- a REST write route, `save_post` handler, autosave hook, direct database write,
  automatic save, or automatic publish;
- cross-post insertion or patching content that is not the current visible
  editor state;
- a queue, retry worker, background continuation, or durable selection,
  approval, or undo store;
- Cloud, Provider, or Toolbox ownership of final WordPress write authority;
- a second local keyword or recommendation algorithm in Toolbox; or
- treating fallback candidates as AI or vector evidence.

Cloud may continue to provide semantic retrieval and candidate evidence.
WordPress and the present editor retain final write authority.

### Data minimization

Default telemetry and evaluation receipts must not transmit anchor text or
source excerpts. They may record bounded reason codes, counts, candidate
source, retrieval status, interaction outcomes, and latency. Test fixtures may
contain synthetic phrases, but artifacts must not retain unnecessary full
article content, Provider raw output, credentials, or personal data.

## Consequences

- Editors can review several internal-link suggestions and apply the valid
  subset efficiently without creating a backend write path.
- Stale or ambiguous anchors fail independently and visibly instead of forcing
  an all-or-nothing mutation or silently changing the wrong phrase.
- Native WordPress Update or Publish remains the only persistence event.
- Core governance remains mandatory for governed batches and all writes beyond
  the one current article's visible editor state.
- Implementation requires editor-state, partial-result, safe-undo, no-write,
  and browser acceptance tests before the UI can ship.

## Alternatives Considered

### Treat every multi-selection Apply as a Core batch

Rejected. It would classify a visible, reversible edit to one current article
by item count rather than by its actual persistence and object boundary.

### Apply links through a backend post-content patch

Rejected. It bypasses visible editor state, can race with unsaved edits, and
would turn Toolbox into a WordPress write owner.

### Require all selected links to succeed atomically

Rejected. One stale phrase should not discard unrelated valid selections, but
partial results must be explicit and independently reviewable.

### Persist undo state for later sessions

Rejected. Durable undo would introduce hidden state and lifecycle complexity.
WordPress revisions remain the durable recovery mechanism after native save.
