# Taxonomy And Tag Review Set

Status: accepted; the `/ai/site-helpers` intent, review set contract, and
Image Handling admin section are implemented together.

## Purpose

The taxonomy and tag review set turns site-level term assignment review
into a bounded operator artifact. Toolbox samples recent published posts
with sparse or missing taxonomy assignments, sends one bounded suggestion
request to the Cloud hosted AI runtime, and renders the returned per-post
term suggestions as a review-only list inside Image Handling (following
the Site Check comment moderation and flagged media pattern). The operator
handles actual term assignments through the native WordPress post editor
or existing governed metadata handoff paths.

This is not a taxonomy workflow owner. Toolbox does not assign terms,
create terms, merge terms, update posts, or write WordPress data from
this artifact. Term assignment and taxonomy governance remain local
WordPress responsibilities or Core-governed proposal paths.

The existing editor-sidebar category and tag suggestions remain a
separate current-article surface and are not replaced by this review set.

## Data Boundary

Published post metadata is public site data (post titles, excerpts, and
existing public term assignments are already visible on the site). This
contract follows the approved-would-be-public rule.

Allowed per-post fields:

- post title, truncated to 200 characters;
- post excerpt or first 300 characters of content, whichever is shorter;
- existing assigned category names (slug list, at most 10);
- existing assigned tag names (slug list, at most 20);
- source post id.

Never sent for this intent:

- full post content beyond the bounded excerpt;
- post author, editor metadata, or revision history;
- draft, private, or scheduled post data (published only);
- media URLs or attachment data.

Bounds:

- at most 50 published posts per request, newest first;
- only posts of type `post` with `post_status=publish`;
- the first version samples posts that have **fewer than 1 category or
  fewer than 3 tags** (sparse assignment); a broader sample is a future
  extension;
- no pagination loops, no cron, no background scan.

Data classification: the request rides the existing `pii` no-store
payload classification used by `media_alt_suggestions` and
`comment_moderation_suggestions`. Cloud must not retain these payloads
or results beyond the response, and Toolbox stores nothing locally.

## Contract

The local response artifact is `taxonomy_tag_review_set.v1`. It is
returned inside the existing `/ai/site-helpers` response when the intent
is `taxonomy_tag_suggestions`.

Required posture:

- `write_posture`: `suggestion_only`;
- `term_assignment_unchanged`: `true`;
- `direct_wordpress_write`: `false`;
- `proposal_created`: `false`;
- `execution_created`: `false`.

Required operational fields:

- `eligibility_summary`;
- `selected_items[]`;
- `blocked_items[]`;
- `operator_next_action`;
- `retryable`;
- `retry_guidance`.

Per selected item:

- `post_id`;
- `post_title`;
- `existing_categories[]`;
- `existing_tags[]`;
- `suggested_categories[]`: existing WordPress category slugs only (no
  new term creation);
- `suggested_tags[]`: existing WordPress tag slugs only;
- `confidence`;
- `reasons[]`;
- `suggested_action` limited to `open_in_wordpress_editor` and
  `review_manually`. Values that imply automatic assignment, term
  creation, or any write are not allowed in this contract.

Suggestions are review hints, never authorization. The admin UI may
render first-action links only to the native WordPress post editor;
Toolbox renders no bulk assignment control.

## Fail-Closed Behavior

- When the Cloud runtime is unavailable, the surface reports its
  `cloud_required` state without local fallback suggestions and without
  fabricated results.
- Provider errors return blocked items with reasons and bounded retry
  guidance; the operator next action points to native WordPress term
  management.
- Toolbox must not create local queues, run tables, cron work, retries,
  Core proposals, or WordPress writes for this flow.

## Non-Goals

- no term assignment, term creation, or taxonomy restructure writes;
- no automatic categorization or auto-tagging;
- no local queue, cron, or background post scanning;
- no second taxonomy governance, term-truth, or audit store;
- no replacement for the editor-sidebar current-article suggestions;
- no new term vocabulary suggestions (existing terms only; the vocabulary
  gap review remains the editor sidebar's separate proposal-only surface).
