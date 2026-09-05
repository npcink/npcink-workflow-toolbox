# Media Recognition Continuation Standard v1

## Scope

This standard defines local continuation only. It does not define Cloud
execution, entitlement, provider status, final WordPress writes, or approval.

## State And Transitions

The local record is a projection and lease, not a run database:

    pending -> running -> waiting_for_cloud -> completed
                         \\-> paused
    running -------------> paused

next_cursor is the last committed id_asc boundary. A batch must be idempotent
at that boundary and must not advance it when upload, result validation, or
artifact verification fails. run_id is an opaque Cloud reference; absence
means no Cloud run has been accepted.

## Batch And Recovery

Use a bounded batch size and a bounded cron duration. Acquire a lease before
reading, renew it only while making progress, and release it in all exit
paths. Retry only retryable transport/Cloud outcomes with bounded exponential
backoff. After the failure limit, persist pause_reason and expose recovery
through the existing media governance page.

## Contract And Evidence

The result fixture is media_derivative_result.v3 and must cover qualified,
skipped, transform_facts, and the auto-safe decision envelope. Behavior tests
cover cursor commit, pending runs, count merging, pause, recovery, and stale
locks. Live evidence must separately record upload, Cloud execution, artifact
validation, ACK, continuation, and recovery.
