# Media Optimization V1

Status: validated fixed workflow contract; V1 frozen for defect fixes only.

`media_optimization_v1` is the fixed Media Library optimization workflow. The
default administrator flow is governed by
[ADR-015](decisions/ADR-015-exact-manifest-local-media-optimization.md):

```text
choose range -> freeze exact SHA-256 manifest -> check representative results
-> confirm once -> run foreground Toolkit replacements -> history and restore
```

This contract supersedes the former default batch review-set and per-image Core
proposal flow. Historical trials and ADRs remain evidence of how the workflow
evolved; they are not the current operator contract.

## Ownership

| Project | Owns |
| --- | --- |
| `npcink-workflow-toolbox` | Range selection, the bounded `toolbox_media_optimization_batch.v1` manifest, representative samples, one administrator confirmation, foreground progress, and history presentation. |
| `npcink-abilities-toolkit` | Media reads, true file SHA-256 checks, backup, replacement, metadata/reference verification, lineage, idempotency, and restore. |
| `npcink-cloud-addon` | Signed upload, Cloud invocation, result polling, derivative download/validation, and Artifact acknowledgement. |
| `npcink-ai-cloud` | `auto_safe.v1` analysis and short-lived `media_derivative_result.v3` Artifacts. |
| Core and Adapter | External-agent, open-ended, URL/settings repair, and other proposal-governed paths; not the default exact-manifest batch. |

Cloud never writes WordPress. Toolbox does not become a general media writer:
each replacement and restore is delegated to Toolkit's bounded WordPress
ability after the present administrator confirms the frozen manifest.

## Default Batch Flow

1. The administrator selects a time range and recommended, JPEG, PNG, or WebP
   media.
2. Toolkit returns an eligible attachment list bound to each current main-file
   SHA-256. The manifest contains at most 1000 attachments.
3. Cloud evaluates `auto_safe.v1` candidates. Toolbox shows totals and at most
   six representative samples without changing WordPress media.
4. If oversized images exist, the administrator may keep dimensions or limit
   the longest side to 1920px.
5. One strong confirmation binds the actor, policy version, manifest digest,
   source fingerprints, and resize choice.
6. The browser runs the frozen list in the foreground. It rechecks the source
   fingerprint before upload and before replacement, then delegates qualified
   writes to Toolkit one item at a time.
7. Item failures are isolated. The run pauses after three consecutive failures
   or when a ten-item block exceeds a 30 percent failure rate.
8. Batch history records success, skipped and failed items, byte savings, and
   restore state. Restore remains item-by-item even for a whole-batch request.

Closing the browser stops further WordPress writes. Reopening the page resumes
from the first unfinished item in the bounded local manifest. This manifest is
not a queue, scheduler, approval registry, workflow runtime, or second media
source of truth.

## Safety Rules

- Preview and sample inspection never write replacement history or change the
  current attachment file.
- Every item is bound to `sha256:<64 lowercase hexadecimal characters>`; an
  attachment ID, URL, filename, or timestamp is not content identity.
- Cloud `auto_safe.v1` chooses the WebP candidate and reports quality and
  transform facts. It does not use a vision model to choose compression.
- Animation, invalid input, unknown format, larger output, insufficient saving,
  or failed quality checks are skipped.
- Successful replacement retains a Toolkit-owned backup. The 30-day restore
  window is advisory; no backup is deleted automatically.
- Repeated item completion, refresh, and transport retry remain idempotent.
- Visual evidence reuse continues to require current fingerprint and verified
  lineage; optimization never treats attachment identity as file identity.

## Other Media Paths

The default flow creates no Core proposal. That exception does not weaken the
governance requirements for other media paths:

- Advanced single-image format, quality, crop, or watermark work uses the
  existing attachment-scoped strong-confirmation transaction under ADR-011.
- External Agent and OpenClaw requests remain Core/Adapter-governed.
- Article/media creation batches remain Core/Adapter-governed.
- Hard-coded content URL and settings reference repairs remain separate Core
  proposals.
- ALT review remains suggestion/review or Core handoff and never becomes an
  attachment-global write through this workflow.

## Non-Goals

V1 does not add a database table, queue, Cron runner, background browser job,
Cloud control plane, automatic backup deletion, global ALT writer, video or
animated-image optimization, CDN rewrite, arbitrary search/replace, or a second
approval system. New media write surfaces require a separate boundary decision.

## Verification

```bash
composer test:single-image-media-optimization
composer test:media-optimization-batches
composer test:media-derivative-local-review
composer test:all
```

The real-site acceptance target is `https://magick-ai.local/`: verify the
simple initial range form, representative samples, one confirmation, foreground
progress, history, one-image restore, and whole-batch restore. Cloud runtime
execution, when required, belongs to the M4 Docker lane.
