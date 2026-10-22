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

- IA-2：折叠站点检查结果 L3 tab。
- 单框自动选包 / 隐藏 pack 下拉。
- 改 REST 合同或 suggestion_only 边界。
