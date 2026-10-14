# ADR-020: Use One Local OCR Configuration With A GitHub CI Mirror

## Status

Accepted

## Date

2026-10-13

## Context

OpenCodeReview runs in two environments. The maintainer's local CLI can read
`~/.opencodereview/config.json`, while GitHub Actions runs on an isolated
runner and can read only repository variables and secrets. The two paths had
drifted: the local Qwen configuration was current, but the Toolbox repository
still held the retired `glm-5.3` model variable.

## Decision

Treat the local OpenCodeReview configuration as the operator's source of truth
for local review. Expose `composer ocr:local` for a bounded review of the
current branch and `composer ocr:config-check` for a read-only comparison of
the local model/provider metadata with GitHub Actions metadata.

GitHub Actions keeps its own copy of the model variable and two secrets because
the runner cannot read the maintainer's filesystem. The checker reports secret
presence and update timestamps only; it never reads, prints, hashes, or sends
secret values. The publisher's exact-head GitHub review delivery remains the
merge gate, and local review remains an early signal.

## Alternatives Considered

### Self-hosted GitHub Runner

Rejected for this stage. It would make the workstation a CI runtime, widen
pull-request trust and secret exposure, and add lifecycle/availability work
outside Toolbox's current boundary.

### Local review as the only publish gate

Rejected. A local result cannot prove that the exact GitHub PR head was
reviewed or provide the repository audit trail used by the publisher.

### Copy the local config into the repository

Rejected. Provider credentials and endpoint configuration must stay outside
source control and generated artifacts.

## Consequences

- Developers run one documented local command and can detect model drift before
  publishing.
- GitHub secrets still require explicit operator synchronization after local
  credential rotation; their values remain opaque to the checker.
- Runner/provider network failures remain a CI operations issue and cannot be
  hidden by treating a successful local review as a delivered CI review.
