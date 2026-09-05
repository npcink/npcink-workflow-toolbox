# ADR-016: Toolbox Owns Local Media Recognition Continuation

## Status

Accepted for the 0.2.0 cross-repository closeout.

## Decision

Toolbox is the sole local continuation owner for media recognition. Its
bounded state contains plan_id, stable_order=id_asc, next_cursor,
Cloud run_id, state, processed, failed, skipped, qualified,
retry_count, next_eligible_at, and pause_reason.

Toolbox may use a bounded WP-Cron, lock, retry/backoff, and recovery action
inside the existing media governance surface. It calls Addon facades only.
It never reads Addon internals, uses the concrete runtime client, or infers
Cloud provider state. Cloud owns execution, quota, run windows, and results.

## Why

Stable cursor ownership prevents page-number drift and duplicate work while
keeping the local workflow resumable. Keeping Cloud truth remote avoids a
second runtime control plane. A recovery action belongs beside the existing
media governance workflow, not in a new operations console.

## Failure Rules

- Commit the cursor only after the batch result is accepted.
- Treat a pending Cloud run as waiting, not as a failed batch.
- Merge qualified and skipped counts from verified results.
- Pause after the bounded failure limit and require an explicit recovery
  action.
- A stale lock may self-heal only after its lease is proven stale.
- No second writer is allowed during migration.

## Rejected Alternatives

Addon-owned continuation, Toolbox-owned Cloud run state, generic callback
wakeup, and a new runtime/repair console all violate the ownership boundary or
increase the operator surface without improving the workflow.
