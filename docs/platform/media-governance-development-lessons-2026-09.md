# Media Governance Development Lessons (2026-09)

This is a cross-repository engineering record distilled from the ALT research,
Toolbox audit, and media continuation closeout. It records reusable rules, not
chat history.

## ALT And Editorial Safety

- ALT is a suggestion/review/explicit-confirmation workflow; it must never
  silently write WordPress.
- Existing ALT is preserved. Initial apply may write only empty core/image
  occurrences.
- Identify occurrences, not only attachment IDs. Repeated attachments need a
  stable occurrence_id and deduplicated review cards.
- Pagination must cover the complete occurrence set with a stable boundary.
- Decorative metadata must round-trip unchanged, and attachment-global ALT
  must not be changed by contextual content suggestions.
- Addon may project bounded visual-evidence attachment IDs; it must not cache
  visual prose or add a generic endpoint.

## Ownership

| Concern | Owner |
| --- | --- |
| WordPress proposal, approval, and final write | Core/host |
| reusable ability contract and schema | Toolkit |
| local continuation, cursor, lock, retry, recovery | Toolbox |
| signed transport and bounded artifact facade | Addon |
| execution, quota, run, result, provider truth | Cloud |

No repository may become a second owner by copying state for convenience.

## Release And Audit Discipline

Keep these evidence levels separate: source tests, clean exact-SHA CI,
cross-repo matrix, M4 candidate, M4 accepted, merged master, package checksum,
and WordPress/manual acceptance. A passing local test cannot prove production
behavior or observation metrics.

Never package a dirty worktree or cherry-pick an obsolete branch wholesale.
Record source SHA, package SHA-256, assertion counts, warning classification,
runtime versions, and known blockers. Preserve a read-only rollback snapshot
before a migration. Never repair a Cloud queue by direct database deletion.

## Failure Lessons

Manual-confirmation backups must not be auto-cleaned. Pagination is not a
single-run budget; use a stable cursor and bounded per-run work. Public schema
fields require consumer inventory and versioned migration. Same-name ability
registration must fail closed or emit a visible conflict. Large modules enter
freeze/observe after contract, security, permission, and performance gates are
credible.

## Closeout Evidence And Limits

The browser ALT proof covered 13 occurrences, two pages, repeated attachment
context, explicit apply, preservation of existing ALT, decorative metadata
round-trip, native save, and unchanged attachment-global ALT. That proves the
editor contract for that fixture; it does not prove provider economics or
production-wide latency.

The local continuation behavior proof covered stable cursors, cursor commit,
pending Cloud runs, qualified/skipped counts, bounded failure pause, explicit
recovery, and stale-lock recovery. The live M4 probe reached Cloud but was
blocked by the single-run commercial concurrency limit. A queued historical run
was present in the database while the API returned run_not_found. This is a
Cloud operations inconsistency to record and resolve through the Cloud owner;
it is not evidence for deleting or rewriting the database locally.

Sunflower/remote desktop access is a machine-recovery aid, not a formal tunnel
or acceptance layer. A real-media E2E remains incomplete until upload, v3
execution, artifact validation, ACK, continuation, pause, and recovery all
produce one traceable evidence record.
