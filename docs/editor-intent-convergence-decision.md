# Editor Intent Convergence Decision

Status: accepted and implemented 2026-10-07. The ten retirements below shipped
with the prose-quality fold into `publish_preflight`; option 2 of the record.
Restoring any retired intent requires a new boundary decision naming its
operator surface.

Date proposed: 2026-10-07. Accepted: 2026-10-07.

## Problem

`/editor/content-support` currently accepts a 22-intent allowlist plus the
separate `format_content` lane. Only a minority are reachable from the
current editor UI. The rest survive as "compatible route/result-rendering
paths" from stages before the default-button policy settled. Each one
costs code surface in the 6.5k-line editor service, render paths in the
10.6k-line editor bundle, static-contract needles, boundary-doc wording,
and translation catalog entries — while the plugin is pre-user-growth
(0.4.0 just published) and the pre-release cleanup precedent
(`/vector-search`, `/knowledge-search`, `/flows/article-brief`,
`/flows/article-assistant`) already established that compatibility routes
with no external callers should be removed outright rather than carried.

## Inventory

Authoritative source: the allowlist in
`includes/Rest_Editor_Content_Support.php` (`editor_content_support()`),
cross-checked against `intent: '...'` call sites in
`assets/editor-content-support.js` and its part files.

| Intent | Current caller | Product role | Recommendation |
| --- | --- | --- | --- |
| `source_adaptation_review` | Draft-from-source modal | Default writing-pack flow | Keep |
| `publish_preflight` | Default sidebar button | Default | Keep |
| `category_suggestions` | Default sidebar button | Default (0.4.0 review set) | Keep |
| `tag_suggestions` | Default sidebar button | Default (0.4.0 review set) | Keep |
| `internal_links` | Default sidebar button | Default | Keep |
| `image_candidates` | Default sidebar button + modal | Default | Keep |
| `image_alt_suggestions` | Default sidebar button | Default | Keep |
| `progressive_recommendations` | Automatic local prefetch | Default (hidden on success) | Keep |
| `article_narration` | Hidden default menu entries | Callable, hidden | Keep (already hidden by policy) |
| `article_audio_summary` | Hidden default menu entries | Callable, hidden | Keep (already hidden by policy) |
| `polish_notes` | Selected-block toolbar | Default paragraph review path | Keep |
| `summary_terms_optimization` | JS diagnostic/full-context path | Merged metadata surface | Keep |
| `writing_support` | None (route only) | Legacy generic writing intent | **Retire** |
| `title_suggestions` | None (route only) | Superseded by merged metadata flow | **Retire** |
| `summary_suggestions` | None (route only) | Superseded by merged metadata flow | **Retire** |
| `article_outline` | None (route only) | Generic-AI-plugin overlap | **Retire** |
| `article_checkup` | None (route only) | Local diagnostic, no UI entry | **Retire** (fold any kept checks into preflight first) |
| `discoverability` | None (route only) | Superseded by merged metadata flow | **Retire** |
| `comment_reply_suggestion` | None (route only) | Toolkit projection with no surface | **Retire** |
| `taxonomy_tags` | None (route only) | Superseded by split category/tag buttons | **Retire** |
| `zhihu_research` | None (editor route) | Internal hot-topic pool uses provider paths, not this route | **Retire from the editor allowlist** |
| `zhihu_hot_topics` | None (editor route) | Same | **Retire from the editor allowlist** |

`format_content` lives on its own bounded lane
(`includes/Editor_Content_Format.php`) and is out of scope here.

## Options Considered

1. **Status quo.** Zero migration cost, but every future session keeps
   paying context and needle tax for dead paths, and the i18n label
   cleanup stays blocked by unreachable strings.
2. **Retire the ten route-only intents now (recommended).** Matches the
   accepted pre-release precedent: no external callers exist to keep
   compatible. Removal shrinks the editor service, the bundle render
   paths, tests, boundary wording, and the translation surface in one
   stroke.
3. **Converge instead of retire.** Fold `article_checkup`-style checks
   into `publish_preflight` and the rest into the merged metadata flow
   first, then retire. Higher value per intent but multiplies the change
   into a product project; only worth it for checkup signal that the
   preflight panel actually lacks.

Recommendation: option 2, with one carve-out — before retiring
`article_checkup`, run one pass to confirm `pre_publish_review.v1` already
covers the sentence-density/fact-gap/tone signals operators valued; if a
signal is missing, fold that signal into preflight in the same change
rather than losing it.

## Removal Mechanics (per accepted precedent)

1. Drop the ten intents from the allowlist in
   `Rest_Editor_Content_Support::editor_content_support()` and delete
   their query/context/result-shaping branches.
2. Delete their result renderers from `assets/editor-content-support.js`
   (this is also the natural moment for the next JS cluster split).
3. Update `tests/run.php` needles, the README/boundary/architecture
   intent lists, and the fixed-button contract table in the same change.
4. Retire the related msgids under the file-by-file Translation Source
   Language Policy; no bulk catalog regeneration.
5. Gate: `composer test:all` plus the editor JS/browser smokes that cover
   the surviving intents.

Estimated reduction: roughly 10 route intents, their PHP branches and JS
renderers, on the order of several hundred lines each side, plus needles
and doc paragraphs — and one whole class of "is this path still alive?"
questions gone from future sessions.

## Explicitly Not In Scope

- No new intents, no new buttons, no behavior change for kept intents.
- No change to the admin `/ai/content-support` route or its intents.
- No change to narration/audio visibility policy.
