# Operator Terminology Standard v1

Status: proposal for cross-plugin alignment; Toolbox-side rules below are
already applied by the 2026-10 UX hardening pass.

## Problem

The Npcink plugin suite uses internal architecture names ("Core proposal",
"Adapter", "runtime owner", contract ids like
`toolbox_core_handoff_receipt.v1`) interchangeably with operator-facing
copy. An operator sees "Create Core proposal" without knowing Core is the
sibling Governance Core plugin they must open next, and machine values leak
into primary UI rows. Each plugin renaming terms on its own would make the
suite less consistent, not more, so this standard defines shared layers
instead of per-plugin vocabulary drift.

## Terminology Layers

1. **Operator layer (default visible UI).** Plain task language that says
   what happens and what the operator does next. Examples:
   - "Submit for governance review" instead of "Create Core proposal".
   - "Continue in Governance Core to review and execute this proposal."
   - "Nothing is written without your review."
2. **Destination-product layer.** Product names that appear as clickable
   destinations stay verbatim and localized consistently across plugins:
   Governance Core, Cloud Addon, Toolbox, Abilities Toolkit. A term naming
   a screen the operator will actually open is not jargon.
3. **Developer layer.** Contract ids (`*.v1`), owner codes
   (`wordpress_toolbox_local`), snake_case action codes, raw JSON payloads,
   and storage flags belong behind a collapsed "Technical details"
   disclosure, a debug toggle, or developer docs — never in primary rows
   or button labels.

## Rules

- Primary buttons and notices use layer 1 language; if they reference a
  destination, layer 2 naming with a link.
- Machine values stay in the DOM for support workflows (fixture smokes and
  support transcripts rely on them) but render only inside layer 3
  surfaces. The Core handoff receipt is the reference implementation:
  humanized next-action row plus a collapsed technical section.
- Error messages are split by audience: author-visible errors state that
  the service is unavailable and point to an administrator; administrator
  surfaces may carry configuration remedies.
- zh_CN translations follow the same layers; destination product names
  stay untranslated ("Core", "Cloud Addon") when the sibling plugin's own
  UI shows the same form.

## Alignment Path

1. Each plugin audits operator-visible strings against these layers and
   files per-plugin diffs; renames ship only after this standard is
   accepted in all five repos (or recorded as declined with reasons).
2. Contract-table docs (`docs/fixed-button-contract-table.json`, route and
   ability boundary tables) keep developer-layer ids — they are layer 3 by
   definition.
3. A cross-repo naming change updates this standard first, then plugin
   copy, then catalogs, in that order.
