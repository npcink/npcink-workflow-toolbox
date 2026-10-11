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

## Deferred audit findings (closed 2026-10-11, PR #247)

Seven fixed and two analyzed-accepted in the same-day follow-up:

- **Fixed — provider input allow-listing and spend coalescing.** The three
  raw-passthrough facade routes (`/ai/content-support`, `/ai/site-helper`,
  `/ai/image-generation`) forward through one `forward_provider_request()`
  helper that rejects unknown top-level fields (the media-derivative
  strict-input precedent) and reduces provider WP_Error data to the
  safe-diagnostics allow-list. `/ai/image-generation` and
  `/image-candidates` coalesce identical payloads behind a sixty-second
  per-user transient with a refresh-variant bypass, bounding rapid-fire
  duplicate spend from the relaxed editor scopes.
- **Fixed — flow cache user dimension.** `editor_flow_cache_key` hashes the
  acting user id into the transient key; identical contexts no longer share
  Cloud results across users.
- **Fixed — evidence post reads gated.** Internal-link target facts
  (status/type/permalink) and summary existing-terms now require
  `read_post` for the acting user before reading.
- **Fixed — dead code removed.** `Settings::get_secret()` and
  `sanitize_secret_input()` are gone; the previously unwired
  `sanitize_provider_error_data()` moved to `Rest_Controller_Support` and
  is actually called by the forwarding helper.
- **Fixed — version constant.** `NPCINK_TOOLBOX_VERSION` tracks the plugin
  header ('0.5.3').
- **Fixed — Site Knowledge status rows.** Local article facts (title/url/
  modified) cache behind a modified-stamp transient while the Cloud
  indexed verdict stays fresh per request.
- **Fixed — fingerprint memo.** `cached_media_fingerprint()` memoizes the
  file sha256 on filemtime+size post meta (uninstall removes it), so the
  weekly scan and recognition batches never re-hash unchanged files.
- **Analyzed-accepted — sequential Cloud chains.** The declared
  `timeout_seconds` values are Cloud-side payload budgets, not local wait
  budgets (this plugin makes zero outbound HTTP calls); the writing-pack
  flow is already stage-gated with early returns, every hop already sits
  behind the 300s flow cache, and blocked paths short-circuit. The
  remaining local lever — the addon's actual HTTP timeout — is owned by
  npcink-cloud-addon and tracked there, not here.
- **Analyzed-accepted — bootstrap autoload weight.** A classmap autoloader
  alone changes nothing while `Plugin::instance()` still constructs the
  full service graph on `plugins_loaded`; true lazy wiring must defer the
  REST/abilities/media surfaces behind `rest_api_init` (REST_REQUEST is
  undefined until parse_request) and is an architectural change to
  Plugin.php with cross-repo hook-timing verification — disproportionate
  to a low-severity finding whose cost is largely absorbed by opcache.
  Revisit with the planned just-in-time cluster splits.

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
