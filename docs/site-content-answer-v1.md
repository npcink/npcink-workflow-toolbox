# Answers from public site content v1

Status: development candidate, 2026-10-30. Not a WordPress.org release or
production acceptance receipt.

## Entry and ownership

Smart Ask → Public site content reuses the existing single-question panel.
`POST /wp-json/npcink-toolbox/v1/site-knowledge/answers` executes the registered
`npcink-toolbox/answer-site-content` Ability. Both retain `manage_options`;
the REST route also uses the existing WordPress authentication/nonce boundary.
It does not broaden editor scopes or add an independent ability registry.

This option is **not a deidentified insight pack**. The four existing insight
packs retain their no-body policy. Toolbox sends only the question and fixed
scene instructions through Addon's registered-task facade. Cloud retrieves
passages from the existing consented public post/page index. The Addon owns
transport; Cloud owns embeddings, retrieval, provider routing and index lifecycle.
No new local content collector, chat history, queue, retry loop or WordPress
write path is introduced. Private/password-protected content is not admitted by
this delivery contract. The question is sent to the configured Cloud runtime;
operators must not put credentials or private material in it.

## Input and output

Input is exactly `{"question":"…"}`, with a nonempty question of at most 2,000
characters and 8,000 UTF-8 bytes after sanitation. No site, model, source, prompt
or evidence override is accepted. The Ability registration declares
`task=site_answer`, `task_family=analysis`,
`context_requirements=[site_knowledge_evidence]`, and
`constraints=[json_object,source_grounded]`. Addon projects the registration and
enables `site_factual_evidence`; Toolbox never passes a replacement registry.
The scene requests at most 2,400 output tokens. Cloud's current managed provider
policy remains authoritative.

Success returns `answer`, 1–8 `citations` (`id`, `quote`), `run_id` and
`grounded_evidence` (`grounded_answer_evidence.v1`). Cloud checks citation identity
and exact source quotes (8–1,200 characters). The evidence manifest comes from
the current indexed source, rather than model-supplied URLs. Toolbox validates
schema, contract, verification status and reference membership; its bounded
projection returns at most six source identities, titles, URLs and completeness
values, without the whole retrieved passages.

The answer and quotes render as text. Only same-origin HTTP(S) source URLs are
clickable, with `noopener noreferrer`. A partial/unknown delivery warning stays
visible. “Delivered in full” describes the cited document delivery, not all-site
coverage, retrieval recall or semantic correctness. Exact quotes do not prove
that every generated claim follows from them; that requires answer evaluation.

Missing evidence is a named 422 failure; unavailable/failed retrieval gives a
specific recovery message with 503 status. Missing/malformed verification or citation mismatch
is 502. Original transport/provider status is preserved. Cloud `run_id` survives
both mapped evidence errors and other runtime failures for folded support detail.
There is no local fabricated answer. Success uses `Cache-Control: no-store`;
Toolbox stores no question/answer history. Cloud runtime follows the existing
result-only retention contract (up to 86,400 seconds), not a new no-store policy.

## Verification and rollback

Deterministic gates: `composer test:all`, `composer lint:standards`,
`composer analyse:php`, `composer validate --no-check-publish`,
`composer check:wporg`, `git diff --check`. Behavior tests cover permission/input
rejection before transport, task projection, partial status, invalid verification,
unknown citations, schema refusal and error correlation. Schema validation is a
controlled dependency in isolated tests; actual WordPress validation is checked
on the Local site.

Local candidate mount is the isolated `site-content-answer` worktree. Rollback
only changes that plugin symlink back to
`/Users/muze/gitee/npcink-workflow-toolbox`; neither checkout nor the existing
Cloud index is reset. Publication, CI, M4 acceptance, actual answer quality and
human adoption must each have separate evidence. No production deployment is
part of this candidate.

## Content delivery receipt, 2026-10-30

On Local `magick-ai.local`, Addon source
`f0c6bd276a418ace73b28c716cb2692d1c4183de` delivered public post 280942:
12,528 sanitized UTF-8 bytes, 11,134 characters, 15 chunks, no truncation.
WordPress source fingerprints before/after refresh were identical.
Refresh run: `run_f8ff81ba129f451cbc1231684f1c6a67`.
Search run: `run_6a8c7d15c1ba40049165720072459f9f`; chunks 12 and 14 include
the Nginx/error_page tail beyond the former excerpt limit.

Disposable development fixture 282978 was created, changed, and trashed through
normal delivery buffering. Cloud readback showed 1 document/4 chunks, then old
fact absent/new fact present, then 0 documents/0 chunks. Other site rows stayed
at 2,131 documents and 2,362 chunks with identical row digests across all three
checks. The fixture remains recoverable in WordPress Trash. These are controlled
developer checks, not customer adoption or semantic answer-quality proof.

The first actual answer attempt (`run_bedcbe726dcc404881e29fe3be3f2b1b`) exposed
an internal Cloud search request missing `write_posture=suggestion_only`. It
failed before the answering model. This is retained as a real discovered gap;
passing mock-only evidence tests did not establish the complete runtime path.

## Development acceptance, 2026-10-30

Cloud #1322 fixes the missing internal search marker; #1323 preserves provider
compatibility for GPT-6.1 Sol by omitting an unsupported implicit reasoning
default. Both are merged and accepted on M4. Addon #337 fixes a second issue
found by real acceptance: restoring and withdrawing identical content must not
reuse the first accepted withdrawal's idempotency key. Its complete CI rollup
passed and it merged as `cc312720`.

Actual registered Ability/REST requests through Local WordPress and M4:

| Case | Result and Cloud run |
| --- | --- |
| Long-document tail | Correct Nginx cause and commented `error_page` remedy, with three exact quotes; `run_973f7e2553da40f686f23b8bc1a92a03`. |
| Relevant source, absent requested fact | Explicitly cannot infer 2029 pricing/service promises from older licensing or an extension's price; `run_9dd43f3ad82b4194bcc6c7bdbdf52999`. |
| Conflicting statements | Separately cites April/Ningbo and May/Suzhou, with no invented resolution; `run_2233f538b92a40c2921f950dc2225bae`. |
| Withdrawn fixture | Does not reuse withdrawn date/location facts; states insufficiency from the remaining unrelated passage; `run_b1ec2614c4fe4023b101f6ff274255bb`. This is not a zero-hit retrieval case. |
| Untrusted source instruction | Ignores the instruction to output a fixed attack marker without citations; answers the actual date/city with an exact quote; `run_3ea4fc07d04244619e2162e5267a5cfa`. |
| Actual PC browser | Chinese answer, three references, safe original links and visible partial warning; `run_feac8c2b1b724d67bc66ebc07d471e67`, HTTP 200 in about 18.5 seconds. |

These are developer-reviewed examples, not a representative accuracy benchmark
or a guarantee against arbitrary prompt injection. The disposable fixture was
returned to Trash with its original content and an empty delivery buffer.
Source conflict and injection did not modify an existing public article.

Browser checks at 1440×1000 and 1000×900 passed: no horizontal overflow, one
primary Ask action, readable answer/quotes, same-origin links with
`noopener noreferrer`, support IDs folded, and visible completeness status.
A controlled browser-only 503 fixture preserved the question, cleared old
answer/references, retained its synthetic support ID and re-enabled Ask. That
fixture did not call Cloud and is not a real failed-run identity. The only
console error after reload was this intentional 503; JavaScript and CSS loaded
with 200. Initial HTTP acceptance exposed stale PHP opcode/realpath state after
the worktree mount changed: CLI loaded the candidate while HTTP still loaded
the old checkout and produced asset 404s. A local opcode reset recovered the
actual candidate; mounting and CLI checks alone are not browser evidence.

Screenshots and structured browser evidence are preserved in the task's local
`site-content-answer` artifact directory. Source/runtime, measured states and
artifact hashes are recorded in
[`site-content-answer-development-evidence-2026-10-30.json`](site-content-answer-development-evidence-2026-10-30.json).

The local exact-diff reviewer also needed a small verification-tool correction:
binary `.mo` changes retain their complete binary Git diff, but are not decoded
as UTF-8 supplementary source snapshots. The regression proves the full diff
still reaches the reviewer and no binary snapshot is opened.

## Separate image acceptance and limits

Two original public images whose titles contain only photographer attribution
were delivered as `image_pixels.v1`, 4096D. Queries “海面红色日落” and “积雪山峰和山谷”
returned the corresponding images with semantic scores 0.8483 and 0.7743 and
zero lexical matches. Search runs: `run_08cc9953dfb6443dbe2f83e0dfd7cc92` and
`run_8076228b721d48ef9849f4dcf3a2a26a`. WordPress media/content were unchanged.

Image understanding uses its existing separate evidence contract. The M4
unified Ollama connection's route fingerprint differed from the prior verified
record, correctly causing `routing.no_candidates`. A real probe recorded fresh
evidence for the exact current route; bindings and provider config were kept.
Ollama's actual model context was 4096 although Cloud's catalog declared a
larger window: a real image required 4337 input tokens. The development model's
`num_ctx` was set to 16384 after saving its original Modelfile on M4. To roll
back that development-only change, recreate the same model tag from the saved
`/tmp/content-vision-model-before-20261030.Modelfile`; remove only the new
`content-vision-20261030` capability record if invalidating this acceptance.

Single-image understanding passed for sunset/ocean on the original image
(`run_6ca6acf6125f4749b14d1b7a76824972`) and snow-covered mountains/valley/houses
on its existing 768px preview (`run_2a1f869850c94b9abc0873f7b27ca02a`). Visual
inspection confirmed those scene descriptions. Human visual confirmation
remains required; no ALT or caption was written. Two-image requests and the
full-size mountain request exceeded the 30-second interactive limit on this
local 2B model; they are failed acceptance cases, not batch-success evidence.
No timeout, fallback policy or production model selection was relaxed.

All 69 legacy metadata-only image IDs were checked against the actual local
attachment paths and recorded variants: zero originals and zero usable variants
exist on this restored development site. Keep them metadata-only. Recovery from
an external backup remains unresolved; never label matching vector dimensions
as pixel coverage. The factual-answer option itself still accepts only public
post/page evidence.

## Remaining acceptance

- Natural operator/customer use and representative answer quality remain open;
  the supplied examples and technical gates cannot establish adoption.
- The development 2B vision route is not accepted for two-image interactive
  batches; production provider readiness requires its own acceptance.
- Recover the 69 original images from a separately available backup before
  upgrading their vectors. No such backup was established in this session.
- Source publication/CI and the central matrix are recorded at closeout;
  WordPress.org packaging/release and production deployment are separate.
