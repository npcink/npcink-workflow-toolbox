# Temporary image recovery PC receipt — 2026-11-13

Tier: material. Page model: one selected-image workbench with recovery status
and a secondary restore request. Environment: installed magick-ai.local,
WordPress 7.1.4, zh_CN, Chrome headless, deterministic Cloud/proposal/read
receipts. No production or M4 UI claim; human visual acceptance: pending.

Routes: /wp-admin/admin.php?page=npcink-toolbox&toolbox_tab=tools&toolbox_tool=
media-batch-optimize (compression), image-settings (watermark).
Viewports: PC 1440×1000 and PC narrow 1100×1000. Mobile/tablet excluded.

Information hierarchy before editing:

- Always visible: selected image, output format where applicable, deadline,
  cleanup exception/retained bytes, actual removed bytes, pending approval.
- On demand: existing More settings, watermark templates, historical cleanup
  settings. Restore is a secondary action beside selected-image status.
- Technical evidence: fixture logs and Core receipt links; hashes stay in tests
  and this receipt, rather than the working form.
- Duplicate removal: replaced blanket backup-storage copy with the seven-day
  rule; historical settings explicitly say legacy only. No extra backup page.

Source state (SHA-256; final UI blobs):

- `assets/admin.js`: `43da61918628835173ede2f0187f034c7dfa05e40a86ea821bf7b5235359cd55`
- `includes/Admin_Page.php`: `ea194d8e867056897c3188b56abe3fa4a556e5f95597816a7d0d1eaa89fb6647`
- `languages/npcink-workflow-toolbox-zh_CN-npcink-toolbox-admin.json`: `1c8565532b9705d29be3fe6030f9d0bcf7047f124e0888f5927fae0aecb938c3`
- `tests/smoke-processed-image-browser.mjs`: `a497e974915f914f0d9c9cf48016ab75492fee51f9f4485cf8b3dc83f5b8d63f`

Deterministic browser: tests/smoke-processed-image-browser.mjs passes. States:
no managed backup, active deadline, restored with previous-version deadline,
cleaned with 2.00 KB actual bytes, cleaned with explicit 0 B, expired blocked with reference/delete failure
and retained bytes, unavailable read and successful retry, restore-proposal
pending, both task previews/default import/checked adoption. Checked adoption
passes temporary_7_days.v1; restore sends dry-run/commit=false only. No approval,
preflight, execution or batch request is made; original bytes stay identical.

| Rule | Result | Evidence |
| --- | --- | --- |
| 1 route/page model | pass | Both single-image tasks rendered; selected-image recovery is inline. |
| 2 viewport | pass | 1440×1000 and 1100×1000. |
| 3 horizontal overflow | pass | Browser scrollWidth assertions for both widths/tasks and recovery. |
| 4 one page title | pass | One Toolbox page title; task heading identifies the active workbench. |
| 5 first viewport | pass | Selection, status, restore, processing action and storage copy visible. |
| 6 primary action | pass | Preview primary; restore/retry secondary. Save replaces preview action after review. |
| 7 text status | pass | Deadline and retained/cleaned/failure text; no color-only state. |
| 8 object/action proximity | pass | Restore directly below selected-image deadline. |
| 9 focus/disabled/selection | pass | Native media selection, blue keyboard focus, disabled preview until selected. |
| 10 dialogs | not_applicable | No new dialog; existing WordPress media picker reused. |
| 11 feedback/retry context | pass | Read failure/retry preserves image; restore pending retains proposal link. |
| 12 console/network | pass | No page errors; one deliberate read 503 tests retry, no unexplained failure. |

Screenshots (local output/playwright/processed-image/): recovery-1440.png,
recovery-1100.png, recovery-restored.png, recovery-cleaned.png,
recovery-blocked.png; compression/watermark-1440.png and -1100.png, plus
*-replace-1440.png. Visual inspection of recovery-1440 and recovery-blocked
confirms readable status and object-local actions without overflow or extra cards.
Five-second scan: selected image → deadline/exception → restore or preview.
No new golden baseline. Review items: human visual acceptance remains pending;
these deterministic states do not prove natural seven-day WP-Cron timing.
