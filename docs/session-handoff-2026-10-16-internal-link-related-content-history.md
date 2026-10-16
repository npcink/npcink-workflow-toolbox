# Session Handoff — 2026-10-16 文章内链与相关文章推荐

状态：历史会话归纳与下一阶段建议。本文是交接记录，不替代
`AGENTS.md`、`docs/boundary.md`、`docs/related-article-and-internal-link-recommendation-standard-v1.md`
或代码中的版本化契约。

来源会话：`thread://01a1390a-7657-78e0-8934-ce36dbc9b60e`，标题为“文章内链和相关文章推荐”。
本记录基于该会话的完整历史内容和当前工作树读取结果整理，读取日期为 2026-10-16。

## 1. 历史结论

### 1.1 对同类项目的调研结论

历史会话调研了 YARPP、Contextual Related Posts、Better Internal Link Search、
Relevanssi、ElasticPress、WP Vector Search、SemantiQ Search、LOOM 和 Semantic Links。
可迁移的共同经验是：

- 把候选召回和最终业务排序分开；向量适合扩大语义召回，不能单独证明候选质量。
- 相关文章和内链是两个排序问题。相关文章更看主题相关性、多样性、阅读价值和新鲜度；内链还要看正文位置、自然锚文本、目标文章价值、重复链接和站点链接结构。
- 混合排序可以组合向量、词项/标题、主题、锚文本和图谱信号；RRF 或归一化融合可避免直接相加不同模型分数。
- 算法版本、索引新鲜度、缓存和失败状态必须可追踪，否则无法解释结果变化。
- 查询、候选展示、打开、复制、Apply、撤销和保存需要以一个有限的推荐会话关联；行为事件是使用信号，不是自动的人工相关性真值。

这些项目不能直接照搬到 Toolbox：本地向量数据库、embedding key、索引/重建队列、自动正文写入、GSC 个性化模型和新的日志数据库都越过当前边界。

### 1.2 当前功能判断

| 能力 | 历史会话确认的状态 |
| --- | --- |
| 内链推荐 | MVP 已落地。Cloud Site Knowledge 召回候选，Toolbox 做展示和安全过滤，Toolkit 负责内链候选契约、图谱证据和精确锚文本校验。作者可人工选择最多 8 条，把结果应用到当前 Gutenberg 可见编辑器状态；随后必须由作者正常保存。支持逐条结果、失败原因和请求级 Undo。 |
| 相关文章推荐 | 已落地为 `related_content` 的只读参考候选。编辑器可打开文章、复制链接并记录最小行为事件；不会自动生成前台相关文章区块，也不会自动插入正文。 |
| 排序质量 | 只能确认链路可用，不能声称排序已经被生产数据证明有效。历史会话明确提出先冻结 baseline、补排序版本和数据字段，再用真实数据验证。该项在被中断的后续会话中尚未完成。 |

### 1.3 已完成的工程跟进

历史会话随后推进了两类工作：

1. 运行最新 UX 版本的浏览器 smoke。progressive、UX hardening 和 review-artifacts
   场景通过；内链场景在 Cloud 未配置时通过了 fail-closed、无编辑器/数据库写入的验收。
   健康 Cloud 候选证据、排序和 Apply 路径仍需要在已连接环境中复验；此前本地 Addon
   也出现过候选证据 HTTP 502，因此不能把这次结果当成 Cloud 质量验证。
2. 将内链候选整理、Toolkit 委托、图谱证据、精确锚文本校验和 URL fallback 从大服务中
   拆到 `includes/Rest_Editor_Internal_Links.php`，并覆盖 Toolkit artifact 的
   `target_ref.url`、`source_candidate_ref`、嵌套 `source.target_url` / `source.url`
   形状。该 S2 URL fallback 已在当前项目记录为关闭项。

历史会话报告的验证包括 `composer test:all`、PHPStan、PHPCS、内链 JS 行为测试和
`git diff --check`；当前分支 HEAD 为 `216e102`（`test: accept current internal-link empty state copy`），工作树在本次文档编辑前保持干净。

### 1.4 2026-10-16 阶段 0 实测

- 本地五插件站通过 PHP 内置服务器运行在 `http://127.0.0.1:8090`，首页返回 HTTP 200；五个 Npcink 插件均已激活。
- 公网 Cloud `https://cloud.npc.ink/health/live` 返回 HTTP 200，说明 Cloud 公网存活；这不等于当前 WordPress 站点已授权。
- 本地 Cloud Addon 只读状态为 `configured=false`、`verified=false`、`site_knowledge_delivery_enabled=false`，因此编辑器真实内链请求显示 Site Knowledge temporarily unavailable，候选为空。
- `composer smoke:editor-internal-link-batch-browser` 在关闭未配置 Cloud 例外时按预期失败于 Cloud/Addon 错误；它没有进入真实候选、排序或 Apply 验证。配置完成后必须用同一 smoke 重跑，并确认 `related_content` 与 `internal_links` 都有 Cloud 证据。

在本地 Addon 完成 Portal 授权或高级恢复配置、连接验证并开启 Site Knowledge delivery 前，不得冻结生产排序 baseline，也不得把空候选写成排序质量结论。

## 2. 当前项目事实

### 2.1 所有权和写入边界

- Cloud Site Knowledge 负责 embedding、向量存储、召回、文档合并和可选 rerank。
- Toolkit/Core 负责内链候选契约、可见编辑器应用和最终治理写入路径。
- Toolbox 负责编辑器上下文、候选展示、复制/打开、人工确认、最小行为埋点和状态提示。
- Toolbox 不拥有本地 embedding、向量库、索引生命周期、rerank 控制面板、队列、自动正文写入或前台相关文章区块。
- 所有推荐结果保持 `suggestion_only`；内链 Apply 只改变当前可见编辑器状态，持久化仍由作者的正常 WordPress 保存完成。

### 2.2 已存在的结果和事件字段

当前 `recommendation_set.v1` / `editor_recommendation_set.v1` 已提供：

- `recommendation_set_id`、`generated_at`、`intent`、`content_fingerprint`；
- `source_layer`、`latency_profile`、`artifact_counts`、`retrieval_sources`；
- `candidate_source`、`retrieval_status`、`fallback_used`；
- 内链候选的 `candidate_relevance`、`quality_score`、`source_match`、`link_graph_issues`、`shared_terms`、`incoming_count`；
- `no_write=true`、`write_posture=suggestion_only` 和 `direct_wordpress_write=false`。

当前 metadata-only 行为链路已分别关联 `recommendation_session`：

- 两类推荐：impression、open、copy；
- 内链额外有 Apply、native save、保存后编辑和 Undo；
- impression 至少带有候选数量、可应用数量、检索状态、候选来源和 fallback 的有界 bucket；
- 反馈不会保存正文、原始锚文本、Provider 输出或 evidence refs。相关口径见
  `docs/recommendation-eval.md` 和推荐标准第 9.5 节。

### 2.3 数据完整性缺口

要冻结排序 baseline 并进行可重复比较，还缺少以下信息：

1. **排序版本**：结果集和事件没有显式 `ranking_version`。算法、Cloud 配置或权重变更后，历史事件无法直接按排序策略分组。
2. **候选位置**：候选数组有顺序，但事件没有稳定的 `candidate_rank`/位置字段，无法计算位置收益或比较 Top-1 与 Top-5 的差异。
3. **排序来源的细粒度状态**：当前主要是 section 级 `retrieval_status`、`candidate_source` 和 `fallback_used`；缺少可安全聚合的 `rerank_applied`、排序配置/特征版本和候选级 `score_bucket`。
4. **真实 Cloud 样本**：当前浏览器验证主要证明 fail-closed 和无写入边界；健康 Cloud 下的真实候选、Apply、反馈回传和延迟还没有形成稳定样本。
5. **人工质量集**：尚未形成可复用的 3–10 篇开发案例、6 篇双人校准集或 30 篇 `gold_set_v1`。行为事件不能直接替代人工相关性判断。
6. **跨仓聚合确认**：Toolbox 已按边界只发有限事件，但下一步仍需确认 Cloud 侧按 `ranking_version` 聚合、保留和导出的实际契约；不要在 Toolbox 内新增本地日志库。

## 3. 下一阶段建议

建议把下一阶段定义为“推荐排序观测闭环”，暂缓继续增加新的推荐动作。顺序如下。

### 阶段 0：先恢复可观测的 Cloud 运行条件

在已连接且索引状态可解释的站点上，重新跑内链和相关文章编辑器 smoke，并记录：

- Cloud 候选证据是否返回；
- `related_content` 与 `internal_links` 是否分别返回；
- 是否启用 rerank；
- 候选展示、复制、Apply、native save、Undo 是否能回到同一个推荐会话；
- HTTP 502、未连接、无索引证据、仅命中当前文章和正常无匹配是否被分开。

这一步只建立运行时前提，不调整排序权重。

### 阶段 1：冻结两个独立的 baseline

相关文章和内链不能共享一个不可解释的版本号。建议采用两个可审计的、只增字段的版本标识，例如：

- `related_content_ranking_version = related_content.v1`；
- `internal_links_ranking_version = internal_links.v1`。

实现时把版本附在结果 section 和推荐会话事件中，并同时记录以下有界元数据：

- `candidate_rank`：1 到展示上限；
- `score_bucket`：离散区间，不发送原始分数；
- `retrieval_source`：Cloud vector、Cloud vector + rerank、明确的 local fallback 或 unavailable；
- `rerank_applied`：布尔值；
- `feature_profile_version`：若 Cloud 已有可识别的特征组合，再以兼容字段加入。

字段必须是 additive、metadata-only，并由 Cloud 负责长期聚合；不要把 Provider 原始分数、正文、锚文本、URL、prompt 或密钥写入事件。

### 阶段 2：先检查数据是否足够，再开始采集

先做一张端到端字段审计表：浏览器事件 → Toolbox REST/Ability → Cloud 接收 → 聚合查询。每一列标出“存在、缺失、由谁拥有、是否可用于排序比较”。

随后用 3–10 个确定性开发案例验证：

- 两类推荐的分母没有混用；
- impression、open/copy、Apply/save/edit/Undo 的会话关联完整；
- 技术失败和正常空结果分开；
- 没有 raw content、raw anchor、URL、evidence ref 或 Provider 原始输出泄漏；
- `candidate_rank`、版本和 bucket 在结果与事件中一致。

### 阶段 3：保持排序不变，积累真实数据

baseline 通过后，连续观察 2–4 周，或直到每个推荐族至少有 20 个有效
`recommendation_session`。先报告描述性指标，不把它们当成相关性真值：

- 相关文章：`open / impression`、`copy / impression`、候选数、空结果率、多样性；
- 内链：`apply / apply_eligible_impression`、保存确认率、保存后编辑率、Undo 率；
- 两类共同报告 Cloud 可用率、fallback 比例、p50/p95 延迟和技术失败率；
- 所有指标按排序版本、检索来源和空结果状态分组；样本不足时明确标记。

### 阶段 4：建立人工 gold set，再做一次单变量回放

从真实 impression 中抽取成功、失败、空结果和分歧案例。先完成 6 篇双人标注校准，
再逐步扩到 30 篇稳定 `gold_set_v1`。相关文章和内链分别标注；内链只有目标文章、
正文位置和自然锚文本都通过时才算可 Apply 命中。

有稳定数据后，一次只改变一个变量：向量权重、rerank、锚文本加分、图谱信号或多样性抑制。使用同一批文章做离线回放，比较 Recall@K、Precision@K、nDCG@K、重复率、多样性和行为指标；没有证据就不提升默认模型或权重。

## 4. 暂缓事项和停止规则

- 暂不在 Toolbox 增加本地索引、重建任务、Qdrant 生命周期、队列、日志数据库或自动写正文。
- 暂不把相关文章变成前台自动区块，也不把相关文章候选直接当作内链候选。
- 暂不因单次 Cloud 502、单个空结果或合成 fixture 改排序。
- 少于 20 个 impression session 时只报告样本不足；行为事件不能自动成为 gold。
- 只有在固定 baseline、数据字段完整且人工样本支持后，才讨论默认开启 rerank 或调整权重。

与本模块无关的仓库债务（Cloud hold-comment 校准、Media ALT 采用率门槛、PHPStan ratchet 和其他集群拆分）按 `docs/current-state.md` 与 `docs/known-issues.md` 的优先级推进，不应混入本次排序观测契约。

## 5. 下一位 AI 的首个动作

1. 阅读本文、`docs/related-article-and-internal-link-recommendation-standard-v1.md`、
   `docs/recommendation-eval.md`、`docs/recommendation-candidate-contract.md` 和
   `docs/boundary.md`。
2. 先做阶段 0 的健康 Cloud smoke，确认是否能得到真实候选证据。
3. 若运行条件满足，再提交一个只增加排序版本和有界候选元数据的最小契约变更，并为每个字段补 `tests/run.php` 静态合同。
4. 通过静态 gate 和可用环境 smoke 后，才进入 2–4 周观察期；观察期间禁止调整排序。

