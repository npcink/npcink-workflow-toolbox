# Translation Source Language Policy

Status: active since the 2026-10 UX hardening pass.

## Problem

Toolbox historically mixes msgid source languages. PHP and the editor
content-support script mostly use English msgids with a complete zh_CN
catalog, while `assets/admin.js` and several PHP files (Dashboard Widget,
parts of `Rest_Controller.php`, `Hot_Topic_Pool.php`) carry Chinese-source
msgids. Consequences:

- English and other WordPress.org locales render untranslated Chinese
  fragments in admin surfaces, the dashboard widget, and REST messages.
- The `.pot` template is not a usable translation base for any locale other
  than zh_CN.
- `tests/run.php` must keep two coverage conventions in parallel.

## Policy

1. All new user-visible strings — PHP `__()`, JS `__()`, and `t()` — use
   English msgids with the `npcink-workflow-toolbox` text domain, regardless
   of the surface. This has been the rule for everything merged since the
   2026-10 hardening pass.
2. Chinese-source msgids in existing code migrate to English msgids file by
   file. Each migration updates the source, adds the English msgid with its
   zh_CN msgstr to `languages/npcink-workflow-toolbox-zh_CN.po`, recompiles
   the `.mo`, updates the per-handle JED files, and updates any static
   contract in `tests/run.php` that pinned the old msgid — in the same
   change.
3. Migration order follows user visibility for non-zh_CN visitors:
   `includes/Dashboard_Widget.php` and `includes/Hot_Topic_Pool.php` first,
   then `assets/admin.js` Chinese-source strings, then the remaining
   `Rest_Controller.php` Chinese msgids.
4. Do not bulk-rename msgids opportunistically inside unrelated feature
   PRs; keep each migration a focused change so catalog diffs stay
   reviewable.

## Verification

- `composer test:all` includes per-file literal coverage contracts: every
  literal `__()`/`t()` string in `Admin_Page.php`, `Abilities.php`,
  `Ability_Surface_Metadata.php`, `Editor_Content_Format.php`, `admin.js`,
  and `editor-content-format.js` must exist in the bundled zh_CN catalog
  files.
- Regenerate catalogs after string changes:
  `wp i18n make-pot . languages/npcink-workflow-toolbox.pot --domain=npcink-workflow-toolbox --exclude=/build,/tests,/docs,/scripts,/wporg-assets`
  then `msgfmt --check --output-file=languages/npcink-workflow-toolbox-zh_CN.mo languages/npcink-workflow-toolbox-zh_CN.po`.
  JED files are hand-maintained single-line-array JSON
  (`"msgid": ["msgstr"]`); keep that formatting so substring contracts
  keep working.
