# Classification Lane 3 Fixture Repair — 2026-10-17

Status: local repair and real WordPress verification complete; protected PR
publication/checks are recorded by the coordinating Core stage document.

The historical follow-up in stash `4830f19e350f9a7e8447cbe8d0a5b3eedd222f21`
had not merged. On baseline `135090bd`, the real WordPress 7.1.3 smoke failed
before intake: Toolbox's globally registered batch planner was incorrectly
required in Toolkit's private callback registry.

The repaired smoke keeps the global `wp_get_ability()` assertion for every
required ability and checks the private registry only for Toolkit's own
namespace. It also tracks the batch id returned by Core. Plan-ingest audit
events have no proposal id, so proposal-id-only cleanup left those events
behind. Cleanup now removes only `proposal.plan_ingested` rows matching a
returned batch id, with prepared SQL and escaped LIKE input. Product code,
public ability ids and authorization behavior are unchanged.

## Real WordPress verification

The [machine-readable evidence](classification-lane3-wordpress-evidence-2026-10-17.json)
binds the exact script SHA-256 and local baseline revisions.

- Normal run: exit 0, 65 assertions. Core returned HTTP 201, one pending batch
  proposal and eight dry-run actions. No article/media execution or local
  consent path was used.
- Failure path: a temporary `rest_request_after_callbacks` filter changed only
  `proposal_count` to zero after a real successful HTTP 201 intake. The script
  failed at its expected count assertion (exit 1) and invoked its failure
  cleanup. Proposal ids and batch id remained available to that cleanup.
- Both runs purged one proposal fixture and one batch's plan-ingest audit
  fixtures. Independent WP-CLI snapshots before and after each run preserved
  **326 proposals / 4,140 audit rows**, including identical SHA-256 fingerprints
  of every pre-existing row. WordPress post count stayed **2,391**, attachments
  **26**, local-consent audit rows **6**. This proves cleanup without deleting
  unrelated existing records; existing site data is not globally purged.

Local `composer test:all`, Composer validation, PHPCS, PHPStan and the
WordPress.org review guard passed. Self-review found no blocking correctness,
readability, ownership, SQL safety or performance issue. The advisory
OpenCodeReview workflow is `disabled_manually`; publication must record the
standard exception rather than claim an AI review was delivered. Required CI
checks and branch protection remain the merge authority.

## Preservation and rollback

The original stash is retained as historical saved work. Other local topics,
stashes, checkouts, fixed RC2 manifest and all tags remain outside this change.
Rollback is a focused revert of this test-fixture change through the normal
PR flow. This smoke is synthetic engineering evidence, not a real-user ledger
entry or a new release candidate acceptance.
