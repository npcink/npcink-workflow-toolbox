# Security And Performance Hardening Closeout 2026-10

Status: closed record for the 2026-10-11 security-and-performance review
implementation session, plus the standing rules it produced. The review
audited the REST layer, the provider/Cloud boundary, the admin/editor
render surfaces, and per-request performance; the user approved the
action list and every priority item shipped the same day.

## Shipped (all merged, master 68ab2c4d)

- **Object-level authorization (PR #242).** `current_user_can('edit_post')`
  guards at three layers: the media-brief handler before packaging post
  context, the editor content-support entry before any cached Cloud flow
  (this closes the flow-cache HIT path, which never re-enters the service
  guard), and the hosted-AI service before `collect_hosted_ai_post_context`
  feeds the Cloud prompt. All three were covered by `manage_options`
  today; the guards satisfy the documented relaxation rule that every
  server-side object read on a relaxable route be object-audited.
- **Cloud URL scheme hardening (PR #243).** A shared
  `Provider_Client_Support::sanitize_item_url_fields()` normalizes
  Cloud-supplied URL fields to http(s): key-preserving for both lists and
  associative maps (the agent handoff), field matching by allowlist plus
  `*_url`/`link`/`permalink` naming pattern, bounded-depth recursion,
  control-character-stripped scheme probing on the same trimmed value that
  is written back, non-string scalar type preservation, `data:audio/`
  inline-audio allowance, and relative-URL tolerance (only explicit
  disallowed schemes drop the field). Applied to Site Knowledge results,
  media evidence items, the agent handoff, and the workflow-plans
  evidence_refs plus the whole rendered proposal_input. The JS side gates
  every remaining dynamic href and `window.open` with `isHttpUrl`.
- **Editor bundle gating and minified release assets (PR #244).** The
  482KB editor bundle now enqueues only on allowlisted post types
  (`npcink_toolbox_editor_supported_post_types` filter, post/page default;
  readme.txt carries the upgrade note), and release packages ship `.min`
  siblings via `scripts/minify-assets.sh` (pinned terser@5.39.2 /
  clean-css-cli@5.6.3, explicit target directory, per-bundle empty/shrink
  refusal with a 64-byte near-tie tolerance, failure cleanup trap, sha256
  audit log) wired into `composer package:release`. Assets measured
  1,040,556 -> 635,933 bytes; the editor main bundle 481,868 -> 270,062.
  Runtime preference lives in the shared `Toolbox_Asset_Paths` trait
  (SCRIPT_DEBUG keeps readable sources).
- **Per-request cost bounding (PR #245).** Legacy Site Knowledge
  retirement and both media scheduler init checks run only in admin, REST,
  or cron contexts through the shared
  `Toolbox_Request_Context::is_maintenance_request()` predicate, so
  front-end traffic pays no per-request option reads or cron lookups;
  media optimization batch creation bounds its full-file sha256 pre-flight
  to the first fifty candidates (declared-size stat check beyond) while
  the per-item fingerprint re-verification before each adoption stays
  authoritative.

## Deferred audit findings (open low-severity items)

Accepted for later small sessions; each names its owner surface:

- Bridge-level input allow-listing and response coalescing for the two
  `edit_posts` image-source routes (`/image-candidates`,
  `/ai/image-generation`) — quota cost sits with Cloud, but author-role
  users can drive uncapped calls.
- The editor flow cache is site-scoped with no user dimension
  (`Rest_Editor_Flow_Cache::editor_flow_cache_key`); identical inputs
  share cached Cloud results across users.
- `internal_links` / summary flows expose post status/type/terms for
  cloud-evidence post ids without a `read_post` check (ids originate from
  Cloud evidence, not direct requests).
- Dead code: `Settings::get_secret()` / `sanitize_secret_input()` (zero
  callers) and `Provider_Client_Support::sanitize_provider_error_data()`
  (defined, never called).
- `NPCINK_TOOLBOX_VERSION` constant ('0.4.0') lags the plugin header
  ('0.5.3'); asset cache-busting is mostly covered by filemtime, but the
  fallback path serves a stale version string.
- Sequential Cloud chains inside one editor REST request (writing pack:
  up to four hops; declared timeout sum ~110-170s) — split into
  operator-triggered stages or tighten the long-form budget.
- Site Knowledge status bridge queries 1000 posts and resolves
  title/permalink per post per request (`Rest_Site_Knowledge_Bridges`).
- 67 `require_once` at bootstrap with no autoloader; admin-only classes
  parse on front-end requests.
- Weekly media fingerprint scan re-hashes up to 100 unchanged files per
  run; a `filemtime+filesize` memo would avoid rehashing.

## Standing rules from this session

1. **WordPress request-context predicates must run inside callbacks.**
   `REST_REQUEST` is defined in `rest_api_loaded()` during
   `parse_request` — it is NOT defined at `plugins_loaded` (when
   `register_hooks()` runs) nor yet at `init`. Any "admin/REST/cron only"
   guard must therefore (a) live inside the callback body, and (b) mount
  the callback on both `init` and `rest_api_init`; the REST branch can
   never be evaluated at registration time. Idempotent work that must not
   run twice per request (init and rest_api_init both fire on REST
   requests) takes a static once-guard (`Site_Knowledge_Auto_Sync`).
2. **PHP default parameter values apply only when the argument is
   omitted.** Passing `array()` explicitly to a parameter whose default
   is the default list silently disables the behavior; make such
   parameters nullable (`?array $fields = null`) and resolve `null` to
   the default at method entry, so no caller can construct a silent
   no-op call.
3. **Sanitizer implementations must be shape-preserving unless dropping
   is the contract.** Rebuilding arrays with `$normalized[]` re-indexes
   associative maps and destroys their string keys; probe and write back
   the SAME normalized string (a probe that strips characters the
   written-back value keeps — or vice versa — reintroduces the bypass);
   keep non-string scalar types instead of stringifying them.
4. **Costly pre-flight checks may be bounded when an authoritative
   per-item check exists later in the flow.** The batch creation hash
   bound is safe precisely because adoption re-verifies each fingerprint;
   state the authoritative gate in a comment at the bounded site.
5. **The AI review loop converges only under a fix-only-regressions
   rule** (already recorded in the AI Code Review Standard): every fix
   round breeds new findings on the fix itself; fix regressions from the
   immediately-preceding edit, accept design-grounded repeats with a
   pointer to the earlier triage, and close when reachable-bug count hits
   zero. Merge mechanics after the gate passes: resolve the delivered
   review threads via GraphQL (branch protection requires conversation
   resolution), and expect later PRs in a batch to go BEHIND — rebase
   locally and force-with-lease when `gh pr update-branch` does not take
   (the Multi-Worktree Safety rule in the PR publishing standard already
   requires publishing from the topic branch's own worktree).
