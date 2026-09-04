# Media Recognition Continuation

Workflow Toolbox is the sole WordPress-side owner of media-recognition continuation. Cloud remains the authority for execution, provider selection, quota, processing windows, `run_id`, and results. Cloud Addon provides only bounded connector facades.

## Local State

The non-autoloaded `npcink_toolbox_media_recognition_continuation` option records one plan with stable `id_asc` traversal, the next committed `after_id` cursor, a pending cursor and counts, the current Cloud `run_id`, state, processed/failed/skipped/qualified counters, retry count, next eligible time, and pause reason. It is not a workflow queue or Cloud run mirror.

The single-event `npcink_toolbox_continue_media_recognition` hook advances at most one batch while holding a self-healing atomic option lock. A cursor advances only after a local-only page completes or the corresponding Cloud run and `image_context_evidence.v1` result succeed.

## Failure And Recovery

Transient transport failures retain the current `run_id` and retry with bounded backoff. A terminal Cloud run failure clears that `run_id` and retries the same uncommitted page. Three consecutive failures pause the plan.

Healthy processing is quiet. Only a paused plan exposes a nonce- and capability-protected resume action inside the existing media governance page. Resume does not create a second plan or control surface.

## Boundaries

- Toolbox owns the local cursor, lock, WP-Cron wakeup, and progress projection.
- Cloud Addon owns bounded upload, image-context evidence, run/result read, and artifact-delivery facades.
- Cloud owns execution, provider/runtime state, quota, windows, and results.
- Core review, Adapter execution, and final WordPress write ownership are unchanged.
- `media_derivative_result.v3` remains the image-optimization artifact contract. Background recognition uses the distinct `image_context_evidence.v1` result contract.

Toolbox does not inspect Addon options, runtime-client objects, credential/provider truth, or legacy Addon continuation state. Release coordination must remove the obsolete Addon plan, lock, callback, and Cron ownership on the Addon side.
