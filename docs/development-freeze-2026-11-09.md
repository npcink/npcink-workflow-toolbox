# Development Freeze And Maintenance Scope — 2026-11-09

Status: accepted by the operator on 2026-11-09 (Asia/Shanghai); active until a
specific later operator decision changes the scope. No automatic expiry.

## 2026-11-12 Contextual ALT entry amendment

The operator approved contracting backend ALT to a Media Library contextual
action. Default image tools show selection guidance, not the review workbench.
Existing selected-image/legacy tool URLs and a folded support link open it on
demand. Attachment IDs and bounded sampling move to support details; an empty
selection does not start sampling unless those sample options are explicitly
opened. Keep Cloud visual evidence, editable drafts, human confirmation,
missing-only Core handoff and editor contextual ALT. No approval/execution
shortcut, media write, new runtime, model or frozen-feature restoration.

## 2026-11-12 Site Check support-only amendment

The operator approved removing manual Site Check from the default working area
and the Start task cards. Folded Start support details retain a manual-report
link; explicit support and legacy check/preview URLs remain usable. Smart Ask
continues to consume the existing pack without requiring an operator scan.
Keep the underlying collectors, routes, permissions, settings, report actions
and runtime pauses. This changes presentation only, with no new checks, packs,
model requests, automatic inspections, writes or feature restoration. See
[the current admin visibility contract](admin-ia-site-and-ask-v1.md).

## 2026-11-10 Backend ALT visual-evidence amendment

The operator explicitly requires actual image recognition for backend ALT drafts.
The existing bounded `/ai/site-helpers` ALT intent reuses current fingerprint-matched
Cloud visual evidence or requests recognition for at most the selected review limit
(1–10 images). Metadata-rich images are not exempt. Metadata may identify and
contextualize an image but cannot supply an ALT candidate. Recognition failure or
incomplete results keep the affected row unavailable; no metadata-only success.
Retain editable drafts, human visual confirmation and missing-only Core proposals.
This does not reopen media optimization, change the editor contextual-ALT policy,
add providers/models/queues, or authorize production deployment. See
[the backend contract](media-alt-caption-review-set.md#backend-visual-evidence-requirement--2026-11-10).

## 2026-11-10 Operator-first admin amendment

The operator approved [the bounded admin convergence](admin-operator-focus-2026-11-10.md):
move profile/boundary/technical preferences and inspection preview out of the
ordinary workflow; stop treating optional profile completion or Cloud connection
as content findings. Replace the five-source selector with one free-form question,
one bounded no-store evidence decision and at most one existing read-only answer.
Clarify ambiguous requests; distinguish general guidance from site evidence.
This explicitly supersedes the earlier selector-preservation default, but does
not add packs, agents, queues, write authority or reopen other frozen work.
Internal-link/related-reading review retains product value; the hidden legacy
admin card stays hidden and expansion remains frozen. Existing editor consumers,
settings, contracts and recovery remain.

## 2026-11-10 Site Profile summary, batch 2

The operator requested the second presentation batch: show saved Site Profile
brief values as a compact summary, with explicit editing through a native
disclosure. Empty briefs and settings errors leave the form open. Preserve
existing field names, Settings API save/sanitize behavior, permissions and
unsaved edits; keep content boundaries and technical details folded. The
Start page, five Ask choices, runtime pauses, stored preferences and product
ownership remain. This does not expand features or restore paused entries.
See [admin IA](admin-ia-site-and-ask-v1.md#站点画像摘要第二批--2026-11-10).

## 2026-11-10 Admin entry contraction, batch 1

The operator explicitly approved a bounded admin presentation change:
remove the industry-specific Site Profile template button and duplicate Ask
shortcut; label Scheduled Review as a preview inside Site Check; collapse
watermark template management while keeping backup cleanup outside the fold.
The current-site profile draft action, contextual Check → Ask action, three
top tabs and all five Smart Ask choices remain. Retain stored settings,
template recovery implementation, backups, restore paths, permissions and
runtime pauses. This does not enable automatic inspections or media optimization.

This amendment changes only the named presentation defaults. It does not
authorize feature expansion or another evaluation campaign. The second batch
of profile summary/edit changes remains a separate slice.
See [admin IA](admin-ia-site-and-ask-v1.md#后台入口收缩第一批--2026-11-10).

## 2026-11-09 Frozen-feature release containment

Source-material drafting is now **server-paused**, in addition to being hidden
and development-frozen. The existing `source_adaptation_review` intent keeps its
ID and permission gate but returns `npcink_toolbox_feature_paused` (HTTP 409,
`feature=source_drafting`, `retryable=false`) before source reading, Site Knowledge
or hosted model work. Both hosted source-research and
`article_draft_from_writing_pack` calls enforce the same policy, including direct
provider calls. No fallback to another paid path is attempted.

Implementation, translations, historical data and existing governance handoffs
remain for recovery. The writing-pack UI/conversion module and its translation
catalog stay in the package but are not enqueued while paused. A few shared
state/default helpers remain in the main editor bundle; this is a bounded load
reduction, not a claim of zero dormant overhead. Generic source-reader abilities
remain available to other consumers. No saved setting can bypass this pause.
Restore only through an explicitly approved source change and fresh verification.
This is a Toolbox-local pause, not a claim that Cloud/Addon reject these tasks.

Release packaging excludes screenshots, local reports, caches, logs and archives.
Both the distribution copy and final ZIP must pass the runtime inventory guard;
see [release packaging](wordpress-org-submission.md#release-package). Frozen source
code is retained; development evidence is excluded. No production deployment or
WordPress.org publication is performed by these changes.

Same-day follow-up: the two Plugin Check errors and five warnings recorded
below are now repaired; installed distribution checks and six read-only host-
filter cases pass. See the [query-policy repair](wordpress-org-release-gate.md#plugin-check-query-policy-repair--2026-11-09).
The original failure receipt remains historical evidence; final candidate
installation/upgrade and publication are still separate from this repair.

### Containment verification — 2026-11-09

- `composer test:all` (4,572 static contracts plus behavior/JS/package gates),
  Composer validation, PHPCS, PHPStan and `composer check:wporg` pass.
- The actual release ZIP contains 117 runtime files and no local evidence,
  tests, vendor dependencies or output directories. ZIP size is 1,200,760 bytes;
  readable editor main JS decreases from 510,220 to 469,419 bytes (40,801 bytes).
  These byte counts are not a measured page-latency improvement.
- Installed `magick-ai.local` browser verification covers four default entries,
  twelve 1440/900px report/menu states, zero JS errors, absent writing-pack JS/JED,
  four real source-stage 409 responses, both direct hosted pause responses,
  zero outbound HTTP for paused calls and unchanged disposable-draft database
  state. The temporary draft, observer and session are cleaned up. No live model
  calls were made. Dormant recovery tests explicitly unpause in isolation.
- Installed `composer plugin-check:release` **fails** on two existing
  `SuppressFilters_suppress_filters` errors in content-structure/page-quality
  pack builders, plus five warnings. The same check against `origin/master`
  reproduces the identical findings. This slice does not alter pack query
  semantics; [Known Issues](known-issues.md) records the release blocker.
  Internal gates and package inventory success do not certify WordPress.org
  submission readiness. No production deployment or publication is performed.

## 2026-11-09 Article check convergence

The operator approved four default sidebar entries: formatting, **Article check**,
site citation suggestions and article images. Source-material drafting is hidden
and frozen; the retained callable IDs now return the pause response documented above.
Its data and compatibility code remain. Restore
its entry only after a specific operator decision tied to a concrete use case.

Article check keeps the `publish_preflight` intent and permissions. Its default
report leads with local/Cloud completion and partial/unavailable scope, then
current-article ALT review and source-backed findings. Long evidence is collapsed;
location only selects a verified current block. No result means neither approval
nor safety. The legacy metadata/readiness checklist is not rendered by this path;
missing excerpt is not a mandatory task. Generic tone, numerical-claim, length,
structure and formatting advice is paused. Local active checks cover repeated
Chinese punctuation only; Cloud retains its verified language/logic/context
contract. Unused private broad-rule implementations are removed; their public
section fields remain with `not_checked` and empty items. No automatic correction, save, publish, new mode, model or ranking change.

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
| Source-material writing packs and draft preview | Hide entry; server-pause generation; freeze development | Preserve IDs, implementation and existing data; restoration requires a concrete use case and explicit operator decision. |
| Smart Ask | Keep usable; freeze expansion | Retain four existing insight packs and the separate Public site content option; no new packs, automatic selection, multi-pack orchestration or multi-turn chat product. |
| Local Site Check and Scheduled Review preview | Support-only; keep usable; freeze expansion | Maintain bounded read-only checks; no new dashboards, scoring dimensions, crawlers or automation. |
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
