# Toolbox Visual Audit Maintenance: 2026-11-27

Source: [2026-11-26 visual review](https://npcink-workflow-toolbox-zwro.pages.grok.bot/).
The maintenance baseline is `9ce171226abd98a2ce22ae19b9329ac94af6bf9e`.
The report is a visual snapshot, not a current behavior contract. The
operator approved bounded admin and article-sidebar repairs, not a redesign
or reactivation of paused features.

| Audit area | Disposition |
| --- | --- |
| Site Check, Scheduled Review preview and Site Profile | Repaired. Support and legacy links put the requested task in the first viewport; ordinary Site & Ask remains a question-first page. The check result still opens Ask with an editable seed. |
| Site Check report | Repaired. The readiness row no longer repeats the Cloud label. A generated report has one local rescan action, while individual finding actions stay on their cards. Rescanning is not a saved completion record. |
| Npcink AI suite overview | Repaired. Rows stack through 480px; wider viewports keep the table. Column and row header markup and entry links are retained. Only the stylesheet is enqueued for the overview. |
| Article Check sidebar and contextual ALT | Repaired. The two-line preview names text, image ALT and Cloud; keyboard disclosure retains the bounded text-transfer, read-only, review-limit and manual-save explanation in both locales. The affected review labels are 12px. |
| Start button hierarchy, H1 translation and shared tokens | Deferred. Do not start a broad redesign or token migration from this snapshot. |
| Mobile editor sidebar entry and image compare/restore | Not verified or changed in this slice. Verification of an expandable disclosure does not establish mobile entry availability or a working media lifecycle. |

The [admin contract](admin-ia-site-and-ask-v1.md#支持页任务优先2026-11-27)
and [editor decision](editor-intent-convergence-decision.md#2026-11-27-article-check-sidebar-readability)
record current behavior. No scan rule, REST entry, capability, Cloud input,
media action, or WordPress save path changes. Hidden defaults and runtime
pauses remain in force.

## Verification And Remaining Scope

- The isolated WordPress/SQLite installation with Toolkit exercised overview
  widths of 390/480/782/1440 CSS pixels and the Site Check support, legacy
  deep links, local fixture scan, single rescan and seeded Ask flow. Editor
  keyboard disclosure was exercised in English and Simplified Chinese.
- See the PR verification for the exact submitted source and CI result.
  Checked-in regressions are in `tests/run.php`,
  `tests/smoke-site-ops-insights-browser.mjs` and
  `tests/smoke-article-language-review-browser.mjs`. The browser scripts are
  optional installed checks, separate from `composer test:all`.
- No real Cloud model, shared WordPress environment, physical mobile device,
  production package or deployment was verified. The separate
  [Smart Ask acceptance blocker](smart-ask-audit-closeout-2026-11-26.md#live-api-acceptance-follow-up)
  remains open.
- Follow the [known-issues entry](known-issues.md#open) for deferred checks and
  the [visual audit workflow](development-workflow.md#evidence-rules-for-visual-audits)
  for a future supported regression. No new evaluation run is scheduled merely
  to close this audit.
