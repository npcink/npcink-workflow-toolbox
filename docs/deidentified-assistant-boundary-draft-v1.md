# 脱密对话助手 — 产品边界草稿 v1（定稿结构 / 待确认）

> **Status:** draft（结构已按已合入 MVP 整理，供产品方确认后升格；**未**宣称已发布产品面）  
> **Date:** 2026-10-20（Asia/Shanghai）  
> **Filename:** 保留 `*-draft*`，升格前勿改名为正式 contract。  
> **Related:** [insight-pack-cloud-qa-cli.md](insight-pack-cloud-qa-cli.md)（验证手册）、[site-ops-cloud-analysis-contract.md](site-ops-cloud-analysis-contract.md)、[boundary.md](boundary.md)（Site Check 硬边界）、[cross-repo-boundary-matrix.md](cross-repo-boundary-matrix.md)、ADR-001 / ADR-008

---

## 0. 本文定位

- 这是**产品边界草稿**，不是第二控制面、不是 Ability/工作流注册表、不是审批库。
- 硬边界仍以 `docs/boundary.md` 与既有 Site Ops 隐私合同为准；本稿只对齐「脱密 pack + 对话/CLI」方向。
- 与已合入 master 的 MVP（PR `#289`）对齐；**不**在本稿扩第二类脱密包（第二包另开独立工作，见 §3 / §11）。

### 已断言（与 `#289` 一致，可按实现核对）

| 断言 | 事实锚点 |
| --- | --- |
| 入口仓是 Toolbox | Admin + REST；Addon 不做第二产品面 |
| MVP 唯一 pack | `site_ops_insight_pack.v1`（本地 Collector → Insight_Builder） |
| 写姿态 | `suggestion_only`；不直写 WP；不自动 Core proposal |
| 运输 | 推理在 Cloud；签名/runtime 经 Addon |
| 通道 | `hosted_ai_content_support.v1`（验证用）；**不**走已暂停的 `cloud_inspection` / Site Ops Cloud detail |
| 验证面 | 只读 REST + CLI + Site Check「关于本次检查提问（开发测试）」薄 UI |

### 待用户确认（§11）

五包完整命名与第二包优先级、授权/审计是否加表、本地降级是否默认、完整对话壳是否升产品面等 — **本稿不代用户拍板**。

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
- **本稿不扩第二脱密包、不新开完整多轮对话产品壳**（见 §6.2 / §11）。

---

## 3. 数据包清单（完整 v1 意向；MVP 仅巡检摘要）

完整 v1 按需拉取下表；**当前已接线 / 已验证的只有「巡检摘要」**。其余四包为意向清单，**未实现、未开第二套 UI**；未点名的 pack 不预取。新字段必须先补合同再接线（Reuse first）。

| 用户可见名 | 状态 | 意图 | 优先复用（投影既有真相，不新造） |
| --- | --- | --- | --- |
| **巡检摘要** | **MVP 已接线**（`#289`） | 当前本地巡检结论与优先处理队列 | `site_ops_insight_pack.v1`；本地确定性扫描；**不**默认触发已暂停的 Cloud inspection |
| 站点概况 | 延后 | 站点身份与就绪度一览 | insight pack 内站点/上下文摘要；Site Context / Cloud readiness 投影 |
| 近期错误 / 健康信号 | 延后 | 有界健康与失败信号 | ops health / 连接与 runtime 失败投影（错误码级，非 raw log） |
| 内容结构摘要 | 延后 | 内容形态与结构量级 | `post_counts` / structure 类聚合（公开计数与分类结构，非正文） |
| Cloud 连接与用量状态 | 延后 | 连接是否可用、窄配额 / entitlement | `cloud_runtime` / nightly entitlement 窄读；非计费后台 |

> **待确认：** 上表「五包」名称与顺序是否即为完整 v1 锁定清单；第二包优先做哪一行（§11）。

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
| **Toolbox** | 对话/Ask UI、pack 本地组装、展示建议与既有工作流深链 | 不拥有审批/审计真相；不直写 WP；不把 Addon 当第二产品面 |
| **Cloud Addon** | 签名运输、entitlement/health 只读桥、把有界请求送 Cloud | 无第二控制面、无能力/工作流注册表、无 WP 写 |
| **Npcink Cloud** | 托管推理与语义归纳、用量/entitlement 证据 | 无 WP 控制面真相；不绕过暂停键；不发明 finding |
| **Toolkit** | 可复用 Ability / 计划合同；写回调只在宿主批准上下文下 | 无 provider 路由、无队列/调度真相 |
| **Core（+ Adapter）** | 提案、审批、预检、审计；对外客户端写通道 | 无托管模型执行、无 Toolbox UI 状态 |

---

## 6. MVP / 完整 v1 / v1.1

### 6.1 MVP（已合入 `#289` — 当前验证切片）

**目标：** 用最小竖切验证「脱密 pack + 一问一答」是否值得继续扩包。

| 项 | MVP 范围（已实现） |
| --- | --- |
| 入口 | **站点检查 → 当前检查 → 关于本次检查提问（开发测试）**；另有 REST + CLI |
| 唯一 on-demand pack | **`site_ops_insight_pack.v1` only** |
| 其它四包 | 全部延后（§3） |
| Cloud | `hosted_ai_content_support.v1` + 本地 pack；`suggestion_only`；禁止 WP 写；不建 Core proposal |
| `cloud_inspection` | **只吃本地 pack**；不调用 `run_site_ops_cloud_analysis` /「Use Cloud detail」 |
| 输出 | 自然语言建议（Markdown 安全渲染）+ `run_id`；写意图不执行 |
| 明确未做 | 完整多轮对话壳、pack 多选 UI、新审计表、新固定按钮、Addon 第二控制面、第二脱密包 |

验证手册：[insight-pack-cloud-qa-cli.md](insight-pack-cloud-qa-cli.md)。

### 6.2 完整 v1（验证通过且用户确认后再扩）

- 五类 on-demand pack（§3）按需拉取；仍尊重 `cloud_inspection` 等可逆暂停。
- 可选独立/更完整的对话壳；写意图仍仅到 handoff 候选。
- **本 PR / 本稿不启动第二包实现。**

### 6.3 v1.1（已知缺口，不阻塞 MVP）

- 环境版本 pack（PHP/WP/插件版本有界摘要）；
- 真实错误面（结构化近期错误，仍禁 raw log/密钥）；
- 对话侧多 pack 编排与引用证据协议；
- 通用用量摘要；完整 CPT census。

---

## 7. 已接线挂点（相对草稿期调查的更新）

| 路径 | 现状（master / `#289`） |
| --- | --- |
| 只读拼包 REST | `GET /wp-json/npcink-toolbox/v1/site-ops/insight-pack`（`manage_options`） |
| Ask / QA REST | `POST …/site-ops/insight-pack-qa`（body `{ "question": "…" }`；同 cap；本地 pack + Hosted AI） |
| 实现 | `includes/Rest_Site_Ops_Insight_Bridges.php` |
| Admin 薄 UI | Site Check → 当前检查 → **关于本次检查提问（开发测试）** |
| CLI | `scripts/insight-pack-cloud-qa.sh` / `.php` |
| 既有本地预览 | `Admin_Page_Site_Ops_Panel` 仍可 Generate site check；与 Ask **双轨并存** |
| 暂停的 Cloud detail | `cloud_inspection` 仍暂停；Ask **不**走该路径 |

---

## 8. 入口约定（MVP）

- **已采用：** Site Check → Current check 上的开发测试提问条（中文 UI）。
- **理由（产品草稿期约定，未改）：** 与 Generate site check 同源；不新开顶栏 tab；与已降级的 Cloud detail 隔离。
- **完整 v1** 是否改 Overview / 独立子页 / 默认可见产品面 — **待确认**（§11）。

---

## 9. 实现切片进度（边界文档视角）

| 切片 | 内容 | 状态 |
| --- | --- | --- |
| A. 本文档 | 边界草稿定稿结构 | **本 PR** |
| B. 只读 pack REST | `GET …/insight-pack` | **已合入 `#289`** |
| C. Admin 薄 Ask | 站点检查提问面板 | **已合入 `#289`**（开发测试） |
| D. Cloud suggestion_only | Hosted content-support + pack | **已合入 `#289`**（通道为既有 `hosted_ai_content_support.v1`，非新 intent 名） |
| E. 深链增强 | 答复内系统深链编排 | 未作为独立切片强绑；finding 既有 URL 可后续加强 |
| 第二包 UI/REST | §3 延后四包任一行 | **不做（本阶段）** |

加载/429 体验等 UX 迭代另开 PR，不并入本稿。

---

## 10. 与相关文档的关系

| 文档 | 关系 |
| --- | --- |
| `docs/boundary.md` | 硬边界权威；本稿不得放宽 |
| `docs/site-ops-cloud-analysis-contract.md` | Site Ops Cloud detail 合同；MVP Ask **不**走该提交面 |
| `docs/insight-pack-cloud-qa-cli.md` | 人/AI 验证手册与 REST/CLI 操作说明 |
| `docs/ai-site-check-effect-verification-plan-2026-10-14.md` | 更广的体检/复测规划讨论；**非**本稿实施授权 |
| `AGENTS.md` Session Startup | 指向验证手册 + 本稿，便于其它 AI 发现 |

---

## 11. 待用户确认（开放问题）

请产品方直接改本节或回复后，再考虑去掉 filename 中的 `draft` / 升格标题。

1. **五包锁定：** §3 五类名称与顺序是否即为完整 v1 清单？第二包优先做哪一行？
2. **授权 / 审计：** MVP 是否维持「同页打开 + `manage_options` 即视为同意拉本地 pack」、不做新审计表？
3. **Cloud 失败降级：** 是否允许纯本地规则/摘要答复作为默认降级（不调 Cloud）？
4. **产品面升格：** 「关于本次检查提问（开发测试）」是否在验证满意后去掉「开发测试」、升为默认可见产品文案？完整多轮壳是否要做？
5. **与 Generate site check 双轨：** 是否长期并存，或助手成为唯一推荐消费面？
6. **v1.1 编排归属：** 多 pack orchestration 落 Toolbox 投影 vs Toolkit ability 组合 — 需单独 ADR（MVP 不涉及）。

---

*本稿为产品边界草稿的定稿结构。实现切片 B–D 已随 `#289` 合入；扩第二包或升格非 draft 合同前，须先关闭 §11。*
