# Media Optimization Stage Summary

Status: V1 validated and frozen for defect fixes only.

ADR-015 supersedes the former default selected-review/Core-proposal batch. The
current administrator flow is:

```text
choose range -> freeze exact SHA-256 manifest -> inspect representative results
-> confirm once -> foreground Toolkit replacements -> history and restore
```

The earlier single-image and selected-batch Core trials remain archived evidence
of the implementation's evolution. They do not describe the current default
Media Library Optimization surface.

## Current Closed Loop

1. A present administrator selects time range and image type.
2. Toolkit builds a bounded manifest of current attachment main-file SHA-256
   values.
3. Cloud Addon transports images to Cloud, where `auto_safe.v1` produces
   qualified short-lived `media_derivative_result.v3` Artifacts.
4. Toolbox shows totals and representative samples without replacing media.
5. One strong confirmation binds the exact manifest and policy.
6. The browser runs items in the foreground; Toolkit rechecks source bytes,
   backs up, replaces, verifies, records lineage, and exposes restore history.
7. Item failures are isolated and the run pauses at the documented stop limits.
8. Individual and whole-batch restore remain item-by-item Toolkit operations.

Cloud returns derivative Artifacts and processing evidence only. It never owns
WordPress files, replacement decisions, or long-term media storage. Toolbox
owns the bounded operator flow, not a general writer. Toolkit is the sole owner
of attachment replacement, backup, verification, lineage, and restore.

## Boundary

- The default batch creates no Core proposal.
- Advanced attachment-scoped format, quality, crop, and watermark transforms
  remain under ADR-011 strong confirmation.
- External Agent, OpenClaw, open-ended media batches, article/media creation,
  and URL/settings repair remain Core/Adapter governed.
- The local manifest is resumable browser-driven state, not a queue, scheduler,
  approval registry, custom table, or second media truth.
- Backups have an advisory 30-day restore window and are never deleted
  automatically.
- Animation, video, CDN rewrite, global search/replace, global ALT writes, and
  unattended execution remain out of scope.

## Verification

```bash
composer test:single-image-media-optimization
composer test:media-optimization-batches
composer test:media-derivative-local-review
composer test:all
```

Use `https://magick-ai.local/` for the WordPress UI and restore acceptance.
Cloud runtime tests, when required, run in M4 Docker. See
[Media Optimization Release Checklist](media-optimization-release-checklist.md).

## Next Stage

Future media ALT/caption work remains a bounded review or Core handoff and must
not inherit the ADR-015 attachment-replacement exception. Do not add another
write-like batch surface without its own boundary decision and operator trial.
