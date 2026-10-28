# Site Knowledge status round-trip acceptance — 2026-10-28

Scope: Toolbox provider/status projection. Cloud and Addon source/runtime stay
unchanged. This fixes missing or misleading article-status rows, not retrieval
ranking, index coverage, article content or production release acceptance.

## Cause and contract

The status producer already allowed 1,000 IDs, but facade transport applied the
generic recursive 80-item sanitizer again. Response coverage used that same
sanitizer. Fixing only the request would preserve a receipt count of 1,000 but
silently drop indexed IDs after row 80, incorrectly marking later rows as not
indexed. The existing complete-comparison guard correctly hid rows when only
80 IDs were sent; it could not detect a subsequently truncated response list.

The service now preserves only the status contract's top-level request ID list
and returned indexed-ID list at their existing 1,000-ID bound. Both the exact
ability and version must match. Other arrays retain the generic 80-item limit;
media evidence stays at 20 and raw debug output stays redacted and bounded.
Malformed or oversized indexed-ID lists are omitted so the existing REST guard
suppresses verdicts. Missing lists differ from a valid empty list; a valid
subset still distinguishes indexed from not indexed. No permission or route
scope changes are made. The stable public contract remains v1; its shape and
intended limit are unchanged. The normative rule is in [Architecture](architecture.md#site-knowledge-status-comparison-bounds).

The local comparison query also excludes password-protected posts/pages,
matching the public-content indexing policy. The initial candidate exposed one
such page as not indexed; a local metadata check confirmed it had a password.
It was never a missing public source and its content was not sent to Cloud.

## Reproducible verification

The new round-trip regression fails against baseline `a9d37f7285536277f6bb545a460f256285720879`
at 81 IDs. It passes after the fix for 80, 81, 1,000 and 1,001 inputs (the last
is bounded to 1,000). The real REST row projection preserves all 1,000 indexed
verdicts, rejects missing/non-array/nested/boolean/zero/oversized/mismatched
receipts, and handles valid empty and 500-item subset results. Tests also pin
exact-contract matching, ID normalization, unrelated/nested 80-item bounds and
the unchanged media request limit. `tests/run.php` pins the public bounds and
password-free comparison contract.

On `magick-ai.local`, WP-CLI loaded each Toolbox source tree in an isolated
process with only Toolbox skipped during normal bootstrap, then registered its
actual routes and used `rest_do_request` as an authenticated administrator.
The Addon transport and M4 Cloud were real; no Cloud result stub was installed.
This is a REST integration check, not a browser or end-user acceptance claim.

| Measurement | Baseline | Final candidate |
| --- | ---: | ---: |
| HTTP status | 200 | 200 |
| IDs sent / Cloud comparison receipt | 80 / 80 | 1,000 / 1,000 |
| Indexed IDs preserved in normalized reply | 80 | 1,000 |
| Article rows shown / marked indexed | 0 / 0 | 1,000 / 1,000 |

Baseline run: `run_b892f03f1ede4c9e9e7d22c960a5e448`.
Final candidate run: `run_3b983a584a714ce5b5104243a3bf21c7`.
Cloud runtime was clean `d32e95cec73167ba69ee1470616e5380e218a23f`;
Addon was merged source `fc9bdc880b7991c591b81b6d6bab13dea31c2f12`.

The 2,340-row WordPress source fingerprint stayed
`aca7e5f335903f6cf956992015ed25e3dfb5d7e0f0e1af4d12880a2e48cad8db`.
All 2,132 site index document rows and 2,364 chunk rows retained exact digests
(`2059a087369b5e5178ef8deefc6613a7` and
`7bfc72af80bdcc4aabbe7f45724f7965`). These include historical stale rows and
media; public post/page coverage remains 1,999 documents / 2,217 chunks. No
source write, sync/rebuild, Cron change or M4 deployment occurred. Status calls
can create ordinary Cloud run records and local row-cache transients.

Raw status replies, fixture metadata, probe source, source hashes and database
snapshots remain in the primary checkout's ignored local directory
`tests/artifacts/status-bounds-2026-10-28`, not distributed plugin fixtures.
Its acceptance manifest SHA-256 is
`76f572841312300a981e9994eff2dd2cdbf99c95488077de8fbd1ef154da07f6`.

## Limits and next step

This view covers a bounded recent 1,000-source manifest; it does not imply
that only 1,000 sources exist or verify every source on each status request.
Coverage recovery and automatic delivery have separate acceptance receipts.
The next development slice belongs to Eval Lab: compare recommendation
relevance on the complete, named corpus/runtime without per-article human
annotation. This status fix makes no semantic-quality improvement claim.

Rollback is a Toolbox code revert; never delete source content, clear indexes
or reset delivery state to undo a display/transport fix.
