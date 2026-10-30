# Article review report v1

Status: bounded local implementation, 2026-10-30. Not a Cloud grammar/risk
delivery, production deployment or independent author acceptance receipt.

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

Formatting remains a separate layout action under
[the formatting contract](editor-content-format-v1.md). It preserves words,
numbers, symbols, punctuation, links and multimedia. Review only selects an
existing block; it never edits it, saves or blocks publishing.

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

## Subsequent analysis work

The [reference research](article-review-reference-research-2026-10-30.md),
[consumer contract draft](article-review-cloud-contract-draft-v1.md) and
[synthetic evaluation seeds](article-review-evaluation-seeds-v1.json) prepare
that work. They do not register a Cloud contract or turn any deferred check on.

Generic grammar, cross-paragraph logic and body-risk classification need an
explicit Cloud/Addon's analysis contract and evaluated useful positives and
false positives before becoming implemented checks. Preserve the same issue,
scope, location and freshness semantics, active no-store/privacy policy and
manual correction boundary. Medical, educational, news and literary context
must not become automatic publishing blocks. No new editor intent, third pack,
media optimization restoration or OSS integration follows from this contract.

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
