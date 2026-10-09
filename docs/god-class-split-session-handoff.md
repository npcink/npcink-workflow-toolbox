# God-Class Split Session Handoff

Status: task handoff for AI split sessions. Not a contract; the authority is
[Provider Split Refactor Standard v1](platform/provider-split-refactor-standard-v1.md)
(including its 2026-10-08 Editor Split Addendum) and `docs/known-issues.md`.
When this file and those disagree, they win.

Verified line counts (2026-10-09, `master` + the media/ALT session):

| Target file | Lines | `function` count | State |
| --- | --- | --- | --- |
| `includes/Rest_Editor_Content_Support.php` | 3008 | 75 | 8 clusters extracted; only cached-flow orchestrators remain |
| `assets/editor-content-support.js` | 10316 | 403 | 3 part files extracted |
| `assets/admin.js` | 8542 | 340 | not started |
| `includes/Admin_Page.php` | 2769 | 79 | Site Check cluster extracted |

Re-verify with `wc -l` before acting; do not trust this or any doc snapshot.

## Cadence Rule (read first)

These splits are intentionally deferred to just-in-time per the refactoring
decision recorded in `docs/development-lessons-2026-10.md` and
`docs/known-issues.md`: either one dedicated split session per week, or a
product session may carry one cluster extraction. Do **not** batch all four
targets into one push. One cluster = one revertible commit. Pick the
lowest-cross-cluster-edge cluster each session.

## Shared Session Preamble (applies to every target)

1. `Read AGENTS.md first.`
2. Read in order: `docs/current-state.md`; the "Structure: staged splits" and
   "sub-divided" entries in `docs/known-issues.md`;
   `docs/platform/provider-split-refactor-standard-v1.md` in full (method +
   verification discipline + Editor Split Addendum).
3. Confirm a clean worktree and no concurrent session:
   `git status --short --branch`, and compare `git log` HEAD against the
   session-start snapshot. If another session is committing, use a dedicated
   `git worktree` + branch and stage per-file/per-hunk.
4. Write the compact change envelope before editing (AGENTS.md rule).

Hard constraints (all from the split standard — do not rediscover them):

- **No autoloader: load order is a hard dependency.** Require the abstract
  base class file BEFORE any child whose `extends` resolves at require time.
  Order: base first, static sub-services next, facade service last.
- **`tests/run.php` needles match exact source strings** (`strpos`,
  `substr_count`, cross-method `preg_match` spans). Before moving any method,
  inventory three needle classes: receiver-pinned (`$this->helper(`),
  declaration (`private function helper` / visibility keyword), and span
  regexes. If a move flips a visibility word, update the needle and say so in
  the commit (accepted as non-semantic when behavior is unchanged).
- **Inheritance for shared helpers, composition for clusters.** A helper
  referenced by receiver-pinned needles from more than one future cluster must
  live in an abstract shared base (`protected`, not `private`; note
  `private const` is also invisible to children — promote to `protected const`).
  Never rewrite pinned call sites to `$this->support->helper(`.
- **Wire cross-cluster calls through the facade only.** Services hold one
  facade reference and never reference each other directly. Facade keeps every
  public signature as a one-line delegate; add small public bridges for
  internal helpers other services need. Never duplicate a method.
- **`phpstan-baseline.neon` entries are path-pinned.** When methods move,
  block-walk the neon and re-path their entries (message + path travel
  together). Baseline count must shrink or hold, never grow.
- **Never run `phpcbf` on `tests/run.php`** — string-mutating sniffs rewrite
  needle CONTENT. Fix needles by hand against real source text.
- **Never pipe a gate through `grep`/`tail`.** Check the composer/php exit
  code itself; a mid-chain script failure otherwise hides every later stage.
- After moving/removing call sites, run a **private-method reachability
  sweep** from public entrypoints and delete every orphaned helper family in
  the same commit.

Test-harness closure: any test that instantiates the facade outside WordPress
must load the base + every service + the facade via one shared loader file,
not per-test require lists. Wire every list — aggregation helper, the
route-registration `foreach` in tests, the security-smoke require block, and
the behavior-test loaders. A moved class missing from any of them either fails
loudly (good) or escapes the no-route-registration constraint (bad).

Gates before publish (all green, exit codes checked):

```bash
php tests/run.php --quiet
composer test:all
composer lint:standards
composer analyse:php
```

Advisory pre-publish quality gate (no provider keys), then publish:

```bash
ocr review --from origin/master --to HEAD   # optional local signal
composer pr:publish -- --title "refactor: extract <cluster> from <Class>" --body-file <path>
```

PR body uses `.github/pull_request_template.md` (Scope / Boundary /
Verification / Risk) plus an `## AI Review Triage` section with a `fix:` or
`accept:` line per finding. Pre-existing findings whose fix would change
runtime behavior are recorded and deferred, not absorbed into a
zero-behavior-change refactor.

---

## Target 1 — `includes/Rest_Editor_Content_Support.php` (finishing, ~3634)

Already extracted (siblings in `includes/`): `Rest_Editor_Flow_Cache` (shared
cache base), `Rest_Editor_Paragraph_Check`, `Rest_Editor_Audio_Text`,
`Rest_Editor_Taxonomy_Shaping`, `Rest_Editor_Summary_Terms`,
`Rest_Editor_Writing_Pack_Shaping`, `Rest_Editor_Progressive_Recommendations`,
and `Rest_Editor_Media_Alt` (2026-10-09 session 6: the media/ALT cluster as an
instance service extending `Rest_Editor_Flow_Cache`; `media_brief` keeps a
one-line facade delegate; `editor_media_library_candidates` intentionally
stays in the facade per session 5's boundary decision).

Remaining per `known-issues.md`: the **summary and taxonomy cached-flow
orchestrators** only. They inherit `Rest_Editor_Flow_Cache` and move with the
same pattern.

## Target 2 — `assets/editor-content-support.js` (~10316)

Extracted part files live in `assets/editor-content-support/`
(`text-utils.js`, `internal-links.js`, `audio-preferences.js`), frozen behind
`window.NpcinkToolbox*` namespaces.

Remaining clusters per `known-issues.md`: **image candidates, preflight,
progressive, draft flows**.

**JED translation gate:** part files must carry no translation strings unless
a per-handle JED contract exists — see
`docs/translation-source-language-policy.md`. Before choosing a cluster, grep
it for `__(` / `esc_html` / `_x(` etc. If it contains translation calls and no
JED contract covers the new handle, either pick a different cluster or land the
JED contract first (bulk JED regeneration is forbidden by that policy).

This session: extract one cluster (image candidates or draft flows, whichever
has fewer dependencies). After the split run the `test:editor-js-undefined`
static net and, if a browser env is available, the editor browser smoke.

## Target 3 — `assets/admin.js` (~8542, not started)

This is a fresh split. **Commit 1 is only the portability prep step**: replace
every single-file `file_get_contents` assertion source with a deterministic
sorted-`glob` aggregation of the directory (keep the variable name), guarded by
a harness completion marker. Then replay every **negative** needle against the
aggregated text (aggregation widens the search surface). Ship prep as its own
commit before touching any cluster.

Then follow the `Rest_Controller` / editor-JS part-file pattern and namespace
convention. This session: prep step + one lowest-risk render cluster. Do not
attempt more.

## Target 4 — `includes/Admin_Page.php` (~2769 facade)

Already reduced 4620 -> 2769; the Site Check render cluster lives in
`Admin_Page_Site_Ops_Panel` (parent-class pattern to reuse).

Remaining clusters per `known-issues.md`: **review-set tools, media derivative
controls, content context form**. Note: the 16 unused-method candidates in
`phpstan-baseline.neon` are mostly `Admin_Page` media-derivative render
helpers — when taking that cluster, run the private-method reachability sweep
first and delete unreachable helpers in the same commit, then regenerate the
baseline (count shrinks).

This session: extract **content context form** or **review-set tools** (one
cluster), reusing the `Admin_Page_Site_Ops_Panel` parent/child pattern and the
already-portable admin-page assertion sources.

---

## Suggested sequencing (lowest risk / nearest done first)

1. **Target 1** — finishing, pattern proven, fastest win.
2. **Target 4** — has a parent/child precedent to copy.
3. **Target 2** — may block on the JED translation contract; verify first.
4. **Target 3** — fresh split, needs the prep commit; highest cost.

Respect the cadence rule: one cluster per session, not all four at once.
