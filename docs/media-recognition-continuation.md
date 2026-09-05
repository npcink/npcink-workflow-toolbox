# Media Recognition Continuation

Workflow Toolbox is the sole WordPress-side owner of media-recognition continuation. Cloud remains the authority for execution, provider selection, quota, processing windows, `run_id`, and results. Cloud Addon provides only bounded connector facades.

## Local State

The non-autoloaded `npcink_toolbox_media_recognition_continuation` option records one plan. `scope=full` uses stable `id_asc` traversal. `scope=changed_attachments` stores a sorted, de-duplicated attachment-ID set and starts in `awaiting_confirmation`. The same option also holds the initiating user ID for Cron-time capability revalidation, the next committed cursor, pending counts, current Cloud `run_id`, processed/failed/skipped/qualified counters, retry count, next eligible time, and pause reason. It is not a second workflow queue or Cloud result mirror.

The single-event `npcink_toolbox_continue_media_recognition` hook advances at most one batch while holding a self-healing atomic option lock. A cursor advances only after a local-only page completes or the corresponding Cloud run and `image_context_evidence.v1` result succeed.

The Toolkit media-version Hook and the weekly fingerprint scan only merge attachment IDs into this option. They do not schedule Provider work. An administrator must confirm the changed-attachment set in the existing Image Handling page; processing then rechecks current files and sends at most ten attachment IDs per batch. Changes found while another plan is active become one follow-up `awaiting_confirmation` set instead of a duplicate plan.

## Failure And Recovery

Transient transport failures retain the current `run_id` and retry with bounded backoff. A terminal Cloud run failure clears that `run_id` and retries the same uncommitted page. Three consecutive failures pause the plan.

Healthy processing is quiet. A changed-attachment plan exposes a nonce- and capability-protected confirmation action, and a paused plan exposes the existing resume action, both inside Image Handling. Neither action creates a second plan or control surface.

## Boundaries

- Toolbox owns the local cursor, lock, WP-Cron wakeup, and progress projection.
- Toolbox owns weekly scan scheduling and actual scheduled-event overdue status; `DISABLE_WP_CRON` alone is not treated as evidence that server cron is unavailable.
- Cloud Addon owns bounded upload, image-context evidence, run/result read, and artifact-delivery facades.
- Cloud Addon projects only verified-connection and Site Knowledge readiness to the scan.
- Cloud owns execution, provider/runtime state, quota, windows, and results.
- Core review, Adapter execution, and final WordPress write ownership are unchanged.
- `media_derivative_result.v3` remains the image-optimization artifact contract. Background recognition uses the distinct `image_context_evidence.v1` result contract.

Toolbox does not inspect Addon options, runtime-client objects, credential/provider truth, or legacy Addon continuation state. Release coordination must remove the obsolete Addon plan, lock, callback, and Cron ownership on the Addon side.
