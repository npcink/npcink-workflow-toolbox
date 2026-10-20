# Reversible Feature Pauses v1

Status: development candidate, 2026-10-19. Not a WordPress.org release receipt.

## Withdrawn entrypoints

- Cloud inspection submission/retry and optional Site Check Cloud analysis.
  Local deterministic Site Check and local Scheduled Review remain usable.
- Article narration/audio-summary generation and regeneration. The REST guard
  precedes text-context preparation and preliminary summary generation;
  direct provider entrypoints also stop before hooks/Cloud calls. Existing
  adopted audio playback and user preferences remain.
- Media optimization workbench and optimize-selected-images bulk action,
  derivative handoff/preview/optimization planning, new manifest/batch creation,
  confirmation and per-item execution. Old bookmarks fall back to ALT review.
  Paused Toolbox planning abilities are omitted from both registration paths.

Source-controlled availability lives in Settings, separately from saved user
preferences. Old REST routes retain their permission checks and return
`npcink_toolbox_feature_paused` (409, `retryable=false`). They must not fall
back to another paid path. Cloud and Addon independently enforce availability.
Existing media snapshots, completed batches, backups, restoration, retention
cleanup and public content remain intact. Toolkit and Core keep their current
contracts and all final-write authority; this plugin adds no second registry.

## Retained scope and development freeze

Continue ordinary title/summary/translation, ALT and image assistance, safe
delivery, Site Knowledge sync/search, existing external search, necessary
monitoring, entitlement/billing integrity and first-party translation support.
Freeze expansion of ontology/knowledge graphs, backend/provider breadth,
advanced ranking campaigns, Agency/commercial fronts, additional dashboards
and upstream translation-shim coverage pending concrete user evidence.
The earlier SEO/AEO/GEO specialist pause remains effective.

Retained implementations, translations and contract tables document future
restoration; they are not active product promises. Do not delete history or
saved options. Restore a feature through a reviewed source change and fresh
consumer verification, not by re-enabling a saved option.


## Cross-repo对照 / 三仓对照

Availability is enforced **independently** in each repository (parallel lists,
not a shared constant package). Unpause publish order remains
**Cloud → Addon → Toolbox** so UI never advertises a path Cloud still rejects.
Do not restore SEO/AEO/GEO or the three runtime keys from documentation alone.

Authoritative task-id / runtime-key tables, error-code families, and the pause
smoke matrix row map live in the Addon inventory. Cloud and this file keep
sibling scopes for the same faces.

| Repo | Inventory |
| --- | --- |
| Addon | [`docs/reversible-feature-pauses-v1.md`](https://github.com/npcink/npcink-cloud-addon/blob/master/docs/reversible-feature-pauses-v1.md) — Tables A–C and matrix row map;对照 landed in [PR #309](https://github.com/npcink/npcink-cloud-addon/pull/309) (use that PR until merge). Local sibling: `../../npcink-cloud-addon/docs/reversible-feature-pauses-v1.md`. **Naming:** Addon has no `$paused_specialist_tasks`; the ten SEO/AEO/GEO ids live inside `is_task_paused()`. |
| Cloud | [`docs/reversible-feature-pauses-v1.md`](https://github.com/npcink/npcink-ai-cloud/blob/master/docs/reversible-feature-pauses-v1.md) — ordered pause batches and evidence. Local sibling: `../../npcink-ai-cloud/docs/reversible-feature-pauses-v1.md`. |
| Toolbox (this file) | Specialist surface: `Settings::DISCOVERABILITY_PAUSED` (whole-surface boolean, no per-task array). Runtime keys in `$paused_cloud_features`: `cloud_inspection`, `audio_generation`, `advanced_media`. Reject code: `npcink_toolbox_feature_paused` (409). |

### Pause smoke matrix v1 (row owners)

Every row must satisfy **entry not visible / not registered** and **execute
path returns the expected pause code**, with **zero outbound Cloud HTTP** on
Addon/Toolbox reject paths. “Visible but rejected” = fail.

| Rows | Owner |
| --- | --- |
| D1–D4, C1–C3, A1–A3, M1–M3 | Toolbox |
| D5–D6, C4–C5, A4, M4 | Addon |
| D7–D8, C6, A5, M5 | Cloud |

SEO specialist narrative for this repo:
[seo-specialist-pause-implementation-2026-10-18.md](seo-specialist-pause-implementation-2026-10-18.md).

## Evidence and rollback

Default-policy tests assert rejected work and zero provider calls; isolated
fixtures retain positive implementation, backup restore and cleanup coverage.
Evaluated editor JS confirms audio entries are absent and formatting remains.
The actual Local WordPress site resolves this checkout; its Site Check browser
smoke verifies absent Cloud analysis entrypoints and preserved local checks
without Cloud execution, Core proposals or WordPress content writes.

Revert the corresponding batch commits across Toolbox, Addon and Cloud after
rechecking current host contracts. Source changes, packaged plugins, M4
candidates, merged acceptance and production distribution are separate states.
