# Toolbox Admin IA — 开始 / 站点与提问 / 图片审核与设置（v1）

状态：Active（IA-1 起；2026-10-22 折叠站点检查顶栏）。产品边界仍以 [deidentified-assistant-boundary-v1.md](deidentified-assistant-boundary-v1.md) 为准；视觉 token 见 [admin-ui-design-standard-v1.md](admin-ui-design-standard-v1.md)。智能提问 **行为**（pack 下拉 + REST）本阶段不变。

## 产品意图（降门槛）

本系列（`#311`–`#321`）目标是**降低站长使用智能提问的门槛**：顶栏变少、稀疏散入口默认隐藏、开始页只留清晰下一步、检查结果一键带到提问。不改 pack REST / `suggestion_only` 边界；不把 Addon 控制面塞进 Toolbox。

## 目标树

```text
流程工具箱（Npcink AI → 流程工具箱）
├── 开始              toolbox_tab=start
├── 站点与提问         toolbox_tab=site-and-ask
│     ├── 站点画像（简报表单）
│     ├── 智能提问（pack 选择器保留）
│     └── 站点检查（折叠区；当前检查 / 定期巡检 L2）
│           └── 更多扫描视图（L3，默认折叠；展开后显示结果维度 tab）
└── 图片审核与设置           toolbox_tab=tools
      └── 工具组 / 工具（L2，不动）
```

顶栏 **3** 个：开始 | 站点与提问 | 图片审核与设置。不引入 Addon 式「连接 / 额度 / Checks」控制面入口。

## 命名

| 中文（站长可见） | 英文 msgid | 规范 slug |
| --- | --- | --- |
| 开始 | Start | `start` |
| 站点与提问 | Site & Ask | `site-and-ask` |
| 图片审核与设置 | Image Review & Settings | `tools` |
| 站点画像 | Site Profile | 面板内分区 |
| 智能提问 | Smart Ask | 面板内分区 |
| 站点检查 | Site Check | 面板内折叠区（不再是顶栏）；兼容查询 `toolbox_tab=operations-insights` → 落到 `site-and-ask` |

与 Cloud Addon「检查」区分：Toolbox 始终称 **站点检查**（运营只读检查），不是连接器 readiness。

## URL（开发期）

规范 `toolbox_tab`：`start` | `site-and-ask` | `tools`。
缺省或未知回落 `start`。`toolbox_tab=operations-insights` 仍接受，映射为 `site-and-ask`（含预览与 `site_check_tab`）。
`site_check_tab`、`toolbox_tool`、智能提问 pack 选择逻辑不变。开始「去检查」深链：`site-and-ask` + `#npcink-toolbox-site-check`。

## 非目标（本笔记）

- 单框自动选包 / 隐藏 pack 下拉。
- 改 REST 合同或 suggestion_only 边界。
- 删除站点检查能力（仅去掉顶栏 Tab，收入站点与提问）。

## 默认隐藏 / 折叠（2026-10-22 产品决议）

UI-only；实现与文案保留，通过 `Settings::admin_ui_entry_hidden()` 不渲染：

| 条目 | 键 | 默认 |
| --- | --- | --- |
| 定时回顾「高级：本站检查设置」 | `scheduled_review_advanced_settings` | 隐藏 |
| 标记媒体回顾 | `flagged_media_review` | 隐藏 |
| 评论审核 / 分类标签 / 内链 三卡片 | `site_check_ops_review_cards` | 隐藏 |

站点档案 SEO / AEO / GEO / Boundaries 深偏好继续收在站点简介下方的 `<details class="npcink-toolbox__context-advanced">`，专项暂停期间主摘要为 **Content boundaries / 内容边界**，仅显示现有主题和表述指引；恢复专项时仍使用 Advanced（智能提问 packs 不变）。

## 开始页清理与下一步卡片（2026-10-22）

- 三张路由卡：**去提问** / **去检查** / **去改图**（`#315`）。去检查深链「站点与提问」并滚到站点检查折叠区。
- 站长向 chrome：去掉冗余能力健康摘要渲染、嵌套 readiness 卡片与嘈杂支持细节（`#316`–`#318`）；支持信息扁平展示。
- 站点检查 chrome：去掉返回顶栏链接与行话条带（`#319`），把注意力留在检查结果与「去提问」。

## 检查 → 提问（2026-10-22）

生成预览后主按钮 **Ask about this check / 去提问**（`#320`）深链智能提问：`ask_pack=site_ops` + `ask_seed`（种子问题可编辑）。站点检查与智能提问同页（`#321`）后 CTA 仍保留——这是「看完检查立刻问」的降门槛主路径，不是装饰。

## 折叠站点检查顶栏（2026-10-22）

去掉顶栏「站点检查」。当前检查 + 定期巡检收入「站点与提问」下部折叠区；检查 → 提问 CTA 保留。

## 结果维度折叠（已由 #313 实现，2026-11-02 复核）

本地检查生成预览后，默认展示概览和下一步操作；「内容 / 媒体 / 评论 / 结构 /
问题清单 / 证据 / 高级」页签收入「更多扫描视图」，点击才展开。Cloud detail 仍受
既有暂停条件控制。源码是 `includes/Admin_Page.php` 中的
`<details class="npcink-toolbox__ops-dimension-fold">`，没有 `open` 属性；
交付提交 `6b395f0b660ec1c9ddc9bb5e69368c3cfa0ecc8e`（[#313](https://github.com/npcink/npcink-workflow-toolbox/pull/313)）。
这只是详细结果的呈现方式，不改变检查项目、评分或模型调用。原待办已销账；本次
仅复核源码并更新文档，没有改 UI，也未重新验收安装站点。

## 开放待办

2026-11-09 操作指引维护：开始页与总介绍以本地检查、智能提问、ALT 预览为准，
不再宣传已暂停的媒体优化。智能提问保留四类摘要及独立公开内容问答；明确摘要
不含正文、环境清单不等于测速，增加「停止等待」及 90 秒浏览器等待上限。
停止或超时只结束浏览器等待，不取消 Cloud 工作、不重试、不接管运行恢复。
失败通过「给客服的信息」展示可用的 Cloud `run_id`，不再指向 Toolbox 不存在的
Checks 标签页。后台 ALT 预览与编辑器当前文章 ALT 应用的边界分别说明。

会话遗留与旁路清单见 [admin-ia-open-todos-2026-10-23.md](admin-ia-open-todos-2026-10-23.md)（L3 折叠已关闭；其余为单框选包、生产 Cloud 部署决策、year-0020、英文 toast 移交、orphan runs、mid-sync 502）。

## 后台设置维护 — 2026-11-10

本次只修复后台巡检发现的展示问题。顶栏「图片审核与设置」和内部「水印与备份」
明确现有范围，URL、选项名、权限和冻结开关保持不变。媒体详情不再指引已暂停的优化。

站点检查状态区区分「可扫描」「扫描暂不可用」「当前快照已生成」；成功后仍以
「去提问」为主动作，「重新扫描」为次动作。保留四包和公开内容问答，不调整选择逻辑。

备份 30 天呈现为固定策略，只有清理方式可改，按钮明确为「保存清理方式」。
水印的已保存状态改成静态文本；JS 初始化后仅在发生修改时显示保存/放弃操作，
保存区和删除撤销区使用正常文档流，不覆盖模板或预览。无 JS 时仍保留表单保存按钮。
选择 Logo 同样标记未保存，防止新的保存区隐藏规则遗漏此操作；撤销删除后同步隐藏过时的空列表提示。

不调整 Site & Ask 的模块顺序，不恢复隐藏能力，不删除已有配置、备份、历史或恢复路径。

### 本地验证与限制

`magick-ai.local` 通过源码符号链接运行。中英文分别在 1440×900 和 390×844
验证命名、固定保留策略、干净/已修改状态、仅预览、默认模板变更、放弃、新建、
Logo 选择、删除/撤销和原 Settings API 表单提交字段；没有横向溢出、JS 错误或
失败请求。`options.php` 提交在浏览器中拦截，没有持久修改设置；Logo 选择使用受控
媒体选择结果，没有上传。现有五个提问选项、专项隐藏和旧优化 URL 回落均保留。
临时登录/语言辅助文件清理。截图与结构化回执在 ignored `output/playwright/admin-maintenance-20261110/`，
不是发布产物；人类视觉验收和生产发布仍单独进行。

已有 Site Check browser smoke 通过本次状态/按钮断言后，在第 272 行要求本地
一定产生内链健康问题而失败。使用 `origin/master` 的 Admin_Page 源码，跳过其
已知旧状态断言后复现同一后续失败；不伪造站点内容来让它通过。相关记录见
[Known Issues](known-issues.md)。完整确定性门禁与本次专项浏览器验证分开报告。

确定性验证：`composer test:all`（4580 项静态契约及行为/JS/包门禁）、
`composer validate --no-check-publish`、`composer check:wporg`、PHPStan 和配置内
运行时 PHPCS 均通过。补充无 JavaScript 浏览器检查确认表单保存按钮仍然可用。

## 后台入口收缩第一批 — 2026-11-10

用户基于当前后台的用途审视，批准以下展示层收缩：

- 站点画像不再渲染「使用 AI 技术博客模板」，保留原模板实现、当前站点草稿和手动编辑。
- 移除画像区重复的「去智能提问」及其私有渲染方法；保留独立智能提问区和检查结果的带上下文提问。
- 站点检查内部的「定期巡检」显示为「巡检预览」，简短说明它只检查公开内容读取，
  不会开启自动巡检。原 `scheduled-review` 和兼容预览 URL 保持有效。
- 「水印与备份」先显示备份清理；水印工作台收入默认关闭的原生 `<details>`。
  暂停媒体优化时，在折叠标题下说明已有模板仍可编辑。展开后保留原模板预览、
  编辑、撤销与 Settings API 保存，备份和恢复路径保持原样。

继续使用既有后台组件。三个顶层页签、五个提问选项、REST/能力契约、权限、配置值、
历史和运行暂停均保持。第二批画像摘要/编辑交互不在本次切片内；当前变更也不是生产发布。

窄屏实测发现巡检预览说明被右侧按钮挤成窄列：仅在该工作区的窄屏样式中将按钮移到
说明下方，桌面仍在标题右侧。水印折叠标题显示原生展开/收起箭头，不引入新 JS。

验收：`composer test:all`（4583 项静态契约及既有行为/JS/包门禁）、PHPStan、
配置内 PHPCS、Composer validation、WordPress.org 规则和 `composer package:release`
均通过；发布副本和最终 ZIP 的运行时清单通过，压缩 CSS 已重新构建。
安装站点 `magick-ai.local` 的 zh_CN/en_US × 1440×900、390×844 检查通过：
五个提问选项、旧巡检链接、检查结果提问及种子、默认折叠、键盘展开、编辑/删除撤销、
无 JS 展开和保存入口均可用，新增说明不超过两行，没有横向溢出。
表单提交在 `options.php` 拦截并核对字段，未持久修改设置；没有调用提问模型或写入接口。
最终截图验收采用减少动画模式，页面 JS 错误为零。最初快速跳转曾触发 WordPress
原生跨页面转场的 `Transition was skipped`，原始回执保留，不把该轮记作通过。
证据位于 ignored `output/playwright/admin-entry-contraction-20261110/`，临时登录、
语言 helper 和本次登录会话均已清理；自然使用、人类视觉验收和生产发布仍单独进行。
旧完整 Site Check smoke 的站点样本假设问题维持原账，本次使用聚焦浏览器检查，未重跑该用例。
