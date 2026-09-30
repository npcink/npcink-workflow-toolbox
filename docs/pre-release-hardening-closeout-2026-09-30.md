# Pre-Release Hardening Closeout (2026-09-30)

Rule-level record of the 2026-09-30 positioning audit and hardening series
(PR #154, #156, #157, #161, #162). It records decisions and reusable
methods, not session history.

## Baseline And Verdict

The audit benchmarked the repository against its own positioning: fixed
operator buttons that return suggestion-only artifacts, Cloud owns heavy
runtime, humans own the final write. The verdict was that the boundary
discipline is real (zero out-of-lane WordPress writes in code review; the
only direct write is the audited ADR-003 featured-image exception), and the
real risks were structural: role mismatch, compatibility surface that only
grew, god classes, and a static-contract suite that froze refactoring.

## Decisions

- **Delete compatibility instead of deprecating it.** The project is
  pre-release with no users, so legacy REST routes (`/vector-search`,
  `/knowledge-search`, `/flows/article-brief`, `/flows/article-assistant`),
  admin URL alias maps, the public/internal slug translation layer, and
  retired-tool routing were removed outright. Do not add deprecation
  windows for surfaces that never had callers.
- **Keep active features, document them.** The Zhihu hot-topics widget is an
  active Cloud-backed feature, not compatibility residue; it stayed and was
  added to the docs and storage allowlist.
- **Scope relaxations by transport, audit by object.** Codified in
  AGENTS.md and `scoped-editor-permissions-lessons-2026-09.md`.

## Methods Worth Reusing

1. **Behavior tests unlock refactors.** Before splitting anything pinned by
   `tests/run.php` string contracts, write behavior tests that drive the
   real classes through stubs (`editor-content-support-behavior.php`,
   `provider-services-behavior.php`). Then refactor while the behavior
   suite stays green; the string contracts stay untouched.
2. **Regression tests before fixes.** Make the reviewed defect fail in the
   harness first (the `$status` shadowing reproduced as a warning plus a
   degraded `'array'` status and a WP_Error fatal), then fix (PR #162).
3. **Aggregate assertion sources before moving code.** See
   `provider-split-refactor-standard-v1.md`; the facade is now a delegate
   shell plus the two methods pinned by span contracts.
4. **Never trust a piped gate.** Check the composer exit code itself; a
   `| grep` pipeline once masked every suite stage after `run.php`.

## Remaining Known Items

Deliberately not absorbed into the hardening series; each needs its own
scoped change:

- `load_plugin_textdomain` is not called; translations rely on WordPress.org
  hosting. Self-distributed packages need the explicit loader.
- The weekly media fingerprint scan cron
  (`npcink_toolbox_weekly`, `Media_Fingerprint_Scan.php`) is guarded by Cloud
  Addon verification but is not yet named in the boundary/architecture
  scheduling notes.
- The editor `整理` (format content) button label is Chinese inside an
  otherwise English UI; unify label language with the i18n pass.
- Sixteen non-default editor intents remain callable; convergence is a
  mid-term product decision, not a cleanup.
- Cross-repo: Cloud Addon quota/attribution follow-ups for editor image
  access are tracked in `scoped-editor-permissions-lessons-2026-09.md`.
