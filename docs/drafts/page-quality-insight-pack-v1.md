# 页面质量分析 Insight Pack 草案 — `page_quality_insight_pack.v1`

> **Status:** DRAFT ONLY（供产品审阅；**未**授权实现、**未**开 PR）  
> **Date:** 2026-10-22（Asia/Shanghai）  
> **Aligned with:** [deidentified-assistant-boundary-v1.md](../deidentified-assistant-boundary-v1.md)、既有 `site_ops_insight_pack.v1` / `content_structure_insight_pack.v1`、`Insight_Pack_Answer_Guidance`  
> **Related drafts:** [environment-performance-insight-pack-v1.md](environment-performance-insight-pack-v1.md)

---

## 1. 目的

给站长一个**脱密、只读、建议型**的「页面质量」能力切片：基于本站已发布内容的有界抽样，用白话说明页面是否好读、好找、好补基础信息（简介、封面、图片说明、标题层级、是否过短/过旧等），并给出最多 3 件可手动处理的事。

产品意图（与边界演进一致）：

- Pack 是**后端能力切片**，服务自由提问 / 个性化排查（页面质量、站点速度、排障、环境估算等），**不**承诺做成固定功能按钮。
- 正式 UI：**一个提问框**；站长**看不到** pack 名称或下拉；由 Toolbox 编排选包（本草案实现前，现有「问什么」选择器可暂存，但目标态是隐形）。
- 推理在 Npcink Cloud；运输经 Addon；`write_posture=suggestion_only`。

## 2. 非目标（明确不做清单）

| 不做 | 说明 |
| --- | --- |
| 不跑真实 Lighthouse / PageSpeed / CI | 不安装、不调用外部性能实验室；不输出「性能分」「LCP/CLS 实测」 |
| 不写 SEO / 不改 WP | 不直写文章、元数据、分类、媒体；不自动建 Core proposal |
| 不声称搜索排名 / 流量 | 摘要不含流量、排名、点击、转化；回答禁止谈「热门页」「排名下降」 |
| 不自动修复 | 不批量补简介、不批量改图、不改主题模板 |
| 不上传正文 / 评论原文 | 本地可读字数，**出站仅布尔与计数** |
| 不做本地假答复 | Cloud 失败只中文错误 + 可重试（与边界 §11.3 一致） |
| 不新开多轮对话壳 | 仍是一问一答 + 既有 智能提问 壳 |
| 不复活 `cloud_inspection` | 不走已暂停的 Site Ops Cloud detail 提交面 |

## 3. 命名与通道（与现有一致）

| 项 | 取值 |
| --- | --- |
| 合同 / contract_version | `page_quality_insight_pack.v1` |
| artifact_type | `page_quality_insight_pack` |
| 内部 pack key（编排用，**不对站长展示**） | `page_quality` |
| REST（拟定，对齐现有） | `GET …/page-quality/insight-pack`；`POST …/page-quality/insight-pack-qa` |
| Cloud ability | `npcink-toolbox/ai-content-support` |
| Hosted 合同 | `hosted_ai_content_support.v1`（与两路 Ask 相同） |
| 写姿态 | `suggestion_only`；`direct_wordpress_write=false`；`core_proposal_created=false` |
| 答法 | 复用并扩展 `Insight_Pack_Answer_Guidance`（新 `PACK_PAGE_QUALITY` + 术语表） |

## 4. 采集什么 / 不采集什么

### 4.1 允许进入脱密包（有界）

**站点级聚合**

- 已发布 `post` / `page` 数量（及可选公开 CPT 计数，若实现时复用既有 census 合同）
- 抽样元数据：样本数、分层方式、全站已发布总数（供首行 caveat）

**公开样本行（每篇最多约 60 篇量级，见 §5）**

- `object_type`（post/page）、`object_id`（仅本地 findings 引用；出站可保留 id 或改用短 ref，实现时与 content_structure 对齐）
- 公开 `title`（去标签）
- `published_at` / `modified_at`（GMT ISO）
- `word_count`（本地算完；**不出正文**）
- 布尔：`excerpt_present`、`meta_description_present`（只探测既有 SEO 插件常见键是否非空，与现 Builder 一致）、`featured_image_present`
- 计数：`category_count` / `tag_count`（或仅有/无）
- 可选结构信号（本地解析 HTML，**只出计数**）：`h1_count`、`h2_count`、`h3_count`、是否疑似多 H1；`internal_link_count`（同域链出）；`missing_alt_count`（文内图缺 alt 的数量）
- 分层标记 `stratum`（如 newest/middle/oldest）

**聚合信号（推荐出站，避免堆原始行）**

- 缺简介率、缺摘要率、缺封面率、过短（&lt;300 字）率、半年未更新率、缺 alt 率等**比率或命中数**（回答侧由 Guidance 改成白话，不暴露字段名）

### 4.2 明确不采集 / 不出站

- 文章/页面**正文**、修订差、草稿/私密/密码保护内容
- 评论原文、作者邮箱、IP、UA
- 用户 PII、密钥、原始日志、媒体二进制
- 任何第三方分析：GA/百度统计原始数据、Search Console 排名、付费 SEO 分数
- 「权威」性能实验室结果、真实用户监测（RUM）CWV
- 完整 permalink 以外的私密 URL、登录态 cookie

## 5. 抽样方式

对齐 `content_structure_insight_pack.v1`：

- 默认上限 **60**（filterable，夹在约 6–120）
- 按**发布日期**分约 **三层**（旧 / 中 / 新），层内均匀抽，层间交错，避免只抽最新
- 仅 `post` + `page`、`publish`
- 首行 caveat 复用 `Insight_Pack_Answer_Guidance::stratified_sample_note()`（「按发布时间分三批共抽查 N 篇…」）
- 不按「流量」加权（站点无可靠流量真相，也禁止假装有）

与 `site_ops` 的差异：本包**更偏单页可读性/基础完备度**；不做评论信号、不做媒体库附件普查（除非后续合同显式加入「文内图 alt」计数——已在 §4.1 可选）。

## 6. 回答形态（复用 Guidance）

严格沿用共享答法：

1. 若有抽样说明 → **第一行只写 caveat 原句**（无「」、无前缀）
2. 结论 1–2 句
3. 最多 3 件事：做什么 / 为什么 / 怎么做（白话、短句）
4. 全文宜 ≤300 字；不写字段名、不写分数原值、不谈流量/排名
5. 只建议、不声称已改站；Cloud 失败不本地编答案

新增术语表示例（写入 Guidance glossary）：

- `missing_meta_description_rate` → 搜索结果里的简介  
- `thin_content` → 内容太短的文章  
- `heading structure / multiple H1` → 标题层级（大标题有几个）  
- `missing_alt_count` → 图片缺文字说明的数量  

默认问题（编排/内部试用，**不**作为站长可见「功能名」）：  
「根据这份页面质量摘要，优先该补哪 1–3 件基础问题？用大白话回答。」

## 7. UX

| 目标态 | 说明 |
| --- | --- |
| 正式 UI | **智能提问** 单一提问框；无 pack 下拉、无「页面质量分析」按钮矩阵 |
| Pack 对站长 | **不可见**；仅后端/日志/支持用 `pack` / `contract_version` |
| 编排 | Toolbox 按问题意图（或后续路由）加载本 pack；Addon 只 `execute_runtime` |
| 过渡 | 若实现期仍挂在现有 select，内部 key=`page_quality`，文案用「页面质量」而非合同 id；尽快收到目标态 |
| 深链 | 回答可指向编辑器 / 媒体库 / 既有元数据流程；不新造写入口 |

## 8. 风险

| 风险 | 缓解 |
| --- | --- |
| 与边界 §11「卡住-run 前不开第三包」冲突 | **实现前须产品显式放开**；本稿仅草案 |
| 与 `content_structure` 重叠 | 本包强调**单页完备度/标题层级/文内图**；内容结构包保留 taxonomy/SEO 健康总览；编排时按问题选一，避免双包同问重复烧配额 |
| 模型谈排名/流量 | Guidance 硬约束 + 合同不含该类字段 + 契约测试钉死禁词 |
| 元描述探测绑死某 SEO 插件 | 继续「多键探测是否非空」，不解析插件私有配置 |
| 标题层级误报 | 本地启发式；finding 标「需人工看一眼」；不自动改 HTML |
| 样本偏差 | 分层抽样 + 强制 caveat |

## 9. 验证计划（实现后）

1. **合同测试**：pack JSON 含 `contract_version`、`write_posture`、`safety` 布尔；断言**无** `post_content` / comment text / email / ip  
2. **Guidance / 答法**：CLI 或 REST QA（镜像 `content-structure-pack-cloud-qa`）；检查首行 caveat、无「」、无 snake_case、无流量词  
3. **边界**：Cloud 失败路径无本地假答复；`manage_options` 门槛；不写 WP（提交前后 diff / 探针）  
4. **UX**：目标态单框时，页面源码/无障碍树不出现 pack 合同 id；过渡态 select 仅中文标签  
5. **手工冒烟**：对 M4 preview 各跑 ≥1 次成功；忙位 429 中文提示仍可用  
6. **证据目录**：`~/npcink-qa-evidence/page-quality-pack-YYYYMMDD/`（json/md/log）

## 10. 实现切片（预告，非授权）

- `Page_Quality_Insight_Builder` + REST bridges + CLI 手册  
- 扩展 `Insight_Pack_Answer_Guidance`  
- 智能提问编排注册（隐形优先）  
- **不**改 Addon / Core / 其他插件

---

*本稿为审阅草案。批准前禁止合入产品代码。*
