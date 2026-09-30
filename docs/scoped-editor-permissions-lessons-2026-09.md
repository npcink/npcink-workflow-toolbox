# Scoped Editor Permissions Lessons (2026-09)

Rule-level record distilled from the ADR-018 rollout (PR #157), which moved
the editor Content Support sidebar from blanket `manage_options` to
scope-mapped default capabilities. It records reusable rules, not session
history. The decision itself, including per-intent rulings and rejected
alternatives, lives in
[ADR-018](decisions/ADR-018-editor-scoped-permissions.md).

## Scope Map As Shipped

| Scope | Routes | Default capability |
| --- | --- | --- |
| `cap.toolbox.editor_suggest` (new) | `/editor/content-support` | `edit_posts` |
| `cap.toolbox.image_source` | `/image-candidates`, `/ai/image-generation` | `edit_posts` |
| `cap.toolbox.feedback.write` | `/agent-feedback` | `edit_posts` |
| every other scope and the `cap.toolbox.admin` fallback | everything else | `manage_options` |

One decision point: `Rest_Controller::default_capability_for_scope()`.
`Editor_Content_Support::enqueue()` and the `format_content` handler reuse
`user_can_use_editor_support()`, so the sidebar script, the handler gate, and
the REST permission cannot drift apart.

## Permission-Widening Rules

- Relax a gate, audit the whole route. Widening one route from
  `manage_options` to a lower capability exposes every server-side object
  read on that route, not only the newly intended users. The rollout review
  caught two: stored comment reads (now `moderate_comments` plus a
  current-post match) and attachment metadata resolution (now `upload_files`,
  matching WordPress core media visibility). Any capability relaxation must
  include an object-level authorization audit of every handler on the route
  in the same change.
- Rule by transport, not by intent. The `image_candidates` ruling was first
  made against the `/editor/content-support` intent list, but the editor
  image modal calls `/image-candidates` and `/ai/image-generation` directly;
  opening the intent alone would have shipped a 403 for the granted role. A
  per-intent permission ruling must name the exact transport routes the
  client actually calls.
- Keep proposal-creating handoffs narrower than suggestions. Adoption,
  metadata-apply, and SEO submissions create Core proposals and stayed
  `manage_options`; suggestion-only flows, including the writing-pack draft
  stage (plain text, never saved or inserted by Toolbox), opened to
  `edit_posts`.
- Host filters stay authoritative. Scoped defaults only change the value
  passed into `npcink_toolbox_rest_permission`; a host returning `false`
  re-tightens any scope, including the relaxed ones. A relaxation change must
  never weaken that channel or make any route public or anonymous.

## Test Rules

- Permission probes go tautological behind earlier gates. Routes guarded by
  `requires_present_admin_ui()` deny every nonce-less request before the
  capability decision runs, so a denied-probe assertion proves nothing about
  the capability map. Pin scope-to-capability mappings directly through
  `default_capability_for_scope()` and probe only routes whose gate order
  lets the capability decision decide.
- Stub `current_user_can` by capability set and honor object ids. The
  behavior suite simulates roles (`edit_posts`-only, administrator, none)
  with an object-aware `edit_post` map, so the `format_content` probe proves
  own-post pass and foreign-post denial.
- Keep the machine contracts in the same change.
  `docs/route-boundary-table.json` scope and `default_capability`, the
  `tests/run.php` static map, and the scoped-permissions doc matrix must
  agree, or the gate fails.

## Process Lessons

- Decision commit before implementation kept the boundary reviewable: ADR-018
  landed first with per-intent rulings and rejected alternatives, then code.
  Mid-rollout amendments (the `image_source` scope) went back into the ADR
  and docs in the same commit as the code that depended on them.
- Document UX seams instead of hiding them. Admin-gated handoff buttons stay
  visible to editor-role users and fail with permission errors; ADR-018
  records the seam and defers per-role JavaScript gating. Hiding buttons
  client-side would mask the server boundary rather than document it.
- Mixed worktrees: a maintainer commit (provider-client assertion-source
  aggregation) landed on the session branch mid-session. Editing-tool
  file-state anomalies were the detection signal; verify with `git log` on
  the branch before staging, never absorb or revert a foreign commit
  silently, and disclose the ride-along in the PR body.
- Advisory review earns its cost. The pre-merge review caught three real
  defects (modal transport mismatch, comment/attachment object reads,
  tautological probe); the remaining findings pointed at pre-existing code
  and were recorded as accepted with reasons in the PR.

## Open Follow-Ups

Recorded in the roadmap Deferred Decisions: relaxing proposal-submitting
handoffs for editor roles after Core policy review; an ability-side or
app-key scoped authorization model beyond the host-mediated filters; and
per-role sidebar gating as UX polish.

## Verification Record

`composer validate --no-check-publish`, `composer test:all` (3622 static
contract assertions), `composer check:wporg`, and two advisory
`ocr review` passes were green on the merged revision (`26e2bbf`); PR #157
squash-merged with both required checks successful.
