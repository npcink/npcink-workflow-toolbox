# Content Support Product Readiness

Status: active current-phase acceptance matrix as of 2026-06-10.

This document records how the current Toolbox product surface supports article
work outside the article body. The default product is not autonomous article
writing. It is a set of fixed editor tools that help a human prepare, review,
enrich, and hand off reviewable changes through the existing WordPress
governance boundary.

## Product Rule

Toolbox may recommend, preview, and package reviewed handoffs. Toolbox must not
approve Core proposals, execute final WordPress writes, create a second media
registry, or become a prompt/model control plane.

Human editors own final article acceptance and WordPress persistence. Article
Assistant is retired from the operator-facing and public Ability surface; the
legacy route remains only as a compatibility path for older callers. The
writing-pack flow may return a confirmed, structured plain-text draft preview,
but it cannot insert, save, or publish that preview.

The operator UI projects that contract as one three-step modal: provide source
or brief, confirm the writing direction, then review the draft. The editor
sidebar keeps only the entry and current request-scoped status. Reader output,
related-site evidence, fact/rights risk, and runtime details are secondary
review surfaces rather than default long-form cards.

## Feedback Observation Loop

The editor feedback loop is designed for operators who often use Toolbox like
an email or editorial assistant: they click a useful result, copy or apply the
candidate, then leave the panel. A large always-visible rating panel creates
friction and is unlikely to receive meaningful clicks after the user already
got the answer.

The current implementation therefore keeps content-result observation passive:

- Editor Content Support does not render manual rating or issue-report
  controls;
- image candidate, Site Knowledge, and Nightly Inspection admin results do not
  render manual Cloud-eval rating controls;
- the default Site Knowledge status panel does not render the developer-facing
  Agent feedback quality summary;
- successful result actions send silent `metadata_only`
  `cloud_agent_feedback.v1` events through the existing `/agent-feedback`
  route;
- observed actions include internal-link copy/open, title and excerpt apply,
  image candidate selection, selection-only image return, governed image
  adoption, AI image regeneration, suggested image query clicks, and reruns;
- payloads use existing fixed outcomes and label vocabulary, plus bounded
  run ids, handoff ids, handoff types, local surface names, and evidence refs;
- payloads must not include article body text, prompts, free-form operator
  notes, user email, provider secrets, approval records, SEO values, media
  write payloads, or WordPress content writes.

This keeps the observation loop useful for Cloud eval and quality rollups
without turning Toolbox into a learning store, prompt/router owner, approval
truth, audit truth, or final WordPress write owner.

## Acceptance Matrix

| Product focus | Current implementation | Acceptance state | Boundary |
| --- | --- | --- | --- |
| Writing preparation | Editor Content Support exposes `writing_support` and calls Cloud Site Knowledge through `writing_support_plan`. | Accepted for current phase. It prepares context, angles, gaps, and evidence prompts around the article. | Suggestion-only. It does not generate or insert the article body. |
| Zhihu global search atom | Reusable Cloud web-search calls may pass `managed_source=zhihu_global_search`, which maps to Cloud `source_type=zhihu_global_search` and consumes the `source_evidence.v1` atom when configured. | Accepted as a reusable full-web evidence atom for fact checks, citation discovery, comparisons, FAQ/AEO research, and article background packs. | Suggestion-only. No local Zhihu keys, no provider picker UI, no official-source bypass for publishable claims, no automatic draft generation, and no WordPress write authority. |
| Zhihu hot topics | The WordPress Dashboard exposes `知乎热榜选题` from a local transient or backup snapshot. An administrator can explicitly refresh the fixed Cloud-managed Zhihu hot-list source, which consumes the `topic_candidate.v1` atom when present. | Accepted as a first-version daily topic-pool lane. It helps editors decide what may be worth researching today before running focused research. | Suggestion-only. Routine Dashboard rendering makes zero Cloud calls; refresh requires an explicit capability- and nonce-protected action. No local Zhihu keys, automatic draft generation, publishing, or WordPress write authority. |
| Zhihu capability checks | Standalone Zhihu and Cloud web-search diagnostics belong in Cloud Addon or Cloud service-plane surfaces, not Toolbox. Toolbox may still call the managed lanes from fixed product flows. | Accepted as Cloud-owned diagnostics and Toolbox-owned suggestion flows. | No local Zhihu keys, no local provider routing ownership, no diagnostic console in Toolbox, no automatic draft generation, no publishing, and no WordPress write authority. |
| Zhihu research | Editor Content Support exposes `zhihu_research`, which calls Cloud-managed web search with a fixed Zhihu managed source for the current query and consumes `source_evidence.v1` / `topic_candidate.v1` atoms when present. | Accepted as a first-version pre-writing research lane. It helps editors inspect audience questions, viewpoints, objections, and citation candidates before drafting. | Suggestion-only. No local Zhihu keys, no generic provider routing UI, no default hot-list mixing, no copying/rewrite/publish flow, and no WordPress write authority. |
| Zhihu direct answer atoms | Reusable Cloud web-search calls may pass `managed_source=zhida_simple`, `managed_source=zhida_deep`, or `managed_source=zhida_deepsearch`, which map to Cloud direct-answer source types and consume `grounded_answer.v1` when configured. | Accepted as reusable answer-preview atoms for FAQ/AEO candidates, short answer previews, and research conclusion previews. | Suggestion-only. Not final article text, no insertion, no publishing, no WordPress write authority, and source references still require local review. |
| Selected paragraph review | The selected-block toolbar exposes a local paragraph review entry beside paragraph image suggestions and routes it through `polish_notes` with selected text only. | Accepted as a contextual paragraph-review tool, not a default article-level writing button. | Returns clarity, fact-boundary, tone, and editing-direction notes only. It does not replace block text or generate insert-ready copy. |
| Summary, category, and tag recommendations | Editor Content Support exposes summary/category/tag flows, including `summary_suggestions`, `category_suggestions`, `tag_suggestions`, and `summary_terms_optimization`, and can return reviewed metadata apply handoff artifacts. Direct `category_suggestions` and `tag_suggestions` entries sit immediately below publish preflight. | Accepted for current phase. High-frequency existing-term review stays directly accessible in the editor surface instead of requiring the publish-preflight result; summary generation defaults to a fast brief, uses cached Cloud Site Knowledge vector context only when already available, reports timing, and exposes an advanced full-context rerun. | Suggestions are limited to existing terms. New vocabulary and accepted metadata writes stay Core-governed or future strong-local-confirmation only. |
| Article audio candidates | Editor Content Support retains callable `article_narration` and `article_audio_summary` compatibility flows, but both entries are temporarily hidden from the default menu. Narration sends bounded article text to Cloud audio generation; audio summary first asks hosted text runtime for an `audio_summary_script`, then sends that script to Cloud `audio_generation_request.v1`. | Runtime contract accepted; default operator exposure paused pending later product reassessment. | Suggestion-only audio candidates until adoption. `Use audio` submits a Core-governed adoption plan; Adapter/Toolkit may import the reviewed audio into the local WordPress media library and write playback metadata only after Core approval/preflight/audit. No post-content insertion, no local audio queue, no provider key ownership, and no Toolbox-owned WordPress write authority. |
| Internal-link candidates | Editor Content Support exposes `internal_links` over bounded article context and optional Cloud Site Knowledge related-content evidence, then delegates candidate assembly to `npcink-abilities-toolkit/resolve-internal-link-targets`. | Accepted for current phase. The surface returns compact manual review candidates with copy-link and open-target actions. Third-party plugins can reuse the Toolkit `internal_link_candidates.v1` artifact. | No automatic insertion, no backend post-content patch, and no link graph control plane. The editor owns where reviewed links are placed. |
| Image candidates and media optimization | Editor Content Support exposes `image_candidates`; Toolbox owns image-source UX and Cloud/provider requests, then delegates review artifact projection to `npcink-abilities-toolkit/build-image-candidate-review-artifact`. Toolbox admin owns the fixed `media_optimization_v1` flow through Media Library image actions and Batch Optimize Images, with media derivative preview and Core proposal handoff. | Accepted for current phase. Crop override controls, preview-only Cloud Checks, and Core media proposal proof are implemented. Third-party plugins can reuse the Toolkit `image_candidate_review.v1` artifact before adoption planning. | Image-source candidates remain candidates; media derivative adoption remains one reviewed Core proposal, not direct media writes. |
| Publish preflight and SEO handoff | Editor Content Support exposes `publish_preflight`, returns `pre_publish_review.v1`, and packages `seo_meta_handoff_preview.v1` for `npcink-abilities-toolkit/set-post-seo-meta`. The summary renders two compact readiness rows: editor readiness from existing preflight counts, and Core handoff readiness from the SEO preview. Its primary `View publish preflight` action sits beside the secondary rerun action, while flow ownership and runtime detail stay in the collapsed footer disclosure. | Accepted for current phase. Browser validation created a pending Core SEO proposal from the editor, and Core review now surfaces `field_patch` values before raw JSON. The readiness rows borrow the checklist-style scan pattern without becoming publish enforcement. | Toolbox creates only a pending proposal. Approval, preflight, audit, and execution authorization stay in Core/Adapter/Abilities. The readiness rows are suggestion-only display guidance; they do not block publishing, submit proposals, approve, execute, or write WordPress fields. |
| Operator feedback loop | Editor Content Support sends fixed-label, metadata-only `cloud_agent_feedback.v1` events from successful result actions through the shared `/agent-feedback` route, without rendering manual rating or issue controls. Image candidates, Site Knowledge, and Nightly Inspection also omit manual Cloud-eval controls from operator-facing results. Site-media search reports coarse result outcomes and real adoption actions under a random session id; contextual block ALT reports saved unchanged/edited/cleared outcomes only after WordPress confirms a non-autosave save. | Accepted as a narrow passive observation loop. The backend feedback contract remains available for automated quality signals, while developer-oriented summaries stay outside the default operator surface. Queries and ALT text are never included, samples below 20 remain explicitly insufficient, and attachment ALT is not claimed without an attachment-write receipt. | Feedback does not mutate prompts, routers, profiles, proposals, audit truth, media, SEO fields, posts, or WordPress content. |
| Article body generation | The retired Article Assistant route remains compatibility-only. Editor Content Support may generate `article_draft_preview.v1` only from a structured, operator-confirmed `article_writing_pack.v1`. | Accepted as a narrow review-first preview with either an empty-body-only native editor load or an explicit handoff through the existing article plan to Core, not a generic writer, product workbench, public Ability, or one-click publishing promise. | Hosted generation consumes the confirmed pack and returns plain text. Explicit loading maps sections to visible Gutenberg blocks only when the current body is empty. A `usable` preview may instead create one pending Core proposal whose approved execution creates only a WordPress `draft`. No existing-body replacement, title/excerpt overwrite, automatic save, or automatic publish is allowed; Toolbox never approves or executes, and publication remains human. |

## Verification Evidence

- `composer test:all`
- `composer smoke:editor-review-artifacts`
- `composer smoke:media-derivative-core`
- Browser check: editor publish preflight created one pending Core SEO proposal
  from `seo_meta_handoff_preview.v1`.
- Browser check: Core proposal detail shows `字段变更`, `seo_title`, and
  `seo_description` in review context before the raw proposal payload.
- Editor Content Support renders no explicit rating or issue-report panel and
  sends silent, fixed-label, metadata-only feedback for useful result actions.
- The observation path does not introduce free-form learning, automatic
  strategy changes, or WordPress write authority.

## Next Gate

Stop expanding the editor surface until the six rows above remain stable in
review. The next useful work should be regression hardening: keep smoke coverage
for real editor-to-Core handoffs and only add new buttons when they reuse the
same fixed ability ids, artifact shapes, and Core-governed write paths.
