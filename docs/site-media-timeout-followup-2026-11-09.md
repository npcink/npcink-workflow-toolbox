# Site Media Search Deadline Follow-up — 2026-11-09

Status: bounded editor maintenance. Browser deadline repaired; real candidate
adoption remains unaccepted because returned media identities do not exist in
the local WordPress snapshot. No index rebuild, model change or deployment.

## Diagnosis

The [real-role acceptance](editor-real-role-acceptance-2026-11-09.md) recorded
a site-media query reaching the browser's 8-second timeout. A subsequent
read-only WordPress provider probe used the same query, `Npcink Local Consent
Featured Image Smoke`, without changing any runtime response:

| Layer | Observation |
| --- | --- |
| Browser, before repair | Shared external-image fast deadline: 8 seconds |
| Toolbox → Addon | Site Knowledge search and optional media-evidence status each carry the existing 20-second budget, with zero retries |
| Cloud search | `run_11438123e32c4a5487db60ca4182c117`: succeeded, 15.056 seconds between Cloud start/finish; HTTP 200 after 16.607 seconds |
| Cloud evidence status | `run_67b83e1cc64e486eb6f9246da0ffeeba`: succeeded, HTTP 200 after 0.319 seconds |
| Complete provider call | 17.019 seconds; ready, zero locally valid candidates |

Authenticated Cloud run reads confirm both runs succeeded. The search receipt
identifies the existing `npcink-qwen3-vl-embedding:8b-q4_k_m-mmproj-q8` index
model; reranking was disabled. No model capacity or recommendation-quality
campaign was run. The measured successful search exceeds the old browser
deadline; it is sufficient to establish the transport-budget mismatch, not a
claim that every search has this latency or a diagnosis of why this query was
slow.

## Focused Repair

`postImageCandidatesFastFirst()` now chooses a 45-second browser deadline only
for `provider: site_media`: the two existing 20-second server budgets plus a
5-second local/transport margin. External image sources retain their 8-second
fast deadline. Addon/Cloud budgets, provider selection, retries, permissions,
candidate identity checks and write boundaries are unchanged.

The behavior regression executes the real request helper with a controlled
clock and fetch promise. It checks the semantic-media budget, external fast
timeout, expiry and cancellation. The pre-repair source fails with
`8000 !== 45000`; the repaired source passes. Existing automatic/manual request
lifecycle and stale-response tests remain in the same gate.

## Live Browser Evidence And Remaining Limit

An actual installed-browser query, `WordPress AI 插件系列配图`, returned HTTP 200
with a ready result after 1.325 seconds. One explicit browser search produced:

- Search `run_1bc13e6306da403f94ddd7dd4382216c`: HTTP 200 after 0.631 seconds.
- Status `run_5e7135fad5664b84aae6d3c9487e606d`: HTTP 200 after 0.268 seconds.
- Nine Cloud media identities, all absent from local WordPress; zero candidates
  after existing local revalidation. The original-query probe also returned
  nine identities, all absent locally.

The faster second query does not prove the slower path is fixed by caching or
that runtime latency is uniformly below eight seconds. Deadline regression and
the successful slow Cloud run establish why a separate browser budget is
needed. Actual installed asset hashes are checked against this checkout.

The **local/Cloud media identity mismatch remains open**, with Addon/Cloud
projection ownership and local WordPress attachment truth. Its historical cause
is unconfirmed. No missing attachment was recreated, no identity was remapped,
no index was refreshed and no credential/site binding was changed. Missing
objects continue to be rejected. No candidate was available for real adoption,
so image selection → editor insertion → native save/readback remains explicitly
unaccepted for this live query. Earlier ALT and taxonomy save acceptance does
not fill that gap.

## Reproduce And Cleanup

`tests/smoke-site-media-live-browser.mjs` is optional, outside the default
gate. Set `WP_PATH`, `WP_CLI_PHP`, `WP_CLI_BIN`, `WP_DB_SOCKET` and `NODE_PATH`;
optionally `CHROME_BIN` and `MEDIA_SEARCH_QUERY`. It requires a `.local` site
and this installed checkout, creates one temporary draft/session, and opens
the real paragraph image picker. It makes one explicit live query, records
safe Cloud run/timing fields through a request-scoped observer, and checks Core
fingerprints and installed assets. It never substitutes search/provider results.
Synthetic feedback and unrelated writes/prefetch are suppressed.

If local candidates exist, the smoke selects existing media, inserts it only
into visible editor state, normally saves the draft and reloads, while checking
unchanged attachment data. Otherwise it records `partial_no_local_candidate`
and leaves the draft unchanged; process completion alone is not full acceptance.
Temporary drafts, revisions, the single session, observer and timing file are
removed on success/failure. Receipts/screenshots stay in ignored
`output/playwright/`. These are local development results, not production,
WordPress.org release, natural adoption or general search-quality evidence.

Close this maintenance slice after the bounded repair and recorded limitation.
Resolving the media projection mismatch requires a separate operator decision
on the correct site snapshot and Addon/Cloud-owned index repair scope; this
record schedules neither index work nor further live query repetition.
