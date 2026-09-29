# Scoped Permissions First Version

Status: implementation guide for host integrations.

Toolbox derives every REST route and ability from a `cap.toolbox.*` scope and
maps each scope to a default WordPress capability. Most scopes keep
`manage_options`; ADR-018 relaxes only the editor suggestion and Agent
feedback scopes to `edit_posts`. Scoped permissions are otherwise a host
integration contract: Core, an app-key host, or a trusted site integration
may use the permission filters to grant narrower or broader access to
selected routes or abilities.

The filters are:

- `npcink_toolbox_rest_permission( $allowed, $request, $required_scope, $route )`
- `npcink_toolbox_ability_permission( $allowed, $ability_id, $required_scope )`

The first version must not create public routes, anonymous access, direct
WordPress writes, or a second approval store.

## Default Capability Map

`Rest_Controller::default_capability_for_scope()` is the single decision
point; the editor sidebar visibility check reuses it.

| Scope | Default capability |
| --- | --- |
| `cap.toolbox.editor_suggest` | `edit_posts` (ADR-018) |
| `cap.toolbox.feedback.write` | `edit_posts` (ADR-018) |
| every other `cap.toolbox.*` scope | `manage_options` |
| `cap.toolbox.admin` fallback (unknown routes) | `manage_options`, fail closed |

## Scope Matrix

For per-route scope, owner, and write-posture metadata, use the
machine-readable [Route Boundary Table](route-boundary-table.json). This matrix
groups routes by scope for host integration planning.
For per-ability scope, owner, provider execution, and write posture, use the
machine-readable [Ability Boundary Table](ability-boundary-table.json).
For Cloud and Cloud Addon bridge ownership, contract versions, provider-secret
ownership, and runtime/write posture, use the machine-readable
[Cloud Bridge Contract Table](cloud-bridge-contract-table.json).

| Scope | REST routes | Ability examples | Notes |
| --- | --- | --- | --- |
| `cap.toolbox.status.read` | `/status` | none | Readiness only; no provider secrets or execution. |
| `cap.toolbox.image_source` | `/image-candidates`, `/ai/image-generation` | `npcink-toolbox/search-image-source`, `npcink-toolbox/generate-image` | Candidate generation only; no media import or featured-image write. |
| `cap.toolbox.vector_search` | `/vector-search` | none | REST compatibility pointer for Cloud-managed Site Knowledge; new Ability clients should use `npcink-toolbox/search-site-knowledge`. |
| `cap.toolbox.knowledge.read` | `/site-knowledge/status` | `npcink-toolbox/get-site-knowledge-status` | Read-only Cloud status projection. |
| `cap.toolbox.knowledge.search` | `/knowledge-search`, `/site-knowledge/search` | `npcink-toolbox/search-site-knowledge` | Semantic context candidates only. |
| `cap.toolbox.knowledge.sync` | `/site-knowledge/sync` | `npcink-toolbox/request-site-knowledge-sync` | Bounded public manifest submission; no indexing lifecycle ownership. |
| `cap.toolbox.web_search` | `/web-search/test`, `/web-search/diagnostics` | `npcink-toolbox/cloud-web-search` | Cloud-owned search execution; source candidates only. |
| `cap.toolbox.editor_suggest` | `/editor/content-support` | none | ADR-018 scoped default `edit_posts` for the editor suggestion-only sidebar intents; adoption and `/flows/*` handoffs stay `manage_options`. |
| `cap.toolbox.feedback.write` | `/agent-feedback` | none | Cloud eval metadata only, default `edit_posts` under ADR-018 so editor-role sidebar actions keep reporting; no approval or audit truth. |
| `cap.toolbox.feedback.read` | `/agent-feedback/summary` | none | Feedback summary display only. |
| `cap.toolbox.workflow_suggest` | `/ai/content-support`, `/ai/site-helpers`, `/flows/*`, `/media-derivative-handoff` | article, media, content context, and review-plan abilities | Suggestion or Core handoff artifacts only; `/editor/content-support` moved to `cap.toolbox.editor_suggest` by ADR-018. |
| `cap.toolbox.local_admin_consent` | `/local-admin-consent/featured-image` | none | Narrow current-post existing-attachment featured-image proof only. |
| `cap.toolbox.nightly_inspection` | `/nightly-inspection/*` | none | Cloud detail bridge and local preview metadata only. |
| `cap.toolbox.admin` | fallback | fallback | Fail closed for unknown future routes until explicitly mapped. |

## Host Rules

1. Grant the smallest scope needed for the caller.
2. Treat `cap.toolbox.workflow_suggest` and `cap.toolbox.editor_suggest` as
   proposal preparation and suggestion output, not write authorization.
3. Keep `cap.toolbox.local_admin_consent` restricted to present local
   administrators unless a later ADR narrows another write proof.
4. Do not use any Toolbox scope as Core approval, Adapter execution, media
   import, indexing lifecycle, quota, billing, or request-log authority.
5. The default capability map is only the unfiltered default. Returning
   `false` from `npcink_toolbox_rest_permission` re-tightens any scope,
   including the ADR-018 editor and feedback scopes.
6. When in doubt, leave `manage_options` as the effective gate.

## Verification

Run:

```bash
composer smoke:security-permission-debug
composer test:all
```

The smoke proves that route scopes and ability scopes reach host filters, that
unknown routes fall back to `cap.toolbox.admin`, and that raw debug payloads can
be force-disabled and redacted. The editor behavior test additionally proves
that an `edit_posts`-only user passes the scoped editor and feedback routes
while admin-gated scopes stay denied.
