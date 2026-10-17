# Internal-link anchor eligibility v2

Scope: Toolbox current-editor eligibility and visible Apply only. Version:
`internal_link_anchor_eligibility.v2`. It is independent of the unchanged
`internal_links.v1` / `related_content.v1` ranking baseline. No weights, recall,
scores, candidate ranks/order, target IDs, supplied anchors or Toolkit matching
contracts change. Frozen v1 remains reproducible at Toolbox
`bfeec7e3518471b55182809cf9683529e4dd19be`.

## Problem and behavior

For 11926 → 439, Toolkit correctly matches `搭建个人` in
`那么现在还有必要为自己搭建个人网站吗？`. Structural Apply previously accepted
it even though the head noun `网站` falls outside the proposed link. Exact
matching alone cannot establish phrase completeness.

v2 keeps this candidate and its exact span visible for manual review, but
disables selection/Apply with a translated reason. It never extends the anchor,
chooses another occurrence or target, rewrites the article, or saves anything.
Readiness continues to run the same Apply engine against current rich-text
state. Direct single-item preparation and batch Apply both enforce the guard.

After unchanged stale-state, existing-range and existing-target checks, reject
these bounded shapes on the actual matched range:

- A span ending in `个人`, `私人` or `独立` immediately before optional whitespace
  and `网站`, `博客`, `主页`, `站点`, `空间`, `账号`, `账户`, `品牌` or `项目`:
  `anchor_incomplete_compound`. The same check protects an ending `Google` /
  `谷歌` before `云端硬盘`, `云盘`, `Drive`, `Docs`, `Sheets` or `Chrome`.
- Dependency-shaped starts `的` (excluding `的确` / `的士` / `的卢`), `来的`, or
  `于` before Latin text; endings `的` (excluding lexical `目的` / `标的`),
  `一个`, `一种`, `一些`, `一款`, `一套`, `一位`, `一名`, `一条`, `即可享受`,
  `便可享受` or `可以享受`: `anchor_dependent_fragment`.
- A span starting/ending inside an ASCII technical identifier, including a
  continued hyphen/dot identifier: `anchor_partial_identifier`.
- Generic project/resource captions (Github, Demo, 原文来源, 代码来源,
  项目来源, 官方网站, 第三方演示, 插件介绍, 主题介绍, 安装教程, 使用文档,
  项目下载, 下载地址, 项目介绍, 功能演示), in a colon/standalone caption position, or a bare numeric price caption:
  `anchor_reference_requires_review`. The current contract does not establish
  the specific resource's ownership, so these stay manual. This does **not**
  judge their language unnatural or assert every target is wrong.

Full `搭建个人网站`, `HTML内容`, `background-attachment`, `Node.js`, standalone
`面向个人`, `JavaScript` before Chinese prose and the discussed `category` URL
component remain eligible if all existing checks pass. Punctuation breaks the
modifier-noun continuation. Unicode offsets still use the existing matcher;
emoji and inline formatting controls verify that the observed span is preserved.
Named `Intro.js功能演示`, `教育目的` and `交易标的` controls stay eligible. Nominalized
`的` phrases outside the explicit exceptions can conservatively become manual;
this is an Apply guard, not a grammar correctness label.

This is a conservative, explicit set of fragment shapes, not general Chinese
segmentation, full naturalness detection or destination-ownership inference.
It cannot prove a target relevant or authorize an insertion. Existing author
review and structural protections remain necessary. Other language patterns
need independent counterexamples before expanding the rule.

## Version and observation

The in-memory `current_article_multi_link_result.v1` gains optional
`anchor_eligibility_version`; readiness returns `anchorEligibilityVersion`.
No REST schema or ability definition changes. New internal-link feedback uses
the existing bounded `source_reason_codes` slot `anchor_eligibility_v2`;
ranking metadata and Cloud feedback schema remain unchanged. Related feedback
does not receive this code. Existing follow-up seeds retain their original
codes rather than relabeling historical events. No raw anchor/context is sent.

Eval Lab stores current and frozen-v1 eligibility side by side for the same
live blocks and unchanged candidates. It records helper hashes and the frozen
revision. The old read-only/model/human artifacts are not overwritten. The
45 control cases are developer assertions, never human relevance gold.

## Verification and rollback

Run `composer test:internal-link-editor-js`, `composer test:all`, and Eval Lab
`composer eval:link-recommendation:anchor-v2` (thin wrapper over the same editor
engine with its explicit control fixture). Replay all 12 real articles against
both versions and check every new rejection. Existing-link, stale-state,
Apply/Undo and native-save positive paths must still pass; save tests use only
disposable copies. Re-run the release assessment on new evidence, leaving the
old hold report intact. Product usefulness remains distinct from these gates.

Rollback is a source revert to the documented v1 helper after reassessing the
known truncated-anchor release blocker. There is no operator/AI parameter that
can select v1 to bypass v2 during live Apply. No production deployment occurs
as part of this source change.
