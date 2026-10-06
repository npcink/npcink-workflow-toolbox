# Internal-Link Review Set

Status: accepted; the `/ai/site-helpers` intent, review set contract, and
Site Check section follow the same implementation pattern as the comment
moderation, flagged media, and taxonomy/tag review sets.

## Purpose

The internal-link review set turns site-level internal-linking review
into a bounded operator artifact. Toolbox samples published posts that
have few or no internal links to other published content, sends one
bounded suggestion request to the Cloud hosted AI runtime using existing
Cloud Site Knowledge vector evidence, and renders the returned per-post
link candidates as a review-only list. The operator handles actual link
insertion through the native WordPress editor or the existing editor
sidebar's governed Apply flow.

This is not an internal-link workflow owner. Toolbox does not insert
links, update post content, or write WordPress data from this artifact.
Link insertion remains the editor sidebar's explicit Apply flow (ADR-014
current-article multi-link transaction) or manual editor work.

## Data Boundary

Published post metadata is public site data. This contract follows the
approved-would-be-public rule.

Allowed per-post fields:

- post title, truncated to 200 characters;
- post excerpt or first 300 characters of content, whichever is shorter;
- existing internal-link target URLs found in the post content (slug
  list, at most 20, parsed from `<a href>` tags pointing to this site);
- source post id and source post URL.

Never sent for this intent:

- full post content beyond the bounded excerpt;
- draft, private, or scheduled post data;
- media attachment data or binary content.

Bounds:

- at most 50 published posts per request, newest first;
- only posts of type `post` with `post_status=publish`;
- the first version samples posts that have **fewer than 3 internal
  links** (sparse linking);
- no pagination loops, no cron, no background scan.

Data classification: `pii` no-store, consistent with the other review
sets.

## Contract

The local response artifact is `internal_link_review_set.v1`. It is
returned inside the existing `/ai/site-helpers` response when the intent
is `internal_link_suggestions`.

Required posture:

- `write_posture`: `suggestion_only`;
- `post_content_unchanged`: `true`;
- `direct_wordpress_write`: `false`;
- `proposal_created`: `false`.

Per selected item:

- `post_id`, `post_title`, `existing_link_count`;
- `suggested_links[]`: per-link `target_post_id`, `target_title`,
  `target_url`, `suggested_anchor_text`, `confidence`;
- `suggested_action`: `open_in_wordpress_editor` or `review_manually`.

Suggestions require Cloud Site Knowledge vector evidence for the
current-article Apply flow; the site-level review set is review-only and
does not expose a batch Apply.

## Non-Goals

- no link insertion, post content update, or SEO meta write;
- no batch Apply from this surface (the editor sidebar owns the explicit
  per-article Apply);
- no local queue, cron, or background link scanning;
- no replacement for the editor sidebar's current-article internal-link
  candidates.
