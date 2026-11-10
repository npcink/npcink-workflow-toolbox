# Existing-function maintenance acceptance — 2026-11-10

Scope: question-only Smart Ask background preparation and final Toolbox ZIP
installation checks. No new pack, collector, setting, chat store, write action,
model configuration or paused-feature restoration. Media drift is diagnosed
below; its repair is not represented as delivered.

## Smart Ask

Cloud #1397 repairs the existing `text.ai` candidate lookup. Read-only M4 status
reports accepted revision `45e3a24a0d5630b1998420a08b2173744d59308a`, containing
that repair. This session did not sync or modify the M4 host.

| Bounded real question | Selected existing background | Decision / answer runs | Result |
| --- | --- | --- | --- |
| Published article category distribution | content structure | `run_ed86442ac306449c9c0d9840c86d70e1` / `run_c3284b82bee2403e8438a726be87fd8b` | HTTP 200; 8.149s; scope 60 of 1,998 public items stated |
| Slow WordPress admin: first troubleshooting step | environment/configuration inventory | `run_1d51d94a42b34338b9e8235ea1ee725c` / `run_79da4b407c4249a4b892140243e2a193` | HTTP 200; 14.639s; existing plugin/cache/debug background used |

Each has one question-only no-store decision and one existing result-only answer,
zero retries/fallbacks, no WordPress write and no proposal. Requests were issued
through installed WordPress REST dispatch from WP-CLI. PHP configuration in the
second result belongs to that CLI context, not independently verified Web PHP.
Two successful cases establish limited wiring, not general model quality or
production third-party API acceptance. No further local-model trial is scheduled.

Existing prompts now prefer automatic collection over asking users to copy site
information, route concrete environment symptoms to the existing inventory,
answer the actual question first, and distinguish known facts from possibilities.
Only an unavailable essential observation merits clarification. Existing privacy,
sampling and read-only constraints remain unchanged.

Installed-page PC rendering: zh_CN/en_US at 1440 and 900 pixels; checked intro,
pending/stop, keyboard submit, answer basis and horizontal overflow. Result
rendering replays the saved real environment response with a controlled REST
interceptor; it is not another live model request. Screenshots were visually
inspected. Temporary login/locale helpers and sessions were removed.

## Final ZIP

Actual `build/npcink-workflow-toolbox.zip`, SHA-256
`b578abe95ce45f37de3ce273387ed7594e76c767a65fc8f0cda432bcb71177f9`, contains 118 runtime files, excluding tests, docs, vendor, logs
and evidence. Frozen implementations remain packaged behind existing release
policies; packaging does not enable them.

WordPress Playground CLI 3.1.43, WordPress 7.0.4, PHP 8.2.30, disposable SQLite:

- Fresh ZIP install/activation and candidate question-only intro pass.
- Overwrite from `fe870ad3e5291c925d151abff8ac20bb8d396f99` runtime-source ZIP to the candidate passes.
  This is a same-version development overwrite, not a historical stable-version
  migration or a WordPress.org release. Settings, site context, media settings,
  watermark templates and draft body remain identical.
- All existing pause flags remain set. Source-adaptation invocation returns
  `npcink_toolbox_feature_paused` / 409 before any HTTP request. Default rendering
  omits the source selector, profile form and inspection-preview tab.
- This fixture had no Addon credentials or live Cloud connection. It does not
  establish production installation, MySQL migration or every editor entry's
  visual acceptance; existing deterministic pause regressions cover those gates.

Local `composer test:all`, PHPStan, scoped PHPCS, WordPress.org guard, release
inventory and installed-package Plugin Check pass. Plugin Check builds a separate
107-file source distribution without generated minified assets; the final ZIP's
118-file inventory is independently checked. CI/merge are separate PR states;
no production deployment or stable release occurred.

## Media source drift

The current WordPress site is the sole media source. Same-site read-only
comparison: 26 local attachments, 71 Cloud media documents, 2 matching IDs,
69 Cloud-only IDs and 24 local-only IDs; all 26 local files are readable.
This proves stale projection data, not the exact historical deletion/restore event.

Addon source `includes/class-cloud-site-knowledge-change-bridge.php` has:

1. `register()` attachment add/edit/meta hooks, but no attachment-delete hook.
2. `handle_removed_post()` delegates only for public posts/pages;
   `is_public_post()` explicitly excludes attachments.
3. `query_reconciliation_posts()` scans existing publish/inherit rows by modified
   cursor; missing/deleted rows cannot enter that scan.
4. Cloud refresh intentionally preserves omitted sources, as required by
   refresh safety: partial delivery, quotas and collection failures must not
   delete previous valid data. It needs explicit typed deletion or a verified
   complete reconciliation; omission alone is not proof.

Therefore, cleaning the 69 old records alone cannot close the defect. The repair
must deliver typed media withdrawals and recover missed notifications, using
WordPress source evidence and Cloud-owned index execution. Incomplete pages,
read errors, disabled delivery and unreachable sites must never imply deletion;
preserve non-media and other sites, and handle uncertain/in-flight deliveries.
No new operator selector or manual routine cleanup is warranted.

No media records were deleted in this session. The earlier exact development
cleanup question is still awaiting an explicit answer. Root-cause repair,
cleanup, successful current-media adoption/save and production evidence remain
separate unfinished states in Known Issues.

## Evidence location

Local bundle:
`/Users/muze/.codex/visualizations/2026/11/09/01a1be25-a365-77f3-8bd3-1b96e4c53e21/maintenance-acceptance-20261110/`.
It contains the two live receipts, PC render receipt/screenshots, ZIP receipts,
package hash, sanitized drift counts/manifest hash and gate logs. No credentials
or raw host configuration are committed. Earlier failures remain in their
original dated records.
