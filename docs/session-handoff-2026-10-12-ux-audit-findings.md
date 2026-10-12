# Session Handoff — 2026-10-12 用户端体验审查（UX Audit Findings）

Status: continuation handoff for the next AI session. **Not a contract** —
the authorities are `AGENTS.md`, `docs/boundary.md`, `docs/known-issues.md`,
and the per-area contract docs. When this file disagrees with them, they
win. 正文使用中文为有意选择（面向操作者的工作交接）。

Read order: `AGENTS.md` → `docs/current-state.md` →
[Session Handoff 2026-10-11](session-handoff-2026-10-11-hardening-release-next-phase.md)
（其优先级仍然有效）→ 本文件 → 所改区域的契约文档。

## 1. 本次会话做了什么

- 用户问题："从用户端视角来看，还有哪些体验问题？给建议"——一次**只读审查**，
  未改任何代码。
- 基线：审查在 `503689ae` 上执行；会话期间并行会话追加了三个提交
  （`faff4b45` 0.6.0 版本号、`c24fb892` 发布 closeout、`29d86e82` 10-11
  交接文档，均为版本号/docs 提交，不影响本文件引用的核心源码行号）。
  本文件行号以 `503689ae` 为准，动手前请按内容重定位行号。
- 方法：三路并行只读探查（编辑器侧边栏 / 管理后台页面 / 首次运行与断连旅程），
  拟写入待办的关键结论回源码抽查核实。标 ✅ 的为本次会话亲自核实；其余行号
  来自探查代理，以 `503689ae` 为准，**动手前请按内容重定位行号**。
- 未做浏览器实测；但本地五插件站实际在运行（`http://127.0.0.1:8090`，会话末
  确认 HTTP 200；登录与环境配方见 2026-10-11 交接的 Environment facts，
  管理密码 `npcink-five-060`）——修复后可直接做浏览器验证。

## 2. 总体结论

正面基线（修改时**不要破坏**）：

- 管理页 Cloud 未连接提示是全插件最强文案（原因细分 + Cloud Addon 设置页深链，
  `includes/Admin_Page.php:1217-1234`）；
- 入门导览已上线且完整（可跳过/重启，`assets/admin.js:8290-8330`，入口
  `Admin_Page.php:715`）；
- 编辑器侧边栏按钮防双击与请求 abort 覆盖完备（`editor-content-support.js:9253`、
  `9690`、`10185`；`beginContentRequest` `7284-7295` abort 覆盖旧请求）；
- 批量优化有进度文案、beforeunload 守卫和"继续优化"恢复路径。

四个系统性问题模式 + 一个待拍板的产品决策：

| # | 模式 | 一句话 |
|---|------|--------|
| P1 | 点击时失败 | 依赖/Cloud 缺失在**点击时**以 503 原文爆发，无链接、无受众区分，作者无法行动 |
| P2 | 浏览器绑定批量 | 批量优化串行绑死单个标签页；失败只有计数无清单，机器原因码从不渲染 |
| P3 | 静默截断 | 50 张上限、屏蔽列表截 10、设置钳制均无提示；"Settings saved." 存的却是另一个值 |
| P4 | 语言混杂 | 仪表盘小工具全中文 msgid、admin.js 进度文案在 t() 外拼接、内链拒绝理由 13 中 1 英 |
| D1 | Site Check 无入口 | boundary.md 称之为"日常站点维护入口"，但导航没有按钮；0.3.0/0.4.0 审查工具全部藏在手输 URL 才能到达的页面里 |

## 3. 待办清单（按优先级）

与 2026-10-11 交接的关系：其 ①（真实待审评论校准）②（③号门读数）都在等
生产数据，本清单不冲突；其 ③"internal-link cluster split 优先"可吸收本清单
TODO-4.3 一并执行。

### TODO-1 失败可见性小会话（最高性价比，一个 PR 量级）

1. **批量优化面板渲染时预检 Cloud**：`render_media_derivative_batch_tool()`
   未接收 `$cloud_ready`（`Admin_Page.php:2644` ✅ 亲自核实签名），而 ALT
   工具（`:2198` 签名 + `:2203-2205` inline notice ✅）和
   `render_content_support_flow_tool`（`:2274` + `:2320` 提交按钮禁用 ✅）
   都已有该模式；调用方 `render_tool_cards( $cloud_ready, 'image' )`
   （`:1552`）手里就有这个值——加参数 + 渲染 notice 即可。
2. **依赖类 503 文案包装**：`includes/Media_Optimization_Batches.php:373-376`、
   `includes/Rest_Editor_Summary_Terms.php:262-268`、
   `includes/Provider_Hosted_AI_Service.php:171/629` + editor JS 透传
   （`editor-content-support.js:666-684` 只改写 `rest_forbidden`）。统一为
   可行动文案：区分"未安装/未启用"、附设置页链接、按受众分流（作者收到
   "请联系管理员"，管理员收到"去连接"）。这是把 ADR-018 的作者/管理员受众
   拆分从权限错误**扩展到依赖错误**。
3. **admin 侧防双击锁**：`runTool()` 全程不禁用提交按钮
   （`admin.js:5002-5034` ✅ 亲自核实，仅 `:5008` 一行 "Running..." 文字）；
   `buildMediaDerivativeBatchPlan` 同样无守卫（`:5303-5313`，每次点击重跑
   最多 6 次付费 Cloud 取样并新建批次记录 `:5550`）。对照组已有正确做法：
   ALT handoff 按钮 `:2618` 有锁、批量循环 `:5683` 有 `data-running` 属性。
4. **截断披露四处**：媒体库多选静默截 50（`Admin_Page.php:235` ✅ /
   `admin.js:36`）；blocked 列表静默截 10、无 "+N more"（`admin.js:2252`、
   `2337`）；评论积压余量不披露（`admin.js:2198-2200`、`2289-2292`；服务端
   钳制 `Provider_Comment_Moderation_Service.php:127`）；设置静默钳制改
   `add_settings_error()`（`includes/Settings.php:580-581`、`644-645`、
   `253-273`——该文件无一处调用）。

验收：新增公共文案全部加 `tests/run.php` 静态契约 needles（needle 钉精确源
串）；admin.js 新字符串进 admin JED（单行数组、2 空格缩进、贴邻插入、禁
`json.dumps` 重 dump）；门槛 `php tests/run.php --quiet` → `composer
test:all`；建议五插件站做一次断连冒烟（停用 Cloud Addon → 批量面板应显示
预检提示而非点击后报错）。

边界：不放宽任何 REST capability/scope（放宽需对象级审计并记入 PR body，
本 TODO 不预期涉及）；十二意图编辑器 allowlist 已冻结，勿加新意图。

### TODO-2 Site Check 入口（已按方案 A 完成，2026-10-12）

- Site Check 现在作为第四个顶层标签显示；Overview 仍是默认入口。
- `toolbox_tab=operations-insights` 深链、只读扫描、可选 Cloud detail、Core
  handoff 与无写入边界均保持不变。
- `Ability_Surface_Metadata` 已改为 `admin_site_check` / `default_visible=true`；
  2026-07 隐藏决策文档、架构说明、当前状态和静态契约已同步记录这次修订。
- 浏览器 smoke 应确认第四个标签可见、兼容深链仍可用，并确认不会自动调用
  Cloud detail、Core proposal 或执行路由。

### TODO-3 批量优化失败明细 + 取消按钮（UI 层，一个 PR）

- 现状：失败项只报计数，per-item `reason` 存机器码
  （`admin.js:5719` `'processing_failed'`、`'cloud_not_qualified'`）从不
  渲染；历史区只列可恢复的成功项（`:5460-5465`、`:5479`）。
- 做：失败项清单（哪些附件、人话化的原因映射）渲染进历史区；运行中取消
  按钮（挂到 `:5683` 的 `data-toolbox-media-batch-running` 附近）。
- 硬边界：ADR-015 + boundary.md Plugin-Admin Batch Boundary——**仅前台、
  现场管理员、可恢复前台执行记录**；禁止后台 worker/队列/Cron/agent 触发。
  "必须开着标签页"的真正解法是 Cloud 意图调度（
  `docs/platform/cloud-intent-scheduling-brief-2026-10-10.md`，跨仓）——
  本 TODO 只做 UI 侧能做的部分，并作为该跨仓项的输入。
- beforeunload 守卫已有（`admin.js:8002-8008`），勿重复加。

### TODO-4 i18n 清理（受 Translation Source Language Policy 约束：逐文件迁移、禁 JED 批量再生成；part 文件不带翻译除非 per-handle JED 契约）

优先级顺序：

1. `includes/Dashboard_Widget.php`（`:36`、`:55`、`:58`、`:100`、`:102`、
   `:116-125`、`:138-149`）+ `includes/Hot_Topic_Pool.php`（`:100`、`:111`）
   ——中文源 msgid，非 zh_CN 站点仪表盘全中文；断连站点首屏即"知乎热榜"
   混排且刷新失败提示不可行动（`:124-126`）。
2. `admin.js` 在 t() 外拼接的进度文案：`'Item ' + … ' of '`（`:5596`）、
   `'Retry ' + …`（`:5644`）、`'Current state: '`（`:589`）——zh_CN 用户
   在最紧张的批量过程中看到英文碎片。
3. 内链拒绝标签（`editor-content-support.js:6330-6347`，14 条中 13 条中文
   源 msgid、1 条英文；`:6524`、`:6526`）——英文站点作者批量应用内链后
   看到中文失败列表。**可并入 2026-10-11 交接排定的 internal-link cluster
   split 会话一并迁移**。
4. 句序拼接改整句 msgid（`editor-content-support.js:1376`、`:1386-1387`、
   aria-label `:2677`）——翻译者无法重排语序。

说明：`整理` 按钮**不在**此清单——源码已干净（PR #170 的 i18n 已修复，
本次核实源码零命中 ✅；zh_CN 目录里的 22 处属正常翻译，且
`tests/run.php:1364` 已断言源码不含该串）。known-issues 的过期条目本次已
移入 Recently Closed。

### TODO-5 编辑器侧边栏修复（1-2 个 PR）

1. **骨架屏按实际数量**：`editor-content-support.js:2652-2662` 固定 9 格、
   缺位补 `is-loading`，`assets/editor-content-support.css:2525-2533` 无限
   动画——4 个结果的搜索会显示 5 个永远"加载中"的格子。
2. **取消传播到弹窗关闭**：`onRequestClose`（`:9817-9820`）不调用
   `cancelActiveContentRequest()`；Cancel 按钮仅 brief/generate 模式渲染
   （`:9834-9839`）——挂住的搜索让模式标签持续禁用（`:9641/9652/9663`）。
3. **保留已输入的 SEO 字段**：结果列表刷新时 `selectedImageForInspector`
   被强制 null（`:9615-9616`），已输入的 alt/title/描述（`:9032-9035`）
   无声消失——至少加显式丢弃警告。
4. **错误通知加重试按钮**：`:9452`/`:10138` 渲染 REST 原文（`:7884`
   `setError(requestError.message)`；`parseJsonResponse :686-692` 抛响应体）。
5. **Unsplash 署名**：候选卡无署名（`:2669-2710`）；仅选中后折叠 details 内
   非链接 small（`:3042`）；插入路径只复制 caption 不带署名
   （`:2820-2825`）。boundary.md（Image Source Providers 节）要求保留
   photographer attribution + `download_location`——响应侧已保留，
   展示/插入侧是缺口；修复时**勿丢 `download_location` 元数据**。

验收：五插件站浏览器冒烟（站点在运行）；JS 改动走
`test:editor-js-undefined` 审计门；editor bundle 新字符串进 po/mo/pot + JED
双目录。

### TODO-6 低优先批次（搭车做）

- 无 `aria-live`/`aria-busy`（全 bundle）；灯箱无 Escape/焦点管理
  （`editor-content-support.js:2722-2752`）。
- 空态：标签建议组空时渲染 null（`:6648-6650`；对照内链 `:6518` 有空态
  注记）；批量 History 空态缺文案（`Admin_Page.php:2636-2639`）。
- 恢复/确认操作零反馈：`Admin_Page.php:67-86` redirect 无 query arg/notice。
- Site Check 子标签刷新丢失：`admin.js:7052-7102` 只同步 2 个 tab 进 URL；
  九个内层标签 `Admin_Page.php:872-882`。
- **激活完全静默**：无 `register_activation_hook`、includes/ 无
  `admin_notices`（`npcink-workflow-toolbox.php:93-103`）；唯一线索是插件
  行 Settings 链接（`includes/Plugin.php:167-178`）。可做激活后一次性指引
  notice，或并入导览 v2（`docs/onboarding-tour-design-v1.md` 已有 v2 事项）。
- getting-started：连接后卡片整体消失而非步骤打勾（`Admin_Page.php:631-633`
  ✅）；步骤 1/3 硬编码 `done=false`（`:650`、`:674`）。
- 两个无 UI 设置：`include_raw_responses` / `enable_image_source`
  （`Settings.php:67-68`、`:576-577`、`:492-498`；仅以隐藏 input 存在
  `Admin_Page.php:1151-1156`）——加设置 UI 或文档明示。
- 升级/卸载沟通：无版本化升级例程（版本常量只用于 cache-busting
  `Toolbox_Asset_Paths.php:38`）；`admin_init` 静默跨插件导入
  （`Settings.php:62`、`:669-680`）；编辑器 post/page 收窄只在
  `readme.txt:93-97` 通知；卸载删数据无说明（`uninstall.php:10-70`）。
- 机器码/术语外露 + 术语漂移：`admin.js:5716-5719`、`:119-128`（formatLabel
  打印 snake_case）、`:1226-1233`（"Write posture: suggestion_only"）、
  `Admin_Page.php:2544`；同一功能四名并存 Image Handling（`:566`）/Image
  tasks（`:1657`）/Media Library Optimization（`:1560`）/Optimize Media
  Library images（`:2599`）；Site Profile（`:1305`）/Site Context（`:820`）/
  site brief（`:1323`）——与 Operator Terminology Standard v1 + "Core
  proposal" 跨仓条目联动收口。

## 4. 已核实为非问题（勿重复排查）

- 编辑器侧边栏防双击完备（disabled + isBusy + abort 覆盖）——TODO-1.3 是把
  同样的标准**补到 admin.js**，不是修编辑器。
- 批量已有进度文案、beforeunload 守卫、"继续优化"恢复路径
  （`admin.js:5710`、`:5728`、`:8035-8040`）。
- 水印库未保存守卫 + aria-live 保存状态（`admin.js:7992-7998`）。
- 表单 label 规范、套件表 `scope="col"`（`Admin_Page.php:98-104`）。
- 采样上限多数有计数披露（评论 20/50 选项、Pending/Sampled/Classified 行）。
- Cloud runtime notice 可行动（`Admin_Page.php:1217-1234`、`:1236-1248`、
  深链 `:753-761`）。
- 导览完整可重启；试用问题仍在 known-issues 等 operator trial，勿重复登记。
- `整理` 标签已修复（见 TODO-4 说明）。

## 5. 边界与规范提醒（与本清单相关）

- ADR-015 / Plugin-Admin Batch Boundary：批量仅前台 + 现场管理员；TODO-3
  不得造 worker/队列/调度。
- boundary.md 把 Site Check 定义为 everyday entry——TODO-2 决策的对齐点。
- Image Source Providers：保留 attribution + `download_location`（TODO-5.5）。
- 十二意图编辑器 allowlist 冻结；恢复任何已退役意图需新边界决策。
- 新公共文案 = `tests/run.php` needles；JS 字符串目录规范（admin JED 单行
  数组；editor bundle 进 po/mo/pot + JED）。
- 不放宽 REST capability；确需放宽 → 同 PR 对象级审计 + PR body 记录。
- 门槛与管道：`php tests/run.php --quiet`（迭代）→ `composer test:all`
  （提交前）；勿用 grep/tail 管道跑门（exit code 会被吞）。
- 提交纪律：一模块一 session；逐文件 staging；PR 走 `composer pr:publish`
  模板（Scope/Boundary/Verification/Risk）。

## 6. 会话状态与环境

- 本次会话**未产生任何 commit**。工作树新增/修改（下一会话 `git status` 即
  可见）：本文件 + `docs/known-issues.md`（新 Open 条目 + 过期 `整理` 条目
  移入 Recently Closed）+ `docs/current-state.md`（评审日期 + Open Debt
  指针）+ `docs/README.md`（索引行）。建议单独 docs commit，或作为 TODO-1
  首个 PR 的前置提交。
- 五插件站在运行：`http://127.0.0.1:8090`（管理密码 `npcink-five-060`；
  MySQL socket 等 Environment facts 见 2026-10-11 交接）。
- 2026-10-11 交接的 ① 真实评论校准、② ③号门读数仍在等生产数据；
  ③ internal-link cluster split 与本清单 TODO-4.3 可合并执行。

## 7. 来源记录

- 本文件是 2026-10-12 用户端体验审查的唯一发现记录（三路只读探查 +
  六处亲自核实 ✅：runTool 无锁、导航仅 3 标签、附件 50 上限、批量工具缺
  `$cloud_ready`、getting-started 条件、`整理` 源码零命中）。
- 优先级上游：[Session Handoff 2026-10-11](session-handoff-2026-10-11-hardening-release-next-phase.md)。
- 重叠的已登记条目（known-issues）：Operator Terminology Standard 跨仓、
  导览试用问题、Translation Source Language Policy——勿重复登记。
