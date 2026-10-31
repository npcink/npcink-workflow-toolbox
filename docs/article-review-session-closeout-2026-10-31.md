# Article review session closeout — 2026-10-31

本会话的排版保护、只读中文语句提示、真实链路验收和长期本地安装已完成，
可以关闭会话。历史问题已分别标记完成、保守阻断或交给后续所有者；不能说
所有设想都已实现，也不能说所有仓库/运行环境已没有未合并改动。

## Change envelope

- Module/repository: Toolbox article-review closeout documentation and its verified merged topic resources.
- Changes: reconcile stale canonical status, record delivery/remaining owners, codify lessons, preserve receipts and clean own merged branches/worktrees.
- Non-goals: product-source changes, new model calls, merging vision/admin work, production release, deleting another chat's work or installed Addon overlay.
- Contracts: unchanged. Formatting preserves original content; all corrections remain manual.
- Files: this audit, delivery lessons, existing status/contract/index/workflow pointers.
- Gates: exact PR/head/check audit, clean worktree checks, docs/static/full suite, local Qwen and protected CI.
- Rollback: preserved Git refs and private local evidence archive; revert docs independently of installed source.

## Verified merged delivery

GitHub metadata was read during this audit, not inferred from local commit names.

| Repository | PRs | Scope |
| --- | --- | --- |
| Toolbox | [#345](https://github.com/npcink/npcink-workflow-toolbox/pull/345), [#346](https://github.com/npcink/npcink-workflow-toolbox/pull/346) | formatting preservation, bounded local report, reference research and strict review contract |
| Toolbox | [#349](https://github.com/npcink/npcink-workflow-toolbox/pull/349), [#350](https://github.com/npcink/npcink-workflow-toolbox/pull/350), [#351](https://github.com/npcink/npcink-workflow-toolbox/pull/351) | Chinese review, signed/model evidence and clean-master M4 promotion receipt |
| Toolbox | [#352](https://github.com/npcink/npcink-workflow-toolbox/pull/352), [#353](https://github.com/npcink/npcink-workflow-toolbox/pull/353) | actual installed editor acceptance and durable local installation |
| Addon | [#338](https://github.com/npcink/npcink-cloud-addon/pull/338) | fixed, bounded, read-only signed review facade |
| Cloud | [#1331](https://github.com/npcink/npcink-ai-cloud/pull/1331) | language-only runtime with strict quote and privacy behavior |

All nine are merged. Toolbox checks passed; Addon PHP 8.0/8.2/8.4, compatibility,
PHPStan and release gates passed (one superseded PR-body run was cancelled and
the later run passed). Cloud required checks passed; conditionally irrelevant
production/full-matrix jobs were skipped, not counted as newly executed tests.
The closeout documentation PR is a separate final delivery after these nine.

Follow-on: the [formatting compatibility verification](article-format-compatibility-2026-10-31.md)
subsequently addresses the two validator refusals below; this dated closeout
retains their original disposition.

## Historical issues and actual remaining work

| Item | Disposition / owner |
| --- | --- |
| Preserve text, punctuation, symbols, links and multimedia during formatting | Delivered and independently checked in PHP/editor; [12-source acceptance](article-format-preservation-acceptance-2026-10-30.md) kept every saved original unchanged |
| General useful formatting on every input | Not claimed: 280942 remains blocked on unsupported/unbalanced inline structure; 280861 remains blocked when protected blocks would change. Cloud/validator owner needs a separate compatibility contract |
| Chinese sentence/punctuation/reference/redundancy hints | Delivered language-only; six seeded real model cases plus installed browser acceptance. Broader accuracy and ordinary author feedback remain ongoing product evaluation |
| Exact quotes, UTF-16, canonical hashing, stale result and error privacy defects | Delivered regressions; see [delivery](article-language-delivery-acceptance-2026-10-31.md) and [lessons](article-review-delivery-lessons-v1.md) |
| Missing Toolbox mount, old Addon facade, transient development connection | Stable primary mounts and managed local tunnel installed; local timeout overlay preserved. Provider network failure was recorded before a successful explicit recheck |
| Logic and content-risk analysis | Deferred, disabled as `not_checked/not_supported`; Cloud/Addon contract and evaluation owner. No automatic policy enforcement |
| OSS/offload media writes | Not delivered by this language slice. Real third-party storage/read/write/backup/restore evidence and governed Toolkit/Adapter execution remain prerequisites; keep unsupported modes blocked |
| Broad provider-retention guarantee, production/WordPress.org release | Not verified/not performed; Cloud no-store is not proof of upstream retention policy |
| Existing CLI dynamic-property deprecations | Recorded environmental/runtime debt; not fixed by documentation closeout |

Latest explicit editor retry passed on Cloud `7002bd56` with six dirty paths on
`codex/admin-supplier-functional-fixes`; pre/post bundle and state matched.
Run `run_117c459bcfce432493ed4775226ef39c` succeeded with one provider call,
retry zero/no fallback, empty durable input, absent ciphertext and omitted result.
Quotes/navigation/staleness and unchanged fixture passed, with zero page errors
or post-write attempts, and zero remaining synthetic drafts. Subsequent liveness
reported clean `21778de0`, reflecting the separate active admin session. That
later source is not the revision of this retry, and was not deployed or fully
accepted by this session. Do not chase or reset shared M4 to reproduce history.

## Cleanup and intentional retention

- Nine exact merged head refs were preserved under `refs/archive/article-review-20261031/*` before deleting their local topic branches. Eight existing remote branches were deleted with expected-head leases; the Cloud article branch was already absent remotely.
- The article-review Addon and Cloud worktrees were removed after clean-source and installation-reference checks. Ignored dependency/cache output is reproducible; original developer receipts/screenshots were privately preserved first.
- Raw receipts and ignored evidence are in `~/.codex/session-archives/article-review-2026-10-31/` (private local archive, not Git). This includes failed attempts as well as successes; no customer article is added to Git.
- The temporary Toolbox writing worktree and this final closeout topic are removed after the documentation PR merges. Main installed Toolbox fast-forwards that docs commit; product runtime bytes are unchanged.
- Codex rejected archiving this chat's primary managed checkout: `The task's primary checkout cannot be removed.` It remains clean and detached, with the completed topic branch removed. It is an application-managed retention exception, not unfinished code, and was not deleted through a shell workaround.
- Primary Toolbox/Addon checkouts remain because the local site uses them. Addon `7c1483c3` intentionally retains one local vision-timeout commit above merged `e8f7d7e`, without upstream. It and its previous state are protected by `refs/local-install/*`; it is not falsely counted as merged/pushed.
- Other sessions' admin, vision, legacy-image, content-loop and Addon-audit worktrees/branches remain untouched, including any of their local-only commits or old divergent refs. Their owners control review/merge/cleanup. This audit does not certify all repository branches clean.

## Documentation and close decision

The report contract, Cloud consumer contract and known-issues ledger no longer
say the shipped language slice is unmerged/unimplemented. Dated failed/candidate
receipts remain historical and are linked to the new state. Development workflow
and orientation/index pages point to the active
[delivery rules and structured retrospective](article-review-delivery-lessons-v1.md).

Closing this chat leaves the installed feature available and does not lose
source/evidence. Future sessions start from these local docs; the retained
Addon overlay, deferred checks, compatibility cases, OSS prerequisites and shared
M4 ownership remain explicit handoffs, not reasons to keep this completed chat open.
