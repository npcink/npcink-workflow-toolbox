# Flagged Media Review Set

Status: accepted contract; the site-helper intent, review set, and Image
Handling surface are implemented. Cloud-side safety status in the media
projection remains pending Cloud scheduling.

## Purpose

The flagged media review set turns Cloud visual-safety signals into a bounded
operator artifact. Toolbox samples bounded recent media-library metadata (no
pixels), asks the Cloud hosted AI runtime which sampled attachments carry a
flagged content-safety status in the Cloud media projection, and renders the
result as a review-only list inside Image Handling. The operator handles the
actual media decisions through native WordPress or, later, the governed
deletion path.

This is not a media writer. Toolbox does not delete, trash, detach, edit, or
replace flagged media from this artifact, and it does not claim to view image
pixels locally. Deletion is explicitly out of scope for this stage; the
deletion policy branches below are the input for the future Toolkit/Core
governed path, not an implemented behavior.

## Data Boundary

- The request sends only bounded media metadata already visible in WordPress
  (attachment id, title, filename, MIME type, bounded ALT/caption text), the
  same class of fields used by the media ALT review set. No image bytes are
  uploaded for this intent; no new local vision model exists.
- The request rides the existing `pii` no-store payload classification used by
  `media_alt_suggestions` and `comment_moderation_suggestions`.
- The expected Cloud answer is a per-attachment content-safety status read from
  the existing Cloud media projection. Cloud may answer `unknown` when the
  projection has no visual evidence for an attachment; `unknown` is a valid
  terminal answer and must not be guessed.
- Bounds: at most 50 recent attachments per request; no pagination loops, no
  cron, no background scan, no re-index trigger.

## Deletion Policy Branches

These branches define the review-time choice recorded for a confirmed flagged
attachment and are the specification input for the future Toolkit deletion
ability and Core policy. Toolbox itself implements neither branch.

- Branch A — confirmed illegal content: no backup, no local copy, and no
  Cloud artifact retention of the material. Direct delete plus an audit record
  that describes the deletion without retaining the content. Reporting or
  referral to authorities remains a manual operator responsibility outside
  Toolbox.
- Branch B — general non-compliant content that is legal (for example adult
  content that violates site policy): the standard governed path with Toolkit
  backup, lineage, verification, and restore before any deletion, and deletion
  only through a Core proposal.

The branch is chosen by the reviewing operator. Toolbox records the choice
only as review input; policy enforcement, audit truth, and restore ownership
belong to Core and Toolkit.

## Contract

The local response artifact is `flagged_media_review_set.v1`. It is returned
inside the existing `/ai/site-helpers` response when the intent is
`flagged_media_suggestions`.

Required posture:

- `write_posture`: `suggestion_only`;
- `media_unchanged`: `true`;
- `direct_wordpress_write`: `false`;
- `proposal_created`: `false`;
- `execution_created`: `false`.

Required operational fields: `eligibility_summary`, `selected_items[]`,
`blocked_items[]`, `operator_next_action`, `retryable`, `retry_guidance`.

Per selected item:

- `attachment_id`;
- `content_safety`: `flagged` only in this first version (`safe` and `unknown`
  items are not selected into the list; they stay in the eligibility counts);
- `confidence`;
- `reasons[]`;
- `suggested_action` limited to the triage-only family such as
  `review_attachment_manually` and `open_attachment_in_wordpress`. Values that
  imply delete, trash, detach, replace, or any write are not allowed in this
  contract.

Classifications are review hints, never authorization. The admin UI may render
first-action links only to the native attachment detail screen. When the Cloud
runtime is unavailable, the surface reports its `cloud_required` state without
local fallback classification and without fabricated results.

## Fail-Closed Behavior

- Cloud unavailable → `cloud_required` review set with blocked items and
  bounded retry guidance; no local fallback safety assessment.
- Attachments without a projection entry are blocked as `safety_status_unknown`
  with `review_manually`-family guidance, never auto-cleared.
- Toolbox must not create local queues, run tables, cron work, re-index
  triggers, Core proposals, or WordPress writes for this flow.

## Non-Goals

- no media deletion, trashing, detaching, replacement, or metadata writes;
- no local vision model, pixel upload, or image-classification claim;
- no automatic proposal creation;
- no full-library scan, crawler, or scheduled background check;
- no second media registry, safety-truth store, or audit owner.
