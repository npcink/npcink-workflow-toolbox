# Recommendation Ranking Baseline v1

状态：2026-10-16 冻结、2026-10-17 正式收口的开发技术基线。Cloud 与 Addon 已合并，
Cloud 已成为 M4 accepted；不代表人工相关性 gold set 或生产质量结论。

## 冻结范围

| 推荐族 | ranking_version | 当前排序与过滤 |
| --- | --- | --- |
| 相关文章 | `related_content.v1` | Cloud `semantic_plus_bounded_title_topic`；语义分 + 标题词项加分（每项 0.02，最多 0.04）+ 分类/主题加分（每项 0.02，最多 0.04）；保留现有弱候选抑制、文章去重及稳定 tie-break。编辑器请求上限 6、展示上限 8。 |
| 内链 | `internal_links.v1` | Cloud `semantic_plus_bounded_lexical_topic_anchor`；词项加分最多 0.05、主题加分最多 0.04、精确 anchor evidence 加分 0.06；保留现有质量过滤。Toolkit 继续执行既有 orphan/shared-term 优先级、目标标题/选区的正文匹配；Toolbox 做可见编辑器安全过滤，最多展示/选择 8 条。 |

Cloud 基于 `2fd7282f`，另含已通过旧/新 matcher 等价验证的性能修复；
对应性能 candidate bundle 为 `0c9ee073447499019ad087686a3da40d5489c2adfc2fe0c5337d860c45d1c9c7`。
Toolbox 基于 `569da58`、Toolkit 基于 `921d1dc`、Addon 基于 `0ae75fe`。
metadata candidate 的 Cloud bundle 为 `9df6f5489c5d42799819ca06576fe94610c32a4eab4620498bae39b59af056da`。
本批没有修改权重、阈值、图谱加分、召回集合或排序顺序。

2026-10-17 正式链路：Cloud [PR #1121](https://github.com/npcink/npcink-ai-cloud/pull/1121)
合并为 `a40625b59e60a957021190febbb22c95203ff242`；Addon
[PR #283](https://github.com/npcink/npcink-cloud-addon/pull/283) 合并为
`b80b966151f26f114271a5534082c336ab2fea50`。Cloud 合并 SHA 的 master CI
及中央 exact-SHA GitHub gate 通过；从干净 master 经内网直传 promotion（约 20 秒），
M4 回执为 `acceptance_state=accepted`、`promotion_pr=1121`、`source_branch=master`、
`source_dirty=false`，revision 与上述 Cloud SHA 一致。未构建镜像或部署生产。

版本标识当前组合，不是运行时控制开关。更改排序、质量阈值、去重、Top K、
embedding space 或 rerank 策略时，应对受影响推荐族升级版本并重新回放；
输出等价的性能优化可沿用版本。Cloud 仍拥有检索/排序运行时，Toolkit 拥有能力契约，
Toolbox 只投影本次已冻结的观测版本。

## 阶段 0 证据

实际站点为 `https://magick-ai.local/`，Addon 通过 `127.0.0.1:18010` 的 SSH 隧道使用 M4。
API、Postgres、Redis、worker 健康；本机旧 Compose DNS 日志不能解释这条链路。

真实文章 4207 的同一查询连续回放三轮，两类请求每轮均返回 8 个候选且各自顺序不变：

| 请求 | 第 1 / 2 / 3 轮耗时 |
| --- | --- |
| 相关文章 | 4569 / 1373 / 1253 ms |
| 内链（24 段正文） | 3780 / 3715 / 3806 ms |

真实文章的编辑器通过候选展示、复制、反馈及无数据库正文写入检查。
两篇自然样本没有安全可应用匹配，因此单列为 review-only，不能宣称采用成功。
正向测试草稿明确包含现有公开目标标题：2 条 Apply、Undo、原生保存及保存反馈通过；
Apply/Undo 期间数据库不变，只有 WordPress 原生保存后变化，测试草稿随后删除。
Undo 按 Gutenberg 规范化序列化后的可见内容比较；原始空白序列化差异不当作内容丢失。

Cloud 汇总确认会话关联且无孤立事件，`raw_content_stored`、`raw_anchor_stored`、
`provider_output_stored` 均为 false；本批属于 developer smoke，不能当作人工采纳或 gold。
当前实际 rerank 为 disabled，metadata 记录 `rerank_applied=false`。

accepted 后同一文章查询再次连续通过三轮，各返回 8 条、顺序与 candidate 证据一致：
相关文章 4511 / 1377 / 1314 ms，内链 3897 / 3758 / 3739 ms（24 段正文）。
两族 metadata impression/copy 回执再次接受。accepted 后的临时草稿复验也通过：
8 候选中 2 条可 Apply，Apply/Undo 无数据库写入，原生保存反馈为
`internal_link_saved_unchanged` 且保留两条展示位置；两个浏览器流程均无网络/控制台错误。
测试源草稿与两个克隆（282803/282804/282805）均已删除。

## Metadata 契约

结果 section 与候选附加字段，反馈沿用 `cloud_agent_feedback.v1` 的可选顶层字段：

| 字段 | 约束 |
| --- | --- |
| `ranking_version` | `related_content.v1` 或 `internal_links.v1`；两族独立。 |
| `candidate_rank` | 1-based；传输上限 20，当前编辑器显示上限 8。候选动作按实际展示顺序发送，客户端去重后重新连续编号。impression/批量动作没有单一位置，不伪造为 0。 |
| `score_bucket` | `low`（<50）、`medium`（50–<80）、`high`（≥80）、`unknown`。内链使用已有 quality_score，相关文章使用已有 score ×100；不同推荐族的 bucket 不当作同一标尺。 |
| `retrieval_source` | `cloud_vector`、`cloud_vector_rerank`、`local_fallback`、`no_evidence`、`unavailable`、`unknown`。沿用真实来源，空结果不冒充 fallback。 |
| `rerank_applied` | 布尔；依据 Cloud `rerank.status=succeeded`。disabled/skipped/failed 为 false；缺失或未知状态省略该字段。 |

批量 Apply 的原生保存、保存后编辑和 Undo 使用现有 `source_reason_codes` 保存
最多 8 个 `candidate_rank_N`，同时保留版本和会话；不发送 candidate ID、文章 ID、
标题、URL、正文、原始分数、锚文本、source match、prompt 或 Provider 输出。
旧事件可以省略全部新字段；新字段在 Toolbox、Addon、Cloud 分别验证类型及枚举。
Toolbox/Addon 允许 `candidate_rank=null` 表示没有单一候选位置，保留既有
`source_score=null` 兼容性；枚举和 `rerank_applied` 只在已知时发送合法值，
未知时省略，不能以 null 代替。当前编辑器的 impression/批量事件直接省略位置字段。
上线时先确保 Cloud/Addon 已兼容，再发布发送新字段的 Toolbox。
Cloud 沿用既有事件 JSON 持久化，没有新增表、队列或本地日志库。

相关文章 impression 现在使用相关文章解析器计数，不再误用内链解析器而报告 0。
客户端事件去重键包含版本、单候选位置或有界批量位置，避免同一会话的第二条
候选动作被第一条错误合并；重复点击同一候选仍去重。
Provider 同时保留有界 rerank 证据与只读 `recommendation_quality` 汇总。
`feature_profile_version` 暂不制造值：当前没有可直接投影的 Cloud 特征组合版本。

## 已知限制与下一步

Toolkit 当前忽略传入的 `anchor_or_context`，只使用目标标题或作者选区匹配；
其能力 schema 也未声明该 hint。此缺口独立记录，当前不改 Toolkit 契约或放宽 Apply。
它解释了为什么 Cloud 有 anchor evidence 而自然样本仍可能仅能复制/打开。
后续需以独立契约修复和真实样本验证，不能通过调整 metadata 隐藏这个限制。

保持本基线，先核对事件字段完整性，再积累真实使用；不足 20 个真实 session 时
报告样本不足。人工相关性标注、版本分组报表、rerank/权重优化属于后续工作。
