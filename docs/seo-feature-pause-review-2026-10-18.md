# SEO 相关功能暂停检查与建议

日期：2026-10-18（Asia/Shanghai）。状态：操作者已同意，已落实为本地开发候选；交付与验证状态见[实施记录](seo-specialist-pause-implementation-2026-10-18.md)。原方案与检查快照保留供追溯。

建议暂停 SEO、AEO、GEO 专项产品入口及其新增开发，保留可复用的内容辅助能力、历史数据和现有治理边界。暂停后的普通操作不应再生成专项建议、发起 SEO 提案或为它们触发远程请求；以后恢复时再逐项重新验证。

本次检查覆盖 Npcink AI Cloud、Cloud Addon、Workflow Toolbox 的本地源码。参考会话的用户要求和最终答复已单独[归档](archive/2026-10/seo-geo-reference-chat-2026-10-18.md)。当前没有核实线上开关和实际用户调用量，因此下文区分源码事实、建议和平台范围限制。

## 建议的暂停范围

| 对象 | 建议处理 | 恢复时保留什么 |
| --- | --- | --- |
| SEO 标题和描述专项建议、优化提案 | 关闭产品入口和新提案生成 | 实现、契约标识、历史提案及结果 |
| AEO 回答摘要、GEO 摘要、SEO 用途 FAQ 和 schema 建议 | 与 SEO 专项入口一起暂停 | 已填规则和配置、源码及少量关键契约测试 |
| 普通文章标题、摘要、翻译、分类和标签 | 保留 | 当前通用内容辅助流程 |
| 内链、相关文章、图片 ALT、caption、图片压缩及媒体处理 | 保留，去掉 SEO 效果包装 | 推荐、无障碍、编辑复用及媒体安全能力 |
| slug | 优先保留为通用 URL 别名工具，取消“利于 SEO”的效果表述；若要进一步收缩，可单独暂停生成入口 | 已发布 URL 和历史 slug 始终保留 |
| Site Knowledge、联网检索、来源核对 | 保留；切断只为 SEO 专项输出发起的调用 | 通用检索与证据能力 |
| 站点资料 | 保留定位、受众、语气、主题关键词和事实约束；隐藏专项规则配置 | 原有 SEO/AEO/GEO 设置不清空 |
| Site Check、计划巡检和发布检查 | 保留通用检查；暂停 SEO 描述缺失等专项提醒、分数影响及“去优化”跳转 | 历史报告，通用检查实现 |
| 排名、搜索收录、流量和 AI 引用的效果表述 | 从当前产品说明、引导和验收指标中退出 | 历史材料保留并标为历史 |

FAQ 和结构化内容本身也有普通编辑用途；暂停的是当前专项输出及包装，未来通用用途应独立定义。Cloud Site Knowledge 中的“页面收录”是站内索引状态，不等于搜索引擎收录，不应按关键词批量删掉。

## 三个仓库的源码落点

| 仓库和位置 | 当前事实 | 暂停建议 |
| --- | --- | --- |
| Toolbox [Admin_Page.php](../includes/Admin_Page.php)，`render_content_context_form` | Site Profile 的高级偏好仍有 SEO、AEO、GEO 页签和候选字段 | 隐藏专项配置，保留通用站点资料；保存时保留未展示的旧字段 |
| Toolbox [Settings.php](../includes/Settings.php)，`content_context_defaults`、`sanitize_content_context` | FAQ、AEO/GEO 摘要默认允许，候选字段含 SEO 标题和描述；清洗器会重建整组配置 | 用执行时暂停判断限制有效输出，不靠改默认值或清空选项停用 |
| Toolbox [Rest_Editor_Content_Support.php](../includes/Rest_Editor_Content_Support.php)，`editor_content_support` | 独立 `discoverability` intent 已不在允许列表；`publish_preflight` 仍调用 discoverability brief，且请求外部检索 | 保留发布检查，切断其中的专项建议与多余检索；有独立事实核对用途的检索可继续保留 |
| 同文件 `editor_summary_terms_optimization` | 仍将 discoverability 和外部检索并入摘要/分类组合结果 | 保留摘要和分类，将专项生成、展示和相关缓存分支退出 |
| Toolbox [Publish_Preflight_Service.php](../includes/Publish_Preflight_Service.php) | `build_sections` 总会构造 `seo_handoff`；没有候选时也会从原标题、摘要或正文生成 fallback | 暂停时明确停止 SEO handoff 和 readiness 生成，不能仅传空候选 |
| Toolbox [Abilities.php](../includes/Abilities.php) | 仍注册 get/validate/build-content-discoverability，以及 build-ai-article-writing-pack 等外部调用面 | 专项构建入口退出默认发现并拒绝新请求；公共上下文读取按实际通用消费者保留 |
| Toolbox [Provider_Workflow_Plans_Service.php](../includes/Provider_Workflow_Plans_Service.php)，`build_content_discoverability_brief` | 候选主要由本地裁剪和规则生成，仍可附带 Cloud 外部检索；`build_ai_article_writing_pack` 复用该 brief | 把通用上下文读取与专项输出分开，避免停用专项后误伤通用写作准备 |
| Toolbox [editor-content-support.js](../assets/editor-content-support.js)、[Ability_Surface_Metadata.php](../includes/Ability_Surface_Metadata.php) | 仍包含 SEO handoff、发布检查渲染与 `Article image ALT (SEO)` 包装 | 随后端暂停同步处理渲染和动作；ALT 改为内容与无障碍描述，不删除能力 |
| Toolbox [Site_Ops_Insight_Builder.php](../includes/Site_Ops_Insight_Builder.php)、[Site_Ops_Snapshot_Collector.php](../includes/Site_Ops_Snapshot_Collector.php) | 读取第三方 SEO 描述字段；metadata finding 混合了描述和普通摘要缺失 | 暂停 SEO 描述专项提示，保留普通摘要、媒体和内容检查 |
| Addon [class-cloud-ai-task-contract.php](../../npcink-cloud-addon/includes/class-cloud-ai-task-contract.php) | 仍将 `ai/meta-description` 和 `ai/slug-generation` 投影到 Cloud task，编辑建议也有通用投影 | 沿用官方 WordPress AI 功能控制和原注册来源；需要硬停时在出站前拒绝专项 task，不新增本地能力注册中心 |
| Addon [class-cloud-wordpress-ai-text-model.php](../../npcink-cloud-addon/includes/class-cloud-wordpress-ai-text-model.php) | 根据注册 Ability 构建并发送 runtime 请求 | 本地化文案不会影响执行；停用检查必须覆盖实际出站路径 |
| Addon [class-ai-plugin-localization.php](../../npcink-cloud-addon/includes/class-ai-plugin-localization.php)、[class-cloud-media-plan-projection.php](../../npcink-cloud-addon/includes/class-cloud-media-plan-projection.php) | 存在 SEO 描述、slug、混合编辑建议文案和媒体 SEO 计划措辞 | 入口由拥有者控制；通用媒体流程保留，仅调整不准确包装 |
| Cloud [wordpress_ai_connector/runtime.py](../../npcink-ai-cloud/app/domain/wordpress_ai_connector/runtime.py)、[contracts.py](../../npcink-ai-cloud/app/domain/wordpress_ai_connector/contracts.py) | 支持 `meta_description`、`slug_generation`；`editorial_notes` 提示覆盖 SEO 等多类检查 | 保留实现；若暂停专项 runtime，按 task/检查类型在调用 Provider 前拒绝，混合编辑建议保留语法、可读性和无障碍用途 |
| Cloud [cloud_batch_runtime/service.py](../../npcink-ai-cloud/app/domain/cloud_batch_runtime/service.py) | `missing_meta_description`、`short_meta_description` 纳入 metadata 分数 | 暂停期不再产生专项扣分和行动建议；若改变分数语义，更新版本并保持历史结果可读 |
| Cloud [image_sources/service.py](../../npcink-ai-cloud/app/domain/image_sources/service.py) | 有 SEO/AEO/GEO 主题词到配图概念的转换 | 属于通用图片检索输入，可保留；暂停专项产品不必禁止用户搜索这些主题 |

### 停用时容易漏掉的地方

1. **隐藏界面不能阻止调用。** Toolbox Abilities、旧客户端及 WordPress AI 插件仍可能发起请求。旧缓存也可能重新显示 SEO 卡片；暂停判断应覆盖生成、结果投影与动作执行，而不只是菜单。
2. **把候选设为空仍可能生成提案。** Preflight 的 fallback 会用原文章内容重建 SEO 标题和描述；必须在构建 handoff 之前判断暂停状态。
3. **隐藏配置可能导致数据丢失。** `sanitize_content_context` 对缺失字段写空字符串、false 或空列表。精简表单后若不调整保存合并逻辑，原规则会在下次保存时被清空。
4. **仅改默认值不能覆盖老站点。** `get_content_context` 会合并已存选项；需要暂停期的有效策略，并保留原配置供未来恢复。
5. **Cloud 不能只删旧 task allowlist。** 当前 `validate_wordpress_operation_contract` 在携带 `task_contract` 时走契约验证分支。若要求服务端停用，必须同时覆盖兼容请求和契约化请求，不能只从 `WP_AI_CONNECTOR_ALLOWED_TASKS` 删除名字。
6. **旧文档不能当成当前入口表。** README 部分 Site Check 可见性描述与当前 metadata 不一致；`Rest_Surface_Bridges.php` 还列有历史 intent。实际入口要以注册、服务端允许列表和消费者调用为准，本次不扩大为全仓库文档清理。

## 暂停方式与边界

推荐先完成“这三个仓库内的产品暂停”：默认界面不再展示 SEO/AEO/GEO 专项入口；普通操作不再附带专项输出和 SEO 提案；保留的专项服务实现不能通过旧入口继续创建新工作。通用能力照常使用，历史结果可读。

实现时先确认宿主已有的启停接口。确需新增判断时，在各拥有者内使用少量明确的专项开关或固定发布策略，记录版本和默认值，不新增统一功能管理后台。Toolbox 管产品组合，Addon 管有界传输，Cloud 管 runtime 接受范围；三者不能各维护一套 WordPress 能力或工作流注册表。

暂停请求应返回稳定、明确的“该功能已暂停”状态，客户端不应自动换任务名重试或回退到另一个付费生成入口。不要按 prompt 中是否含 SEO/GEO 关键词封禁通用文本和图片请求。

官方 WordPress AI 插件的 Meta Description、Slug 和 Editorial Notes UI 由该插件拥有。落实前需核实安装版本及可用的功能控制接口；Addon 翻译词条、Cloud 拒绝响应均不能单独证明官方 UI 已关闭。

### 三仓库暂停与全平台停用的区别

本方案不构成“所有渠道均不能执行 SEO 写入”的证明。参考会话还指出 Toolkit 的 SEO/GEO 分析与 `set-post-seo-meta`，以及 Adapter/OpenClaw、Core 的提案链路。它们可以在完全绕过本次三个仓库专项入口的情况下继续作为本地能力存在；当前状态需由各拥有者重新核实。

如果目标升级为全平台硬停，应另设 Toolkit/Adapter/Core 范围：阻止新专项调用与新写入，在执行 preflight 复查暂停状态，并明确如何处理已审批未执行的 SEO 提案。历史提案和审计证据保留。不要把这项治理放进 Cloud 或 Addon。

参考会话所提 `ai_visibility_score` 位于 Toolkit。它是优先复核的外部依赖，本轮未重新验收计算公式；即便只暂停三个仓库，也不宜继续借用该分数宣传搜索排名、收录或 AI 引用效果。

## 分批实施建议

| 批次 | 工作 | 完成判据 |
| --- | --- | --- |
| 第一批，Toolbox 产品入口 | 隐藏专项偏好；切断 preflight、摘要组合中的 SEO 分支；控制专项 Abilities；保留通用资料与写作准备 | 普通用户操作无专项卡片、无新 SEO handoff、无专项远程调用；旧配置保存后仍完整 |
| 第二批，Addon 和 WordPress AI | 核实宿主功能控制；关闭 Meta Description 等明确专项入口；为需要硬停的任务增加有界出站判断 | 官方 UI 与实际调用一致；暂停请求不出站；标题、摘要、翻译和 ALT 不受影响 |
| 第三批，Cloud 及观测 | 按选定暂停范围限制 runtime；停掉专项巡检行动；修订当前产品说明和验收范围 | 旧式及 task-contract 请求均遵守范围；暂停请求不调用 Provider、不新增生成费用；通用运行正常 |
| 可选扩展，全平台硬停 | Toolkit、Adapter、Core 及 Eval 同步新请求与执行边界 | 通道发现、提案提交、已审批待执行场景都有明确结果 |

每批只做本模块的可审阅变更，不把暂停扩大成大规模重构、翻译重建、依赖升级或测试删除。当前三个仓库的 runtime/能力行为改动按 L2 管理；仅隐藏 UI 也不能按外观 L0 处理。

### 实施后的最小验证

- Toolbox：发布检查仍可用，缺少 SEO 描述不再生成专项提案或专项提醒；组合摘要/分类不带专项候选；站点资料往返保存保留隐藏字段；材料准备流程不因旧 brief 依赖失效。运行相关 preflight、editor、provider 行为测试及默认 `composer test:all`，补受影响 PC 页面检查。
- Addon：task-contract 和 WordPress AI connector 行为测试；暂停任务在出站前拒绝，其他任务可走原传输。固定 UI 文案变化再运行本地化门禁；变更发布包文件时按仓库打包规则验证。
- Cloud：先用 `pnpm run check:changed -- --plan <实际文件>` 路由，选择 WordPress AI connector、batch runtime、site ops 的相关测试；源码行为改变后遵守 M4 candidate 规则。保留通用 metering、签名、权限与无 WordPress 写入边界。
- 跨仓库功能里程碑：从 Toolbox 运行 `composer quality:matrix:run`。它与 M4 runtime 证据分别报告，不以跳过相关 lane 的 CI success 代替验证。
- 为暂停增加的测试应证明负向行为：没有新 SEO proposal、Provider 调用或 WordPress 写入；历史结果可读；通用能力可用。不要简单删掉原有断言来获得通过。

### 恢复条件

先确定真实需求与明确范围，再核实宿主插件版本、契约、权限和数据兼容，逐个恢复入口。恢复后重新通过通用回归、专项正向测试及当前 runtime 门禁。不要在一个入口恢复时顺带自动开启所有 GEO/AEO 选项，也不要把过期缓存或旧审批当成新的执行授权。

暂停期可为保留实现修复安全、兼容性和通用依赖问题，不继续扩建专项评分、增长承诺或 SEO 仪表盘。若长期没有恢复需求，再用实际调用和维护成本决定删除范围，无需现在建立插件化框架或新服务。

## 本次文档改动和证据

改动包络：仅 Toolbox 的文档模块；新增本建议、参考会话归档，并在文档索引加入链接。公共 API、配置、能力、runtime 与写入边界均未改动。回退只需撤销上述文档增量，不处理其他任务文件。

| 仓库 | 检查时本地 HEAD | 工作区状态 |
| --- | --- | --- |
| Cloud | `db4baabd189ad4454787972d4157d3a08b15c14f` | `codex/commercial-runtime-light-reads`，比本地追踪的 origin/master 落后 1；已有商业 runtime 与文档等 6 项修改 |
| Addon | `be277c720ffedd4d60b0e6d048f92d7e4084aef8` | `codex/fix-ai-localization-gaps-20261018`；开始干净，检查期间出现 4 项本地化审计相关修改，本任务未触碰 |
| Toolbox | `8f186e9c8b2bab6f36aaf77c1499a20a5f0ef984` | `master` 与本地追踪的 origin/master 对齐；开始干净，本次仅增加文档改动 |

本地追踪分支未在本轮 fetch；这些是可复查的本地快照，不是远端最新或生产状态证明。

`composer quality:matrix` 只读盘点通过，报告各仓库 gate 为 `not_run`，M4 为 `not_requested`。`composer test:boundary-vocabulary` 与 `git diff --check` 通过；两份新文档的 37 个本地链接均存在，归档文本的 Markdown 行尾空白已规范化。文档范围不运行跨仓库全测试，不调用付费 Provider、不创建 PR、不操作 M4 或生产。当前交付状态为本地文档，未提交。
