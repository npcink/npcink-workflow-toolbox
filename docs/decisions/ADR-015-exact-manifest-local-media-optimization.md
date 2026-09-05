# ADR-015: Exact-manifest local media optimization

Status: Accepted

## Context

The previous batch workbench created one Core proposal per image. That posture is correct for agent-initiated or open-ended writes, but it makes a present administrator repeat the same decision for a deterministic and reversible optimization policy.

## Decision

Toolbox may use one strong local confirmation for a frozen media optimization manifest only when all of these conditions hold:

- the actor is a currently present WordPress administrator;
- every local planning, confirmation, replacement, and restore POST is made by
  a same-origin WordPress admin page with the logged-in cookie and a valid
  `wp_rest` nonce; application-password and delegated external requests fail
  closed;
- Toolbox invokes Toolkit's existing read-only media planning ability through an administrator-protected local route, without a separate Core read approval;
- Toolkit produced a bounded manifest of at most 1000 Media Library attachments;
- every item binds its attachment ID to the current main-file SHA-256;
- the only policy is `auto_safe.v1` with WebP output and no user quality, crop, or watermark parameters;
- Cloud only analyzes and creates short-lived derivative Artifacts;
- the browser processes one item at a time and stops writing when it closes;
- Toolkit remains the sole file replacement, backup, verification, lineage, and restore owner;
- source SHA-256 is checked when the manifest is frozen, before upload, and before replacement;
- repeated item completion requests are idempotent;
- each failure is isolated, with execution paused after three consecutive failures or a block failure rate above 30%;
- every successful replacement retains a local backup and can be restored individually or as a browser-driven sequence.

The manifest is stored in one bounded WordPress option as a resumable execution and history record. It is not a queue, scheduler, approval registry, or new media source of truth.

## Consequences

This lane does not create or auto-approve Core proposals and does not change Core or Adapter contracts. It cannot be used by an external agent, Cron, a background worker, or a Cloud control plane. Manual formatting, quality, cropping, watermarking, AVIF, and other transformations remain in the existing advanced single-image path.

Building the candidate manifest is read-only and is part of the administrator's explicit check action. The one strong confirmation is reserved for starting writes against the frozen manifest digest.

Backups have a 30-day suggested restore window. Expiry is advisory in this version: no backup is deleted automatically, and restore remains possible until a future separately confirmed cleanup action exists.
