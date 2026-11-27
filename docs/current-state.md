# Current State

Reviewed: 2026-11-27. This is the session map, not a behavior contract or a
release ledger. Follow [AGENTS.md](../AGENTS.md) for startup, read
[Boundary](boundary.md) before editing, and apply the
[development freeze](development-freeze-2026-11-09.md) before selecting work.
Use the owning contract below when summaries disagree.

## Product Snapshot

- **Editor:** Npcink Content Support defaults to `post` and `page` (host filter:
  `npcink_toolbox_editor_supported_post_types`). The maintained loop is formatting,
  Article Check with contextual ALT, site citations/related reading and image
  candidates. The [FAQ action](editor-body-faq-v1.md) previews suggestions for
  the current body and appends only author-selected items. The
  [credential check](article-credential-check-v1.md) is local; Article Check and
  FAQ also recheck derived outbound text in WordPress. Reviewed edits are saved
  only through the author's normal WordPress Update or Publish. The
  [single-code-block formatting pilot](editor-code-format-pilot-v1.md) is opt-in.
- **Admin:** Start, Site & Ask and Image Processing (`toolbox_tab=tools`). Start
  offers Ask and Image Processing, with lower-frequency actions under support
  details. Ordinary Site & Ask is question-first; explicit support and legacy
  links open the requested Site Check, Scheduled Review preview or Site Profile.
  Image Processing hosts single-image compression/conversion and watermark
  previews with reviewed Adapter/Core handoff; Toolkit owns media writes and
  recovery. Backend ALT review UI, legacy replacement routes and batch execution
  are not restored. See the [admin IA](admin-ia-site-and-ask-v1.md) and
  [single-image contract](processed-image-save-as-new-v1.md).
- **Pauses:** Cloud inspection, source drafting, new audio generation, advanced
  media batch/legacy execution and SEO/AEO/GEO specialist surfaces stay paused.
  Supported local checks, existing playback, recovery, ordinary editor tools
  and Site Knowledge remain separate. See [runtime pauses](reversible-feature-pauses-v1.md)
  and [specialist pause](seo-specialist-pause-implementation-2026-10-18.md).
  Retained routes, code and docs are not proof of enabled features.
- **Version and evidence:** GitHub source, local WordPress installation and the
  WordPress.org package are distinct. See [Version and Evidence Identities](version-and-evidence-identities.md).

## Repo Map

| Work area | Start with | Code entry |
| --- | --- | --- |
| Article editor | [Editor intent decision](editor-intent-convergence-decision.md), [Article Check](article-review-report-v1.md), [native editor write lane](decisions/ADR-006-native-editor-commit-and-governed-batch-handoff.md) | `includes/Rest_Editor_Content_Support.php`, `includes/Editor_*`, `assets/editor-content-support.js` and its helper directory |
| Smart Ask | [Operator scope](admin-operator-focus-2026-11-10.md), [deidentified boundary](deidentified-assistant-boundary-v1.md), [public site answers](site-content-answer-v1.md) | `includes/Smart_Ask.php`, `includes/Site_Content_Answer.php`, `includes/Rest_*_Insight_Bridges.php` |
| Site Check and preview | [Admin IA](admin-ia-site-and-ask-v1.md), [local fallback module](../modules/local-automation-runtime/README.md) | `includes/Admin_Page_Site_Ops_Panel.php`, `includes/Site_Ops_Insight_Builder.php`, `modules/local-automation-runtime/` |
| Image Processing | [Single-image contract](processed-image-save-as-new-v1.md), [runtime pauses](reversible-feature-pauses-v1.md) | `includes/Admin_Page.php`, `includes/Rest_Media_Derivative_Previews.php`, `assets/admin.js` |
| Permissions and provider work | [Boundary](boundary.md), [route table](route-boundary-table.json), [architecture](architecture.md) | `includes/Rest_Controller.php`, `includes/Rest_*_Bridges.php`, `includes/Provider_Client.php`, `includes/Provider_*_Service.php` |

## Verification

- `composer test` (or `php tests/run.php --quiet`) checks static contracts. Use
  the result for the revision under review rather than a fixed count on this page.
- `composer test:all` is the default gate. For PHPStan, PHPCS, exact-diff AI
  review, and the difference between CI jobs and merge-required checks, see
  [Development Workflow](development-workflow.md#ci-and-merge-protection) and the
  [active AI review standard](platform/ai-code-review-standard-v1.md).
- Installed-browser, live WordPress and Cloud acceptance smokes are separate.
  Verify the target and revision and follow the corresponding workflow before
  running them. No local check alone proves deployment or representative quality.

## Open Debt

[Known Issues](known-issues.md#open) is the open-debt ledger. Read only the
relevant entry and its evidence. In particular, Smart Ask's representative
production API acceptance is open; see the
[live acceptance blocker](smart-ask-audit-closeout-2026-11-26.md#live-api-acceptance-follow-up).
The visual audit's bounded fixes are merged; the
[disposition](visual-audit-maintenance-2026-11-27.md) identifies deferred or
unverified states. Neither outstanding acceptance nor file size authorizes
expansion, paused-feature restoration or a structural rewrite.

Use the [documentation catalog](catalog.md) for dated closeouts and evidence.
Keep new entries here limited to current defaults, code entry points, gates
and links; put dated results in their owning record.
