# Toolbox Admin IA — 开始 / 站点与提问 / 图片处理（v1）

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
│           └── 结果维度 tab（L3，留给后续折叠）
└── 图片处理           toolbox_tab=tools
      └── 工具组 / 工具（L2，不动）
```

顶栏 **3** 个：开始 | 站点与提问 | 图片处理。不引入 Addon 式「连接 / 额度 / Checks」控制面入口。

## 命名

| 中文（站长可见） | 英文 msgid | 规范 slug |
| --- | --- | --- |
| 开始 | Start | `start` |
| 站点与提问 | Site & Ask | `site-and-ask` |
| 图片处理 | Image Handling | `tools` |
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

站点档案 SEO / AEO / GEO / Boundaries 深偏好继续收在站点简介下方的 `<details class="npcink-toolbox__context-advanced">`，主摘要文案为 **Advanced / 高级**（智能提问 packs 不变）。

## 开始页清理与下一步卡片（2026-10-22）

- 三张路由卡：**去提问** / **去检查** / **去改图**（`#315`）。去检查深链「站点与提问」并滚到站点检查折叠区。
- 站长向 chrome：去掉冗余能力健康摘要渲染、嵌套 readiness 卡片与嘈杂支持细节（`#316`–`#318`）；支持信息扁平展示。
- 站点检查 chrome：去掉返回顶栏链接与行话条带（`#319`），把注意力留在检查结果与「去提问」。

## 检查 → 提问（2026-10-22）

生成预览后主按钮 **Ask about this check / 去提问**（`#320`）深链智能提问：`ask_pack=site_ops` + `ask_seed`（种子问题可编辑）。站点检查与智能提问同页（`#321`）后 CTA 仍保留——这是「看完检查立刻问」的降门槛主路径，不是装饰。

## 折叠站点检查顶栏（2026-10-22）

去掉顶栏「站点检查」。当前检查 + 定期巡检收入「站点与提问」下部折叠区；检查 → 提问 CTA 保留。
