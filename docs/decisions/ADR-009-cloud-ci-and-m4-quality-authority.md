# ADR-009: Separate Cloud Source CI From M4 Runtime Acceptance

## Status

Accepted.

## Date

2026-07-24.

## Context

The shared development architecture keeps Git and source truth on the MBA/M5
and uses M4 Docker for builds, runtime tests, and preview. The central
cross-repository matrix still ran `npm run check:fast` inside the local Cloud
checkout. That command invokes Docker Compose, so an intentionally Docker-free
MBA was reported as a Cloud code failure.

Replacing the command with an unconditional remote M4 test would create a
different false claim: M4 may still be running an older revision. A passing
remote test is evidence only for the deployed revision, not automatically for
the current local or GitHub revision.

## Decision

The matrix uses two explicit Cloud evidence authorities:

1. `composer quality:matrix:run` validates the clean Cloud worktree's exact
   `HEAD` through GitHub source checks: backend, frontend, secret scan, and
   Python plus JavaScript/TypeScript CodeQL.
2. `composer quality:matrix:m4` additionally requires M4 to report that exact
   revision as accepted and clean, verifies service/HTTP status, and then runs
   the full remote contract/domain gate.

The default matrix never deploys or synchronizes M4. Revision mismatch is
reported as `needs_deploy`; unavailable GitHub, SSH, or M4 evidence is
`blocked_environment`; pending or absent exact-SHA source checks are
`needs_validation`. These states are distinct from a test failure.

M4 deployment remains an explicit Cloud repository operation. The matrix is an
evidence reader and test dispatcher, not a second deployment control plane.

## Alternatives Considered

### Keep local Docker as a requirement

Rejected because it contradicts the adopted MBA/M5 source plus M4 runtime
architecture and duplicates a heavy local environment.

### Always run the remote M4 test

Rejected as the default because a stale M4 revision could produce a false
positive and every central closeout would incur the full remote test cost.

### Automatically deploy M4 from the matrix

Rejected because a read-oriented cross-repository gate must not silently mutate
the shared preview runtime.

## Consequences

- Normal cross-repository closeout no longer requires MBA Docker.
- GitHub CLI authentication and network access are required for exact-SHA Cloud
  source evidence.
- Dirty or unpushed Cloud work cannot be declared source-validated.
- M4 runtime acceptance is explicit, revision-bound, and slower.
- Operators receive actionable states instead of environment failures being
  mislabeled as code failures.

## Verification

Run:

```bash
composer test:cloud-gate-evidence
composer quality:matrix
composer quality:matrix:run
composer quality:matrix:m4
```

The M4 command is expected to return `needs_deploy` until the exact Cloud
revision has been deployed and accepted.

## Rollback

Revert the focused matrix commit. Do not restore an implicit local Docker
requirement without revisiting the MBA/M5 and M4 architecture decision.
