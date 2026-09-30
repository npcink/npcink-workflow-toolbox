# MCP Governance Surface Decision - 2026-09

Status: accepted 2026-09-30 with the resolution recorded below. The
governed MCP surface ships as an `mcp` subcommand of the existing
`@npcink/openclaw-adapter-cli` package in `npcink-ai-client-adapter`; no new
repository is chartered.

Source review: `npcink-ai-client-adapter`
`docs/adapter-positioning-notes-2026-09.md` Phase 3 and
`docs/threat-model.md` (boundary classes). This record follows the platform
authority rule: it does not fork Adapter, Core, or Toolkit contracts; it
records the placement decision and links to owners.

## Resolution (2026-09-30)

The operator accepted the governed MCP surface with these decisions:

| Question | Decision |
| --- | --- |
| Q1 ownership | No new repository. The surface is a stdio MCP server embedded as an `mcp` subcommand of the existing `@npcink/openclaw-adapter-cli` package, reusing existing local key-pair profiles and the Adapter REST channel. Operator rationale: the suite already spans multiple plugins; adding another repository would raise coordination cost without a concrete second-client demand. |
| Q2 tool scope | v0 exposes status, read, read-request, and propose tools only. Execute tools are deferred to a later phase and must go through approve-and-execute semantics with the `npcink.execute` key scope. |
| Q3 transport | stdio first, matching local clients and the existing CLI profile model. Streamable HTTP is deferred. |
| Q4 naming | Moot under Q1; the `npcink-mcp-gateway` working name is retired. |
| Coexistence | Phase 1 operator guidance only (do not expose write-class abilities through ungoverned MCP paths). Detection work is deferred. |
| Upstream proposal | Deferred until the governed loop is observable through MCP; the draft stays in this repository. |
| Release note | The CLI 0.3.0 package is intentionally not published to npm yet; it ships with the `mcp` subcommand in a later combined release. |

Implementation stays inside `npcink-ai-client-adapter`
`packages/adapter-cli`: client-side tooling only, no WordPress runtime
changes, no new final write path, and no change to the Adapter, Core,
Toolkit, or Toolbox charters. The option analysis below is kept as the
decision rationale.

## Background

The official
[WordPress MCP Adapter](https://developer.wordpress.org/news/2026/02/from-abilities-to-ai-agents-introducing-the-wordpress-mcp-adapter)
(announced 2026-02) exposes Abilities as MCP tools using WordPress-native
authentication. It has no approval primitive; its published security guidance
relies on permission callbacks, dedicated limited users, and preferring
read-only abilities.

The Npcink suite's governance loop (proposal, human approval,
commit-preflight, allowlisted execution, audit) currently protects only
clients that enter the Adapter REST channel. Mainstream MCP clients such as
Claude Desktop or Cursor cannot enter that loop, and the suite cannot
constrain them through it. As connection becomes a commodity standardized by
WordPress itself, the defensible layer is governance; if governance speaks
only one transport, it protects only a fraction of real client traffic.

## Problem Statement

1. Governed actions have no MCP projection, so governance is invisible to
   mainstream MCP clients.
2. If a client can reach both a governed MCP surface and any ungoverned
   path to the same abilities (the official adapter, `wp/v2`), the approval
   gate becomes voluntary again. The Adapter threat model calls this a
   conventional boundary; the same weakness must not be rebuilt on MCP.

## Decision Questions

### Q1 - Tool surface

Project the governed channel, not raw WordPress writes. Initial tool set:

| MCP tool | Backed by (Adapter REST) | Scope |
| --- | --- | --- |
| `health` / `capabilities` | `GET /health`, `GET /capabilities` | `npcink.status` |
| `run_read_ability` | `POST /run-read-ability` (sensitive reads keep the Core read-request flow) | `npcink.read` |
| `read_request_create` / `read_request_status` | `POST`/`GET /read-requests` | `npcink.read` |
| `list_proposals` / `proposal_status` | `GET /proposals`, `GET /proposals/{id}` | `npcink.status` |
| `propose_write` / `propose_from_plan` | `POST /proposals`, `POST /proposals/from-plan` | `npcink.propose` |
| `approve_and_execute` / `execute_approved` | `POST /proposals/{id}/approve-and-execute`, `POST /execute-approved-proposal` | `npcink.execute` |

No standalone approve or reject tool, matching the disabled Adapter stubs.
Approval truth, preflight truth, and audit truth remain in Core.

### Q2 - Enforced boundary bridging

An MCP surface is only an enforced boundary if the client credential cannot
reach WordPress any other way. Requirement: MCP clients authenticate with a
credential WordPress core does not accept.

Recommendation: reuse `npcink-key-pair-auth.v1`. The surface supports
Ed25519 key-pair device pairing approved in wp-admin, signs requests, and
validates them the way Adapter does. The paired key's scopes map to the tool
set above. Application Password or cookie-authenticated MCP connections are
labeled conventional and must be refused for `npcink.execute`-scoped tools.

### Q3 - Coexistence with the official WordPress MCP Adapter

- Phase 1 (guidance): operators who run governed writes should not expose
  write-class abilities through the official adapter; document this in the
  suite onboarding material.
- Phase 2 (detection, optional): a health check that reports when
  write-capable abilities appear reachable through an ungoverned MCP path.
- Long term: propose an upstream approval/consent hook so governance can
  become a filter over the official adapter instead of a parallel surface.
  Draft: [`wordpress-mcp-approval-hook-proposal-draft.md`](wordpress-mcp-approval-hook-proposal-draft.md).

### Q4 - Ownership

Boundary facts: Adapter's charter forbids MCP runtime in Adapter; Toolbox's
charter keeps MCP control-plane state out of Toolbox; Core stays
governance-only; Toolkit owns definitions, not channels.

- Option 1 (recommended): a new thin repository (working name
  `npcink-mcp-gateway`) that acts as a signed Adapter client. It translates
  MCP tool calls into the existing Adapter REST routes using its own paired
  key. It adds zero new write paths, no approval state, and no ability
  registry; execution profiles stay in Adapter; Core stays the truth source.
- Option 2: amend the Adapter charter to host MCP transport. Rejected for
  now: Adapter's thinness discipline is a moat, and charter changes have
  cross-repo coordination cost. Revisit only if the platform prefers one
  channel repository.
- Option 3: host in Toolbox. Rejected: couples the operator UI surface to a
  client-facing transport and contradicts Toolbox's stated non-ownership of
  MCP control-plane state.

## Non-Goals

- No new final write path, queue, retry store, or workflow runtime.
- No ability definitions, workflow definitions, or approval state in the
  gateway.
- No provider credential, model routing, or prompt ownership.
- No replacement for the Adapter REST channel; the gateway is a projection
  over it.

## Open Questions For The Operator

1. Accept Option 1 (new thin repo) or prefer amending the Adapter charter?
2. Initial tool scope: v0 read + propose only, with execute tools later, or
   the full set gated by key scopes from day one?
3. Transport priority: streamable HTTP first (remote clients) or stdio first
   (local Claude Desktop / Cursor flows)?
4. Repository naming: `npcink-mcp-gateway` or `npcink-ai-mcp-adapter`.

## Phases After Acceptance

| Phase | Item |
| --- | --- |
| 1 | Charter the repository; write the MCP tool contract doc; mirror Adapter `client_policy` and boundary classes. |
| 2 | Key-pair pairing plus status/read/propose tools against Adapter REST; fail-closed dependency checks. |
| 3 | Execute tools through approve-and-execute only, with `npcink.execute` scope and commit-intent semantics. |
| 4 | Coexistence guidance in onboarding; optional ungoverned-path detection; track the upstream hook proposal. |

## Sources

- [From Abilities to AI Agents: Introducing the WordPress MCP Adapter](https://developer.wordpress.org/news/2026/02/from-abilities-to-ai-agents-introducing-the-wordpress-mcp-adapter)
- Adapter positioning notes and threat model: `npcink-ai-client-adapter` `docs/adapter-positioning-notes-2026-09.md`, `docs/threat-model.md`
