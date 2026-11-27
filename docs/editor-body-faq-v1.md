# Current-article FAQ preview

Toolbox owns the Gutenberg preview, insertion, and clipboard actions. The author explicitly
requests generation in the FAQ row immediately below Site citation suggestions.
The existing Cloud Addon transport runs one inline text request; no new provider, ability, runtime or
Core write handoff is needed. The sidebar explains the transfer before the click.

`POST /editor/content-support` accepts the JSON fields `intent: "body_faq"`,
`post_id` (positive integer), and `content` (current serialized Gutenberg body).
The existing editor route gate and `edit_post` permission both apply. No other
post data is read for context. Blank, unsupported and oversize payloads fail
before model work. Bounds are 256 KiB serialized HTML, 4,000 Unicode characters
of normalized visible text, and the Addon contract's 32 KiB JSON budget; no
partial body is sent. The inline model request uses `pii`/`no_store`, no retries,
no fallback, no retention and no snapshot or result cache in Toolbox. It sends
only fields accepted by the shared Cloud runtime schema; Addon-only transport
metadata is not forwarded. Only plain body text is supplied as evidence: no
post ID, title, excerpt, site profile, knowledge retrieval, external search,
media or hidden block attributes.

Known provider and HTTP transport timeouts surface a safe FAQ timeout message.
The author can retry manually; the same model budget and no-retry policy apply.
The Cloud execution budget is 30 seconds. An Addon with FAQ transport headroom
uses a 40-second minimum HTTP timeout for the fixed content-support contract
with the `editor_body_faq_` idempotency namespace; the browser waits up to 45
seconds. The margin is local to the Addon and does not change the signed Cloud
payload or the deadline for other content-support requests.

Successful `editor_body_faq.v1` returns `status` (`ready` or `empty`), the local
`post_id`, `items` (0–3 `{question, answer, evidence}`), `suggestion_only` posture,
and `direct_wordpress_write: false`. Invalid provider output is distinct from
an explicit empty result. Evidence must be a contiguous body excerpt after
whitespace normalization; this confirms location, not semantic support. The
model is instructed to skip repetitive questions, facts requiring outside
verification and claims the source does not support, and to preserve applicable
plans, versions, conditions and limits in the copyable question and answer.
Exact normalized repeats of explicit questions in the body or candidate list
are suppressed; matching
words within ordinary prose are not. The author reviews each answer against
its evidence. No rendered or model-supplied HTML is inserted.

Checking a question does not edit the article. An explicit **Add selected FAQ
to the end** click appends only the checked question/answer pairs to the current
article's visible, editable Gutenberg state in one action. It uses a native H2
section heading (or reuses a final FAQ section with recognizable H3 question
and answer paragraph pairs), H3 questions prefixed `Q:`, and
answer paragraphs prefixed `A:`. Candidate text is encoded as text, never as
model-supplied HTML; evidence stays in the preview. The action checks the exact
post/body and root block permissions and can be undone with native editor Undo.
The secondary clipboard action still copies only the checked plain-text pairs.
Both actions expire when the post/body changes. Results are temporary. The
author alone saves or publishes through WordPress (`native_editor_commit`). No
backend write, post metadata, schema, batch generation or specialist unpause.

Verify permissions, body-only payload, empty/error separation, duplicate and
missing evidence rejection, and stale response/copy/insertion blocking in focused tests.
The development smoke uses isolated WordPress/Gutenberg and a controlled
provider. This validates request, editor and clipboard behavior, not Cloud
connectivity, answer quality, or production availability.

## Live development check (2026-11-25)

Local WordPress REST dispatch with Addon 0.4.4 reached the M4 development
preview (`2ea09edb0067`, reported dirty). Before the transport correction,
the extra timeout keys produced Cloud HTTP 422 before a run was created.
After the correction and the instruction to retain source qualifications,
the two synthetic fixtures produced these results:

| Fixture | Local result | Cloud run ID |
| --- | --- | --- |
| CSV import tutorial | `ready`, three source-backed candidates, including the 500-row trial-workspace limit | `run_e0d1b6158df1493793912fe2f69cbd88` |
| Short diary note | `empty`, no candidates | `run_aed6ea30195f401fbc869b4a1c4ad0ce` |

Both used `gpt-6.1-sol`, one provider call each, no fallback and `pii` /
`no_store`. The outbound source matched only the synthetic body, and the local
post fingerprint was unchanged. This is a two-sample result on a development
runtime, not general answer-quality, installed-browser or production acceptance.

## Local integration check (2026-11-27)

On the local `magick-ai.local` credential-check snapshot with FAQ adoption and
Addon timeout headroom, the authenticated Gutenberg smoke used a temporary draft
with a handwritten final FAQ and deterministic candidate responses. It verified
entry placement, the credential guard, selected-only copy and insertion,
two appends under one heading, native Undo, no save request or stored-body
change before Save draft, persistence after reopening, and the local theme's
authenticated draft preview. The controls fit at 1440 px and 390 px. The draft
was never published and was removed after the check. This does not measure
current Cloud response time or model quality.
