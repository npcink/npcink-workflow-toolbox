# 脱密对话助手 — 产品边界草稿 v1（MVP）

> 草稿：用于对齐「脱密数据 + 对话/CLI」方向。非已发布产品面。

## MVP（当前验证切片）

- **入口仓:** Workflow Toolbox（Addon 不另开第二控制面）
- **第一包:** 仅 **巡检摘要** `site_ops_insight_pack.v1`（Site Check 同源）
- **验证方式:** 只读 REST + CLI 试问 Cloud — 见 [insight-pack-cloud-qa-cli.md](insight-pack-cloud-qa-cli.md)
- **写姿态:** `suggestion_only`；不直写 WP；不自动 Core proposal
- **运输:** 推理在 Cloud；签名/runtime 经 Addon；**不**走已暂停的 `cloud_inspection`
- **通道:** `hosted_ai_content_support.v1`（验证用），不是 Site Ops Cloud detail UI

## 后续（非 MVP）

站点概况环境字段、真错误面、内容结构/连接用量等更多脱密包、对话壳 UI、按需取包编排 — 待 MVP 验证通过后再开。

## 红线（摘要）

不上传正文全文、评论正文、邮箱、IP、密钥、原始私密 URL 等；以现有 Site Ops / Site Knowledge 隐私省略为准。
