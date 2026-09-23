# Npcink 管理界面设计规范 v1

状态：Active。本规范是 Npcink 全部管理界面的视觉与交互语言标准，权威所有者是
`npcink-workflow-toolbox`（见 `docs/platform/README.md` 的职权表）。

## 1. 目的与适用范围

本规范把 dash.cloudflare.com 后台的设计语言提炼成 Npcink 自己的一套规则，
让六个项目的管理界面在外观、层级和用词上保持一致：

| 仓库 | 界面载体 | 说明 |
| --- | --- | --- |
| `npcink-workflow-toolbox` | wp-admin | 操作员产品面，`Npcink AI` 菜单所有者 |
| `npcink-governance-core` | wp-admin | 治理页面 |
| `npcink-abilities-toolkit` | wp-admin | 能力列表与管理页 |
| `npcink-ai-client-adapter` | wp-admin | 通道连接页 |
| `npcink-cloud-addon` | wp-admin | Cloud 连接器设置页 |
| `npcink-ai-cloud` | Web 门户（Next.js） | Cloud 运营/客户界面 |

参考来源是 Cloudflare Dashboard 的域名概览、SSL/TLS 设置页与账户首页。
我们不复制它的左侧栏和全局顶栏（wp-admin 已有自己的导航语境），提炼的是
它的信息层级、组件语言和克制方式。

本规范管"长什么样、怎么交互、怎么用词"。每个仓库自己的边界文档继续管
"放什么内容"（例如 `npcink-cloud-addon/docs/admin-surface-standard.md` 管
设置页的信息架构）。两者冲突时，边界文档优先。

## 2. 设计原则

1. **一屏一个主动作。** 每个视图最多一个实心主按钮；其余动作是描边按钮或
   文字链接。健康状态没有主动作。
2. **一个事实只说一遍。** 数字、百分比、图形条是同一事实的三种表达时，只
   保留两种（大数字 + 细进度条），多余的删掉。
3. **颜色只用于状态和主操作。** 正文永远黑白灰；绿色只表示正常，黄色只
   表示需要注意，红色只表示失败，蓝色是主色与进行中。
4. **层级克制。** 页面标题大，卡片标题 16px 加粗，往下不再出现第三级可见
   标题；需要分组时用留白和分隔线，不叠标题。
5. **健康状态安静。** 自动化能力表达为自动；只有真实失败才出现恢复入口；
   时间戳、验证时间、成功上传时间等支持事实不进入默认视图。

## 3. 设计令牌

每个仓库的 `assets/admin.css`（`npcink-ai-cloud` 为
`frontend/src/app/globals.css`）顶部放同一份 token 块。token 名统一为
`--npcink-ui-*`，组件类名继续使用各仓库自己的前缀（见第 9 节）。

```css
:root {
	/* 主色与交互 */
	--npcink-ui-accent: #3858e9;
	--npcink-ui-accent-hover: #2f4fd4;
	--npcink-ui-focus-ring: rgba(56, 88, 233, .35);

	/* 文字三级 */
	--npcink-ui-text-strong: #1d2327;
	--npcink-ui-text: #50575e;
	--npcink-ui-text-quiet: #646970;

	/* 面板与线条 */
	--npcink-ui-surface: #fff;
	--npcink-ui-border: #dcdcde;
	--npcink-ui-divider: #f0f0f1;
	--npcink-ui-track: #dcdcde;      /* 进度条底槽 */
	--npcink-ui-canvas: #f0f0f1;     /* wp-admin 页面底色，门户可用 #f6f7f7 */

	/* 状态色（文字 / 底） */
	--npcink-ui-ok: #008a20;
	--npcink-ui-ok-bg: #edfaef;
	--npcink-ui-warning: #996800;
	--npcink-ui-warning-bg: #fcf9e8;
	--npcink-ui-error: #b32d2e;
	--npcink-ui-error-bg: #fcf0f1;

	/* 字号 */
	--npcink-ui-font-page-title: 23px;
	--npcink-ui-font-card-title: 16px;
	--npcink-ui-font-body: 13px;
	--npcink-ui-font-quiet: 12px;
	--npcink-ui-font-stat: 20px;

	/* 间距（4 的倍数） */
	--npcink-ui-space-1: 4px;
	--npcink-ui-space-2: 8px;
	--npcink-ui-space-3: 12px;
	--npcink-ui-space-4: 16px;
	--npcink-ui-space-5: 20px;

	/* 圆角：wp-admin 保持方正；胶囊元素用 999px */
	--npcink-ui-radius-card: 4px;
	--npcink-ui-radius-pill: 999px;

	/* 内容列 */
	--npcink-ui-column: 880px;
	--npcink-ui-column-wide: 1040px;
}
```

`npcink-ai-cloud` 门户在 `globals.css` 里把同一批变量映射到自己的选择器
即可；组件语义必须对齐，不要求 DOM 结构一致。

## 4. 组件词汇表

以下 12 个组件覆盖当前全部管理界面需求。新界面先从这里选，确需新增组件
时先改本规范再实现。

### 4.1 页面头

- 眉题（所在区域名，quiet 字号）+ 页面大标题 + 一句话范围说明；
- 动作靠右，最多一个主按钮；
- 页面只允许一个 H1 和一句范围说明，不重复页面标题。

### 4.2 卡片

- 白底、`--npcink-ui-border` 边框、`--npcink-ui-radius-card` 圆角、内边距
  16–20px；
- 卡片标题 16px/600 + 一行灰色说明；说明里可以内联文档链接；
- 卡片之间 18px 纵向间距；不嵌套卡片，确需分层时用分隔线而不是第二层边框
  （Cloudflare 的"设置卡内嵌模式图"是唯一允许的例外形态）。

### 4.3 设置行

Cloudflare 设置页的经典形态，所有开关、下拉、低频配置都用它：

- 左侧：标题（600）+ 一行灰色说明；
- 右侧：控件（开关 / 下拉 / 按钮）；
- 长说明必须压缩成一行或收进折叠，不允许三行以上的段落式描述；说明文字
  限宽 `max-width: 60ch`（约 30–40 个汉字每行），宽屏下不得拉满卡片宽度；
- 需要保留的完整说明（如隐私承诺清单）放在标题旁的 ⓘ 提示里：16px 圆圈
  `i`，`cursor: help`，可聚焦，内容走 `title` 悬停文本；可见说明仍然只说
  主线。每个组件只允许一个 ⓘ，完整文案同时保留在语言文件中；
- 折叠组的 summary 必须携带状态提示（如 `隐私设置 · 匿名诊断已关闭`），
  状态部分用常规字重；折叠把控件藏起来时，summary 就是唯一的状态信号；
- 开关本身即状态，已可见的开关旁不得再写"已启用/已关闭"；
- 卡片标题与第一行内容之间不画分隔线，靠行距分隔；分隔线只用于区与区之间。

### 4.4 统计卡

额度、用量、容量的唯一表达形态（参考实现：`npcink-cloud-addon` PR #163
的 Overview 面板）：

- 三列网格（`repeat(3, minmax(0, 1fr))`），列间 1px 分隔线（推荐给后续列加
  `border-left`，不要用容器底色 + gap 方案——隐藏的统计格会在网格里留下
  空洞）；
- 统计区是所在卡片的内部分区，铺满卡片宽度，与卡片标题、右侧动作共用同一条
  内容列边界；不得在卡片内再画第二圈边框或用更窄的 max-width 造成右侧空带；
- 每格：quiet 小标签 → 20px/600 数值（`tabular-nums`）→ 4px 细进度条；
- 网格在窄屏塌成单列（分隔线相应从竖线改为横线）；
- 百分比只存在于进度条的 `aria-valuenow` 和悬停提示里，不做可见文字。

### 4.5 进度计量条

- 高度 4px、圆角 999px、连续渐变填充（`--npcink-ui-track` 为底槽）；
- 禁止分段方块、斜纹、动画循环；
- 颜色默认主蓝；警告/错误态用 `--npcink-ui-warning` / `--npcink-ui-error`；
- 必须带 `role="progressbar"` 和 `aria-valuenow`。

### 4.6 状态徽章

- 胶囊形（999px），12px/600，状态色文字 + 状态色底（第 3 节 token）；
- 词与色必须来自第 5 节状态词表，不允许自造词；
- 表格、统计卡、页面头里的同一事实使用同一个徽章，不重复造形。

### 4.7 提示条 banner

需要用户处理时的唯一全局形态（替换重复的表格行）：

- 一行文字 + 一个动作按钮 + 可关闭，位于内容列顶部；
- 黄色 = 需要处理；蓝色 = 中性提示；红色 = 失败；
- 同一问题只出现一条 banner，逐行重复同一句解释是错误。

### 4.8 标签页

- 主标签：下划线式（4px 底边），15px/600，`overflow-x: auto`；
- 次级标签：13px，放在页面卡内部顶部；
- 顶层入口数量按各仓库边界文档执行（如 Cloud Addon 固定三个）。

### 4.9 按钮

- 主按钮（实心 `--npcink-ui-accent`）：每屏最多一个；
- 次要按钮（描边）：打开外部详情、次要动作；
- 文字按钮（`button-link`）：重试等行内恢复动作；
- 危险操作（断开、删除）单独分区，永不与健康状态动作并排；
- **外部系统入口唯一**：指向外部系统（如 Cloud 门户）的通用入口全站只允许
  一个，放在概览页，用按钮形态。其余页面的外部链接必须是"上下文相关的深
  层链接"（指向与当前任务对应的具体资源），且一律用文字链接形态，不重复
  通用按钮。

### 4.10 表格

- 表头浅灰带、行高宽松、去竖线；
- 状态列用徽章不用裸文字；
- 需要截断的列表（"仅显示前 N 项"）不允许出现在管理页——要么全量进
  详情页/Cloud，要么收敛为计数 + 链接。

### 4.11 折叠

- 低频详情用 `<details>`，summary 加粗；
- 禁止折叠套折叠（沿用 `npcink-cloud-addon` 管理标准）；
- 调试 URL、机器时间戳等支持事实放在折叠内，等宽字体呈现。

### 4.12 空状态

- 一句话说明 + 一个进入动作；左侧 4px 黄色竖线的窄条是既定形态
  （`.npcink-cloud-empty` 同款）；
- 空状态不得出现"重试""立即更新"等恢复动词。

## 5. 状态词表

所有界面的状态文字、徽章颜色和可用动作必须使用下表，不得自造近义词：

| 状态词 | 颜色 | 含义 | 允许的动作 |
| --- | --- | --- | --- |
| 正常 / 已连接 / 可用 | 绿 | 缓存读取可用 | 无（只留详情链接） |
| 进行中 / 更新中 | 蓝 | 异步任务在跑 | 无（可取消时才给取消） |
| 已过期 / 待检查 | 黄 | 缓存超出时效 | 一次"运行检查" |
| 失败 / 需要注意 | 红 | 真实失败已记录 | 唯一一个恢复动作 |
| 未配置 / 未检查 | 灰 | 尚无事实 | 一次初始化动作 |

配套规则：

- 同一层事实只有一个状态词，不堆叠"已保存、已验证、已返回、未知"这类同义
  近义词；
- "重试"只允许出现在第 5 行（真实失败已持久化）；
- 健康状态不显示验证时间、上次成功时间等技术事实。

## 6. 布局与响应式

- wp-admin 内容列 `max-width: 880px`（宽表 1040px），不新增全局侧栏或顶栏；
- 断点 782px：多列网格塌单列，动作换行；480px：表格转块（沿用 Cloud Addon
  现行做法）；
- `npcink-ai-cloud` 门户可使用自己的导航骨架，但内容区列宽、卡片、字号、
  状态色必须与本规范对齐。

## 7. 文案规则

- 按用户任务组织：**当前是否正常 → 最近是否更新 → 是否需要我处理 → 去哪里
  看详情**；
- 技术词（buffer、cursor、run_id、retryable、provider、contract id）不进
  产品文案，只进折叠的技术详情；
- 截断的列表等于错误的列表；大量明细永远链接到真相所有者（通常是 Cloud）；
- 每个界面用一句话回答"这里是干什么的"，不写第二句重复说明。

## 8. 无障碍要求

- 异步刷新的数值带 `aria-live="polite"`；
- 所有进度条带 `role="progressbar"` + `aria-valuenow`；
- `[hidden]` 必须真实生效：带 `display:flex/grid` 的类要配套 `[hidden]` 规则；
- 焦点环：`0 0 0 2px #fff, 0 0 0 4px var(--npcink-ui-focus-ring)`；
- 状态不得只用颜色区分，徽章必须带文字。

## 9. 实施与命名约定

- 组件类名继续使用各仓库自己的前缀：`.npcink-toolbox-*`、
  `.npcink-abilities-toolkit-*`、`.npcink-openclaw-adapter-*`、
  `.npcink-governance-core-*`（治理仓库需先完成改名，见下）、
  `.npcink-cloud-*`；
- token 块统一为第 3 节的 `--npcink-ui-*`，每仓库一份原样拷贝；改 token
  必须先改本规范，再同步六个仓库；
- **已知冲突**：`npcink-governance-core` 与 `npcink-cloud-addon` 当前都定义
  了无前缀的 `.npcink-ai-tabs` / `.npcink-ai-tab`（两者同挂 `npcink-ai`
  菜单，存在同页覆盖风险）。治理仓库迁移到本规范时必须加上自己的前缀；
- `npcink-ai-cloud` 门户不在 wp-admin 内，允许使用自己的组件库，但状态色、
  字号、间距、圆角 token 必须与本规范同名同值。

## 10. 采用路线

| 仓库 | 现状 | 下一步 |
| --- | --- | --- |
| `npcink-cloud-addon` | Overview 已按本规范完成（PR #163） | 高级与排查（banner 化重复检查行、统一状态词）、站点知识库（待处理列表收敛为计数 + Cloud 链接） |
| `npcink-workflow-toolbox` | 部分形态接近 | 操作员入口页优先：统计卡 + 徽章 + 按钮层级 |
| `npcink-governance-core` | 与 Cloud Addon 存在类名冲突 | 先加前缀去冲突，再迁 token |
| `npcink-abilities-toolkit` | 表格为主 | 表格 + 徽章 + 状态词表先行 |
| `npcink-ai-client-adapter` | 连接页 | 设置行 + 按钮层级 |
| `npcink-ai-cloud` | Next.js 门户 | `globals.css` 引入同名 token，门户关键页对齐状态色与字号 |

每个仓库在自己的 `docs/` 里补一条指向本规范的采用记录（单独 PR），不必把
规范复制过去。

## 11. UI 变更验收清单

提 UI PR 时逐项自查：

- [ ] 每屏最多一个实心主按钮；
- [ ] 没有一个事实出现两次（数字/百分比/条只保留两种表达）；
- [ ] 状态词来自第 5 节词表，徽章颜色与词表一致；
- [ ] 没有三行以上的段落式说明；长解释已折叠；
- [ ] 没有需要截断的列表；
- [ ] 健康状态没有恢复按钮；失败状态只有一个恢复入口；
- [ ] token 使用 `--npcink-ui-*`，没有硬编码色值（`:root` 块除外）；
- [ ] 组件类名带仓库前缀，未与兄弟仓库冲突；
- [ ] 进度条 / aria / `[hidden]` 满足第 8 节；
- [ ] 782px 与 480px 两档不破版。
