# Comment Moderation Review Set

Status: accepted; the site-helper intent, pii lane, review set, and Site Check
comment moderation section are implemented. The Cloud-side classification
runtime behavior remains pending Cloud scheduling.

## Purpose

The comment moderation review set turns pending-comment spam triage into a
bounded operator artifact. Toolbox samples the newest pending WordPress
comments, sends one bounded classification request to the Cloud hosted AI
runtime, and renders the returned per-comment classifications as a review-only
list inside Site Check. The operator handles the actual moderation through the
native WordPress comment administration screens.

This is not a comment workflow owner. Toolbox does not approve comments, mark
spam, trash, delete, edit, or publish replies from this artifact. Comment
moderation, status changes, and comment workflow governance remain local
WordPress responsibilities, and any future governed status-change path belongs
to Core proposals and Toolkit abilities, not Toolbox.

The existing editor comment-reply suggestion remains a separate compatibility
route over `npcink-abilities-toolkit/build-comment-mention-reply-suggest` and
is not part of this review set.

## Data Boundary

Pending comments are private site data. This contract is the narrow admission
decision for sending them to Cloud, and it follows one rule: only fields
WordPress would render publicly once the comment is approved may be sent
(approved-would-be-public fields).

Allowed per-comment fields:

- comment content, truncated to 2000 characters;
- author display name;
- author URL;
- bounded parent post title for context, only when the parent post is public;
- source comment id.

Never sent for this intent:

- comment author email;
- IP address;
- user agent;
- cookies, session data, or any other non-public request metadata.

Bounds:

- at most 50 pending (`hold`) comments per request, newest first;
- the first version samples `status=hold` only; spam-folder false-positive
  recheck is a future extension that needs its own bounded contract;
- no pagination loops, no cron, no background scan.

Data classification: the request rides the existing `pii` no-store payload
classification used by `media_alt_suggestions`. Cloud must not retain these
payloads or results beyond the response, and Toolbox stores nothing locally.

Demarcation from the existing comment data flows, which remain unchanged:

- Site Knowledge sync continues to send only approved public comments attached
  to indexed public entries;
- the local Site Check pack and `site_ops_cloud_analysis_request.v1` continue
  to include only aggregate approved-comment signal counts, never comment
  author emails, IP addresses, user agents, or full comment text;
- this intent is a separate narrow surface and does not relax those
  prohibitions.

## Contract

The local response artifact is `comment_moderation_review_set.v1`. It is
returned inside the existing `/ai/site-helpers` response when the intent is
`comment_moderation_suggestions`.

Required posture:

- `write_posture`: `suggestion_only`;
- `comment_status_unchanged`: `true`;
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

- `comment_id`;
- `classification`: `spam`, `legitimate`, or `uncertain`;
- `confidence`;
- `reasons[]`;
- `suggested_action` limited to the triage-only family such as
  `open_in_wordpress_moderation_queue` and `review_manually`. Values that
  imply automatic approval, automatic spam marking, deletion, or any write are
  not allowed in this contract.

Classifications are review hints, never authorization. `uncertain` is a valid
terminal answer and must not be auto-resolved. The admin UI may render
first-action links only to native WordPress moderation screens such as the
moderated queue and the per-comment edit screen; Toolbox renders no bulk
status-change control.

## Fail-Closed Behavior

- When the Cloud runtime is unavailable, the surface reports its
  `cloud_required`/unavailable state without local fallback classification and
  without fabricated results.
- Provider errors return blocked items with reasons and bounded retry
  guidance; the operator next action points to native WordPress moderation.
- Toolbox must not create local queues, run tables, cron work, retries,
  Core proposals, or WordPress writes for this flow.

## Implementation Order

This document is accepted first as the cross-repo contract spec so Cloud can
implement the classification intent in parallel. The Toolbox change that
follows adds the `/ai/site-helpers` intent, the provider intent allowlist
entry, the `pii` data-classification extension, the Site Check comment
moderation section, and the `readme.txt` bounded-data list update together. No
pending comment data leaves the site before that implementation change lands.

## Non-Goals

- no comment status writes, approval, spam marking, trashing, or deletion;
- no reply publishing or automatic moderation;
- no local queue, cron, or background comment scanning;
- no second comment governance, moderation-truth, or audit store;
- no email-, IP-, or user-agent-based heuristics; classification uses only
  approved-would-be-public content and byline fields;
- no Akismet replacement claim; this is a semantic triage aid beside existing
  WordPress moderation tools.
