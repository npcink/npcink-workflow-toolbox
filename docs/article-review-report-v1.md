# Article review report v1

Status: bounded local report plus Cloud language/logic/adult-context hints are
merged as of 2026-11-01 (Toolbox #359, Addon #339, Cloud #1335; Addon #340
handles timeout receipt margin). See the [current acceptance](article-review-context-acceptance-2026-11-01.md)
and [strict extension contract](article-review-context-v1.md). Older language-only
acceptances remain historical compatibility evidence, not production proof.

## Surface and preservation

Reuse `POST /npcink-toolbox/v1/editor/content-support` with `publish_preflight`
and its existing `sections.local_article_checkup` / `article_checkup.v1` shape.
The existing scoped capability, per-post authorization and no-write boundary
remain in force. Do not restore the retired `article_checkup` intent.

The local section adds `report_contract=article_review_report.v1`. Its items
retain `article_checkup_issue.v1`: id, type, severity, location, evidence,
issue, edit_direction, evidence_refs and `operator_review_only_no_insert`.
Repeated Chinese punctuation is a possible issue, never an automatic verdict.
All wording, symbol and punctuation corrections belong to the author.
Neither review nor formatting returns an executable correction action.

The additive `sections.article_language_review` section uses the same
`publish_preflight` route. It accepts only the fixed Cloud result contract,
rechecks the snapshot hash and UTF-16 evidence locally, and exposes quote,
reason, review direction and optional read-only locations. The current collector
requests language, logic and content_risk explicitly; old snapshots without checks
stay language-only with other categories not_checked. Logic requires verified
scope and paired evidence; content_risk is limited to adult sexual context hints.
Cloud or evidence failure leaves local checks usable and discloses category state.

The UI labels this area “Cloud 文章审阅（只读）”. It has no Apply/Replace
control. A dedicated editor subscription increments a monotonic change generation;
location stays disabled after an edit, including edit/Undo with identical final
text, changed block ids, a different post, and edits while the request is in flight.

Formatting remains a separate layout action under
[the formatting contract](editor-content-format-v1.md). It preserves words,
numbers, symbols, punctuation, links and multimedia. Review only selects an
existing block; it never edits it, saves or blocks publishing.

## Cloud language snapshot

Only explicit `publish_preflight` sends `article_review_snapshot`; other intents
keep their existing context. This separate snapshot preserves original whitespace,
line breaks, symbols and Unicode. Legacy normalized `content_blocks` cannot stand
in for it. Missing/invalid snapshots produce an unavailable Cloud section without
a model call. The snapshot is not added to `post_context` or flow caches.

The collector supports title/excerpt and paragraph, heading, list-item and verse
text, recursing known group/column/list/quote containers. Unsupported custom,
code and media blocks are excluded. It submits at most 80 blocks, 1,600 UTF-16
units each and 30,000 total, without cutting surrogate pairs. The UI shows checked,
excluded, omitted and truncated block counts plus partial/invalid/limit feedback.
An explicit pre-call notice explains that bounded text will be sent to Cloud.

Toolbox and Addon compute the same compact JSON identity as Cloud, preserving
Unicode, slashes and U+2028/U+2029. Addon places `contract_version` only in the
runtime envelope, never inside Cloud `input`. Each explicit call gets a fresh
Addon idempotency key; there is no background retry or indefinite text-hash replay.
The consumer accepts the strict requested-category result shape, preserves the
language-only baseline and verifies every evidence range. One invalid evidence invalidates its whole finding. Quotes are
rendered directly as escaped text with whitespace preserved, never through the
legacy normalized item renderer. Repeated quotes remain readable without location.

## Coverage

`coverage.scope=bounded_local_heuristics` describes the actual local work:

| Field | Meaning |
| --- | --- |
| input_text_characters | Plain text received before the context limit |
| context_text_characters | Sanitized text available to local checks |
| context_truncated | Whether the 30,000-character context limit was exceeded |
| paragraphs_in_context / paragraphs_checked | Available paragraph segments / first at most 24 checked |
| sentences_in_context / semantic_sentences_checked | Available sentence segments / first at most 80 checked by local semantic rules |
| issues_found / issues_shown | Findings before / after the 12-item display cap |
| partial | A context, paragraph, sentence or display limit was reached |
| not_checked | Comprehensive grammar, general cross-paragraph logic, body-risk classification and factual verification |

Paragraphs/sentences are local text segments, not a claim of Gutenberg block
coverage or full language understanding. Counts after context truncation cover
only that prefix. A no-findings notice counts as zero findings and does not
prove an article is correct or safe. Existing summaries keep their shape.

## Location and freshness

Each shown item adds `source_location`. A single occurrence of its evidence
within exactly one of the supplied bounded text blocks yields
`status=matched`, `client_id` and `excerpt`. Otherwise only
`status=unavailable` is returned; the original text/location remain visible.
Client ids are navigation hints supplied by the editor, never authorization.

The editor verifies the live block and unique excerpt again before selecting
it. It does not insert or replace text. The report's title, excerpt, post id and
body snapshot stay in component memory only. A later change marks the report
out of date and disables its navigation; rerunning preflight refreshes it.
Results arriving after an edit are also shown as stale. Reloading loses this
local snapshot; there is no durable review or approval store.

## Analysis scope and remaining work

The [extension contract](article-review-context-v1.md) is current authority for
logic kinds, distinct quotes/scope, adult-context taxonomy and per-category
completion/refusal/partial states. Read-only hints cannot certify semantics,
external facts, safety or permission to publish. Local `not_checked` coverage
above describes only local heuristics; Cloud category status is separate.

The [24-case developer screen](article-review-context-acceptance-2026-11-01.md)
includes useful positives and normal controls but is not independent gold or a
general accuracy estimate. One news control timed out and remains unvalidated.
Medical, educational, news and literary context never creates an automatic
publishing block. No new editor intent, third pack, media optimization restoration
or OSS integration follows from this contract.

## Verification and rollback

The [2026-10-30 acceptance record](article-format-preservation-acceptance-2026-10-30.md)
and its body-free receipt record the browser checks and twelve real articles,
including two safely rejected candidates. They do not certify production.

Run editor-content-support and formatting behavior checks, the actual WordPress
tokenizer smoke, editor browser preservation/Undo and report navigation/stale
checks, then `composer test:all`. Browser receipts must name source revision,
fixture/source ids and cleanup, and distinguish real signed Cloud formatting
from local/fixture review evidence. Revert this slice to remove additive report
fields and guards; no article or schema migration is required.

## 2026-11-01 logic/context-risk extension

[Compatible extension](article-review-context-v1.md) adds requested category
statuses, strict logic scope and context-sensitive adult-content hints to the
same preflight section. Language-only input and all manual-edit boundaries remain.
[Runtime/model/browser acceptance](article-review-context-acceptance-2026-11-01.md) is recorded separately from source implementation.
