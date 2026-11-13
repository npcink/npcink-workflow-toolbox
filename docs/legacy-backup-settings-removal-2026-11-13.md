# Legacy backup settings removal — 2026-11-13

Tier: material, local Toolbox Image Processing form removal. The operator approved
removing the complete backup-settings section after reviewing its legacy-only
purpose. No replacement settings page or Cloud functionality is introduced.

## Decision and disposition ledger

| Content | Disposition / destination | Evidence |
| --- | --- | --- |
| Shared backup disclosure, cleanup selector and save button | Removed from both image tasks and their fallback URLs; no replacement UI | PHP renderer removal; installed route/no-JS checks |
| Shared-backup help copy and dedicated geometry rules | Removed; help now describes compression/watermark only | PHP/CSS and Chinese MO; screenshots |
| Selected-image seven-day deadline, restore proposal, retained/removed bytes and blocker reasons | Remain beside the selected image | Existing deterministic recovery browser states |
| Watermark template selection and management | Remain in the existing on-demand template section | Task-switching and unsaved-selection checks |
| Stored legacy options, per-record policy, policy filter, recovery and cleanup | Preserved in existing Settings/Plugin/Toolkit owners; no settings/database migration | Settings.php, Plugin.php and Toolkit unchanged; existing contracts |
| Technical evidence | This receipt and local browser logs; no technical IDs added to product UI | Hashes and evidence paths below |

Always visible: selected-image identity, processing controls, storage/approval facts
and applicable recovery state. On demand: existing More settings and watermark
templates. Technical evidence: this receipt and existing Core receipt links.
Deleted: the legacy backup form and repeated shared-backup help text.

## Local browser evidence

Environment: installed magick-ai.local, Chrome headless, zh_CN. Two peer task
routes use /wp-admin/admin.php?page=npcink-toolbox&toolbox_tab=tools with
toolbox_tool=media-batch-optimize or image-settings. Page model: one selected-image
workbench. PC viewports: 1440×1000 and 1100×1000; mobile/tablet are excluded.

Before source: master b5eb8c9. Same-state before/after captures are under ignored
output/playwright/legacy-backup-removal/: before- and after-compression-{1440,1100}.png,
before- and after-watermark-{1440,1100}.png. The after-recovery-1440.png,
after-recovery-1100.png and after-recovery-blocked.png retain deadline/actions and
blocked-state context. User screenshot supplied the expanded legacy form baseline.

Deterministic processing/recovery browser: pass, including preview, option
invalidation, default save-as-new, checked replacement, pending proposal, restore
request, blocked/cleaned/zero-byte state and read failure/retry. Original bytes are
unchanged; proposal responses are fixtures, not approved writes. Logs:
/tmp/remove-legacy-backup-before-browser.log and /tmp/remove-legacy-backup-after-browser.log.
Route/no-JS browser: pass on the final run. Default, retired ALT, media-library,
unknown and both image-task URLs contain no legacy backup controls. Task switching
preserves unsaved template selection; template management still opens. Native links
and template/backup absence work without JavaScript. Log:
/tmp/remove-legacy-backup-routes-browser-recheck.log. The first run completed those
checks but failed on the existing WordPress upload.php ClipboardJS undefined error
(previously recorded in the IA document); its failure log remains at
/tmp/remove-legacy-backup-routes-browser.log. It was not suppressed or counted as a
pass; the unchanged-code rerun completed without page errors.

| Visual rule | Result / evidence |
| --- | --- |
| 1 route/page model | pass — compression and watermark retain their workbenches |
| 2 PC viewport | pass — both declared widths |
| 3 horizontal overflow | pass — browser assertions for both tasks/widths |
| 4 page title | pass — shared Image Processing header; no backup section heading |
| 5 first viewport | pass — selected-image working form starts in the first viewport |
| 6 action hierarchy | pass — preview/save primary; restore/retry secondary |
| 7 text status | pass — deadline, retained/deleted bytes and failure reasons remain |
| 8 object/action proximity | pass — recovery remains inside selected-image form |
| 9 focus/disabled/selection | pass — existing option invalidation/disabled controls preserved |
| 10 dialogs | not_applicable — no dialog change |
| 11 context and retry | pass — read failure/retry preserves selected-image context |
| 12 console/network | pass — no unexplained page error; deliberate fixture read failure only |

Human visual acceptance: pending. M4: not_requested (local PHP/CSS only).
Production: not deployed. No natural seven-day Cron observation is claimed.
No new golden baseline, table, drawer, tab or shared layout primitive is added.
Visual inspection of after-compression-1440, after-watermark-1100 and
after-recovery-blocked confirms the removed block leaves no empty card and the
selected-image/status/action scan remains readable. Existing focus outlines,
selection and disabled controls remain distinct.

## Source verification

composer test:all, analyse:php, lint:standards, check:wporg and msgfmt --check pass.
No runtime JS, Settings.php, Plugin.php, Toolkit, Cloud or stored values changed.
The MO is compiled from the reviewed PO, not edited as an opaque binary.

## Source hashes

- `includes/Admin_Page.php`: `d42915cda89ee7004de8d422b1b42dc46e3025c92b74e49a6a4a58d02f8d71b0`
- `assets/admin.css`: `bf9f896e20ba7ddeb13dceea97d57cb1b44a51b8232b355b5516128982116fa9`
- `languages/npcink-workflow-toolbox-zh_CN.mo`: `d5ce997ef6809cd10a8e6d22ada366f1630752bc828127e83afb69a7b84838b7`
- `tests/smoke-media-alt-caption-browser.mjs`: `2bb076d6625b983b2439fbb827d543e73920bce40c5bc40a7c72f1516472189e`
