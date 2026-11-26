# Smart Ask audit closeout: 2026-11-26

Status: the review corrections are merged and verified on a local WordPress
installation. This is a dated closeout, not a production release, a Cloud
compatibility receipt, or an amendment to the [active freeze](development-freeze-2026-11-09.md).
The [Smart Ask contract](admin-operator-focus-2026-11-10.md) and
[de-identification boundary](deidentified-assistant-boundary-v1.md) remain authoritative.

## Review dispositions

| Findings | Result and limit |
| --- | --- |
| F1, F12: upload diagnosis and units | A local comparison is labeled `upload_limit`. It reads the current WordPress limit, not the environment pack; supported short, Chinese and decimal units are covered by behavior tests. Within-limit or unknown values are not diagnoses. |
| F2, F3: fallback and attempts | Fallback is off for the selector and all four summary requests. Three successful turns are a single-question UI limit on the current page, not REST rate limiting or a billing guarantee. |
| F4, F6, F9: test evidence | The README distinguishes static contracts from behavior. Site Check asserts the finding if its scan reports a positive internal-link issue count and requires its absence at zero. PO text and static contracts do not prove runtime behavior. |
| F5, F13, F15: toolchain | The affected Composer commands require `WP_PATH`; the Site Check browser smoke also requires an explicit site URL. Other machine-specific defaults are outside this change. PHPCompatibility stays at `9.3.5` rather than moving to prereleases, and is not a substitute for a target-version runtime check. PHPStan has **48** baseline rules; contract-pinned methods are retained. |
| F7, F8: pack contract and privacy | The environment contract documents `wp_upload_max_bytes` and `uploads_dir_writable` as separate inventory signals, neither of which proves a particular upload failure. The four compact outbound packs omit `site_id`; local packs and signed transport identity are unchanged. |
| F10: orientation | The published contracts are indexed and the stale uncommitted-work note is replaced by the merged state. No new task or freeze exception is created. |
| F11, F14: REST and audio authorization | Smart Ask reads dispatched values only for fields present in the JSON body and preserves rejection of unknown keys, explicit nulls and invalid aggregate lengths. The resolver remains the aggregate budget authority; other routes were not migrated. Audio meta checks `edit_post` for the specific object. |

Two additional review findings informed the tests: parse the JSON actually embedded
in the outbound message and prove that a forbidden key fails, rather than searching
a second encoding for quoted text; preserve the JSON key set when reading WordPress
REST parameters, so query values cannot supply an omitted body field.

## Verification and limits

- [#421](https://github.com/npcink/npcink-workflow-toolbox/pull/421) merged at
  `aa99e10ade9d266b385bac06ffafe055fc0cc81c`; [#422](https://github.com/npcink/npcink-workflow-toolbox/pull/422)
  merged at `b9332636ef1adf2b0d6b76bd462a7cf2709eb287`. The tested topic tree
  matched the final squash tree. Exact-diff review and required GitHub checks passed.
- PHP and JS behavior, 4,647 static contracts in `composer test`, `composer test:all`,
  PHPCS, PHPStan and installed-package Plugin Check passed. `msgfmt --check` validated
  the PO; a separate `msgfmt -o` compilation produced a byte-identical MO with 3,621
  translated messages. The review did not inspect the binary.
- The local installation used a complete archive of `b933263`, verified against
  579 Git blobs. WordPress loaded from the new slot and the HTTP response for
  `admin.js` matched it. The prior slot was retained for rollback.
- The follow-up reproduced a stale `admin.js` path in the admin page despite a
  correctly switched symlink. A site-specific PHP worker reload cleared it.
  Separately, the smoke now requires explicit `WP_PATH`/`WP_BASE_URL`,
  waits for observable URL and panel states, records script/request diagnostics,
  and cleans its temporary login helper through failure paths. The installed
  smoke passed against the active local snapshot, including the upload limit
  recheck and Site Check. It used fixture answers, not live Cloud; no business-
  content writes or public release were performed.

## Local snapshot and cleanup checklist

1. Confirm the site, its current plugin link and the exact merged commit. Compare
   the tested topic tree with that commit. Archive the commit into a unique slot
   outside the public tree; verify extraction against Git. Do not overlay a file
   from a checkout or confuse the local snapshot with the WordPress.org ZIP.
2. Retain the previous slot. Recheck the expected link target immediately before
   atomically renaming a temporary symlink in the same directory into place. Verify
   active plugin and actual PHP load path, hash served assets, and run the bounded
   installed smoke. If verification fails, roll back only if the link still points
   to your candidate. Confirm that temporary login files are removed even on failure.
3. For Squash cleanup, verify each PR's final head, its merge on `master`, source
   equivalence for the PR, current local and remote tips, and absence of open PRs
   and dirty worktrees for those refs. Preserve original topic history in a verified
   Git bundle. Delete only those exact, approved refs, checking the remote tip
   during deletion; never prune other sessions or remove the primary checkout.

The two application topic branches (`codex/smart-ask-follow-up` and
`codex/toolbox-audit-maintenance`) and this delivery's temporary maintenance
worktree were removed after verification. Other sessions' worktrees and the
current and rollback site slots were left in place.

## Follow-up maintenance

- The follow-up removed the clean worktrees and local and remote topic branches
  of [#417](https://github.com/npcink/npcink-workflow-toolbox/pull/417) and
  [#420](https://github.com/npcink/npcink-workflow-toolbox/pull/420). Each final
  PR head matched its squash tree; their original heads were saved in a verified
  bundle outside the worktrees. The three unrelated dirty worktrees, including
  the two locked ones, remain untouched pending owner disposition.
- [#425](https://github.com/npcink/npcink-workflow-toolbox/pull/425) ignores
  the retired OpenCodeReview action in Dependabot, not other dependencies.
  The workflow remains disabled and [#413](https://github.com/npcink/npcink-workflow-toolbox/pull/413)
  closed without merge. [#414](https://github.com/npcink/npcink-workflow-toolbox/pull/414)
  updated PHPStan independently of the baseline. Full behavior, PHPStan and
  PHPCS checks ran with the new lock file on the updated PR tree.
- [#426](https://github.com/npcink/npcink-workflow-toolbox/pull/426) requires
  an explicit WordPress root for all four pack QA scripts. Fake WP-CLI proves
  rejection and argument forwarding without a Cloud call. The
  [real API checklist](known-issues.md#smart-ask-third-party-api-acceptance-checklist)
  stays open until the operator identifies the test site, model and budget.
- The Site Check builder fixture now tests both an available graph scan with
  zero issues and a positive scan. That repair narrowed the PHPStan baseline
  from 48 to 46 entries; contract-pinned methods remain. Installed browser smoke
  still observes its chosen site's actual sample. No live content or installed
  snapshot was changed for these maintenance checks.
- Nine additional optional browser smokes now validate their explicit WordPress
  target before side effects and use no site-specific CLI runtime defaults. The
  [development workflow](development-workflow.md) documents the configuration.
  The deterministic gate checks executable entry points with missing targets and
  a fake CLI; it does not certify any installed UI or provider response.
