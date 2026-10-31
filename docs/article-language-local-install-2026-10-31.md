# Durable local article-language installation — 2026-10-31

## Change envelope

- Target: local `magick-ai` installation; Toolbox documentation only; existing Addon local development overlay.
- Focus: keep the verified article-language feature available through durable primary checkout paths.
- Plan: fast-forward the clean primary Toolbox master; replace its dangling site symlink with the primary path; rebuild the primary detached Addon from merged master plus the exact already-installed local vision-timeout patch.
- Non-goals: merge/publish the separate vision branches, change Cloud/M4 or provider settings, release to production/WordPress.org, edit article text/media, enable logic/content-risk.
- Ownership: Toolbox suggestions, Addon signing/transport, Cloud runtime; author owns all text correction and save.
- Public contracts: unchanged.
- Files: this receipt, body-free JSON and documentation pointers; site Toolbox symlink; Addon files already belonging to the existing local timeout patch.
- Frozen: credentials/options, local timeout MU plugin, unrelated repositories, active-plugin records and customer articles.
- Gates: clean source checks, exact timeout-patch comparison, Addon full suite/PHPStan, installed browser smoke, unchanged connection/config fingerprints, Toolbox docs/full suite, local Qwen, protected CI.
- Cross-repo source matrix: merged review sources retain their earlier exact-SHA gates; the combined local Addon is independently tested and explicitly remains a local overlay.
- Rollback: restore the recorded Toolbox symlink and detached Addon revision; no database import/reset or customer-content restore is needed.

## Installed state

The local site now keeps both plugins mounted to durable primary checkouts:

| Plugin | Installed source | State |
| --- | --- | --- |
| Toolbox | `/Users/muze/gitee/npcink-workflow-toolbox` at `63f6a075` | clean master, matching origin/master at acceptance |
| Addon | `/Users/muze/gitee/npcink-cloud-addon` at `7c1483c3` | clean detached local overlay on merged master `e8f7d7e` |

The Toolbox symlink was atomically changed from its missing worktree target to
the primary checkout. The Addon symlink already used its primary checkout and
was retained. Both plugins were already active; active-plugin records and the
connection/Toolbox settings fingerprints are unchanged.

The Addon combines merged article-review support with the exact already-installed
vision timeout patch from `b75a944b` (same patch as published candidate `4654f9b`).
Runtime, test and README additions/deletions were compared exactly. Two
cherry-pick conflicts affected only complexity documentation and its static
line-count limit: the combined file is 1753 lines, accounting for both additions.
No new timeout behavior was designed here. The existing local MU plugin and its
180-second opt-in remain byte-identical.

The overlay is intentionally local-only and has no upstream. It is one commit
above `origin/master`, preserved as `refs/local-install/20261031-article-review-vision`;
the old mounted revision is preserved as
`refs/local-install/20261031-before-article-review`. The separate vision branch
was not merged, pushed or submitted as a PR here. Future updates must preserve
or explicitly retire this overlay after its owner's review.

## Connection and real editor acceptance

The old foreground SSH tunnel had exited. A local user LaunchAgent now maintains
the existing loopback development connection with login startup, SSH keepalive
and reconnect throttling. Its label is `com.npcink.cloud-m4-dev-tunnel`; the plist
and logs stay outside Git. A managed process restart was followed by a successful
liveness read. This is local transport maintenance, not a WordPress queue.

At acceptance shared M4 had independently changed to clean candidate `34496a70`,
branch `ui/admin-subscriptions-quiet-20261030`. Its history includes merged article
review `7002bd56`. No M4 deploy/promotion, provider or credential change occurred
here. The earlier accepted-master receipt is historical; this session does not
claim the new shared candidate is an accepted deployment.

After one tunnel-unavailable attempt and one actual `provider.network_error`,
read-only connectivity probes succeeded and one explicit recheck passed. The
recheck addressed transport failure, with the same usefulness expectation; no
model output was substituted. Every attempt cleaned its draft and session.

The real installed browser smoke verified one punctuation hint, exact UTF-16
quote/location after emoji, preserved spaces, code/image exclusions, read-only
navigation and edit/Undo invalidation. Language completed with zero invalid
findings; the overall report is partial because coverage excludes code/images.
Logic/content-risk remain not checked. Editor title/excerpt/content and database
fixture title/excerpt/content/status/timestamps were unchanged. There were zero
post-write attempts and page errors, and a separate query found zero remaining
synthetic drafts. Local screenshot inspection confirmed manual-review controls.

The [body-free receipt](article-language-local-install-evidence-2026-10-31.json)
includes both named model runs and their failure/success. Successful review
input/result are omitted durably; the failed run has empty input, no ciphertext
and a null result. Each made one provider call, retry zero and no fallback.
Upstream provider retention was not inspected.

## Gates and limits

- Addon `composer test:all`, PHPStan and `git diff --check` passed on the combined source.
- The central matrix with explicit Toolbox/Addon primary-path overrides passed both full PHP suites. Its detached-HEAD upstream counters do not describe the Addon overlay; the explicit comparison above records the local commit. Default CLI PHP emitted existing Toolbox dynamic-property deprecations; browser acceptance used Local PHP 8.2 and had no page errors.
- Local Qwen reviewed the exact Addon overlay. F1 concerns the inherited process-global test constant: accepted for this unchanged local patch; it is defined once, the full shared-process suite passed and the final reset clears environment overrides. Arbitrary external test bootstraps are not claimed. F2 asks to cache repeated calls to a pure timeout helper: accepted as negligible, without side effects or altered transport output. No broader timeout/test refactor is included.
- Toolbox production PHP/JS/CSS, formatting behavior, credentials and customer articles were not edited. Only documentation/evidence is published by this closeout.
- Availability still depends on LocalWP, the private-network connection, M4 and its configured provider. The network failure remains recorded; this is not an availability guarantee.

Ordinary local author use is available from **Npcink Content Support → 发布预检**.
Corrections remain entirely manual. Formatting changes layout only, preserving
every word, symbol, punctuation mark, link and media item. Logic/content-risk,
production deployment and WordPress.org release remain outside this slice.

## Rollback and future handoff

Initial mount targets, revisions and opaque settings fingerprints are recorded
in `/tmp/article-durable-before-20261031.json` for this operation; the source refs
above retain both Addon states independently. To undo installation, restore the
prior Toolbox symlink and detached Addon revision after checking for newer work.
The previous Toolbox target was already missing, so that rollback restores an
unavailable plugin path. The tunnel can be stopped with `launchctl bootout` for
its user service and its plist removed. No database reset/import or customer
content restore is required. The primary Toolbox may fast-forward this docs PR
after merge; its runtime bytes do not change.
