# Onboarding Tour Design v1

Status: proposed. Acceptance loop: the operator trial
([ux-hardening-operator-trial-2026-10.md](ux-hardening-operator-trial-2026-10.md),
step 1 and the new step 9). Nothing here is committed implementation scope
until the trial picks it up in its own session with a boundary check.

## Problem

The shipped fresh-install surface is notices plus a three-step
getting-started card. The trial question "what would you do first?" tests
whether that card alone orients a new operator. A full guided tour was
deliberately deferred until it had product design
([UX Hardening Development Lessons 2026-10](ux-hardening-development-lessons-2026-10.md),
section 8). This document is that design.

## Goals

- A first-run operator can state, after the tour: what Toolbox is
  (suggestion-only), where the one bounded governed flow lives (Image
  Handling), and where the editor surface is.
- The tour is optional, dismissible, and restartable; it never blocks the
  Overview.
- Zero new runtime surface: no REST routes, no writes, no dependencies.

## Non-Goals

- No editor-sidebar tour: the editor persona (authors, ADR-018) differs
  from the administrator persona, and the sidebar already carries its own
  empty states.
- No cross-plugin tour (Core/Toolkit/Addon screens are out of boundary).
- No user-meta or options writes for tour state; no email, no telemetry.
- No spotlight/overlay library; the implementation stays vanilla JS over
  server-rendered markup per the repository's build rule.

## Design

Entry and state:

- The getting-started card gains one text link: "Take the 2-minute tour".
  One primary action per view stays intact (the tour link is a text link,
  not a button).
- Tour state is client-side only, in `localStorage` under
  `npcinkToolboxAdminTour.v1` with values `done` or `skipped`. The card's
  existing dismissal behavior is unchanged; a "Restart tour" text link in
  the card corner re-enters.
- ESC and "Skip tour" end it at any step; completion sets `done`.

Steps (five, each one anchored callout):

1. **What this plugin is.** Anchored on the getting-started card. Copy:
   Toolbox returns suggestions and review-only artifacts; nothing is
   written to WordPress without your review or a governed Core handoff.
2. **Cloud readiness.** Anchored on the Cloud Addon status row. Copy:
   hosted AI runs need the Cloud Addon connected; everything else works
   without it.
3. **Image Handling.** A link-step into Image Handling, anchored on the
   Batch Optimize tool header. Copy: the one bounded flow with local
   write authority (ADR-015); originals stay restorable.
4. **System status.** Back on Overview, anchored on the workflow
   readiness summary. Copy: readiness and defaults visible without
   becoming a runtime.
5. **In the editor.** Anchored on the card again: open any post to see
   the Npcink Content Support sidebar (suggestions only; publishing stays
   native WordPress).

Presentation:

- Each step is a plain notice card (existing banner vocabulary, section
  4.7 of the admin UI design standard) anchored above its target element
  with `scrollIntoView`, plus "Step N of 5", Back, Next, and Skip.
- No dimming, no modal, no focus trap: the page stays usable while the
  tour is open (层级克制 and 健康/安静 rules; no new colors).

Localization: all copy through `t()` with the admin script text domain;
new strings follow the standard catalog rebuild order.

## Static Contracts (when implemented)

- `tests/run.php` pins: the tour entry link id, the localStorage key
  string, the five-step anchor ids, and that no tour code touches
  `fetch(`, `wp.ajax`, or REST paths.

## Trial Questions (added to the operator trial, step 1 and step 9)

- Did the operator start the tour voluntarily, and finish it?
- Which step's copy did they read aloud or misread?
- After the tour, could they name one suggestion-only guarantee unprompted?
- Review-set adoption data (new step 9): see the trial script.

## Decision

Implement in a dedicated session after the trial answers the questions
above; the three-step card remains the shipped default until then.
