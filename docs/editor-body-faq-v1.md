# Current-article FAQ preview

Toolbox owns the Gutenberg preview and clipboard action. The author explicitly
requests generation under Article check. Existing Cloud Addon content-support
transport runs one inline text request; no new provider, ability, runtime or
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

Only checked question/answer text goes to the clipboard. The UI disables copy
when the current post/body no longer matches the request and checks again on
copy. Results are temporary. The author pastes and saves manually. No automatic
insertion, save, post metadata, schema, batch generation or specialist unpause.

Verify permissions, body-only payload, empty/error separation, duplicate and
missing evidence rejection, and stale response/copy blocking in focused tests.
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
