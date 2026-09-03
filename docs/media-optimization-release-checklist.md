# Media Optimization Release Checklist

Status: release gate for the fixed `media_optimization_v1` flow.

ADR-015 supersedes the former default per-image Core proposal checklist. Use
this checklist for the administrator-present exact-manifest flow. Keep legacy
Core smokes only for external Agent, OpenClaw, and other proposal-owned paths.

## Source Gates

```bash
composer test:single-image-media-optimization
composer test:media-optimization-batches
composer test:media-derivative-local-review
composer test:all
```

For cross-repository closeout, provide an explicit clean Cloud worktree when
the normal Cloud checkout contains unrelated work:

```bash
NPCINK_REPO_FAMILY_ROOT=/Users/muze/gitee \
composer quality:matrix:run -- \
  --repo-path=npcink-ai-cloud:/absolute/clean/npcink-ai-cloud-worktree
```

The override is explicit and repository-identity checked. The matrix must not
guess another worktree or treat dirty source as exact-SHA evidence.

## Pass Criteria

1. Checking a range is read-only and freezes no more than 1000 attachment IDs
   with their current SHA-256 values.
2. Samples show qualified and skipped results without replacing media.
3. The run action remains hidden until checking completes and oversized-image
   resize choice appears only when applicable.
4. One present-administrator confirmation binds the exact manifest digest and
   `auto_safe.v1` policy; the default batch creates no Core proposal.
5. Upload and final replacement both recheck source SHA-256. Drift skips only
   the affected item.
6. Cloud Addon validates downloaded MIME, dimensions and SHA-256; Toolkit owns
   backup, replacement, reference repair, verification, lineage and restore.
7. Partial failures preserve completed evidence and pause at the documented
   three-consecutive or 30-percent block threshold.
8. Refresh resumes the bounded foreground run without duplicate completion or
   duplicate replacement.
9. History reports original size, new size, savings and restore state.
10. One-image and whole-batch restore work item by item; no backup is deleted
    automatically.

## Manual UI Check

At `https://magick-ai.local/`, open:

```text
Npcink AI -> Toolbox -> Image Handling -> Media Library Optimization
```

Confirm that the first view contains only time range, image type and **Check
optimizable images**. After checking, verify representative samples, the
conditional resize choice, one **Start optimization** action, foreground
progress, and **History and restore**. The page must not expose provider,
model, quality, Artifact, fingerprint, queue, or Core proposal terminology.

This copy-only check must not click **Start optimization** unless the release
task explicitly authorizes real media writes.

## Separate Governed Paths

- Advanced single-image transforms remain under ADR-011 strong confirmation.
- External Agent/OpenClaw and open-ended media batches remain Core/Adapter
  governed.
- Hard-coded URL and settings repairs remain Core proposals.
- Article/media creation and attachment ALT writes are not authorized here.

## Non-Goals

This checklist does not authorize production deployment, automatic backup
deletion, animation support, CDN changes, a local queue/Cron runner, a Cloud
control plane, or any new WordPress write path.
