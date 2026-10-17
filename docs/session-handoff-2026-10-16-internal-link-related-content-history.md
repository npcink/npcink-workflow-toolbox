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

### 1.5 `magick-ai.local` 阶段 0 复验

- 正确站点为 `https://magick-ai.local/`。Addon 只读状态为 `configured=true`、`verified=true`、`site_knowledge_delivery_enabled=true`；缓存的 Site Knowledge 状态为 `ready`，约有 2130 个索引文档。
- Addon 实际指向 `http://127.0.0.1:18010`，该端口由 SSH 隧道转发至 M4 preview 的 `8010`。它不是 authoring Mac 上的旧 Docker Compose；旧 Compose 的 worker/Postgres DNS 和 frontend 重启日志不能作为本次失败的根因证据。此前把两者关联起来的判断已撤回。
- M4 API、Postgres、Redis 和 worker 当前健康，accepted revision 为 `fe920796`（诊断时）。用真实编辑器 payload 回放：80 字符查询的 `related_content` 在约 8.25 秒返回 HTTP 200、8 个候选；相同查询加 24 段 source passages 的 `internal_links` 在 20 秒后发生 cURL timeout。M4 proxy 记录对应请求约 19.93 秒后为 499，符合客户端超时中断。
- 已将诊断重点缩小到内链 source anchor 匹配：当前 PostgreSQL fallback 会对候选逐一检查正文锚文本，窗口扫描次数随候选量、段落数及文本长度增长。正在独立 Cloud worktree 验证保留匹配语义的性能修复；不通过提高 timeout 掩盖问题，也不重启无关的旧 Compose。
- 因此当前阶段 0 只证明授权、索引状态和健康端点存在，尚未证明编辑器链路能稳定返回相关文章/内链候选、反馈和 Apply。排序 baseline 与 metadata-only 字段实现继续等待一次稳定的真实 smoke。

### 1.6 阶段 0 性能修复的本地检查点

- Cloud 修复保存在 `/Users/muze/gitee/.worktrees/cloud-site-anchor-performance`，分支 `codex/site-anchor-performance`，基于 `fe920796`。原 Cloud checkout 中另一会话的前端改动保持原状。
- 修复只涉及 `app/domain/site_knowledge/service.py` 及对应 domain 测试：每次候选请求共享正文的四字符 prefix 索引，按实际可能匹配的段落跳过无效窗口；找到 title 证据后跳过优先级更低的 chunk 扫描。原有 exact substring、ASCII word boundary、通用/完整标题过滤、最长窗口和 tie-break 仍为最终判断依据。
- 使用真实请求的 24 段正文与合成的 32 个候选作本地比较，匹配输出完全一致，耗时从约 5.41 秒降至 0.23 秒（约 24 倍）；这只是 matcher benchmark，不是端到端延迟或排序质量结论。另有 600 组固定随机种子的旧/新 matcher 等价比较通过。
- 最终本地验证：15 项 anchor domain 测试 + 46 项 Site Knowledge API 测试通过；Ruff 和定向 mypy 通过。早一版候选曾同步 M4 并通过 60 项定向测试，最终小修尚未重新同步，因此不能把该证据归到最终工作树。
- Toolbox browser smoke 补充 fail-closed 断言：配置后的 Cloud 若返回 `cloud_unavailable`，即使编辑器 REST 外层为 HTTP 200 也必须失败。只有显式的未配置 Cloud 验收模式仍允许相应降级路径。
- M4 同期被另一会话同步。用户明确选择“另一会话优先，本次先完成本地修复与测试”，本次因此停止后续 M4 同步、readiness/候选回放和浏览器操作。同期回放中的 timeout/502 不用于判断本修复效果，也不形成新的稳定性证据。
- 后续恢复时先取得 M4 使用时段，把最终 Cloud 工作树按标准同步为 candidate，重跑定向测试，再连续验证两类真实候选、反馈及 disposable draft 的 Apply/Undo/native save。阶段 0 通过后才冻结两类 baseline、补 `ranking_version` / `candidate_rank` 等字段；当前尚未实施这些后续改动。

### 1.7 恢复验证准备与运行环境交接

- 已通过 `read_thread` / `wait_threads` 确认另一会话“服务器资源占用监测”仍持有 M4，正在完成 PR #1119 的检查与正式接收。用户授权发送一次协调消息；消息已发出，对方确认完成当前验收及 clean-master promotion 后记录释放状态与最终 revision。在读取到交接证据前，不覆盖该候选。
- 等待期间用本地 WordPress 的 1981 篇已发布文章标题及最长 1200 字符正文片段回放 24 段真实 source passages，最终 matcher 约 1.50 秒完成。该检查没有调用 Cloud、embedding 或付费 Provider，也不代表端到端 smoke 或人工相关性评测。
- 后续运行预算先限定为三轮两类直接请求，再运行现有浏览器候选、复制、Apply/Undo 与临时草稿 native-save smoke。每轮分别记录耗时、候选数与顺序、Cloud ranking strategy 和 rerank 状态；出现失败即停止该组回放并保留证据。
- metadata 接口审计确认：Toolbox payload builder、Addon guard、Cloud Pydantic DTO 都有字段约束，不能仅在浏览器添加未知字段。另发现 `recommendationFeedbackContext` 对相关文章误用内链解析器，可能把真实候选的 impression 分母上报为 0；健康门通过后的同批 metadata 改动需要覆盖这个回归。

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
2. 读取下方 2026-10-17 正式收口和 [Ranking Baseline v1](recommendation-ranking-baseline-v1.md)，
   复用已完成的阶段 0、metadata 和 accepted 证据；环境或代码有变化时再做对应 smoke。
3. 保持两族 baseline 不变，开始核对真实事件字段并进入 2–4 周观察期；不足 20 个真实会话时报告样本不足。
4. 把 Toolkit anchor hint/schema 缺口作为独立契约任务处理；不要通过扩大 Apply 或改排序掩盖问题。


## 6. 2026-10-16 阶段 0 完成及 baseline/metadata candidate

- 另一会话完成 PR #1119 与 clean-master promotion 后，明确释放 M4，revision 为 `2fd7282f`。本次独立 Cloud worktree fast-forward 到该基线，保留对方前端改动，完成两次 candidate sync（性能修复、可选反馈字段）。未部署生产、未做本次改动的 accepted promotion。
- 三轮真实回放各自返回 8 个候选且顺序稳定：相关文章 4569/1373/1253 ms，内链（24 段正文）3780/3715/3806 ms。M4 anchor 定向测试 15 项通过；Cloud 本地 anchor/API 61 项通过。
- 自然样本的真实编辑器候选、复制、反馈、无正文写入通过，但缺少安全 Apply 匹配。调查确认 Toolkit 忽略 Cloud `anchor_or_context` 且 schema 未声明该字段，仅按目标标题/作者选区匹配；本次未修改 Toolkit，限制进入独立后续事项。
- 正向临时草稿正文明确包含现有目标标题，2 条 Apply、Undo、native save 及关联反馈通过。Undo 的初次 raw-string 断言因 Gutenberg 规范化空白失败；规范化前后比较确认内容一致（raw_equal=false、canonical_equal=true），测试改为比较规范化可见内容，保留数据库不变断言。所有本次正向测试草稿均已删除，公开文章未保存修改。
- 已冻结 [Ranking Baseline v1](recommendation-ranking-baseline-v1.md)：`related_content.v1`、`internal_links.v1`，保持排序/权重不变。候选和事件增加 `ranking_version`、`candidate_rank`、`score_bucket`、`retrieval_source`、`rerank_applied`；批量保存/Undo 沿用有界 `candidate_rank_N` 原因代码。缺失的 rerank 证据省略布尔字段，不伪造 false。
- 修正相关文章 impression 误用内链解析器导致的候选数 0；Provider 保留有界 rerank 状态，并投影现有 `recommendation_quality` 只读汇总。未新增数据库、队列、本地日志、写入权限或排序控制面板。
- 最终自审补齐客户端位置去重：同一会话不同候选/不同批量位置集合不会错误合并，重复点击同一候选仍去重。新增单元断言和真实浏览器断言确认两条 Apply 各自携带展示位置；最终 `test:all` 通过（4212 项静态契约检查），PHPCS/PHPStan 通过。
- 两类 metadata 的真实查询 → Toolbox → Addon → Cloud impression/copy 均得到 `accepted_for_eval=true` 和事件回执，实际 rerank 为 disabled。新增字段后的编辑器 Apply/Undo/native-save smoke 通过，无 Toolbox/Addon HTTP 错误；这些开发 smoke 不是人工相关性或生产采用数据。
- 验证：Cloud 反馈 API 本地 21 项通过；M4 新字段存储/非法值 7 项通过；Ruff/mypy、Toolbox PHPStan/PHPCS、Addon `test:all`/PHPStan/打包及包校验通过。Toolbox `test:all` 首次在新文档缺少索引链接处失败，补齐 `docs/README.md` 后重跑通过。
- 跨仓矩阵使用本次 Cloud worktree override，不使用正被另一会话修改的主 Cloud checkout。Addon gate 通过；Toolbox 文档索引修复后单独重跑。Cloud exact-SHA GitHub gate 为 `needs_validation`：当前改动未提交，不能把基线 CI 当作这些本地改动的证明。因此本轮是本地/M4 candidate 交付，正式 PR、合并与 accepted closeout 尚未执行。

本次涉及文件按模块分组：Toolbox 的 `Recommendation_Metadata.php`、Provider/REST 投影、编辑器 JS、测试与上述契约文档；Addon 的 metadata validator、runtime guard 委托、release manifest 与测试/边界说明；Cloud 的 anchor matcher、可选反馈 DTO、对应测试及反馈契约文档。Toolkit 保持干净。三个开发分支没有新增提交；修改与新增文件保留供 review，不包含其他会话的变更。


## 7. 2026-10-17 正式提交、合并与 accepted 收口

本节更新上一节的 candidate 状态；不部署生产，不创建、移动或推送 tag。

- Cloud [PR #1121](https://github.com/npcink/npcink-ai-cloud/pull/1121) 已受保护合并，topic head `d7741eed15b9c590bf3836a4816d46bb3d9b7cc1`，合并 SHA `a40625b59e60a957021190febbb22c95203ff242`。PR 必需检查通过；合并 SHA 的 master Cloud CI（38989868856）及 CodeQL 通过。中央 exact-SHA GitHub gate 通过，未用旧基线或跳过的后端 lane 冒充本批证据。
- Addon [PR #283](https://github.com/npcink/npcink-cloud-addon/pull/283) 已合并，topic head `9637ca592f4570bd292b107144e61b4f8271cbae`，合并 SHA `b80b966151f26f114271a5534082c336ab2fea50`。全部 PHP、WordPress AI 兼容性、PHPStan 和 Release static gates 通过；共享根恢复到同步、干净的 master。独立 reconciliation 修复 worktree 不在本批范围。
- Addon 本地 OCR 在预算限制后部分交付 3 条意见：增加 helper 重复加载保护、明确 rank 错误提示；保留 nullable 位置/既有 score，补测未知枚举和布尔须省略的语义。初次 Release static gates 因新增提示的 POT 过期而失败，暂停自动合并，补齐 POT/中文 PO/MO、复验后再合并。部分 OCR 不当作完整 AI 审查。
- Cloud advisory OCR 在配置阶段报 `Cannot find module /usr/local/bin/ocr`，没有交付审查；PR 已记录原因，不称为 review 通过。该启动问题由原 Cloud owner 的独立任务处理，本批未修改其文件。
- 本次 Cloud `check:changed` merge lane 通过：release policy、Ruff/mypy、36 项反馈/anchor 定向测试、Agent Feedback quality（含前端 type/lint）、anti-drift。额外 1200 个确定性混合文字/标点 matcher 回放与冻结旧算法结果一致。`check:fast` 的本机 Docker 入口因 worktree 无 .env 在启动前退出；完整后端集成证据来自 GitHub master CI，本机没有启动 Cloud Docker。
- 从本次独立 worktree 的干净、当前 master 经 `m4:preview:promote -- --pr 1121` 完成 accepted，内网 `192.168.10.200` 直传，约 20.04 秒；M4 回执为 `acceptance_state=accepted`、`promotion_pr=1121`、`source_branch=master`、`source_dirty=false`，revision 为上述 Cloud 合并 SHA。仅 1 次 source promotion，0 次镜像 deploy。随后 worktree 切回 detached 同 SHA，释放 master checkout；Cloud 共享根正在处理独立 OCR 任务，未覆盖其分支或改动。
- accepted 后真实文章 4207 的两族查询连续三轮各返回 8 条、顺序保持一致：相关文章 4511/1377/1314 ms，24 段内链 3897/3758/3739 ms。两族 metadata impression/copy 回执再次 accepted_for_eval=true；临时草稿的 2 条 Apply/Undo 和原生保存回执通过，展示位置独立保留，无 Toolbox/Addon HTTP 错误。所有本次测试草稿 282803/282804/282805 已删除。
- Toolbox `composer test:all`（4212 静态契约及行为套件）、PHPStan/PHPCS、WordPress.org guard 通过；发布包构建、ZIP 完整性、压缩 JS 语法和 Packaged Plugin Check 通过（仅既有警告，无 ERROR）。本变更将本节和冻结基线随同正式 PR 提交；自身 topic/merge SHA 以该 PR 回执为准。

下一阶段保持独立 `related_content.v1` / `internal_links.v1`，先积累真实事件和字段完整性，单列 Toolkit anchor hint/schema 缺口。开发 smoke 不能当成人工 gold、自然文章采纳率或生产验证。
