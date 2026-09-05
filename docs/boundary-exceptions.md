# Boundary Exceptions Registry

Npcink Workflow Toolbox is a fixed-button, review-only operator surface. This
registry records the current exceptions that are intentionally allowed to
exist inside the plugin without changing that product posture.

An entry here is not a precedent for new direct writes, schedulers, queues,
provider control panels, indexing lifecycle controls, or approval stores. New
exceptions require a separate ADR and matching static contracts before any code
path is added.

## Exception 1 - Local Admin Consent Featured Image

Status: accepted narrow proof.

Owner: Toolbox route and editor UI; Core owns audit truth.

Allowed scope:

- one present WordPress administrator action;
- one current post;
- one existing WordPress image attachment;
- set that attachment as the current post featured image;
- record Core-owned `local_admin_consent.requested` and
  `local_admin_consent.completed` audit events;
- include the current `operation-classification-v1` `decision_envelope` in
  Core audit metadata;
- roll back the featured-image change if completion audit fails.

Required static contracts:

- only one `/local-admin-consent/*` REST route is registered;
- the route remains `/local-admin-consent/featured-image`;
- the route requires an image attachment and
  `Operation_Classifier::LOCAL_ADMIN_CONSENT`;
- the route sends `operation_classification` evidence with a
  `decision_envelope` to Core audit;
- completion-audit failure triggers rollback;
- article/media batch handoffs never use Local Admin Consent audit events.

Hard stop:

- no media import;
- no media metadata write;
- no SEO meta write;
- no taxonomy or excerpt write;
- no post creation or publishing;
- no proposal approval or execution;
- no batch action;
- no new Local Admin Consent route without a new ADR.

Primary decision record:

- [ADR-003: Local Admin Consent Requires A Separate Write Boundary](decisions/ADR-003-local-admin-consent-boundary.md)

ADR-003's no-import stop remains true for this legacy route. ADR-017 retired
the former ADR-010 import path and does not widen
`/local-admin-consent/featured-image`.

## Exception 2 - Single-Article Strong Local Image Adoption

Status: retired by ADR-017.

The local import and featured-image adoption route, class, and editor actions
have been removed. Image candidates remain reviewable suggestions; import,
metadata, and optional featured-image writes now go through Adapter, Core, and
Toolkit. No compatibility handler remains.

Historical record:

- [ADR-010: Allow Strong Local Confirmation For Single-Article Image Adoption](decisions/ADR-010-single-article-strong-local-image-adoption.md)
- [ADR-017: Retire Single-Image Local Write Exceptions](decisions/ADR-017-retire-single-image-local-write-exceptions.md)

## Exception 3 - Single-Image Strong Local Media Replacement

Status: retired by ADR-017.

The local replacement, backup-listing, and restore routes and workbench have
been removed. Single-image replacement and restore now use Adapter, Core, and
Toolkit. ADR-015's exact-manifest administrator batch remains a separate active
exception and must not be treated as compatibility for this retired path.

Historical records:

- [Single-Image Media Workbench Standard v1](single-image-media-workbench-standard-v1.md)
- [ADR-011: Single-Image Local Media Replacement](decisions/ADR-011-single-image-local-media-replacement.md)
- [ADR-017: Retire Single-Image Local Write Exceptions](decisions/ADR-017-retire-single-image-local-write-exceptions.md)

## Exception 4 - Local Fallback WP-Cron Preview

Status: accepted bounded fallback preview.

Owner: `npcink-local-automation-runtime` module bundled in Toolbox until a
separate extraction ADR accepts a stable cross-plugin API and graceful Toolbox
degradation.

Allowed scope:

- disabled-by-default WP-Cron hook;
- local public-content dry-run evidence collection;
- one latest scheduled-review preview option;
- operator-visible preview inside Site Check Scheduled Review;
- JSON download of the dry-run preview for review/debugging.

Required static contracts:

- the clean disabled state does not register a schedule;
- the class keeps `latest_preview_option_only` safety metadata;
- the route surface does not add admin-post or Ajax execution endpoints beyond
  read-only JSON download;
- scheduled-review preview stays separate from Cloud run recovery;
- Cloud runtime runs, status, result, and retry ownership remain in Cloud Addon.

Hard stop:

- no Cloud call from the Basic WP-Cron dry-run;
- no Core proposal creation;
- no WordPress content write;
- no Action Scheduler path;
- no custom runtime tables;
- no lease store;
- no retry processor;
- no dead-letter processor;
- no local Pro scheduler truth.

Primary decision records:

- [ADR-004: Bundle Local Automation Runtime As An Isolated Module](decisions/ADR-004-bundle-local-automation-runtime-as-isolated-module.md)
- [ADR-005: Use WP-Cron Local Preview And Cloud Batch Runtime For Nightly Automation](decisions/ADR-005-wp-cron-cloud-batch-orchestration.md)

## Exception 5 - Exact-Manifest Local Media Optimization

Status: accepted bounded administrator batch.

Owner: Toolbox owns selection, one confirmation, foreground continuation, and
the bounded manifest record; Toolkit owns every file replacement, backup,
verification, lineage, and restore.

Allowed scope:

- one currently present WordPress administrator;
- one frozen `toolbox_media_optimization_batch.v1` manifest containing no more
  than 1000 Media Library attachments;
- every item bound to its current main-file SHA-256;
- only the fixed `auto_safe.v1` WebP policy and the documented resize choice;
- one confirmation bound to the exact manifest digest;
- foreground, browser-driven, one-item-at-a-time Toolkit replacements;
- bounded local history and explicit item-by-item restore.

Hard stop:

- no external Agent, OpenClaw, CLI, Cron, background worker, or Cloud callback
  may start or continue this local batch;
- no open-ended attachment selection, mutable manifest, generic workflow
  runner, queue, scheduler, lease, retry worker, or approval store;
- no Toolbox-owned file replacement, backup registry, media registry, or final
  write implementation;
- no article/media creation batch, attachment ALT batch, URL/settings repair,
  manual quality, crop, watermark, AVIF, or other uncontracted transform.

External, open-ended, and other write-like batches remain Core/Adapter governed.
This exception does not grant those callers the present-administrator lane.

Primary contracts:

- [Media Optimization V1](media-optimization-v1.md)
- [ADR-015: Exact-manifest local media optimization](decisions/ADR-015-exact-manifest-local-media-optimization.md)

## Current Non-Exceptions

These are not exceptions and must remain outside Toolbox ownership:

- Cloud Checks or Troubleshooting Checks product surfaces;
- Site Knowledge indexing, rebuild, delete, or vector collection lifecycle;
- AI image generation as a provider playground;
- final publish, media upload, open-ended featured-image or attachment
  replacement batches, or SEO meta mutation without governed handoff;
- workflow runtime queues, schedulers, leases, retries, run tables, or approval
  stores.
