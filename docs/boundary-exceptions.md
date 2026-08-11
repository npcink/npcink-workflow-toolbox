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

ADR-003's no-import stop remains true for this legacy route. ADR-010 defines a
separate route, classification, permission model, and compensation contract;
it does not silently widen `/local-admin-consent/featured-image`.

## Exception 2 - Single-Article Strong Local Image Adoption

Status: accepted bounded editor transaction.

Owner: Toolbox editor UI and local WordPress write; Cloud Addon is candidate
runtime transport only.

Allowed scope:

- one logged-in editor with `edit_post` and, for import, `upload_files`;
- one current article and one fully previewed image;
- `import_only`, `set_featured_existing`, or `import_and_set_featured`;
- exact source, license state, media fields, and final action shown before one
  explicit confirmation;
- safe HTTPS download, MIME and dimension verification, and 10 MiB limit;
- deletion of a newly created attachment and featured-image restoration when a
  combined transaction fails.

Hard stop:

- no batch, background, cron, CLI, Adapter, Agent, or Cloud callback execution;
- no publish, attachment replacement, unrelated attachment mutation, taxonomy,
  settings, permissions, SEO, or cross-object write;
- no custom audit table, approval store, retry worker, or local run history.

Primary contracts:

- [Single-Article Editor Tools Development Standard v1](single-article-editor-tools-development-standard-v1.md)
- [ADR-010: Allow Strong Local Confirmation For Single-Article Image Adoption](decisions/ADR-010-single-article-strong-local-image-adoption.md)

## Exception 3 - Single-Image Strong Local Media Replacement

Status: accepted bounded Media Library transaction.

Allowed scope: one present admin action, one image attachment, one exact
same-origin derivative preview, one explicit `replace_current` confirmation,
and Toolkit-owned backup/reference-repair/rollback execution.

Hard stop: no batch, background, Adapter, Agent, Cloud callback, save-as-new,
queue, approval store, or authorization of any Toolkit ability other than
`adopt-cloud-media-derivative` for the current request.

Primary contracts:

- [Single-Image Media Workbench Standard v1](single-image-media-workbench-standard-v1.md)
- [ADR-011: Single-Image Local Media Replacement](decisions/ADR-011-single-image-local-media-replacement.md)

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

## Current Non-Exceptions

These are not exceptions and must remain outside Toolbox ownership:

- Cloud Checks or Troubleshooting Checks product surfaces;
- Site Knowledge indexing, rebuild, delete, or vector collection lifecycle;
- AI image generation as a provider playground;
- final publish, media upload, featured-image batch replacement, or SEO meta
  mutation without governed handoff;
- workflow runtime queues, schedulers, leases, retries, run tables, or approval
  stores.
