# ADR-018: Scoped Editor Permissions For The Content-Support Sidebar

## Status

Accepted.

## Date

2026-09-29.

## Context

Every first-version Toolbox surface (admin pages, editor sidebar, dashboard
widget, REST routes) is gated by `manage_options`, and the docs explicitly
defer editor-role (`edit_posts`) access until a scoped host authorization model
is intentionally designed. This ADR is that design.

The **Npcink Content Support** post editor sidebar is the highest-frequency
Toolbox surface, and its default buttons are suggestion-only: publish
preflight, existing-category and existing-tag candidates, internal-link
candidates, image candidates, current-article contextual ALT review,
selected-paragraph review, title/summary suggestions, local progressive
recommendations, the URL/manual writing-pack review flow, and the `format_content`
validated formatting candidate. Under ADR-006, ADR-013, and ADR-014 the only
commit lane these flows touch is the author's own current-article visible
editor state, persisted solely by the author's native WordPress Publish or
Update.

Administrator-only gating blocks the primary persona (working editors and
authors) from the surface designed for them, while every governed write path
(Core proposals, Adapter execution, Toolkit writes, media and settings
mutation) is independently authorized elsewhere.

The host integration contracts already exist: every REST route derives a
`cap.toolbox.*` scope through `Rest_Controller::rest_route_scope()`, and
`npcink_toolbox_rest_permission` / `npcink_toolbox_ability_permission` are the
authoritative host channel for tightening or broadening access.

## Decision

### Scope-to-default-capability map

`permission()` now maps the route scope to the default WordPress capability
instead of hardcoding `manage_options`:

| Scope | Routes | Default capability |
| --- | --- | --- |
| `cap.toolbox.editor_suggest` (new) | `/editor/content-support` | `edit_posts` |
| `cap.toolbox.image_source` | `/image-candidates`, `/ai/image-generation` | `edit_posts` |
| `cap.toolbox.feedback.write` | `/agent-feedback` | `edit_posts` |
| every other scope (`workflow_suggest`, `image_adoption`, `local_admin_consent`, `nightly_inspection`, knowledge/web/status/feedback-read scopes) | all `/flows/*` and remaining `/ai/*` routes, media, Site Check, status, and sync routes | `manage_options` (unchanged) |
| `cap.toolbox.admin` fallback | unknown routes | `manage_options` (fail closed, unchanged) |

The editor sidebar script registration and visibility check use the same
scope decision through one shared helper; the sidebar no longer keeps a
separate `manage_options` copy.

### Per-intent rulings for the editor route

Open to `edit_posts`:

- All suggestion-only intents: `publish_preflight`, `category_suggestions`,
  `tag_suggestions`, `taxonomy_tags`, `summary_suggestions`,
  `summary_terms_optimization`, `internal_links`, `image_candidates`,
  `image_alt_suggestions`, `polish_notes`, `title_suggestions`,
  `article_outline`, `article_checkup`, `discoverability`, `writing_support`,
  `comment_reply_suggestion`, `progressive_recommendations`, the compatibility
  narration/audio-summary intents, and `format_content` (the formatting
  candidate still additionally requires `edit_post` for the target post).
- The writing-pack `extract` and `research_plan` stages and the `draft` stage.
  The draft stage returns a request-scoped, suggestion-only
  `article_draft_preview.v1`; it never saves, inserts, or publishes, and the
  optional empty-body Gutenberg load is an author-visible
  `native_editor_commit` under ADR-006. An author who already holds native
  write authority over their own draft gains no additional write power.

Kept at `manage_options`:

- SEO, article-audio, and image adoption submissions, and every `/flows/*`,
  `/ai/*`, `/local-admin-consent/*`, media-optimization, and media-derivative
  route. Adoption and metadata-apply submissions create Core proposals (and
  the single-post SEO lane treats the author click as the approval step for
  execution after the next native save), so they stay administrator-facing in
  the first scoped version. Because those routes remain admin-gated,
  editor-role users see the corresponding sidebar actions but receive
  permission errors when submitting them; relaxing proposal-submitting
  handoffs is deferred until Core policy for editor-role proposals is
  reviewed.
- Admin pages, Site Check, media optimization, local-admin-consent, nightly
  inspection, Site Knowledge, web-search, image-source, and status routes.
- The dashboard widget (unchanged).

`/agent-feedback` is opened to `edit_posts` because the sidebar sends silent
metadata-only Agent feedback for successful actions and swallows failures; an
admin-only default would silently drop eval coverage for editor-role actions.
The feedback contract already forbids article body text, prompts, user email,
provider secrets, free-form notes, and any write authorization.

`cap.toolbox.image_source` is opened to `edit_posts` because the editor
image-source modal — the product surface for the `image_candidates` suggestion
flow this ADR opens — calls `/image-candidates` directly for its primary
search and completion requests. Both routes in the scope return
candidate-only `image_candidate.v1` evidence with attribution, license-review
state, and Unsplash download tracking preserved; `/ai/image-generation` is the
same modal's reviewed-prompt hosted candidate request and stays a candidate
normalization seam (Cloud owns generation runtime, model routing, and quota;
Toolbox owns neither). Media import, featured-image adoption, and every other
durable write keep the governed Adapter/Core/Toolkit path, and the admin
image tools stay behind the `manage_options` admin menu capability.

Object-level guards keep server-side object reads inside the editor route
object-authorized now that the route accepts `edit_posts` users:
`comment_reply_suggestion` reads a stored comment only when the user has
`moderate_comments` and the comment belongs to the current post, otherwise it
falls back to operator-supplied or selected text; attachment metadata
resolution (featured image, media items, and the media-library prefetch)
requires `upload_files`, matching WordPress core media visibility, so lower
roles cannot enumerate comment content or attachment metadata by id.

### Filters stay authoritative

The scoped map only changes the default value passed into
`npcink_toolbox_rest_permission`. A host may still return `false` to
re-tighten any scope, including the editor scope. Scoped defaults are a
relaxation of the unfiltered default, not a bypass of host authorization or
Core governance. No route becomes public or anonymous, and no write authority
changes: the relaxed surfaces stay suggestion-only and final writes remain
Core-governed.

Abilities are unchanged: external AI and app-key callers keep the
host-mediated `npcink_toolbox_ability_permission` channel with the
`manage_options` default. A separate ability-side scoped model is a deferred
decision.

### Compatibility note

`/editor/content-support` previously derived
`cap.toolbox.workflow_suggest`. Host filters keying on that scope for the
editor route must follow it to `cap.toolbox.editor_suggest`. The project is
pre-release with no external callers to migrate (the ADR-017 precedent), and
the route boundary table plus static contracts are updated in the same
change.

## Alternatives Considered

### Keep administrator-only until a full role model exists

Pros: minimal change. Cons: the primary persona cannot use the surface built
for them, and the deferral has no defined trigger. Rejected.

### Relax the whole `cap.toolbox.workflow_suggest` scope

Would open `/ai/*` and every `/flows/*` planning route (article plans,
adoption plans, metadata apply plans, media derivative handoffs) to editors.
That is broader than the sidebar need and includes proposal-creating
handoffs. Rejected.

### Gate admin-flow sidebar actions per role in JavaScript

Localizing a capability flag and hiding adoption/handoff buttons for
editor-role users would mask the permission seam instead of documenting it.
The server-side boundary stays authoritative either way; the first scoped
version documents that those actions surface permission errors for
editor-role users. Deferred as UX polish.

### Split capabilities per intent inside the editor route

Per-intent permission checks inside one route would duplicate the intent
allowlist in a second place and complicate host filters. Every intent on the
relaxed route is suggestion-only or a native-editor action, so the per-item
rulings above are recorded here instead. Rejected.

## Consequences

- Users with `edit_posts` (administrators, editors, authors) see and can run
  the Content Support sidebar and its suggestion-only flows, including the
  writing-pack draft stage and `format_content`; other roles do not see it.
- Editor-role users remain denied on adoption/metadata handoff submissions,
  remaining `/ai/*` routes, admin pages, media optimization,
  local-admin-consent, Site Check, and the dashboard widget; those sidebar
  actions surface permission errors for them until the deferred handoff
  decision.
- The editor route's host-filter scope changes to `cap.toolbox.editor_suggest`,
  and `/image-candidates` plus `/ai/image-generation` keep
  `cap.toolbox.image_source` while inheriting its relaxed default;
  `docs/route-boundary-table.json`, `docs/scoped-permissions-first-version.md`,
  the README REST section, and the boundary/architecture docs are updated in
  the same change.
- `tests/run.php` pins the scope-to-capability map, the shared sidebar gate,
  and the unchanged admin gates; the editor behavior test pins editor-pass
  and admin-gated-denied permission outcomes with a capability-stubbed
  `current_user_can`.
- Remaining deferred decisions: relaxing proposal-submitting handoffs for
  editor roles, and an ability-side or app-key scoped authorization model.
