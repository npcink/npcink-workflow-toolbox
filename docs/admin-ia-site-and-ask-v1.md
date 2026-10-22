# Toolbox Admin IA — 开始 / 站点与提问 / 站点检查 / 图片处理（v1）

状态：Active（IA-1 起）。产品边界仍以 [deidentified-assistant-boundary-v1.md](deidentified-assistant-boundary-v1.md) 为准；视觉 token 见 [admin-ui-design-standard-v1.md](admin-ui-design-standard-v1.md)。智能提问 **行为**（pack 下拉 + REST）本阶段不变。

## 目标树

```text
流程工具箱（Npcink AI → 流程工具箱）
├── 开始              toolbox_tab=start
├── 站点与提问         toolbox_tab=site-and-ask
│     ├── 站点画像（简报表单，原 context 面板）
│     └── 智能提问（原 smart-ask 壳，pack 选择器保留）
├── 站点检查           toolbox_tab=operations-insights
│     ├── 当前检查 / 定期巡检（L2，IA-1 不动）
│     └── 结果维度 tab（L3，留给 IA-2 折叠）
└── 图片处理           toolbox_tab=tools
      └── 工具组 / 工具（L2，不动）
```

顶栏 **4** 个。不引入 Addon 式「连接 / 额度 / Checks」控制面入口。

## 命名

| 中文（站长可见） | 英文 msgid | 规范 slug |
| --- | --- | --- |
| 开始 | Start | `start`（原 Overview 顶栏文案） |
| 站点与提问 | Site & Ask | `site-and-ask` |
| 站点检查 | Site Check | `operations-insights`（内部路由名保留） |
| 图片处理 | Image Handling | `tools` |
| 站点画像 | Site Profile | 合并面板内的分区标题，不再是顶栏 |
| 智能提问 | Smart Ask | 合并面板内的分区；开发壳行为不变 |

与 Cloud Addon「检查」区分：Toolbox 始终称 **站点检查**（运营只读检查），不是连接器 readiness。

## URL（开发期，无旧链负担）

规范 `toolbox_tab`：`start` | `site-and-ask` | `operations-insights` | `tools`。
'
    '缺省或未知回落 `start`。不保留 `context` / `smart-ask` 顶栏别名。
'
    '`site_check_tab`、`toolbox_tool`、智能提问 pack 选择逻辑不变。


## 非目标（本笔记）

- ~~IA-2：折叠站点检查结果 L3 tab~~ → 默认只露行动清单/概览；分维 tab 收入「更多扫描视图」折叠。
- 单框自动选包 / 隐藏 pack 下拉。
- 改 REST 合同或 suggestion_only 边界。


## 默认隐藏 / 折叠（2026-10-22 产品决议）

UI-only；实现与文案保留，通过 `Settings::admin_ui_entry_hidden()` 不渲染：

| 条目 | 键 | 默认 |
| --- | --- | --- |
| 定时回顾「高级：本站检查设置」 | `scheduled_review_advanced_settings` | 隐藏 |
| 标记媒体回顾 | `flagged_media_review` | 隐藏 |
| 评论审核 / 分类标签 / 内链 三卡片 | `site_check_ops_review_cards` | 隐藏 |

站点档案 SEO / AEO / GEO / Boundaries 深偏好继续收在站点简介下方的 `<details class="npcink-toolbox__context-advanced">`，主摘要文案为 **Advanced / 高级**（智能提问 packs 不变）。


## 开始页下一步卡片（2026-10-22）

「开始」默认展示三张路由卡（`data-toolbox-start-next`）：**去提问** / **去检查** / **去改图**，分别深链到 `site-and-ask`、`operations-insights`、`tools`。不恢复已移除的 Site Check 促销块（`start-priority`）。隐藏条目策略不变。


## 开始页精简（2026-10-22）

去掉重复大标题「开始」与「下一步」层级；仅保留一句引导 + 三张路由卡（文案不出现 packs）。站长可见就绪仅 **站点画像 / Npcink Cloud**；原 Workflow readiness 行收入「支持详情」折叠。
