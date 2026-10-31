# Chinese language review delivery — 2026-10-31

本轮把中文语句提示从离线候选推进到真实签名链路的小样本验收。
Toolbox #349、Addon #338、Cloud #1331 均已通过受保护检查并合入。
Cloud 精确候选 SHA 的 GitHub 源码门已通过；M4 已晋升为干净 master 验收版本。

产品边界不变：排版只处理版式，保留文字、符号、标点、链接与多媒体。
语句检查只展示原样引文、位置、疑点和核对方向；作者自行判断并手动修改。
报告没有更正、替换、插入、保存、发布或阻断发布的权限。
只启用中文 `language`；`logic` 和 `content_risk` 始终为
`not_checked/not_supported`。

## Source and verification

| Layer | Source / delivery | Evidence |
| --- | --- | --- |
| Toolbox | PR [#349](https://github.com/npcink/npcink-workflow-toolbox/pull/349), merged `d5b153df` | PHP contracts and static analysis CI passed; local full suite, PHPCS, PHPStan, WP.org checks and metadata validation passed |
| Addon | PR [#338](https://github.com/npcink/npcink-cloud-addon/pull/338), merged `e8f7d7e` | PHP 8.0/8.2/8.4, WordPress AI blocking lanes, PHPStan and release static gates passed; POT freshness failure was fixed before merge |
| Cloud | PR [#1331](https://github.com/npcink/npcink-ai-cloud/pull/1331), tested `d9d668c4`, merged `7002bd56` | Local changed Python quality/type gates, 42 review tests and 12 result-normalization regressions passed; required CI and exact-SHA GitHub matrix passed |
| M4 candidate | Clean `d9d668c4` | Sync and service health passed; real signed review calls passed |
| M4 accepted | Clean master `7002bd56`, promotion PR #1331 | Retry passed after the shared lock was released; status is accepted, API/frontend/database/Redis health and signed WordPress connectivity passed |
| Production / WordPress release | No operation | No production deployment or WordPress.org release |

Local Qwen findings were triaged with source/test evidence. Fixes include portable
browser smoke configuration, lightweight immutable editor identity comparison,
consistent canonical hashing and a specific UTF-16 size assertion. Cloud now
sanitizes review errors after merging usage contexts; a signed canary regression
covers this order. The final full Cloud review attempt returned malformed findings
and was not counted as passed; the earlier feature receipt and a separate final
privacy-delta receipt cover the delivered changes. No same-payload retry occurred.
Raw receipts stay local; PR bodies record their hashes and dispositions.

## Real signed calls and usefulness

The test used the running local WordPress with real candidate plugin classes in
one isolated WP-CLI process. Shared Toolbox/Addon mounts were not replaced and
plugins were not activated. The harness invoked the actual Toolbox language
section with real `WP_REST_Request` objects, so unrelated preflight search and
Site Knowledge calls were excluded. The real Addon facade signed requests using
the existing connection; the normal M4 runtime routed them to its configured
`openai / gpt-6.1-sol` model. No provider, prompt profile or credential binding
was changed for this acceptance.

| Synthetic case | Observed result |
| --- | --- |
| Repeated punctuation | One punctuation hint; asks the author whether it was intentional |
| Redundant expression | One redundancy hint; asks which time expressions are needed |
| Ambiguous pronoun | One reference hint; asks the author to identify the referent |
| Ordinary correct prose | Zero hints |
| Explicitly intentional title symbols | Zero hints |
| Emoji before punctuation | One verified punctuation hint; exact quote/location preserved |

All six returned `ready/completed`, zero invalid findings, one provider call each,
zero retries and zero fallback. The author review directions contained no
replacement sentences. This is a developer judgment of six seeded examples,
not independent gold, a general error-rate estimate, or proof that whole articles
are problem-free. No customer article was submitted.

A read-only query of the six named Cloud runs verified empty durable input,
absent encrypted execution input and omitted durable result for every run.
Provider calls and bounded usage metadata remain available; upstream provider
retention was not verified. The [body-free receipt](article-language-delivery-evidence-2026-10-31.json)
records source revisions, case ids, run ids, model, counts and booleans without
article text, model prose, credentials, headers, cookies or local host addresses.

## Browser evidence and remaining scope

The separate real Gutenberg browser smoke passed exact whitespace/Unicode,
inert-script exclusion, block/UTF-16 limits, quote display, read-only navigation,
edit/Undo invalidation, same-text block replacement, in-flight edits and explicit
partial/limit/error states. It recorded zero post-write requests and unchanged
synthetic fixture content, then removed draft `283012` and its temporary session.
That browser used controlled offline output: it is interaction evidence, while
the CLI run above is signed transport/model evidence. Neither proves installed
shared-browser integration or human visual acceptance of a released product.

The [2026-10-30 checkpoint](article-language-integration-acceptance-2026-10-30.md)
and its unpushed-SHA failure remain historical. The first current three-repository matrix passed both PHP suites while Cloud CI
was still pending. A subsequent Cloud-only exact-SHA matrix passed at
`d9d668c4`; its tree is identical to merged `7002bd56`. The old source-gate
limitation is therefore resolved without repeating both unchanged PHP suites.

The first clean-master promotion attempt exited 75 while the separate image-timeout
acceptance owned the operation lock. After that lock was released, promotion
succeeded from current clean master `7002bd56`. Status confirms
`acceptance_state=accepted`, `promotion_pr=1331`, `source_branch=master`, and
`source_dirty=false`. The HTTP liveness receipt reports the same revision.
One real signed Addon entitlement read passed after promotion, with zero provider
calls and zero WordPress writes. No lock was removed. No extra model batch was
needed: the tested candidate and merged revision have identical Git trees.

The later [installed editor acceptance](article-language-installed-acceptance-2026-10-31.md)
now verifies real enqueued assets, actual preflight/Cloud output and read-only
editor behavior. Temporary mounts were restored in that earlier test; the later
[durable local installation](article-language-local-install-2026-10-31.md) now
provides the feature through primary checkouts while preserving local vision
work. Use ordinary author feedback to improve Chinese
language usefulness. Do not enable logic/content-risk or automatic correction.
OSS remains outside this language-review slice; its remote media write modes
still need verified third-party adapter capabilities and governed execution.
