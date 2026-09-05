# ADR-017: Retire Single-Image Local Write Exceptions

## Status

Accepted.

## Date

2026-09-05.

## Context

ADR-010 allowed one editor-present image import or featured-image adoption, and
ADR-011 allowed one administrator-present attachment replacement or restore.
Both paths performed durable WordPress writes from Toolbox after a local
confirmation.

The product boundary has since converged on one governed write path for image
adoption and media mutation. OpenClaw and other third-party clients use Adapter
to submit a reviewed plan to Core; Core owns proposal truth, approval, and
audit; Toolkit owns the reusable WordPress write abilities. Keeping separate
Toolbox local-write paths would duplicate authorization and make the result
depend on which UI initiated the same operation.

The project has no production users or compatibility requirement for these
routes, UI controls, or result contracts.

## Decision

ADR-010 and ADR-011 are superseded. Toolbox no longer imports a remote image,
sets a candidate as featured, replaces one attachment file, lists backups for a
local restore workbench, or restores one attachment through a Toolbox-owned
local confirmation route.

The supported path is:

```text
OpenClaw or another third-party client
-> Npcink AI Client Adapter
-> Governance Core proposal, approval, and audit
-> Npcink Abilities Toolkit write ability
```

Adapter is a client boundary and transport bridge. It must not approve its own
proposal, become a second governance store, or write WordPress directly.

Toolbox continues to own image-source search, candidate review, attribution,
preview, and read-only planning. In particular,
`npcink-toolbox/build-image-candidate-adoption-plan` remains available for the
OpenClaw recipe. Media derivative preview and handoff artifacts may remain
short-lived review inputs; they do not grant write authority.

No compatibility aliases, deprecated handlers, or hidden UI controls are kept
for the retired routes because there are no users or stored client contracts to
migrate.

## Unchanged Decisions

- ADR-003 remains the historical, narrow existing-attachment/current-post
  featured-image exception.
- ADR-006 continues to allow an author-reviewed value in the current article's
  visible editor state to persist only through the author's normal WordPress
  Save, Update, or Publish action.
- ADR-015 remains the present-administrator exact-manifest Media Library
  Optimization workflow. It is a bounded batch exception and is not a single-
  image compatibility path.
- Cloud and Cloud Addon remain runtime and artifact-detail owners only. They do
  not become WordPress write owners or governance truth stores.

## Consequences

- The four `/strong-local-confirmation/*` image routes are removed.
- The single-image adoption and media-replacement classes are removed.
- Editor Adopt/Import-only controls and Media Library single-image
  optimize/restore entry points are removed.
- Static contracts reject reintroduction of the retired routes and classes.
- Operators who need import, replacement, or restore use the governed external
  client path. Toolbox stops at reviewable candidates, plans, and handoff
  suggestions.
