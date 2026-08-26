# ADR-013: WordPress-First Content And Recommendation Contracts

## Status

Accepted

## Date

2026-08-22

## Context

Toolbox currently serves WordPress editor workflows while Cloud supplies
semantic retrieval and hosted AI runtime detail. The editor already has a
compatibility wrapper named `editor_recommendation_set.v1`, but its input
context and candidate references are not yet an explicit cross-platform
contract. Future Ghost, Typecho, and Astro support is possible, but those
adapters are intentionally out of scope while the WordPress product surface
is still being built.

## Decision

Keep WordPress as the first and current platform implementation, and add two
additive contracts:

- `content_context.v1` describes the bounded article context sent from the
  WordPress editor. It identifies `platform=wordpress`, the article identity,
  language, scope, content fingerprint, and sanitized editorial fields.
- `recommendation_set.v1` is the canonical name for the existing additive
  recommendation wrapper. `editor_recommendation_set.v1` remains as a
  compatibility field for current consumers.

Domain artifacts remain authoritative. Site citation suggestions use
`internal_link_candidates.v1`; candidates with a safe exact match may be
applied to visible editor state, while other related articles remain
reference-only items in the same artifact. The recommendation set only
references these bounded candidate records for common consumers.

Cloud remains the semantic retrieval/runtime owner. WordPress remains the
editor, confirmation, save, and final-write owner. Every Toolbox projection
keeps `direct_wordpress_write=false`.

## Alternatives Considered

### Build Ghost, Typecho, and Astro adapters now

Rejected: it would expand the product surface before the WordPress contracts
and operator workflow have enough evidence.

### Replace `editor_recommendation_set.v1` immediately

Rejected: existing editor, smoke, and external consumers depend on the
compatibility name. The canonical alias is additive until a separately
reviewed migration proves replacement is safe.

### Let Cloud own platform writes

Rejected: Cloud is a runtime enhancement layer, not a second WordPress or
content-management control plane.

## Consequences

- WordPress responses now carry a reusable, bounded content context.
- Related-article candidates participate in the same recommendation counts,
  source metadata, and candidate references as other editor artifacts.
- Future adapters can consume the canonical contracts without requiring their
  implementation today.
- Legacy fields remain during migration, so consumers must prefer the
  canonical aliases when available.
