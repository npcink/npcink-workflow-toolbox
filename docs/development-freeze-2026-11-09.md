# Development Freeze And Maintenance Scope — 2026-11-09

Status: accepted by the operator on 2026-11-09 (Asia/Shanghai); active until a
specific later operator decision changes the scope. No automatic expiry.

## Decision And Purpose

Concentrate Toolbox work on the everyday usability of the existing article
editor loop: review, format, choose links and images, fill missing ALT, and
persist reviewed editor state through normal WordPress save. Fix concrete
failures in that loop; keep a stable version when no new problem is observed.

The implementation already covers several product lines, while representative
quality, natural adoption and production acceptance remain separate from
developer checks. Stop adding maintenance surface or repeating evaluation
merely because those claims are still unproven.

This is a development-priority decision. Existing available entrypoints,
preferences, history and contracts retain their current permissions. Existing
runtime pauses and default-hidden entries stay in effect. This decision makes
no feature removal, data migration, deployment or release.
[Boundary](boundary.md) remains the hard-block authority.

## Scope Matrix

| Feature or workstream | Disposition | Permitted follow-up |
| --- | --- | --- |
| Article preflight, read-only language/logic/context hints and formatting | Maintain the current editor loop | Reproduce concrete failures or ordinary-use feedback; fix the affected behavior and verify preservation, freshness and native save as applicable. |
| Current-article missing ALT, image candidates and existing-media selection | Maintain existing use; freeze expansion | Fix safety, permissions, compatibility and blocking defects; preserve attribution and editor/governed adoption boundaries. |
| Related articles, internal links and Related reading | Keep usable; freeze ranking/model baseline and expansion | Apply the existing recommendation no-repeat rule and restart conditions; verify candidate quality separately from insertion safety. |
| Category/tag suggestions, site profile and Site Knowledge consumption | Maintenance only | Preserve current contracts and dependency health; no new vocabulary automation, local indexing or retrieval management. |
| Source-material writing packs and draft preview | Keep usable; freeze expansion | Maintain bounded review and empty-body editor adoption; no new writing platform, bulk drafting or CMS integration. |
| Smart Ask | Keep usable; freeze expansion | Retain four existing insight packs and the separate Public site content option; no new packs, automatic selection, multi-pack orchestration or multi-turn chat product. |
| Local Site Check and Scheduled Review preview | Keep usable; freeze expansion | Maintain bounded read-only checks; no new dashboards, scoring dimensions, crawlers or automation. |
| External research, image sources and Zhihu hot-topic widget | Maintenance only | Preserve existing consumers and transports; no new providers, topic channels or topic-planning system. |
| Media-library ALT and legacy comment/taxonomy/internal-link/flagged-media review sets | Freeze expansion; preserve current visibility | Maintain contracts and safe handoff; do not restore default-hidden cards or add bulk execution. |
| SEO/AEO/GEO specialist surfaces; Cloud inspection/site analysis; new audio; media optimization | Continue runtime pauses | Preserve history, existing audio playback, backups and recovery; restoration follows the pause contracts and a specific operator decision. |
| OSS/offload real operations and site-effect verification | Remain deferred | Resume only on an explicit operator request with the relevant owners and acceptance scope. |
| New models, rerankers, knowledge graphs, providers, CMS adapters, commercial fronts, dashboards and local large/multiple-image capacity campaigns | Freeze research and expansion | Reopen only for a concrete consumer need or changed evidence under the restart rules below. |
| File extraction, PHPStan debt, onboarding and translation polish | Demand-driven maintenance | Touch a justified dependency cluster or concrete usability/compatibility defect; no cleanup quota or standalone redesign campaign. |

Ordinary title/summary/translation/selected-text rewriting continues through
the WordPress AI plugin path. Do not restore Toolbox's retired generic editor
intents to duplicate it. Site Knowledge transport/indexing and provider runtime
stay with Addon/Cloud; governance and final writes retain their existing owners.
This governs Toolbox work and its handoff requests; it does not claim that
sibling repositories have adopted a new policy.

## Work Admission And Restart Rules

1. Continue security, authorization, privacy, data-integrity, necessary
   compatibility and release fixes. Concrete defects in supported behavior
   may be repaired without asking again for the freeze to be lifted.
2. Before another product-quality change, record the new question: a concrete
   accepted/rejected/missed suggestion, an editor failure, an actual consumer
   need, or a runtime/model/corpus/weight/contract revision that changes the
   evidence. Name the affected feature, owner and revision when relevant.
3. Use [Known Issues](known-issues.md) for the problem, evidence and next
   bounded action. Reuse existing receipts and run the smallest applicable
   regression plus required deterministic gates. Keep refactoring separate
   from behavior changes.
4. A changed runtime or feedback permits bounded investigation of maintained
   behavior. It does not unfreeze a roadmap. Expansion or restoration of
   paused/hidden surfaces requires a specific operator decision naming the
   scope, owner and completion evidence.
5. Missing user feedback, an old TODO, unproven general accuracy or a large
   source file alone is not a restart trigger. Do not schedule another model
   vote, fixed-sample campaign, corpus expansion or feedback dashboard to fill
   that absence. No mandatory human worksheet, pilot deadline or new telemetry
   collection requirement is introduced.

For recommendations, retain the existing
[evaluation disposition](recommendation-eval.md#internal-development-disposition--2026-11-02),
including `insufficient_real_gold`. Developer traffic, replays and AI agreement
do not establish natural adoption or independent human gold.

## Availability, Verification And Recovery

Runtime availability stays defined by [Reversible Feature Pauses](reversible-feature-pauses-v1.md),
the [SEO specialist pause](seo-specialist-pause-implementation-2026-10-18.md)
and the [admin visibility policy](admin-ia-site-and-ask-v1.md). Four insight
packs and public-content answers already exist; older instructions about not
opening a third pack now mean no further pack development, not removal or
fresh acceptance of existing packs.

Maintained changes keep current permission, native-editor and governed-handoff
boundaries. Required deterministic CI and exact-diff local AI review still
apply. Live model/browser/runtime checks run only when the affected scope needs
them. Record source, installed runtime, package, production and ordinary usage
separately; a documentation merge certifies neither deployment nor quality.

Record later scope changes here and synchronize active startup/product pages.
Runtime restoration needs a reviewed source change and fresh consumer
verification; a saved option or retained implementation is insufficient.
Preserve historical evidence and unfinished debt throughout the freeze.
