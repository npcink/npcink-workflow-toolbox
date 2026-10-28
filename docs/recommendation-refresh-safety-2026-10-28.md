# Recommendation index refresh safety — 2026-10-28

This closes the refresh-safety slice after the separate 1,999-document corpus
recovery. It does not change ranking, measure semantic-quality improvement,
implement Typecho/Astro, or prove production/human acceptance.

## Problem and decision

Cloud refresh deleted selected indexes before validating/embedding replacements
and enforcing account capacity. Missing input, quota rejection or a worker
interruption could therefore erase usable old knowledge. Empty collection could
also mean either a real privacy withdrawal or incomplete delivery.

Refresh now replaces only delivered, prepared sources. Omitted/invalid/skipped
sources retain their old rows. SQL upsert retains the document root and replaces
only its source chunks. External vector upsert happens before exact obsolete-tail
cleanup; failed upsert never starts deletion. Explicit delete, rebuild and
completed full-manifest stale pruning retain their separate semantics. Rebuild
remains destructive and SQL/vector writes are not a distributed transaction.

Addon now re-reads sources and signs explicit `withdrawn_post_ids` and
`withdrawn_comment_ids` for genuine removal. Public collection gaps fail before
transport. Comments retain bounded identity/revision beside parents in the
existing buffer, send only approved public sanitized text, and preserve changes
observed during HTTP through the existing fingerprint comparison. A comment
withdrawal cannot delete a same-number post/media. Cloud rejects malformed lists
and delivered/withdrawn conflicts, including withdrawn-parent/child delivery,
before mutation.

Migration for this change is **Addon first, then Cloud**. Other omission-only
producers must migrate withdrawal to explicit delete/markers. Old public updates
remain usable but omission no longer removes indexes. There is no runtime
version hard gate or new WordPress write/queue/registry owner.

Canonical contracts:

- Cloud `docs/site-knowledge-runtime-contract-v1.md`, refresh safety section.
- Cloud `docs/compatibility-matrix.md`, matching candidate/release row.
- Addon `docs/site-knowledge-full-index-delivery.md`, explicit withdrawal section.

## Verification and delivery

- Cloud: 267 focused API/domain regressions passed; changed-file Ruff/mypy and
  release-policy gates passed. The 23 new safety regressions also passed in the
  M4 container. Baseline tests reproduced the former destructive behavior.
- Addon: full contracts, PHPStan, WordPress.org rules, Composer validation and
  POT freshness passed. A newly added private reflection invocation was fixed
  for PHP 8.0 after CI caught its missing `setAccessible(true)`.
- Local Qwen review is advisory. Complete receipts bind exact committed diffs;
  each finding was triaged, with factual counter-evidence or an explicit
  retained limitation. No cloud AI review was awaited.
- Clean M4 candidate: `f641f429714aedd1058b2ff976d4cdbe08d82fca`.
- Real `article_refresh` run `run_9d6ed7059d03484086caee804943dedd` succeeded:
  1 document / 1 chunk indexed, 0 deletions/failures/quota skips.
- Existing save-handler buffering scheduled a named Site Knowledge Cron event;
  executing that event alone produced successful run
  `run_d229e02e1d9c4394a49fe8f1543b3809`, again 1 document / 1 chunk and 0 deletions.
  This proves the scoped producer/schedule/Cron callback/runtime chain, not
  unattended global WP-Cron recovery.
- Coverage remained 1,966 public posts + 33 pages = 1,999 and 2,217 public
  post/page chunks; other source rows/chunks were unchanged. WordPress's
  2,340-row post/page fingerprint stayed
  `aca7e5f335903f6cf956992015ed25e3dfb5d7e0f0e1af4d12880a2e48cad8db`.
- Unrelated index comparison excludes only the refreshed source 21764:
  2,131 document rows digest `894de30799ec73e7459db5594d7b815c` and 2,363 chunk
  rows digest `8600db7542725d523aaa61b3e8e85892`, identical before/after.

- [Cloud PR #1304](https://github.com/npcink/npcink-ai-cloud/pull/1304) merged
  as `d32e95cec73167ba69ee1470616e5380e218a23f`; [Addon PR #335](https://github.com/npcink/npcink-cloud-addon/pull/335)
  merged as `fc9bdc880b7991c591b81b6d6bab13dea31c2f12`. Required PR CI passed.
- M4 clean-master promotion #1304 is accepted at `d32e95ce`; signed status,
  HTTP health and post-promotion read-only corpus/digest/source checks passed.
  The scoped cross-repository matrix passed Addon contracts and exact-SHA Cloud
  GitHub checks at reviewed head `f641f429`; its Git tree equals the merged
  revision. This is distinct from the accepted-master M4 evidence above.

Delivery states and exact merge/runtime identities are in the accompanying
[receipt](recommendation-refresh-safety-evidence-2026-10-28.json). Raw receipts,
logs and operational helpers are retained outside tracked source under
`tests/artifacts/refresh-safety-closeout-2026-10-28` in the primary Toolbox
checkout; they must not be treated as portable fixtures or secret storage.

## Retained limits and next work

1. The temporary local `000-npcink-local-validation-cron-guard.php` still disables
   global WP-Cron. Read-only inventory found overdue cleanup hooks for media
   backups, trash/auto-drafts and governance history, besides Site Knowledge.
   The scoped test ran only the named knowledge hook. Retiring the broad guard
   still needs a bounded plan for those unrelated overdue cleanup effects;
   do not run all overdue Cron merely to test recommendation delivery.
2. Existing reconciliation cursor position was preserved. A successful bounded
   incremental test does not prove a complete unattended reconciliation cycle.
3. Addon buffer option updates are not an atomic concurrent-request queue.
   Overflow (>500 parents or >200 comment identities) and retry exhaustion are
   surfaced through existing diagnostics. No lossless delivery claim is made.
4. External vector partial upserts/prune failures or crashes between stores
   remain observable failed-run risks. Actual-site evidence uses `postgres_json`;
   fake-vector tests prove ordering/type isolation, not live Zilliz operation.
5. Toolbox status request and response still truncate at 80 versus intended
   1,000 IDs. Fix both directions with contract-specific bounds and preserve
   incomplete-receipt guards; this is independent of corpus-loss prevention.
6. Then freeze comparable source/candidate identities and use Eval Lab with AI
   judgments to compare relevance. Human effort remains actual product use and
   final feedback, not per-article labels. No semantic improvement is claimed
   from restored coverage or safety gates alone.

## Development lessons

Check account and site quotas independently; site `quota=ok` cannot exclude an
account-level plan limit. Prepare before replacement, and never infer deletion
from absent content. Keep withdrawal evidence explicit, signed and typed where
needed. Test failure paths with progress callbacks that commit, because a
single outer rollback cannot protect cleanup committed by progress updates.
Separate deterministic tests, M4 candidate, exact-SHA CI, merged master,
accepted runtime and human adoption. A queued receipt is not completion:
correlate the Cloud `run_id` and inspect its terminal metrics.
