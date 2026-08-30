# Recommendation Evaluation Loop

Status: development-only pointer and operating guidance.

The executable evaluation workflow lives in the sibling npcink-eval-lab
repository. Toolbox owns the operator actions and minimal behavior events; it
does not own Provider profiles, AI judge execution, gold-set truth, or ranking
promotion.

## Current Default

Start with a small useful feature and collect real denominators:

- use 3 to 10 mixed developer cases for wiring, privacy, error routing, and
  report-contract checks;
- use GPT as the first generator and Grok as the second reviewer;
- rotate GPT/Grok directions only when comparison is useful;
- keep DeepSeek available only as an explicit historical or compatibility
  profile, never as an active default;
- do not add a third or fourth default model without repeated unresolved
  GPT/Grok disagreement.

Provider-backed evaluation must be explicitly requested. Dry runs and
deterministic fixtures remain the normal inner loop.

## Quality Families

Related Articles and Internal Links must stay separate:

- Related Articles judge topical relevance, unique targets, non-self results,
  coverage, and diversity.
- Internal Links additionally require an exact source match, a natural anchor,
  duplicate-link protection, visible editor application, undo, and native
  WordPress save evidence.

AI cross-review is silver evidence. Any disagreement, tie, invalid response,
Provider error, or boundary failure goes to human review. AI agreement cannot
become human gold or authorize WordPress writes.

## Minimal Telemetry

Use a random recommendation_session to correlate bounded actions without
sending article text, title, URL, WordPress ID, prompt, or evidence refs.

- Both families: impression, open, and copy.
- Internal Links only: apply, native save, saved edit, and undo.
- Related Articles currently has no explicit Ignore action. Do not infer
  rejection from closing the panel, refreshing, or leaving the page.
- Mark fewer than 20 impression sessions as insufficient sample.

The action rate denominator is impression sessions. Open, copy, apply, and save
are behavior signals, not automatic relevance labels.

## Staged Evaluation

1. Validate deterministic fixtures and GPT/Grok blind-review wiring on 3 to 10
   cases.
2. Ship the minimal actions and observe whether people use them.
3. If use exists, sample representative success, failure, disagreement, and
   empty-result cases for independent human review.
4. Grow toward 30 independently reviewed query articles and freeze a stable
   gold set only when enough real cases accumulate.
5. Add models, features, or rerank tuning only when evidence identifies a
   repeated problem.

Thirty independently reviewed articles are a later gold-set milestone, not an
MVP prerequisite. Synthetic fixtures or AI agreement alone cannot prove
production ranking improvement.

## Boundary

Eval Lab produces ignored local reports and never writes WordPress. Cloud owns
runtime ranking and read-only quality evidence. Toolbox owns the visible
operator actions. WordPress remains final review and persistence truth.

Do not add raw-content telemetry, user profiles, a local queue, another vector
database, automatic WordPress writes, or a model-routing control plane for this
evaluation loop.
