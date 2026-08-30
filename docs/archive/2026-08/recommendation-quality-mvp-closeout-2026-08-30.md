# Recommendation Quality MVP Closeout - 2026-08-30

Status: historical stage closeout.

This record summarizes the recommendation-quality MVP across Cloud, Toolbox,
and Eval Lab. Current contracts remain in the active recommendation standard;
this document is evidence and development history, not runtime or quality
authority.

## Delivered Evidence

### Cloud recommendation ranking

Npcink AI Cloud PR #875 merged as
b8640927ce9fa0a53385552969b15852cc7250b1. It preserved separate
internal_links and related_content quality families and fixed four reviewed P2
ranking defects:

1. Related Content could underfill unique documents when several chunks from
   one document occupied the retrieval window.
2. Comment documents with empty metadata could override the source post
   taxonomy used for enrichment.
3. Two-character CJK terms were excluded from lexical evidence.
4. ASCII substring matching allowed a token such as ai to match email.

Cloud keeps semantic retrieval, controlled reranking, runtime evidence, and
read-only feedback aggregation. Feedback rollups deduplicate by site/session,
keep recommendation families separate, and mark fewer than 20 impression
sessions as insufficient sample.

### Separate image prompt work

Npcink AI Cloud PR #876 merged as
00860609a400df2564d465ddb8368600f01c4c28. It translated reviewed Chinese
image prompts before image generation and stopped image execution on
translation failure. This work was deliberately kept out of the recommendation
ranking PR because it belongs to image-generation experience, runtime retry,
and idempotency behavior.

Npcink AI Cloud PR #877 merged as
611d9d339125e622de543b42749971ccd44ef944. It isolated M4 candidate guard
contracts and did not change recommendation ranking or user telemetry.

### Toolbox actions and telemetry

Npcink Workflow Toolbox PR #120 merged as
4aadcb7694bbc987c0a6ccd0fad4fd0f50ba0fab. It added explicit Open article and
Copy link actions for Related Content and emits related_content_open and
related_content_copy with a random recommendation_session.

The events do not send article title, URL, WordPress ID, content, or evidence
references. Current Related Content has no explicit Ignore action, so closing,
refreshing, or leaving the panel must not be interpreted as rejection.

Toolbox telemetry required no M4 runtime validation. Its focused local gates
and editor smoke passed without changing the sampled WordPress post.

### Eval Lab

Npcink Eval Lab PR #58 merged as
c7f1160e60b871d44a35b951459e8ab8dffb72aa. It added separate Internal Links
and Related Content strategy comparisons, bounded blind GPT/Grok cross-review,
human-review routing, and regression contracts.

The active default uses GPT and Grok only. DeepSeek remains an explicit
historical or compatibility path. Cross-judge reports omit candidate payloads,
retain only bounded judgments and routing evidence, and keep candidate_ready
false.

Local validation used deterministic fixtures and dry runs. No Provider calls
were made.

## Final Ownership

| Owner | Responsibility |
| --- | --- |
| Cloud | Runtime retrieval, ranking, evidence, usage-safe quality aggregation |
| Toolbox | Visible operator actions and minimized action telemetry |
| Eval Lab | Development fixtures, strategy comparisons, GPT/Grok silver review |
| WordPress | Final human review, visible editor state, native persistence |

None of these changes adds a second registry, queue, scheduler, vector
database, prompt/model control plane, or automatic WordPress write mechanism.

## Development Lessons

### Separate conflict domains early

Recommendation ranking and image prompt translation touched overlapping
runtime files but represented different products, tests, and release risks.
Moving them into independent branches and PRs made the review boundary clear
and prevented one feature from blocking the other.

### Reimplement against current master

The original recommendation branch was behind origin/master, and upstream had
introduced a different feedback aggregation shape. Replaying the stale
feedback commit would have created conflicts and duplicated ownership.
Retaining the ranking behavior while adapting tests to current master was
safer than replaying the old commit unchanged.

### Fix data shape before adding models

The four P2 defects came from document uniqueness, metadata precedence, token
length, and token-boundary behavior. Another model would not have corrected
those deterministic faults. Data-shape and lexical regression tests therefore
precede any expansion of the judge pool.

### AI agreement is silver evidence

GPT/Grok agreement helps triage a small developer batch but cannot establish
human gold or production uplift. Disagreement, ties, invalid output, Provider
errors, and boundary failures all require human review.

### A real denominator comes first

Click counts without impression sessions cannot distinguish unseen candidates
from seen-but-unused candidates. Minimal telemetry therefore begins with an
impression denominator and a random recommendation_session.

### Ambiguous absence is not negative feedback

Closing a panel, refreshing, or leaving WordPress has many possible causes.
Related Content records explicit Open and Copy actions only; it does not infer
Ignore. Small explicit actions produce cleaner evidence than a large feedback
form or implicit behavioral guess.

### Privacy belongs in the event shape

Correlation needs a random session identifier, not article content or stable
user profiling. Titles, URLs, WordPress IDs, prompts, evidence refs, and raw
candidate lists remain outside default telemetry and cross-judge reports.

### Run broad gates once per revision

Narrow syntax, contract, and focused behavior tests should run first. The
combined repository gate runs after the candidate is coherent or after a base
revision changes. A successful sub-gate remains valid when an unrelated later
gate fails; rerun only the seam that answers a new risk question.

### Acceptance states are different

Local tests, a pushed branch, an open PR, a merged PR, M4 candidate evidence,
production state, real-user behavior, and human quality acceptance are
separate facts. Reports must name the achieved state instead of collapsing
them into one claim that the feature is done or good.

## Next Evidence Cycle

1. Keep the current small action surface and observe real impression, open,
   copy, apply, save/edit, and undo signals by family.
2. Treat fewer than 20 impression sessions as insufficient sample.
3. When real use exists, manually review a few representative success,
   failure, disagreement, and empty-result cases.
4. Grow toward 30 independently reviewed query articles gradually; do not
   block the MVP or manufacture traffic to reach that number.
5. Add another model, feature, or rerank change only when repeated evidence
   identifies a specific unresolved problem.

The preferred outcome is not a large evaluation platform. It is a small
recommendation feature whose next investment is earned by real use.
