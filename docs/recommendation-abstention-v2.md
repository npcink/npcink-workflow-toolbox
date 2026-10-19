# 推荐“不推荐”规则 v2

当前开发版本在 Cloud 的原 v1 scorer 后增加阅读任务资格检查，分别返回
`related_content.v2` / `internal_links.v2`。冻结 v1 的函数、权重和对照证据保留。
规则说明在 Cloud 的 `docs/site-knowledge-reading-task-abstention-v2.md`。

Toolbox 只消费同推荐族的已知 Cloud 版本；旧 Cloud 缺少版本时沿用 v1；未知或跨推荐族版本不伪装成 v1/v2，反馈省略排序观测。
健康的明确 v2 abstention 不调用 Toolkit 填充本地候选，保持 0 条、无 Apply，
并沿用当前文章的 native_editor_commit 边界。Cloud 错误仍显示 unavailable。
普通 v1 空结果和既有 fallback 行为不因这项兼容修改而变化。

Metadata-only impression 保留 v2、`no_evidence`、`candidate_count_0`、
`applicable_count_0`、`fallback_not_used` 与 `recommendation_abstained`；没有单一
候选位置就不发 `candidate_rank`，不添加原文、锚点、标题、URL 或原始分数。
旧 v1 反馈仍有效。Cloud 与 Addon 先兼容版本，再发布 Toolbox。

回归由 npcink-eval-lab 绑定原 12 篇来源、完整公开目录和同索引 v1/v2 对照。
锚点资格 v2、Apply/Undo/原生保存继续独立验证；不把过滤数当准确率，不把
AI/developer smoke 当人工采纳或 gold。正文投递仍有裁剪，目标提及也未必直接
解决阅读任务；这些限制在发布评估中单列。无生产部署。
