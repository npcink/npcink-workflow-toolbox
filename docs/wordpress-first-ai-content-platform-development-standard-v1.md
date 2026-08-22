# WordPress-First AI Content Platform Development Standard v1

Status: active development standard.

Purpose: capture the implementation lessons from the editor internal-link,
related-article, and broader AI content-support work so future changes remain
focused, evidence-based, and compatible with a later multi-platform strategy.

This standard applies to WordPress editor AI capabilities first. Ghost,
Typecho, Astro, and other publishing platforms are future adapter work, not
current implementation scope.

## 1. Product Direction

Npcink is building a WordPress-first AI content platform. The first product
loop is:

```text
WordPress editor context
  -> Toolbox request and review surface
  -> Cloud semantic retrieval or hosted AI runtime
  -> bounded recommendation artifact
  -> human editor review
  -> native WordPress save or a separately governed handoff
```

The platform should improve the operator's editorial decision without making
the AI runtime a second WordPress control plane.

The current implementation priority is:

1. make WordPress editor workflows reliable;
2. stabilize reusable content and recommendation contracts;
3. collect real operator evidence before expanding to other platforms.

## 2. Ownership Boundary

| Concern | Owner |
| --- | --- |
| WordPress editor state and native save | WordPress and local Toolbox/UI |
| Editor buttons, review states, copy/open/ignore actions | Toolbox |
| Semantic site-content retrieval, embeddings, vector storage, rerank, index lifecycle | Cloud Site Knowledge runtime |
| Hosted model/provider execution and runtime detail | Cloud |
| Ability and workflow definitions | Toolkit/local Npcink stack |
| Approval, preflight, audit, and governed proposal truth | WordPress local stack/Core governance |
| Final WordPress mutation and publication | WordPress native editor or approved local/Core path |

Cloud may return runtime evidence and recommendations. It must not become the
owner of WordPress content writes, local prompts, local workflow truth, final
approval, or the editor's unsaved state.

## 3. Semantic Retrieval Rule

For related content and internal-link relevance, Cloud vector evidence is the
primary ranking signal:

- `related_content` is used for related-article recommendations;
- `internal_links` is used for internal-link evidence and target selection;
- `site-knowledge.zh.v1` remains Cloud-owned profile/runtime detail;
- Toolbox consumes evidence and does not configure embeddings, vector stores,
  collection names, rerank models, or indexing jobs.

Local rules are safety and quality boundaries only. They may exclude the
current post, remove malformed URLs, cap candidate count, prevent duplicate
targets, require an exact editor phrase before applying a link, reject stale
editor state, and keep actions review-only.

Local keyword matching must not silently replace Cloud semantic ranking when
Cloud evidence is available. A fallback must be explicit in the response and
must not be presented as vector relevance.

## 4. Candidate Design

Domain artifacts remain authoritative and should not be flattened into a
generic list:

| Capability | Authoritative artifact | Allowed first-version action |
| --- | --- | --- |
| Internal links | `internal_link_candidates.v1` | copy/open, or human-confirmed visible-editor placement |
| Related articles | `related_article_candidates.v1` | open/copy/ignore only |
| Title/summary/taxonomy | existing domain artifacts plus `recommendation_candidate.v1` | review and explicit local/Core path |
| Image candidates | `image_candidate.v1` | review and the existing governed adoption path |

Every Toolbox projection must declare `write_posture=suggestion_only` and
`direct_wordpress_write=false`. Related articles do not require a matching
anchor phrase and must not reuse the internal-link application contract.

## 5. Canonical Contracts

### 5.1 `content_context.v1`

The editor input projection is bounded and platform-labelled:

```text
contract_version, platform=wordpress, site_id, post_id, post_type,
post_status, canonical_url, language, context_scope, title, excerpt,
content_text, selected_text, selected_block_text, category_ids, tag_ids,
content_fingerprint, write_owner=wordpress_local,
direct_wordpress_write=false
```

It describes article input, not the site-level `npcink_toolbox_content_context`
settings option. It must not contain provider keys, credentials, quota state,
request logs, or write authorization.

### 5.2 `recommendation_set.v1`

The recommendation set is a bounded envelope around domain artifacts. It
contains the recommendation id, generated time, content context and
fingerprint, intent, artifact counts, candidate references, retrieval sources,
definition-only proposal targets, and no-write governance metadata.

`editor_recommendation_set.v1` remains for compatibility until consumers have
migrated. The wrapper references candidates; it does not replace the richer
domain artifact or become approval, audit, feedback, queue, or write truth.

## 6. Phased Development Method

### Phase A: WordPress baseline

- Keep the editor workflow visible and understandable.
- Use Cloud vector evidence for related content and internal links.
- Keep copy/open/ignore actions independent from insertion actions.
- Exclude the current post and protect against duplicate/stale links.
- Do not add a frontend related-articles block automatically.
- Verify the actual Gutenberg interaction, not only response JSON.

Exit condition: a human can run the feature, understand the evidence source,
and decide what to apply without hidden writes.

### Phase B: Content context

- Normalize current editor fields into `content_context.v1`.
- Preserve existing request fields as compatibility inputs.
- Bound text lengths and sanitize at the WordPress REST boundary.
- Compute one content fingerprint for replay and stale-state checks.
- Keep `platform=wordpress` explicit before other adapters exist.

Exit condition: focused editor results carry the same bounded context
projection and no secret or write-authority fields.

### Phase C: Recommendation set

- Add the canonical `recommendation_set.v1` name additively.
- Keep `editor_recommendation_set.v1` during migration.
- Count and reference every domain candidate, including related articles.
- Preserve each candidate's domain-specific source contract.
- Keep proposal targets definition-only and user-triggered.

Exit condition: a consumer can discover context, source, candidate kind,
quality evidence, and safe next action without knowing every UI section.

### Phase D: Evidence and refinement

- Add static contract assertions for every new field and intent.
- Add behavior tests for current-post exclusion, source status, no-write flags,
  and candidate action boundaries.
- Run the focused browser/editor smoke when a real WordPress runtime exists.
- Record local, CI, candidate runtime, merged, and accepted evidence separately.
- Use operator feedback to improve Cloud retrieval and quality gates, not to
  move UI rules into the Cloud runtime.

## 7. Future Platform Expansion Gate

Do not start a new platform adapter merely because common fields exist. Start
one only after WordPress has stable contracts, real workflow evidence,
documented authentication and credential custody, a clear local write path,
and focused contract, behavior, and security tests.

Future adapter responsibilities will include reading content, displaying
recommendations, authenticating to the platform, and performing a
user-confirmed local write. Cloud remains shared semantic/runtime
infrastructure.

Platform-specific delivery modes are expected to differ: Ghost can use its
Admin API and editor surface; Typecho can use a plugin and admin editor;
Astro is better served by Markdown/MDX CLI, pull-request, or build-time review
than by a universal online editor sidebar.

## 8. Verification Checklist

- [ ] current post is excluded;
- [ ] Cloud source and fallback status are explicit;
- [ ] candidate count and text/URL fields are bounded and sanitized;
- [ ] domain artifact remains authoritative;
- [ ] `content_context.v1` is present where claimed;
- [ ] `recommendation_set.v1` and compatibility fields are present;
- [ ] `direct_wordpress_write=false` is present at response, section, and handoff boundaries;
- [ ] no backend `post_content` patch or automatic frontend block is added;
- [ ] static contract tests and focused editor behavior tests pass;
- [ ] real WordPress smoke is run when the runtime is available;
- [ ] the final report names the highest evidence state actually reached.

## 9. Common Failure Modes

Avoid short keyword matches as the primary AI relevance signal, local vector
databases or embedding settings in Toolbox, treating related articles as
internal-link placement candidates, masking Cloud failures as semantic
recommendations, replacing compatibility contracts before migration, creating
one AI implementation per CMS, adding automatic frontend blocks before
editorial evidence, or claiming runtime evidence from static tests alone.

## 10. Related Documents

- [Recommendation Candidate Contract](recommendation-candidate-contract.md)
- [Site Knowledge Vector Operations Contract](site-knowledge-vector-operations-contract.md)
- [Editor Recommendation Logic](editor-recommendation-logic.md)
- [Editor Content Support Recommendation Review](editor-content-support-recommendation-review.md)
- [Cross-Repo Boundary Matrix](cross-repo-boundary-matrix.md)
- [ADR-013: WordPress-First Content And Recommendation Contracts](decisions/ADR-013-wordpress-first-content-and-recommendation-contracts.md)

