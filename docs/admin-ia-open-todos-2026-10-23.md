# Admin / 智能提问 — 开放待办清单（2026-10-23）

> 从 `#311`–`#322` 会话与 insight-pack 实作沉淀的**未关闭项**。本稿只记账，不授权开干；生产变更须用户点名。壳层见 [admin-ia-site-and-ask-v1.md](admin-ia-site-and-ask-v1.md)；包约定见 [insight-pack-dev-notes-v1.md](insight-pack-dev-notes-v1.md)。

状态：Active（记账）。合入 master 后若某项关闭，在本表改「状态」并链 PR，勿 silently 删行。

## 产品 / IA（Toolbox 本仓可做）

| 项 | 说明 | 状态 | 备注 |
| --- | --- | --- | --- |
| **L3 折叠** | 站点检查结果维度 tab（L3）仍展开；IA 树写明「留给后续折叠」 | 开放 | 顶栏已 3 Tab、检查已折入「站点与提问」；L3 另开切片 |
| **单框一问 / 自动选包** | 隐藏 pack 下拉、单框自动路由多 pack；多 pack 编排 | 开放 / 非本阶段 | 边界 §3.1 / §6.3 / §11.6；编排若做归属 Toolbox |

## 部署 / 环境（须用户决策）

| 项 | 说明 | 状态 | 备注 |
| --- | --- | --- | --- |
| **生产 Cloud 部署** | 本机 M4 ≠ 生产；生产 Cloud / 插件是否跟到四包 + IA 壳 | 待用户决定 | **未经点名不部署、不改生产、不标 stale** |

## 已知旁路（勿当回归缺口误报）

| 项 | 说明 | 状态 | 归属 |
| --- | --- | --- | --- |
| **year-0020 日期显示** | 日期类显示成 year-0020 等异常 | 旁路 / 已知 | 非本包必修；另跟 |
| **Adapter / WP AI 英文 toast** | adapter 连接页、WP AI success toast 仍英文 | 已移交 | **本仓不动**；跟接收方 |
| **Cloud orphan runs** | DB 残留 orphan / 卡住 run（Cloud AI 侧未清） | 旁路 | 约定环境 drain / 取证；**勿自行清生产 orphan** |
| **mid-sync 短暂 502/busy** | 同步中短暂 502 或 busy | 预期旁路 | 勿当硬失败；可重试 |

## 使用约定

- 新 agent 接手：先读本表 + admin-ia + insight-pack-dev-notes，再开分支。
- 「旁路 / 已移交 / 待用户决定」**不是**本会话未合代码的缺口。
- 关闭一项时：改状态、写 PR 号、必要时回写 admin-ia 或 insight-pack-dev-notes 的交叉句。
