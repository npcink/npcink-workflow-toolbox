# Installed editor language review acceptance — 2026-10-31

真实安装资源 → Gutenberg 发布预检 → Toolbox REST → 已安装 Addon 签名
→ 已验收 M4 → 配置中的模型 → 只读报告与正文定位已通过开发验收。
本轮没有修改产品运行时代码或新增接口。

## Change envelope

- Target repository / focused module: Toolbox optional article-language browser acceptance and evidence.
- Intended change: add a reproducible installed-plugin smoke and body-free receipt.
- Non-goals: automatic correction, logic/content-risk enablement, formatting changes, OSS integration, production or WordPress.org release.
- Boundary owner: Toolbox suggestion-only UI; Addon transport; Cloud review runtime; author owns every text/media edit and native save.
- Public contracts touched: none; reuse `publish_preflight` and `article_language_review.v1`.
- Expected files: one optional browser smoke, this acceptance/JSON receipt, documentation pointers.
- Frozen areas: production PHP/JS/CSS, credentials, provider routing, shared M4 configuration and other repositories' source.
- Gates: real installed smoke, Node syntax, `composer test:all`, metadata, WP.org checks, exact-commit local Qwen and protected CI.
- Matrix: prior three-repository exact-SHA delivery remains applicable; no runtime source changed here.
- Rollback: revert the optional harness/docs; restore temporary site mounts to their recorded original targets.

## Installed environment and cleanup

Toolbox runtime source was clean merged master `f9461880`. Addon was clean
`b44189ce`, the already merged #338 candidate; its Git tree equals that merged
revision. M4 HTTP liveness reported clean accepted master `7002bd56`.

The shared site's Toolbox symlink pointed to a missing checkout and the installed
Addon checkout did not expose the review facade. The test temporarily mounted
the clean checkouts above through the normal plugin symlinks. Toolbox was already
in `active_plugins`; the activation command reported already active, so activation
state did not change. Both symlinks were restored to their original targets after
the test, and the original Addon checkout stayed clean. Shared M4 configuration,
provider bindings and credentials were not changed.

Consequently this proves the installed development versions work together; it
does **not** mean the shared site's old/missing mounts now provide this feature.
The later [local installation closeout](article-language-local-install-2026-10-31.md)
retains durable primary mounts and preserves the image-timeout session's settings
and source work. The restored-mount limitation above describes this earlier
temporary acceptance, not the subsequent installed state.

Each browser attempt created only a synthetic draft and one temporary login
session, removed in `finally`. The passing run observed zero post-write attempts,
zero page errors and identical database title, excerpt, content, status and
modification timestamps. Fixture creation/deletion are test setup/cleanup writes;
the no-write claim concerns the review action. No customer article was sent.

## Observed behavior

- WordPress enqueued real installed resources; response bytes matched the checkout's editor support/format assets. No script/style/localization injection or substituted result was used.
- One actual preflight returned one punctuation hint. Its quote matched the submitted text at verified JavaScript UTF-16 offsets after an emoji; doubled spaces were preserved.
- Code and image blocks were excluded from the Cloud language snapshot. Language completed with zero invalid findings; the overall report correctly remained `partial` because coverage excluded those blocks.
- The Cloud panel offered only location buttons and no editable correction fields. Location selected the paragraph without changing title, excerpt or serialized content, including code/image attributes.
- Editing and immediately returning to identical text retired navigation. The old report stayed readable and every location control became disabled.
- Logic and content risk remained `not_checked/not_supported`. The existing local preflight notes are separate and can still mention code punctuation or coarse fact-gap rules; those are not new Cloud judgments.

The screenshot was visually inspected locally. It shows the warning, exact quote,
manual review direction and location control at the normal sidebar width. This
is developer acceptance of one synthetic positive, not independent human adoption,
an error-rate estimate, whole-article clearance or a released-product claim.
The prior offline smoke remains the broader evidence for same-text block identity,
in-flight edits and partial/error states; this run does not replace that evidence.

## Debugging history and retention

The first attempt timed out waiting for sidebar registration after mounting; no
preflight was sent. A subsequent run loaded the installed sidebar, but the test
incorrectly counted automatic local progressive requests as preflight requests.
The harness now counts them separately and waits for the actual preflight response.
No product-source defect or fix is claimed from these setup/test failures.

The body-free JSON records every named language run from this acceptance window,
including the model call made before the harness accounting assertion failed.
Read-only Cloud database checks verified successful runs, one configured
`openai / gpt-6.1-sol` call per run, retry count zero, no fallback, empty stored
input, absent execution ciphertext and omitted stored result. These observations
do not verify upstream provider retention. Raw screenshots and logs remain local.

## Reproduce

First mount/activate the intended clean Toolbox and Addon revisions in a local
WordPress site and confirm its existing Cloud connection. The script verifies
the installed Toolbox is the checkout running it; it does not repair mounts,
activate plugins, change settings or inject an implementation.

```bash
WP_PATH="/path/to/local/wordpress" \
WP_CLI_PHP="/path/to/local/php" \
WP_CLI_BIN="/path/to/wp" \
WP_DB_SOCKET="/path/to/mysql.sock" \
NODE_PATH="/path/to/node_modules" \
node tests/smoke-article-language-installed-browser.mjs
```

This optional command sends one synthetic preflight through the site's configured
Cloud and allows normal local progressive suggestions. It is intentionally outside
`test:all`. The real model can abstain; a missing seeded punctuation hint fails
the usefulness assertion and must be investigated, not substituted or retried
until it happens to pass. Native post REST writes are blocked and autosave locked;
the test never clicks Save/Publish. Output is under ignored `output/playwright/`.

Next product work remains ordinary author feedback and bounded Chinese-language
quality improvements after durable local installation. Formatting continues to
preserve all original text, punctuation, symbols, links and multimedia; only the
author may correct them. Logic/content-risk and remote OSS write support remain
outside this acceptance slice.
