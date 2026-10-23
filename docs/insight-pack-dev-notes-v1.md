# Insight Pack 开发约定笔记 v1（2026-10-22）

> 从 `#289`–`#309` 实作沉淀的**操作约定**，给后续 agent / 人接手用。产品边界仍以 [deidentified-assistant-boundary-v1.md](deidentified-assistant-boundary-v1.md) 为准；各包契约以对应 `*-insight-pack-v1.md` 为准。本稿不另立政策。

## 1. 包是后端切片，不是站长产品壳

| 约定 | 含义 |
| --- | --- |
| Pack = 后端切片 | Builder + REST bridges +（可选）CLI + Guidance 术语；合同 id（如 `page_quality_insight_pack.v1`）**不对站长展示** |
| 正式 UI 现状 | 顶栏三 Tab（开始 / 站点与提问 / 图片处理）；智能提问在「站点与提问」内下拉选包；站点检查同页折叠。壳层见 [admin-ia-site-and-ask-v1.md](admin-ia-site-and-ask-v1.md) |
| 尚未做 | **单框一问 / 自动选包 / 多 pack 编排**（边界 §3.1 / §6.3 / §11.6：编排若做归属 Toolbox，本阶段不做） |
| 通道 | 一律 `hosted_ai_content_support.v1` + `write_posture=suggestion_only`；Cloud 失败只中文错误可重试，**不做本地假答复** |

四包对照（已接线）：边界 **§3.1**。

## 2. 写姿态与答法

- **`suggestion_only`**：不直写 WP；不自动建 Core proposal；最多可审阅 handoff 候选（既有治理链路）。
- **白话 Guidance**：扩展 `Insight_Pack_Answer_Guidance`（pack 常量 + glossary）；答复禁 snake_case 字段名、禁伪测速/排名/流量词；环境包用条件句（「可能拖慢」），禁断言式「会拖慢」。
- **环境包**：`measurement_kind=config_and_inventory_only`；无 Lighthouse/伪分数/密钥/路径/phpinfo/原始日志；勿在注释或字符串里写会被 wporg 扫描的 `wp-admin/includes/...` 路径字面量。

## 3. 自测与证据（本机，非生产）

| 项 | 约定 |
| --- | --- |
| 站点 | `magick-ai.local`（插件 symlink；WP-CLI 用户按站规，常见 `178`） |
| Cloud / 队列 | 需要真 Cloud 时用 **M4**；扩第三包前曾以 M4 `#1177` drain 为门禁，drain 验证后产品放开 |
| 证据目录 | `~/npcink-qa-evidence/<主题>-YYYYMMDD/`（json / md / log）；例如 `four-pack-hardening-20261022/` |
| 生产 | **未经用户点名，不改生产、不部署、不标 stale、不碰 `tmp/`** |
| 回归 | `composer lint:standards`（与 CI Static analysis advisory 同命令）+ `composer test:all`；对齐类警告用 `phpcbf`，勿改行为 |

各包 CLI 手册：`docs/insight-pack-cloud-qa-cli.md`、`content-structure-pack-cloud-qa-cli.md`、`page-quality-pack-cloud-qa-cli.md`、`environment-performance-pack-cloud-qa-cli.md`。

## 4. 卡住-run / 门禁（已发生过的事实）

- 边界曾约定：**卡住 Cloud run 未 drain 前不开第三包**。
- **2026-10-22：** M4 `#1177` drain 验证后产品显式放开；第三包（`#306`）与第四包（`#307`）已接线；四包 hardening（`#308`）；PHPCS 对齐（`#309`）。
- 本稿不提供生产 unlock 步骤；若再遇 stuck run，先在约定环境 drain / 取证，**不要**自行清生产 orphan 或改 Cloud。

## 5. 已知未做 / 旁路（勿当回归缺口误报）

完整记账见 [admin-ia-open-todos-2026-10-23.md](admin-ia-open-todos-2026-10-23.md)。摘要：

- 单框一问 / 自动选包未做；L3 结果维度 tab 待折叠（壳层 IA）。
- 生产 Cloud 可能仍旧（本机 M4 ≠ 生产）— **部署待用户决定**。
- Core handoff 英文、adapter 连接页 / WP AI 英文 success toast：已移交，本仓不动。
- year-0020 日期类显示问题、DB orphan runs（Cloud AI 未清）、mid-sync 短暂 502/busy：已知预期/旁路，非本包必修。

## 6. PR / 分支习惯（本切片相关）

- 从 `master` 开短分支；PR 正文 **Scope / Boundary / Verification / Risk**；检查绿后 **squash-merge**。
- 不 force-push `master`；不改 Toolbox 以外插件。
- github.com HTTPS 偶发不通时，可用 `gh` + `api.github.com` Git Data API 推送；SSH deploy key 可能绑其它仓，勿硬推。

## 相关文档

- [deidentified-assistant-boundary-v1.md](deidentified-assistant-boundary-v1.md) — 产品边界 / §3.1 四包对照表 / §11 政策
- [admin-ia-site-and-ask-v1.md](admin-ia-site-and-ask-v1.md) — Admin 壳层 IA（三 Tab、隐藏入口、检查→提问、降门槛）
- [admin-ia-open-todos-2026-10-23.md](admin-ia-open-todos-2026-10-23.md) — 开放待办 / 旁路记账
- [page-quality-insight-pack-v1.md](page-quality-insight-pack-v1.md) / [environment-performance-insight-pack-v1.md](environment-performance-insight-pack-v1.md) — 第三/四包契约
- [drafts/README-insight-packs-2026-10-22.md](drafts/README-insight-packs-2026-10-22.md) — 草案索引（正式稿以 `docs/*.md` 为准）
