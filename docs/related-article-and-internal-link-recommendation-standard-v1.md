# 相关文章与内链推荐开发规范 v1

状态：Active

最近更新：2026-08-30

适用范围：WordPress 编辑器中的相关文章推荐、内链候选推荐，以及它们依赖的 Cloud Site Knowledge 向量检索。

本文是历史讨论、实现调查和阶段决策的归纳。它记录产品边界、算法原则、用户体验和验证方法；具体接口字段仍以代码中的版本化契约为准。

本轮实际开发的跨仓库排障与验证记录见 Cloud 的
`site-knowledge-recommendation-development-record-v1.md`，Addon 的
`site-knowledge-recommendation-connector-record-v1.md`，以及本文第 13 节的
历史经验归纳。

## 1. 目标与非目标

### 目标

- 在编辑文章时，发现站内语义相关的已发布文章。
- 为人工运营内链提供可审阅的目标文章、链接和理由。
- 以 Cloud 向量检索作为主要相关性来源，支持后续 rerank 和反馈评估。
- 保留 WordPress 编辑器、Core 审批和最终写入的控制权。
- 让空结果可解释：区分 Cloud 不可用、没有索引证据、只命中当前文章和正常无匹配。

### 非目标

- 不在 Toolbox 建立本地 embedding、向量库、索引或 rerank 控制面板。
- 不自动向正文插入链接，不自动保存、发布文章。
- 不自动生成前台“相关文章”区块。
- 不把关键词规则、标题匹配或本地 fallback 冒充成语义推荐。
- 不把推荐结果当作 SEO 排名保证。
- 当前阶段不扩展 Ghost、Typecho、Astro 适配；先完成 WordPress 闭环。

## 2. 产品边界

| 部件 | 负责内容 | 明确不负责 |
| --- | --- | --- |
| WordPress Toolbox | 编辑器上下文、按钮、候选展示、复制/打开、人工确认、最小行为埋点和状态提示 | embedding、索引生命周期、Cloud 向量存储、自动写正文 |
| Cloud Site Knowledge | 公共文章收集、embedding、向量召回、文档合并、可选 rerank、检索状态 | WordPress 审批、文章最终写入、前台区块发布 |
| Toolkit/Core | 内链候选契约、可见编辑器应用和最终保存/发布 | RAG、索引生命周期、Cloud 凭证和集合管理 |
| Eval Lab | 离线 fixture、GPT/Grok 盲审、策略比较和开发证据 | 生产质量真相、自动晋级、WordPress 写入 |

所有推荐结果均为 `suggestion_only`。Cloud 向量是证据和排序输入，不是第二个 WordPress 控制面。

## 3. 相关文章与内链不是同一个排序问题

两者共享召回层，但不能共用完全相同的最终排序：

### 相关文章

主要回答：“读者接下来还应该看什么？”

排序应关注：

```text
主题相关性 + 内容多样性 + 阅读价值 + 新鲜度 + 内容质量
```

### 内链

主要回答：“当前文章的哪个位置，适合链接到哪篇文章？”

排序应关注：

```text
段落/句子匹配 + 目标文章价值 + 锚文本适配 + 插入位置 + 链接结构机会
```

因此，相关文章候选不能未经重新排序直接作为内链目标；内链还需要段落级证据、重复链接保护和人工确认。

## 4. 当前实现链路

当前相关文章流程为：

1. 从标题、摘要、正文和必要的用户指令组成查询文本。
2. Toolbox 调用 Cloud Site Knowledge 的 `related_content` intent，要求 `document` 粒度，最多召回 8 项。
3. Cloud 过滤公开文章，按向量相似度召回；配置启用时可用 Jina Reranker 做二阶段排序。
4. Cloud 对 chunk 结果进行文档级合并，返回文章标题、URL、摘要、理由和分数。
5. Toolbox 再次确认文章仍为公开发布状态，排除当前文章，过滤缺少标题或 URL 的候选。
6. 编辑器最多显示 5 项，当前 MVP 提供打开和复制动作，不自动修改正文。

当前相关代码：

- `includes/Rest_Controller.php`：相关文章候选组装和安全过滤。
- `includes/Provider_Client.php`：Cloud Site Knowledge 请求与结果规范化。
- `assets/editor-content-support.js`：相关文章面板和状态提示。
- `npcink-ai-cloud/app/domain/site_knowledge/service.py`：Cloud 召回、文档合并和 rerank 编排。
- `npcink-ai-cloud/app/domain/site_knowledge/rerankers.py`：可选 Jina rerank 适配。

## 5. 算法原则

### 5.1 召回优先使用语义证据

向量召回负责扩大语义覆盖；关键词、分类、标签和链接图只能作为后续安全边界或排序特征，不能替代 Cloud 语义检索。

### 5.2 召回与排序分层

推荐实现采用两阶段：

```text
候选召回（向量 Top-K）
  -> 文档去重/合并
  -> 发布状态与当前文章过滤
  -> 可选 rerank
  -> 多样性、新鲜度、质量、链接机会调整
  -> 返回有限候选供人工审核
```

不要在 WordPress 端偷偷增加一个与 Cloud 结果竞争的本地向量系统。

### 5.3 可解释性优先于伪精确分数

分数必须标明来源和含义。不能把不同模型的 score 直接横向比较，也不能仅凭分数宣称“最适合 SEO”。候选至少应能追溯到：

- Cloud Site Knowledge 证据引用；
- 文章 ID、标题和公开 URL；
- 召回或 rerank 状态；
- 当前文章排除数和无效候选数；
- 人工采纳、忽略或撤销结果（只记录有限反馈元数据，不记录正文和原始 provider 输出）。

### 5.4 相关文章要控制多样性

如果前 5 篇只是同一主题的重复表达，推荐质量仍然不好。后续排序应加入主题簇或相似度抑制，优先保留不同子主题、不同内容类型或不同阅读阶段的文章。

### 5.5 内链要保护链接结构

内链排序可以使用孤立文章、入链不足、段落匹配和目标文章质量等信号，但这些信号只能帮助人工决策。系统不得为了提高数量而批量插入、重复链接或生成不自然锚文本。

## 6. 第三方组件与参考边界

当前实现没有复制某个开源相关文章插件的完整算法。采用的是业界常见的“向量召回 + rerank + 业务过滤”模式。

当前明确使用或兼容的第三方技术路线：

- Milvus/Zilliz 兼容向量后端；
- 可选 Jina Reranker HTTP API；
- Cloud 侧 embedding provider 和 Site Knowledge 服务。

可以参考但尚未作为本项目实现依据的公开项目/方法包括：

- Qdrant、Milvus 的语义搜索和 metadata filter 示例；
- Haystack、LlamaIndex 的 retriever/reranker 分层设计；
- WordPress 相关文章插件对公开文章、排除当前文章和结果数量限制的产品经验。

参考第三方项目时只吸收通用检索方法，不直接引入其本地索引、爬虫、写入或控制面板。任何新增依赖都必须单独评估许可证、数据传输、成本、可替换性和 Cloud/WordPress 边界。

## 7. 空结果与故障语义

空结果不是一个状态。接口和 UI 应至少区分：

| 状态 | 含义 | 操作提示 |
| --- | --- | --- |
| `cloud_unavailable` | Cloud 请求失败或未连接 | 检查 Cloud Addon/站点知识库连接后重试 |
| `no_cloud_evidence` | Cloud 可用，但没有检索到可用已发布文章 | 确认站内有其他已发布文章且索引完成 |
| `only_current_post` | 只命中当前文章，排除后为空 | 检查索引覆盖和查询质量 |
| `cloud_vector_evidence` | 有 Cloud 候选 | 展示候选并人工审核 |

禁止把空结果标记为 `local_fallback`，除非代码确实执行了本地 fallback。接口可以附带 `cloud_result_count`、`excluded_current_post_count`、`invalid_candidate_count` 和 `candidate_count`，帮助定位“没有推荐”的真实原因。

## 8. SEO 使用规范

相关文章推荐服务于阅读路径和主题覆盖，不等于关键词堆砌。运营内链时：

- 优先链接到能补充当前段落的文章，而不是只因标题包含同一个词；
- 锚文本应自然描述目标内容，避免所有文章都使用同一个短词；
- 一篇文章不应重复指向同一个 URL；
- 重要文章应形成清晰的主题中心页与支柱文章关系；
- 新文章先获得人工审核，再逐步积累链接结构；
- 不要把标题、标签或超短主题词单独作为强相关依据；
- 不承诺排名提升，使用点击率、采纳率、覆盖度和人工相关性评估效果。

## 9. 评测规范

先用 3 至 10 个开发案例验证接线、隐私、分母和评审流程，再决定是否投入
完整人工标注。调整模型、权重或 rerank 前至少保留可重复的小型基线：

1. 选择 3 至 10 篇有代表性的已发布文章作为第一批查询文章。
2. 每篇人工标注候选为“强相关、可补充、仅主题相似、不相关”。
3. 比较纯向量召回、向量 + rerank、向量 + 业务排序三种结果。
4. 同时记录：Recall@K、Precision@K、nDCG@K、重复率、多样性、人工采纳率、忽略率和点击率。
5. 单独评估相关文章和内链，不把两套指标混在一起。
6. 对空结果记录索引状态、文章数量、当前文章排除数和 Cloud 错误，不把空结果当作模型质量结论。

没有真实评测数据时，只能说“架构合理”或“候选可用”，不能说“算法优秀”或“能够提升 SEO”。

### 9.1 后续 30 篇人工标注集

30 篇是稳定人工 gold set 的后续里程碑，不是 MVP 发布门槛。确认真实使用存在
后，再选择 30 篇公开文章，
建议按内容丰富、标题或正文较短、候选较弱或可能为空各 10 篇分层。总体指标
同时报告各层结果和 30 篇 macro average，避免某一类文章掩盖另一类问题。

每篇文章保存一个最小记录；`related_gold` 与 `internal_link_gold` 必须由人工
独立建立，不能把 Cloud 返回候选直接复制成 gold set：

```json
{
  "post_id": 123,
  "title": "文章标题",
  "public_url": "https://example.test/post",
  "content_summary": "不超过 300 字的人工摘要",
  "topics": ["主题或分类"],
  "sample_bucket": "rich|short|weak_or_empty",
  "related_gold": [
    {
      "target_post_id": 456,
      "relevance": "strong|supplemental",
      "annotator_note": "为什么适合读者继续阅读"
    }
  ],
  "internal_link_gold": [
    {
      "target_post_id": 789,
      "source_excerpt": "适用句子或段落的最小必要摘录",
      "suggested_anchor_text": "自然锚文本",
      "anchor_natural": true,
      "annotator_note": "为什么该位置和目标匹配"
    }
  ],
  "annotator": "pseudonymous-id",
  "annotation_notes": "歧义、空集原因或需要复核的事实"
}
```

标注顺序：先阅读查询文章和站内可选文章，形成初始人工认可集合；再盲审系统
Top 5，把候选标为 `strong`、`supplemental`、`theme_only` 或 `irrelevant`。
系统候选可以提醒标注者复查遗漏，但只有经过独立判断和记录理由后才能加入
gold set。先用 6 篇双人标注校准口径；分歧由第三次复核或共同裁决解决，再
继续剩余 24 篇。

内链 gold item 必须同时认可目标文章、正文位置和锚文本。找不到安全、具体、
可匹配的正文短语时，保留目标为“可复制/打开”候选，但不得把它计为可 Apply
的正确内链。`source_excerpt` 只保存判断所需的句子或短段落，不保存全文。

### 9.2 指标口径

相关文章和内链分别计算，不合并成一个质量分数：

- `Precision@5 = Top 5 中命中人工认可集合的数量 / 5`；少于 5 个候选仍以 5
  为分母，并另报 `candidate_count`，让覆盖不足显式影响结果。
- `Recall@5 = Top 5 中命中人工认可集合的数量 / 人工认可集合大小`。人工认可
  集合为空时不计算 Recall，单独报告为 `no_gold_target`，不得用 0 或 1 代替。
- Related Articles 将 `strong` 和 `supplemental` 计为相关，同时分别报告强相关
  命中率，避免宽松标签抬高结果。
- Internal Links 只有目标、适用位置和自然锚文本均通过时才算命中；仅目标相关
  但无安全 `source_match` 的候选不计入 Apply Precision。
- 多样性按 Top 5 中不重复目标、主题/分类和内容类型的覆盖率报告，并同时记录
  同一目标重复率；第一版不为计算多样性额外调用 Provider。
- 相关文章分别计算 `open / impression` 和 `copy / impression`；当前 MVP
  没有明确 Ignore 动作，不得用关闭面板、刷新或离开页面推断负反馈。
- 内链另外计算 `apply / apply_eligible_impression`、保存、保存后编辑和撤销；
  一次候选曝光为一个 impression，Apply 分母只包含有安全精确
  `source_match` 的候选。

技术失败、Cloud 不可用和正常空结果分开统计。`601/601` 只记录为知识库覆盖
证据，不进入 Precision、Recall 或锚文本自然度结论。

在人工标注集完成阈值校准前，编辑器不得把向量分数或通用
`quality_score` 映射为“高度相关”“相关”等质量等级。只有当
`candidate_source=cloud_vector` 且
`retrieval_status=cloud_vector_evidence` 时，才可显示客观的“语义候选”
来源标签；本地回退不得使用该标签。

### 9.3 数据最小化与产物

稳定 gold set 的产物只需要版本化的 schema、标注说明和 30 条脱敏记录。不要保存不必要
的文章全文、Provider 原始输出、prompt、凭证、请求日志或敏感草稿。公开 URL、
短摘要和最小适用摘录足以支持复核；如文章包含敏感信息，使用不可逆样本 ID
并省略 URL。标注集版本必须记录评测日期、Cloud/Toolbox revision、K 值和纳入
规则，后续排序比较必须复用同一版本 gold set。

### 9.4 独立开发者的真实用户积累方式

30 篇是第一版人工质量集的目标规模，不是一次招募任务，也不要求
`10 位用户 x 每人 3 篇`。独立开发阶段按下面的顺序积累：

1. 先由开发者用 3 至 10 篇内部文章证明事件、分母、隐私和保存边界正确；
2. 再让自愿参加内部测试的真实用户正常使用，不给每位用户分配固定篇数；
3. 每周或每两周从真实 impression 中抽取少量有代表性的成功、失败、空结果
   和保存案例，由人工补充 gold 判断；
4. 当累计达到 30 篇不同查询文章时冻结 `gold_set_v1`，再计算第一版
   Precision@5、Recall@5 和多样性；
5. 后续按自然新增案例扩充版本，不为凑数量制造 Provider 请求或冷请求。

行为事件不能自动成为 gold set。`open`、`copy`、`apply` 和 `save` 只能说明
用户采取了动作；它们可能受时间、UI 位置或运营习惯影响。只有人工确认目标、
位置和锚文本后，该案例才能进入人工认可集合。

### 9.5 推荐漏斗口径

推荐结果集用随机、站点范围内的 `recommendation_session` 关联一组行为：

```text
相关文章：impression -> open/copy
内链：    impression -> open/copy -> apply -> native WordPress save/edit
                                      \-> undo
```

- `impression` 是候选结果集分母，不是接受或拒绝结论；
- `engagement_rate`、`open_rate`、`copy_rate`、`apply_rate` 和
  `saved_adoption_rate` 以有 impression 的 session 为分母；
- `save_confirmation_rate` 以发生 Apply 的 session 为分母；
- `saved_edit_rate` 以确认保存的 session 为分母；
- `undo_rate` 以发生 Apply 的 session 为分母；
- 候选数和可 Apply 数只记录有界 bucket，不记录候选正文；
- 每项指标低于 20 个 impression session 时标记为样本不足。

线上 `apply_rate` 用于描述完整产品漏斗；离线人工评测仍应另算
`apply / apply_eligible_impression`，避免没有安全 `source_match` 的文章被误判
为锚文本质量失败。两个指标用途不同，不应合并成一个分数。

## 10. 分阶段路线

### 阶段 A：小型开发基线

- 用确定性 fixture 和边界测试覆盖重复、自荐、空结果、精确 source match、
  两字 CJK 词和 ASCII 词边界；
- 用 GPT 与 Grok 做盲 A/B 银标复核，先跑 3 至 10 个开发案例；
- 默认 dry run，不在未明确需要时消耗 Provider 调用；
- AI 一致只用于缩小人工复核范围，不作为生产质量证明。

### 阶段 B：交付最小动作和真实分母

- 先交付可用的小功能，再观察真实使用；
- `internal_links` 与 `related_content` 分开记录；
- 两类均记录 impression、open、copy；
- 内链额外记录 apply、原生 save、保存后 edit 和 undo；
- related-content 当前没有明确 Ignore 动作，不能从关闭、刷新或离开推断；
- 少于 20 个 impression session 一律标记样本不足。

### 阶段 C：按真实使用积累人工 gold

- 只有出现真实使用后，才抽取分歧、成功、失败和空结果做人工复核；
- 逐步积累 30 篇独立人工标注的查询文章；
- 30 篇是稳定 gold-set 里程碑，不是初始功能的发布阻塞；
- 相关文章和内链分别计算质量指标，行为事件不能自动成为 gold。

### 阶段 D：证据支持后再扩展

- 只有数据证明问题重复存在时，才调整 rerank、权重或候选特征；
- 只有 GPT/Grok 持续出现无法通过 rubric、fixture 或人工复核解决的分歧时，
  才评估第三或第四个默认模型；
- 不因“可能以后有用”新增用户画像、原始正文埋点、队列、向量数据库、
  自动 WordPress 写入或复杂权重面板。

### 停止规则

- 不得用合成 fixture 或 AI 一致率宣称排序已经提升；
- 不得把站点级覆盖、运行成功、PR 合并、M4 验收和人工质量结论混为一谈；
- 连续观察仍无实际使用时，停止扩展模型和基础设施，保留当前小功能；
- Cloud 继续负责运行时和质量证据，WordPress 继续负责最终审核与写入。

## 11. 开发与验证要求

相关文章或内链功能改动至少验证：

- PHP lint 和静态契约测试；
- 编辑器 JS syntax/行为测试；
- 翻译 JSON 校验；
- `git diff --check`；
- 有可用 WordPress/Cloud 环境时，运行相关文章编辑器 smoke；
- 记录本次改动的风险等级、涉及边界、是否调用真实 Cloud、是否达到运行时验证状态。

如果没有真实 WordPress/Cloud 环境，必须明确报告“未执行运行时 smoke”，不得把静态测试结果写成 Cloud 检索质量证明。

### 11.1 锚文本与 Apply 验收

- 锚文本优先使用 Cloud 的 `anchor_or_context` 或
  `suggested_anchor_text`，并且必须与正文中的精确 `source_match` 对应；
- “主题”“文章”“内容”“这里”等泛化词必须拒绝；
- 没有安全锚文本时不得回退到文章标题作为最终 Apply 锚文本；
- 没有 `source_match` 时不显示 Apply，只允许复制链接或打开目标文章；
- Toolbox 只做安全校验和编辑器事务，不重新实现语义关键词推荐算法。

### 11.2 原生保存验收

至少保留一个可重复的临时草稿浏览器场景：

1. 使用已知内容丰富文章的正文创建一次性草稿，不修改原发布文章；
2. 获取 Cloud vector 候选并选择具有精确 `source_match` 的建议；
3. Apply 后读取数据库，证明 `post_content` 尚未变化；
4. 由浏览器显式触发 WordPress 原生保存；
5. 保存成功后再次读取数据库，证明正文此时才变化；
6. 记录 `internal_link_saved_unchanged` 或
   `internal_link_saved_edited`，并验证它与原 impression 使用同一个
   `recommendation_session`；
7. 删除临时草稿和登录 helper，不保留正文快照。

Autosave、Toolbox REST 请求和 Apply 本身都不能被计为保存确认。

## 12. 快速排障清单

相关文章为空时按以下顺序排查：

1. 当前文章是否已保存并有正确 `post_id`；
2. 站内是否存在其他 `publish` 状态文章；
3. Site Knowledge 是否已完成首次索引或增量同步；
4. Cloud 返回状态是不可用、无证据，还是只命中当前文章；
5. 返回结果是否包含合法 `post_id`、标题和 URL；
6. 查询是否被选中文本或过短主题词主导；
7. 结果是否因文档合并、当前文章排除或公开状态过滤而减少；
8. 再考虑模型、embedding 或 rerank 本身的问题。

这份顺序体现一个经验：先确认索引和边界事实，再判断算法质量。

## 13. 历史开发经验归纳

### 13.1 先查边界，再查算法

本次“文章没有相关文章/索引显示为 0”的问题，最终不是向量算法失效，
而是请求在 Cloud 外层通用列表校验中被 200 条上限拦截。小规模请求成功、
完整文章清单失败，是定位这类问题最有效的对比实验。

### 13.2 契约限制必须按能力收窄

Site Knowledge 状态接口已有 1000 个文章 ID 的业务契约，修复时只对
`input.post_ids` 增加能力专属上限，不能把所有运行时列表统一放宽。
每一个上限都要有“允许边界、超限边界、其他能力不受影响”三类测试。

### 13.3 空状态必须可解释

“没有推荐”至少要区分 Cloud 不可用、没有索引证据、只命中当前文章、
正常无匹配和本地筛选为空。默认“未索引”列表在缺失数为 0 时显示空状态，
属于正常业务结果；“全部”列表才是完整覆盖核对入口。

### 13.4 浏览器验收不可省略

后端测试只能证明接口返回，不能证明 WordPress 页面正确展示。每次涉及
覆盖或推荐的改动，都要实际验证摘要、筛选、分页、中文、编辑器结果和
无写入边界。当前站点 601 篇全部已索引，因此“未索引单篇刷新”仍需隔离
测试数据补验，不能用全量已索引结果代替。

### 13.5 先建立小型基线，再逐步积累 gold

先用 3 至 10 个案例和真实 impression 验证功能是否值得继续。30 篇人工标注
是后续稳定 gold-set 目标，不是初始阻塞。没有独立人工 gold 时，只能说
“流程可用”或“出现使用信号”，不能说“算法优秀”或“能够提升 SEO”。

### 13.6 推荐结果始终是建议

打开、复制和人工插入属于编辑动作；当前相关文章没有显式 Ignore 动作，
不能从关闭或离开推断拒绝。自动插入锚文本、自动保存、
自动发布和自动生成前台相关文章区块继续禁止。任何后续内链应用功能都
必须保留可见编辑器、撤销和 WordPress 原生保存边界。

### 13.7 分母必须先于优化

只有点击事件没有 impression，就无法区分“用户没看到”与“用户看到了但没采用”。
先建立 session 级分母和保存确认，再讨论模型、排序或 UI 优化。

### 13.8 Apply 不等于采纳

Apply 只改变当前 Gutenberg 可见状态。用户可能撤销、继续编辑或关闭页面。
真正的编辑采纳必须等待一次成功、非 autosave 的 WordPress 原生保存；Cloud
只接收结果元数据，不接收保存后的正文。

### 13.9 浏览器测试应验证数据库边界

只断言编辑器 DOM 或 Block API 变化不够。浏览器验收应在 Apply 前后和原生
保存后读取真实 `post_content`，这样才能发现隐藏 REST 写入或错误的保存归因。

### 13.10 运行时证据与质量结论分层

`retrieval_status=cloud_vector_evidence`、`candidate_source=cloud_vector` 和
`fallback_used=false` 证明候选来源；它们不证明推荐自然、有用或能提升 SEO。
运行链路、人工质量、合并状态和生产状态必须分别报告。

## 14. 文档维护规则

后续修改按实际所有权落到对应仓库，不在 Toolbox 文档中复制另一仓库的
接口细节：

- Cloud 的检索字段、数量限制、索引状态、向量召回和 rerank 行为，更新
  `npcink-ai-cloud/docs/` 中的运行时契约或开发记录；
- WordPress 文章快照、签名传输、覆盖比较、权限、翻译和状态投影，更新
  `npcink-cloud-addon/docs/` 中的连接器契约或连接器记录；
- 编辑器按钮、候选展示、SEO 文案、人工确认、撤销和不写入边界，更新
  本仓库的相关文章与内链规范及相关测试；
- 跨仓库决策先更新边界文档，再修改实现，并在各仓库 README 或文档索引
  保留可发现入口；
- 文档与实现冲突时，以版本化运行时契约、当前测试和活动边界标准为准，
  同步修正文档，不用历史记录覆盖当前事实。
