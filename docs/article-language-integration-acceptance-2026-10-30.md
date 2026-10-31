# Article language integration acceptance — 2026-10-30

本轮完成本地候选修正及离线跨仓/浏览器验收，**未达到跨仓正式收口或上线状态**。
只检查中文语句疑点，逻辑和内容风险仍为 `not_checked/not_supported`。
文字、符号、标点与多媒体始终由作者人工修改；报告没有更正、插入、保存、发布
或阻断发布的权限。排版仍是独立的版式操作，本轮没有修改排版执行器。

## Named candidates and evidence

| Repository | Verified revision | State |
| --- | --- | --- |
| Toolbox | `830ec2d46383b9110fbc892a239f0b6a25614b36` | Rebased on `ce041351`; local candidate, not pushed |
| Addon | `cf11b22d0e0dfe94e4c9094d312aad8b29f73ae2` | Rebased on `cc31272`; local candidate, not pushed |
| Cloud | `57670ed7f6cefd0567eed1e99657e1a2fcde44c3` | Unchanged named candidate; local ahead 1 / behind 3 at final fetch |

The accompanying [body-free receipt](article-language-integration-evidence-2026-10-30.json)
binds runtime/test source hashes and the code revisions above. This acceptance
record is a subsequent documentation-only commit, not a claim that its own SHA
already had GitHub CI. Earlier M4 evidence names the Cloud candidate only; this
session did not synchronize, deploy or retest M4, or deploy WordPress/production.

## Defects fixed

- Addon previously included `contract_version` in Cloud `input`, which the real
  strict Cloud validator rejects. The identity now appears only in the outer
  runtime envelope. Regression exercises the actual cross-repository functions.
- The dedicated preflight snapshot preserves whitespace, line breaks, literal
  angle-bracket text, Unicode and punctuation. It does not reuse legacy normalized
  `content_blocks`, escape U+2028/U+2029 for hashing, or split surrogate pairs.
  Missing/malformed snapshots cannot silently become title-only checks.
- Code/media/custom blocks are excluded. The UI discloses Cloud transmission
  before running and reports checked/excluded/omitted/truncated blocks, partial
  coverage, hidden invalid findings and the result limit. No findings is not a
  correctness or safety verdict.
- Result fields/types/statuses and every UTF-16 evidence range are checked locally.
  One invalid evidence hides its entire finding. Literal quotes render as escaped
  text with whitespace preserved; repeated quotes have no navigation shortcut.
- A subscription records monotonic editor generations. Edit/Undo, changed block
  ids and edits during requests permanently retire the older report's navigation.
  A click rechecks identity/generation and the live exact quote before selecting
  a block. No post editing or save method is called by this report.
- Each explicit call uses the Addon's fresh default idempotency key, avoiding
  indefinite text-hash replay. There are no automatic retries or new routes,
  registries, approval stores, background tasks or Addon version hard gates.

## Gates and limits

| Gate | Result |
| --- | --- |
| Toolbox `composer test:all` via final matrix | Passed; includes 4,513 static contracts and behavior gates; existing PHP dynamic-property deprecations remain |
| Addon `composer test:all` via final matrix | Passed |
| Toolbox `composer lint:standards` / `composer analyse:php` | Passed |
| Addon `composer stan` | Passed |
| Both `composer validate --no-check-publish` / `git diff --check` | Passed |
| Addon `composer smoke:playground` | Passed on WP 7.0.4 / PHP 8.2; activation, default fail-closed boundary and reconciliation cursor smoke |
| Four offline cross-repository round trips | Passed: exact Unicode, UTF-16 boundary, repeated quotes, zero findings, read-only projection |
| Real Gutenberg + injected candidate browser UI | Passed: actual collector limits, preserved whitespace, read-only location, edit/Undo, changed block ids, in-flight edit, partial/limit/unavailable feedback, zero post-write requests, unchanged fixture database source |
| Cloud exact-SHA GitHub source gate | **Not passed**: matrix reports `blocked_environment`, exit 75; direct API diagnostic returns HTTP 422, `No commit found for SHA` |
| Matrix overall | Exit 1; cannot be presented as an all-green cross-repository acceptance |

The initial matrix on `4ecd58a` / `75f7070` also passed both local suites while
missing the real envelope incompatibility. Independent repository suites alone
were insufficient; the new offline bridge catches that failure without invoking
a provider. The final matrix receipt is locally preserved under ignored
`build/acceptance/article-review-quality-matrix.json`.

Browser acceptance used `https://magick-ai.local` and a synthetic disposable
post. The site's old Toolbox symlink pointed at an absent worktree. Candidate
assets were injected only in the test browser; all unrelated Toolbox calls were
blocked. Addon guard and Cloud validator/finalizer were actual candidate code,
while WordPress/PHP calls used the existing behavior stubs and provider output was
controlled synthetic data. This proves interface/interaction preservation, **not
installed-plugin activation, signed Cloud transport, live model quality or provider
retention policy**. Playground activation is separate evidence.

The final verified draft was `283011`; it and its temporary login session were
removed. Earlier harness failures were corrected; the orphan synthetic draft was
removed and an exact-title query found zero remaining test drafts. The final
browser source comparison covered its own fixture; no customer article was used
or saved. Screenshots contain synthetic content only, under ignored
`output/playwright/`. No token/cookie/body is retained in the JSON receipt.

## Reproduction and next stage

With each candidate available, run from Toolbox using a Python environment with
the Cloud candidate's dependencies:

```sh
"$ARTICLE_REVIEW_PYTHON" tests/article-review-cross-repo-contract.py \
  --addon-root "$ARTICLE_REVIEW_ADDON_ROOT" \
  --cloud-root "$ARTICLE_REVIEW_CLOUD_ROOT"
node tests/smoke-article-language-review-browser.mjs
```

Export the three `ARTICLE_REVIEW_*` variables for the browser script as well.
Set `WP_PATH`, `WP_CLI_PHP`, `WP_CLI_BIN`, `WP_DB_SOCKET` and a resolvable
Playwright dependency (`NODE_PATH` when needed) explicitly. It creates/deletes only its own synthetic draft/session and
blocks browser post writes. The shared `vendor` installations were used through
ignored worktree symlinks for deterministic PHP tooling.

The next publication stage must establish exact-SHA GitHub source evidence,
perform advisory local Qwen review, and follow each repository's PR publishing
contract. After a separately authorized installation/runtime step, verify the
signed end-to-end path and bounded Chinese useful positives/false positives on
named revisions. Keep language quality separate from schema correctness. Do not
activate logic/content-risk from the older general design examples, or infer OSS,
indexing, media writes or production approval from this milestone.
