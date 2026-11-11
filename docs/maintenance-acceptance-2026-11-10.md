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

At the initial diagnosis, Addon source
`includes/class-cloud-site-knowledge-change-bridge.php` had:

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

### Media source repair and historical writer limit

Source repair merged: Cloud #1400 (`9f7fe0916499f109c87f4f5e5739dffc0049d6c2`)
and Addon #347 (`a55ca329bad2ffdd5c7157dec3fcd5feb16d955a`). Their exact tested
source trees match their squash merges. All final-head CI checks passed.
The Addon release ZIP has 55 files and SHA-256
`af75b36799c3e02d2d933da78e31521a7a9fd7b6b3cbb9cdb166b238dff184e5`;
package verification, strict Plugin Check, PHPStan, full behavior tests and
Playground compatibility passed. A disposable real WordPress attachment deletion
also confirmed durable typed withdrawal after deletion, retained parent and zero
HTTP inside the hook. This is separate from Local MySQL and M4 evidence.

The operator explicitly authorized M4 synchronization and this exact 69-source
cleanup. Another Portal session occupied M4; synchronization waited until that
session ended, as requested. Governed promotion of Cloud #1400 now reports
`acceptance_state=accepted`, clean `master`, revision `9f7fe0916499`.

The installed hourly callback then automatically queued 69 missing-media
identities before the manual acceptance driver reached its queue stage. D0 run
`run_0b7366424200492682e2a3f0237066d3` succeeded, with zero provider calls.
Read-only before/after SQL and fresh signed typed presence confirm:

| Scope | Before | After | Evidence |
| --- | --- | --- | --- |
| Media documents / chunks | 71 / 71 | 2 / 2 | Exactly the 69 absent manifest IDs removed |
| Current indexed media | 2 | 2 | Complete document/chunk row hashes unchanged |
| Non-media documents | 2,060 | 2,060 | Complete row hash unchanged |
| Non-media chunks | 2,305 | 2,305 | Complete row hash unchanged |
| Local attachments | 26 | 26 | Local SQL inventory; no WordPress content writes |

The active M4 vector backend was independently read as `postgres_json`; its
vectors reside in the verified chunk rows. This does not establish external
Zilliz deletion or production behavior. The 24 local-only images were not
bulk indexed or recognized.

**Lifecycle confirmation remains pending.** The existing operation retains
55 old producer identities. Seventeen were positively witnessed, then
`run_ad5e8b35be5b4fd098edd855348cf06b` failed witness validation: it was
administratively marked `runtime.operator_stale_running_failed` on 2026-10-28,
not positively observed exiting the executor. Its result is purged. Two other
historical producers failed with `site_knowledge.image_embedding_source_unavailable`;
the failed-run allowlist was not broadened during acceptance. Do not clear these
records, manufacture a terminal witness, or declare compensation complete.
`media_cleanup_error=cloud_site_knowledge_writer_unknown` stays visible, while
scan-read error is empty. Legacy uncertainty and the unrelated previously
blocked article-exclusion intent were preserved.

The manual driver first rejected the unrelated blocked intent, then required a
pre-cleanup 71-ID manifest after the automatic callback had already deleted the
69 IDs. Both preflight rejections occurred before manual mutations; they are
not product failures or evidence of a complete first-pass acceptance run.
Bounded production-path callbacks then advanced known-writer checks and stopped
when progress ceased. The remaining action is evidence-based disposition of
historical writer uncertainty; it is separate from the verified data cleanup.
No new model evaluation, manual run-state override or production deployment was
performed. Editor adoption and final customer value acceptance remain separate.

## Media withdrawal compensation completion (2026-11-11)

This follow-up supersedes the pending-withdrawal state above. Addon
[#348](https://github.com/npcink/npcink-cloud-addon/pull/348) merged as
`dc0200128ea76545abccdb1c8056281b6d3c60bb`; all final-head CI checks passed.
The Local site's existing symlink loads that merged tree. Cloud required no
new deployment: M4 was accepted at `84d4bc55f1b4feb3cfeb5ec1cf1bcc990090400a`,
which includes #1400.

The original intent `215b6215-d227-4529-b64e-2212b969a17b` completed at
poll 28. The exact 69-ID absence manifest was rechecked against Local SQL
before each validation callback, and the driver refused any other runtime
mutation. Compensation `run_f76007cad4a44365b58082f0ce4971ab` succeeded at
2026-11-11 01:19:56 UTC with zero Provider calls. Fresh signed presence at
01:20:32 UTC confirms all 69 absent and both current media retained; cleanup
and scan errors are empty. No media suppression remains from this intent.

Before/after SQL snapshots match in full: two media documents/chunks,
2,060 non-media documents, 2,305 non-media chunks and all retained-row hashes.
Local attachments remain 26 with the same inventory hash. The unrelated
article-exclusion intent remains blocked at poll 121, unchanged.

Of 55 old producers, 52 have verified terminal receipts; three failed runs
retain **unverified exits**, separately from terminal witnesses. The
`legacy_unknown` flag remains true. Read-only checks found no queued/running
originals or linked descendants; M4 API/worker generations postdate the
October run. These observations do not manufacture executor-exit proof.
Current cleanup is complete; permanent exclusion is not claimed. A late write
is subject to the existing hourly eventual repair. Mismatched receipts,
transport failures and running writers still block advancement; explicit
exclusion semantics were not relaxed.

Local deterministic/PHPStan/WordPress.org/gettext gates, Playground and packaged
Plugin Check passed. Regression fixtures cover late writes before confirmation,
later hourly repair, mismatched site identity and persistent writes hitting
the outer poll cap. Local DeepSeek review was triaged against the actual caller
and immutable state; CI and installed acceptance are separate evidence.
Production, customer value and the 24 current unindexed attachments remain
unverified/out of scope.

Evidence bundle: `media-withdrawal-20261111/`, alongside the earlier bundle
listed below. It includes the merged PR rollup, source/package/review receipts,
signed presence, compensation, SQL hashes and final operation state.

## Evidence location

Local bundle:
`/Users/muze/.codex/visualizations/2026/11/09/01a1be25-a365-77f3-8bd3-1b96e4c53e21/maintenance-acceptance-20261110/`.
It contains the two live receipts, PC render receipt/screenshots, ZIP receipts,
package hash, sanitized drift counts/manifest hash and gate logs. No credentials
or raw host configuration are committed. Earlier failures remain in their
original dated records.

The media repair bundle is alongside it under `media-withdrawal-20261110/`:
final source/package/CI receipts, M4 accepted state, signed presence, before/after
counts and hashes, the automatic D0 run, local/Playground evidence and the
unresolved writer diagnosis. Raw configuration and credentials are excluded.
