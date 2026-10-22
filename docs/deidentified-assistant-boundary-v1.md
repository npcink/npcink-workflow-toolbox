# 脱密对话助手 — 产品边界 v1（已定稿）

> **Status:** 已定稿 v1（产品方 2026-10-20 按建议拍板 §11）  
> **Date:** 2026-10-20（Asia/Shanghai）  
> **Filename:** `deidentified-assistant-boundary-v1.md`（自 `*-draft*` 升格）  
> **Related:** [insight-pack-cloud-qa-cli.md](insight-pack-cloud-qa-cli.md)（巡检摘要验证）、[content-structure-pack-cloud-qa-cli.md](content-structure-pack-cloud-qa-cli.md)（内容结构验证）、[site-ops-cloud-analysis-contract.md](site-ops-cloud-analysis-contract.md)、[boundary.md](boundary.md)（Site Check 硬边界）、[cross-repo-boundary-matrix.md](cross-repo-boundary-matrix.md)、ADR-001 / ADR-008

---

## 0. 本文定位

- 这是**已定稿的产品边界**，不是第二控制面、不是 Ability/工作流注册表、不是审批库。
- 硬边界仍以 `docs/boundary.md` 与既有 Site Ops 隐私合同为准；本稿对齐「脱密 pack + 对话/CLI」方向。
- 与已合入 master 的 MVP（PR `#289`，加载/429 UX `#290`）及第二包接线 PR 对齐；第三包仍延后（见 §3 / §11）。

### 已断言（与 `#289` / `#290` 一致，可按实现核对）

| 断言 | 事实锚点 |
| --- | --- |
| 入口仓是 Toolbox | Admin + REST；Addon 不做第二产品面 |
| MVP 第一包 | `site_ops_insight_pack.v1`（本地 Collector → Insight_Builder） |
| 第二包（内容结构） | `content_structure_insight_pack.v1`（本地 Builder；Site Profile Ask） |
| 写姿态 | `suggestion_only`；不直写 WP；不自动 Core proposal |
| 运输 | 推理在 Cloud；签名/runtime 经 Addon |
| 通道 | `hosted_ai_content_support.v1`（验证用）；**不**走已暂停的 `cloud_inspection` / Site Ops Cloud detail |
| 验证面 | 巡检：REST + CLI + Site Check Ask；内容结构：REST + CLI + **站点资料** Ask（内部试用） |
| Ask UX | 进行中态 + Cloud 忙位/429 中文提示（`#290`） |

---

## 1. 目标

- 在 **Toolbox** 提供一处对话式排查入口，帮助站长/运营用自然语言理解「站点怎么了、先看什么、接着走哪条既有工作流」。
- 对话只消费**已脱密、有界的合同数据包**（on-demand pack），不把固定按钮矩阵扩成「通用 AI 控制台」。
- **Cloud** 只做托管推理 / 语义归纳（`suggestion_only`）；**写入**仍走 **Toolkit 能力 + Core 提案/预检/审计**（及既有 Adapter 通道）。
- **Addon** 仅作签名运输与 entitlement/health 读取，**不**新增第二套控制面、能力注册表、审批库或写路径。
- 助手**消费合同与投影**；`cloud_inspection` 等仍处可逆暂停时，不得绕过暂停去调用对应 Cloud 提交面。

---

## 2. 非目标

- 不做固定功能按钮替代品，也不为对话单独开「万能按钮」准入（ADR-008 / `fixed-button-contract-table.json`）。
- 不做第二套 Site Check / Nightly / 巡检 UI；不复活已暂停的 Cloud inspection 提交/重试默认入口。
- 不在 Toolbox / Addon / Cloud 任一侧建立：本地队列、自定义 run 表、审批真相、provider/model 路由真相、计费真相、WP 直写执行器。
- 不上传正文全文、评论原文、私信、邮件、IP、密钥、原始日志、未公开内容。
- 不把对话回复直接写成 WP 变更；任何写意图最多产出**可审阅的 handoff 候选**，由既有治理链路落地。
- **不新开完整多轮对话产品壳**；第三包门禁已在 M4 `#1177` 验证后放开（见 §6.2 / §11）；环境性能包仍延后。

---

## 3. 数据包清单（完整 v1 意向；MVP 仅巡检摘要）

完整 v1 按需拉取下表；**当前已接线：「巡检摘要」+「内容结构摘要」**。其余三包仍为意向清单；未点名的 pack 不预取。**不做第二套完整对话壳。**新字段必须先补合同再接线（Reuse first）。

| 用户可见名 | 状态 | 意图 | 优先复用（投影既有真相，不新造） |
| --- | --- | --- | --- |
| **巡检摘要** | **MVP 已接线**（`#289`） | 当前本地巡检结论与优先处理队列 | `site_ops_insight_pack.v1`；本地确定性扫描；**不**默认触发已暂停的 Cloud inspection |
| **内容结构摘要**（第二包） | **已接线** | 内容形态与结构量级 / SEO·内容健康类脱密摘要 | `content_structure_insight_pack.v1`（`post_counts` / taxonomy shape / 公开标题样本；非正文）；仍 `suggestion_only` |
| **页面质量摘要**（第三包） | **已接线（本 PR）** | 页面基础完备度（简介/封面/过短/标题层级/文内缺 alt 等抽样） | `page_quality_insight_pack.v1`；分层抽样；`suggestion_only`；**非** Lighthouse/测速 |
| **环境与性能清单**（第四包） | **已接线（本 PR）** | WP/PHP/插件主题版本与粗信号清单 | `environment_performance_insight_pack.v1`；`measurement_kind=config_and_inventory_only`；**非**测速 |
| 站点概况 | 延后 | 站点身份与就绪度一览 | insight pack 内站点/上下文摘要；Site Context / Cloud readiness 投影 |
| 近期错误 / 健康信号 | 延后 | 有界健康与失败信号 | ops health / 连接与 runtime 失败投影（错误码级，非 raw log） |
| Cloud 连接与用量状态 | 延后 | 连接是否可用、窄配额 / entitlement | `cloud_runtime` / nightly entitlement 窄读；非计费后台 |

> **已定：** 五包名称为完整 v1 意向清单；**第二包「内容结构摘要」已接线**。**第三包「页面质量摘要」**（`page_quality_insight_pack.v1`）在 M4 `#1177` drain 验证后由产品显式放开（2026-10-22），本 PR 接线。**第四包「环境与性能清单」**（`environment_performance_insight_pack.v1`）本 PR 接线；`measurement_kind=config_and_inventory_only`，非测速。

---

## 4. 红线 — 什么可出站 / Cloud 可见什么

继承 `site_ops_cloud_analysis_request.v1`、Site Check（`boundary.md`）与 Snapshot `privacy` 标志。对话 / insight-pack-qa 上行载荷适用同一套。

### 4.1 不得离开站点（不得进入 Cloud 推理载荷）

- 文章/页面**正文**、修订差、私有草稿全文；
- 评论**原文**、作者邮箱、评论 IP、User-Agent；
- 用户邮箱/电话、登录名以外的可识别 PII；
- provider / API **密钥**、签名材料、原始请求日志；
- 未批准或非公开内容；媒体原文件二进制；
- 原始私密 URL、会话 cookie、完整数据库/配置导出。

本地 Snapshot 对评论侧已声明：`comment_text_returned=false`、`author_email_returned=false`、`ip_address_returned=false`、`user_agent_returned=false`（见 `Site_Ops_Snapshot_Collector`）。

### 4.2 允许进入脱密包 / Cloud 可见（有界）

- 公开聚合计数、taxonomy 形状、脱敏 finding 摘要；
- 错误码与高阶连接/就绪状态；
- 已批准评论的**信号计数**（非原文）；
- 操作员在 Ask/CLI 中输入的**问题文案**（与 pack 一并送 Hosted AI；仍不附带上述禁止字段）。

### 4.3 Cloud 返回约束

- `write_posture=suggestion_only`
- `direct_wordpress_write=false`
- 不自动创建 Core proposal
- 不发明 finding / 凭据 / 私密内容（Ask 系统提示已约束）

运行时禁止塞入 Cloud `RuntimePayload`（`extra=forbid`）的键：`summary_prompt_mode`、`http_timeout_seconds`、`connect_timeout_seconds` — 见验证手册。

---

## 5. 仓职责（边界，非第二控制面）

| 仓 | 职责 | 明确不做 |
| --- | --- | --- |
| **Toolbox** | 对话/Ask UI、pack 本地组装、展示建议与既有工作流深链；**v1.1 多 pack 编排归属 Toolbox** | 不拥有审批/审计真相；不直写 WP；不把 Addon 当第二产品面 |
| **Cloud Addon** | 签名运输、`execute_runtime`、entitlement/health 只读桥 | 无第二控制面、无能力/工作流注册表、无 WP 写 |
| **Npcink Cloud** | 托管推理与语义归纳、用量/entitlement 证据 | 无 WP 控制面真相；不绕过暂停键；不发明 finding |
| **Toolkit** | 可复用 Ability / 计划合同；写回调只在宿主批准上下文下 | 无 provider 路由、无队列/调度真相 |
| **Core（+ Adapter）** | 提案、审批、预检、审计；对外客户端写通道 | 无托管模型执行、无 Toolbox UI 状态 |

---

## 6. MVP / 完整 v1 / v1.1

### 6.1 MVP（已合入 `#289` + `#290` — 当前验证切片）

**目标：** 用最小竖切验证「脱密 pack + 一问一答」是否值得继续扩包。

| 项 | MVP 范围（已实现） |
| --- | --- |
| 入口 | **智能提问** → 选择「本次站点检查」（站点检查→当前检查 有「去智能提问」链接）；另有 REST + CLI |
| 唯一 on-demand pack | **`site_ops_insight_pack.v1` only** |
| 其它四包 | 全部延后（§3）；第二包见 §11 |
| Cloud | `hosted_ai_content_support.v1` + 本地 pack；`suggestion_only`；禁止 WP 写；不建 Core proposal |
| `cloud_inspection` | **只吃本地 pack**；不调用 `run_site_ops_cloud_analysis` /「Use Cloud detail」 |
| 输出 | 自然语言建议（Markdown 安全渲染）+ `run_id`；写意图不执行 |
| UX | 进行中态；忙位/429 中文提示（`#290`） |
| 明确未做 | 完整多轮对话壳、pack 多选 UI、新审计表、新固定按钮、Addon 第二控制面、第二脱密包代码 |

验证手册：[insight-pack-cloud-qa-cli.md](insight-pack-cloud-qa-cli.md)。

### 6.2 完整 v1（第二包已接线）

- 按 §3 / §11：第二包 **内容结构摘要** 已接线；第三包 **页面质量摘要** 已接线；第四包 **环境与性能清单**（`environment_performance_insight_pack.v1`）本 PR 接线。
- 仍尊重 `cloud_inspection` 等可逆暂停。
- Ask（巡检）与 Generate site check **双轨并存**；内容结构 Ask 挂在 **站点资料**，与巡检 Ask 入口分离（见 §11）。

### 6.3 v1.1（已知缺口，不阻塞 MVP）

- ~~环境版本 pack~~ → 已接线为 `environment_performance_insight_pack.v1`（配置/清单，非测速）；
- 真实错误面（结构化近期错误，仍禁 raw log/密钥）；
- 对话侧多 pack 编排与引用证据协议（**编排归属 Toolbox**；Addon 仅 `execute_runtime`）；
- 通用用量摘要；完整 CPT census。

---

## 7. 已接线挂点

| 路径 | 现状 |
| --- | --- |
| 巡检只读拼包 REST | `GET …/site-ops/insight-pack`（`manage_options`） |
| 巡检 Ask / QA REST | `POST …/site-ops/insight-pack-qa` |
| 巡检实现 | `includes/Rest_Site_Ops_Insight_Bridges.php` |
| 巡检 Admin 薄 UI | **智能提问** → 选择「本次站点检查」 |
| 巡检 CLI | `scripts/insight-pack-cloud-qa.sh` / `.php` |
| **内容结构只读拼包 REST** | `GET …/content-structure/insight-pack`（`manage_options`） |
| **内容结构 Ask / QA REST** | `POST …/content-structure/insight-pack-qa` |
| **内容结构实现** | `includes/Content_Structure_Insight_Builder.php` + `Rest_Content_Structure_Insight_Bridges.php` |
| **内容结构 Admin 薄 UI** | **智能提问** → 选择「内容结构」（站点画像 有「去智能提问」链接；与巡检共用一个提问壳） |
| **内容结构 CLI** | `scripts/content-structure-pack-cloud-qa.sh` / `.php`；手册 `docs/content-structure-pack-cloud-qa-cli.md` |
| **页面质量只读拼包 REST** | `GET …/page-quality/insight-pack`（`manage_options`） |
| **页面质量 Ask / QA REST** | `POST …/page-quality/insight-pack-qa` |
| **页面质量实现** | `includes/Page_Quality_Insight_Builder.php` + `Rest_Page_Quality_Insight_Bridges.php` |
| **页面质量 Admin** | **智能提问** → 选择「页面质量」（白话标签；合同 id 不对站长展示） |
| **页面质量 CLI** | `scripts/page-quality-pack-cloud-qa.sh` / `.php`；手册 `docs/page-quality-pack-cloud-qa-cli.md` |
| **环境与性能只读拼包 REST** | `GET …/environment-performance/insight-pack`（`manage_options`） |
| **环境与性能 Ask / QA REST** | `POST …/environment-performance/insight-pack-qa` |
| **环境与性能实现** | `includes/Environment_Performance_Insight_Builder.php` + `Rest_Environment_Performance_Insight_Bridges.php` |
| **环境与性能 Admin** | **智能提问** → 选择「环境与性能」 |
| **环境与性能 CLI** | `scripts/environment-performance-pack-cloud-qa.sh` / `.php`；手册 `docs/environment-performance-pack-cloud-qa-cli.md` |
| 既有本地预览 | Generate site check 仍可用；与两路 Ask **三轨并存、不合并 UI** |
| 暂停的 Cloud detail | `cloud_inspection` 仍暂停；两路 Ask **均不**走该路径 |

---

## 8. 入口约定

- **巡检摘要：** Site Check → Current check 提问条（内部试用）。
- **内容结构摘要：** 统一在 **智能提问** tab（`toolbox_tab=smart-ask&ask_pack=content_structure`）；站点画像只放「去智能提问」链接。
- **完整多轮壳：** 不做（本阶段）；Ask ≠ Generate；两路 Ask 共享 hosted AI 通道，**不合并 UI**。

---

## 9. 实现切片进度（边界文档视角）

| 切片 | 内容 | 状态 |
| --- | --- | --- |
| A. 本文档 | 边界已定稿 v1 | **本 PR** |
| B. 只读 pack REST | `GET …/insight-pack` | **已合入 `#289`** |
| C. Admin 薄 Ask | 站点检查提问面板 | **已合入 `#289`**；文案去「开发测试」随本 PR |
| D. Cloud suggestion_only | Hosted content-support + pack | **已合入 `#289`** |
| UX 加载/429 | Ask 进行中 + 中文忙位 | **已合入 `#290`** |
| E. 深链增强 | 答复内系统深链编排 | 未作为独立切片强绑 |
| 第二包 UI/REST | 内容结构摘要 | **本 PR 接线**（REST + Site Profile Ask + CLI）；见 §11 |

---

## 10. 与相关文档的关系

| 文档 | 关系 |
| --- | --- |
| `docs/boundary.md` | 硬边界权威；本稿不得放宽 |
| `docs/site-ops-cloud-analysis-contract.md` | Site Ops Cloud detail 合同；MVP Ask **不**走该提交面 |
| `docs/insight-pack-cloud-qa-cli.md` | 人/AI 验证手册与 REST/CLI 操作说明 |
| `docs/ai-site-check-effect-verification-plan-2026-10-14.md` | 更广的体检/复测规划讨论；**非**本稿实施授权 |
| `AGENTS.md` Session Startup | 指向验证手册 + 本稿 |

---

## 11. 已定政策（原「待确认」已关闭）

产品方 2026-10-20 按建议定稿：

1. **五包 / 第三包：** 「巡检摘要」+「内容结构摘要」已接线。第三包 = **页面质量摘要**（`page_quality_insight_pack.v1`，页面基础完备度 de-id，`suggestion_only`）。**门禁更新（2026-10-22）：** 产品在 M4 `#1177` drain 验证后显式放开第三包；第四包 = **环境与性能清单**（`environment_performance_insight_pack.v1`，配置/清单 only，`suggestion_only`，非 Lighthouse）。
2. **授权 / 审计 v1：** 记录 `run_id` + 操作者 + 时间即可；能力门槛 = WP `manage_options`（经 `cap.toolbox.admin`）；**不另造角色、不新建审计表**。
3. **Cloud 失败降级：** 失败只展示**中文错误 + 可重试**；**不做**本地假答复 / 纯本地规则默认答复，避免双真相。
4. **Ask 文案：** 去掉「开发测试」；Ask 为**内部试用就绪**。完整多轮对话壳本阶段不做。
5. **Ask ≠ Generate：** 两条入口、共享 hosted AI 通道；**不合并 UI**；长期双轨并存。
6. **v1.1 编排归属：** 多 pack orchestration 落 **Toolbox**；Addon 只 `execute_runtime`；**不写第二控制面**（细节若需可另开 ADR，不阻塞本定稿）。

---

*本稿为脱密对话助手产品边界已定稿 v1。实现切片 B–D / 加载·429 已随 `#289` / `#290` 合入；第二包（内容结构）已接线；第三包（页面质量）与第四包（环境与性能清单）已接线。*
