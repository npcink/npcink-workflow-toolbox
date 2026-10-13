# Session Handoff — 2026-10-11 Hardening, 0.6.0 Release, Next Phase

Status: continuation handoff for the next AI session. **Not a contract** — the
authorities are `AGENTS.md`, `docs/boundary.md`, `docs/known-issues.md`, and the
per-area contract docs. When this file disagrees with them, they win.

Read order for the next session: `AGENTS.md` → `docs/current-state.md` →
this file → the specific doc for the area you touch.

## What shipped this session (all merged; re-verify SHAs, do not trust snapshots)

| PR | Title | Effect |
| --- | --- | --- |
| #242 | require edit_post on post-context reads | media-brief, editor entry, hosted-AI guards |
| #243 | sanitize Cloud URL schemes | `sanitize_item_url_fields` + JS `isHttpUrl` gates |
| #244 | editor bundle gating + minified assets | post-type allowlist; `.min` release packaging |
| #245 | bound per-request maintenance work | `Toolbox_Request_Context`; batch hash cap |
| #246 | security/performance closeout record | `docs/security-performance-hardening-2026-10.md` |
| #247 | close out the deferred audit findings | allow-lists, coalescing, caches, memo |
| #248 | release bump 0.6.0 | header + constant + changelog |
| #249 | 0.6.0 release closeout record | `docs/wordpress-org-submission.md` |
| eval-lab #110 | comment moderation calibration | framework + seed run (separate repo) |

**0.6.0 is live on WordPress.org (SVN r3739544)**; the plugin API reports
stable 0.6.0 and the live zip carries nine `.min` asset siblings. Master at the
time of writing: `c24fb892`.

## Follow-up delivery after this handoff

The subsequent UX and maintenance arc is merged on `origin/master`:

| PR | Title | Effect |
| --- | --- | --- |
| #256 | restore milestone one quality gates | restored the complete local quality gate set |
| #257 | surface cloud readiness and batch feedback | added preflight visibility, in-flight protection, and bounded-value disclosure |
| #258 | make site check a visible admin tab | made Site Check the fourth tab while keeping Overview as default |
| #259 | harden internal-link URLs and editor translations | closed the shipped internal-link URL and translation fixes |
| #260 | expose media batch failures and cancellation | added per-item failure detail and foreground cancellation |
| #261 | improve editor image sidebar feedback | added the image-sidebar feedback and attribution improvements |
| #262 | update dependency and static analysis toolchain | completed the independent Dependabot/PHPStan maintenance batch |
| #263 | make dependency failures actionable | added audience-aware, actionable dependency error copy |

## Where to continue (in priority order)

### 1. Browser verification, then real hold-comment calibration (highest value)

First run the UX browser smokes for PRs #257–#263 on the five-plugin site. The
required commands and environment are recorded in the 2026-10-12 UX handoff.
After the smokes pass, complete the real hold-comment calibration below before
any Cloud scheduling productization.

The comment-moderation intent is the assessed highest daily-value Cloud
scheduling item, and the only missing input is **real, operator-labeled hold
comments**. The framework is ready in eval-lab.

- Export on a site with real pending comments:
  `wp comment list --status=hold --fields=comment_ID,comment_author,comment_content --format=csv --posts_per_page=100`
- Label each line `legitimate` / `spam` / `uncertain` (operator reads them),
  convert to JSONL (schema in `npcink-eval-lab/comment-moderation/README.md`),
  then:
  `php comment-moderation/run-calibration.php samples=<file>.jsonl profiles=qwen,deepseek`
- Attach results to
  `npcink-eval-lab/docs/comment-moderation-cloud-scheduling-calibration-brief.md`.
- Acceptance bar: ≥95% overall and **zero legitimate→spam** confusions on 50–100
  real samples. Below 85% on `legitimate` → iterate the prompt (max three
  iterations), not the labels.
- Boundary reminder: samples carry only approved-would-be-public fields — never
  email, IP, or user agent.

Seed evidence already recorded (not production proof): 10/10 agreement on both
lanes, ten clear-cut Chinese patterns.

### 2. ③ gate: media ALT adoption loop decision (needs ~1 week production use)

The 2026-10-07 plan's third gate: decide whether the media ALT review-set
batch-apply loop is worth building, based on real acceptance volume.

- Data source: the adoption tally (`npcink_toolbox_review_tally` option, or the
  Last-7-days line on each review-set panel). Buttons accumulate marks during
  normal use; nothing else to do.
- Decision bar: a stable two-digit weekly accepted count → build the loop
  (the cross-repo governed path already exists: abilities-toolkit
  `media_alt_apply_plan.v1` → governance-core preflight/proposal). Below the
  bar → the review-set family goes to maintenance mode.
- Current reading (local five-plugin test site, 2026-10-11): only 3 test marks
  (2 accepted / 7d) — **not production data**, so the gate is unmeasured, not
  failed. Do not decide from the local site.

### 3. Carried code debt (just-in-time; ≤1 cluster split per week)

- **Internal-link cluster split** preferred next: it carries the outstanding
  S2 follow-up — server-side URL rewrite for the toolkit-artifact fallback
  chain (`source.target_url` / `source.url`), currently relying on the JS gate
  only. One commit, one cluster.
- Then: editor JS remaining clusters, `Rest_Editor_Content_Support` cached-flow
  orchestrators, `assets/admin.js` (8.2k), remaining `Admin_Page.php` clusters.
- PHPStan baseline ratchet (121 findings across 86 baseline patterns) shrinks with each split; regenerate
  the baseline only when a split moves findings and counts must not grow.
- Split standard: `docs/platform/provider-split-refactor-standard-v1.md` and
  the just-in-time cadence in `docs/god-class-split-session-handoff.md`.

### 4. Waiting on external input (do not start unprompted)

- Guided onboarding tour operator-trial answers
  (`docs/onboarding-tour-design-v1.md`).
- "Core proposal" terminology standard across five repos.
- Cloud Addon items: editor image quota/attribution follow-up; the editor
  Cloud-chain HTTP timeout (the local lever lives in the addon, since Toolbox
  makes zero outbound HTTP calls).
- Toolkit #167 (delete-attachment ability) and Core #85 (flagged-media
  deletion policy) — both must land before the M4 delete button can ship.

## Lessons and gotchas from this session (avoid re-learning them)

### WordPress runtime

- **`REST_REQUEST` is defined in `rest_api_loaded()` during `parse_request`** —
  it is NOT set at `plugins_loaded` (when `register_hooks()` runs) nor yet at
  `init`. Any "admin/REST/cron only" guard must run **inside the callback** and
  mount on **both `init` and `rest_api_init`** (see
  `Toolbox_Request_Context`). Idempotent work that must not double-run takes a
  static once-guard.
- **PHP default parameter values only apply when the argument is omitted.**
  Passing `array()` explicitly silently disables a defaulted list. Use
  `?array $x = null` and resolve `null` to the default at method entry.
- **Sanitizers must preserve array keys and scalar types.** Rebuilding with
  `$normalized[]` re-indexes associative maps and destroys string keys; probe
  the exact string you write back (trim/control-strip mismatches reintroduce
  bypasses); `esc_url_raw` returns `''` for a disallowed scheme, so decide
  drop-vs-keep deliberately.

### Release and packaging

- **First minified release**: `composer package:release` runs
  `scripts/minify-assets.sh` (needs `node`; pinned terser/clean-css-cli via npx;
  per-bundle empty/shrink refusal with a 64-byte tolerance; failure cleanup
  trap; sha256 audit log). Runtime prefers `.min` unless `SCRIPT_DEBUG`.
- **SVN**: the checkout at `/tmp/npcink-toolbox-svn` can corrupt when `/tmp` is
  cleaned (pristine missing) — just `svn co` fresh. When wiping trunk, do NOT
  `find trunk -delete` the directory itself; recreate + `svn up trunk` if lost.
  A fresh `tags/0.5.3` predated four editor-split files; trunk legitimately
  gained them at 0.6.0.

### PR / review mechanics

- **Merge needs conversation resolution**: after the AI review gate passes,
  66+ unresolved review threads still BLOCK the PR. Batch-resolve via GraphQL
  `resolveReviewThread`, then auto-merge proceeds.
- **Later PRs go BEHIND when an earlier one merges**: `gh pr update-branch` may
  silently no-op — rebase locally and `--force-with-lease` push; expect
  bootstrap `require_once` conflicts when two PRs insert at the same anchor.
- **The AI review loop only converges under the fix-only-regressions rule**:
  fix regressions from the immediately-preceding edit, accept design-grounded
  repeats (point at the earlier triage line), close when reachable-bug count
  hits zero. #243 took 19 rounds and #247 took 15 — every fix breeds findings.
- Publisher: `composer pr:publish -- --title … --body-file …`; the gate wants a
  `fix:`/`accept:` line per finding in `## AI Review Triage`.

### eval-lab conventions (separate repo)

- `composer check` is the self-check gate (there is **no** `test:all` there);
  `composer test` runs the suite. Both must pass.
- `*/generated/` dirs track **only** a `.gitignore` placeholder — commit run
  outputs nowhere; add a `.gitignore` with `*` + `!.gitignore`.
- Its CI posts a code-review round; triage findings in the PR body and resolve
  threads before merge, same as this repo.
- Homebrew PHP curl ships an unresolved `@@HOMEBREW_PREFIX@@` CA default — pass
  `CURLOPT_CAINFO` from a resolved bundle (see the calibration runner).

## Environment facts (this machine)

- Five-plugin test site: `~/wp-sites/npcink-five` (web root
  `.../public`), served at `http://127.0.0.1:8090`.
- MySQL socket: `~/Library/Application Support/Local/run/s63K4c8XP/mysql/mysqld.sock`
  (DB `npcink_five`, user/pass `root`/`root`). Pass it to wp-cli via
  `php -d mysqli.default_socket=… /opt/homebrew/bin/wp --path=…`.
- The five-site admin password was reset to `npcink-five-060` for the release
  browser smoke (local test site only).
- WP-CLI: `/opt/homebrew/bin/wp`; PHP: `/opt/homebrew/bin/php`; `node` on PATH.
- Sibling repos under `~/gitee/`: `npcink-abilities-toolkit`,
  `npcink-ai-client-adapter`, `npcink-ai-cloud`, `npcink-cloud-addon`,
  `npcink-governance-core`, `npcink-eval-lab`.
- `vendor/` is not tracked; helper worktrees symlink it and add `vendor` to
  `.git/info/exclude` so the publisher's clean check passes.

## Verification quick reference

```bash
php tests/run.php --quiet          # fast contract gate while iterating
composer test:all                  # full gate before commit/PR
composer lint:standards            # phpcs
composer analyse:php               # phpstan level 5 + baseline
composer validate --no-check-publish
composer check:wporg               # WordPress.org review guard
composer package:release           # minified release zip (needs node)
```

Never pipe a gate through `grep`/`tail` — always check the composer exit code
(`composer test:all > /tmp/log 2>&1; echo "EXIT=$?"`).
