# Insight Pack → Cloud CLI 验证（MVP）

面向人与其它 AI 的本机验证手册：用**脱密巡检摘要**（`site_ops_insight_pack.v1`）经 Addon 问 Cloud 一题，确认「脱密包 → 建议」链路是否值得做成产品面。

产品边界（MVP = 仅巡检摘要一包、suggestion_only、不写 WP）：见 [deidentified-assistant-boundary-v1.md](deidentified-assistant-boundary-v1.md)。Site Ops 契约背景：[site-ops-cloud-analysis-contract.md](site-ops-cloud-analysis-contract.md)。

**MVP 薄 UI 已有（`#289` 已合 master）：** 站点检查 → 当前检查 → **关于本次检查提问**。完整对话壳仍未做。验证也可用 REST + CLI。产品边界见 [deidentified-assistant-boundary-v1.md](deidentified-assistant-boundary-v1.md)（已定稿）。

---

## 目的 / Purpose

| 要验证 | 不要做 |
| --- | --- |
| 本地拼包是否够用 | 不调用已暂停的 `cloud_inspection` / `analyze-site-ops` |
| Addon 签名 → Cloud 归纳 | 不写 WordPress、不建 Core proposal |
| 操作员/AI 可复现的问法 | 不塞会被 Cloud 拒的额外字段（见下） |

通道：`hosted_ai_content_support.v1`（content-support），**不是** Site Ops Cloud detail。

---

## REST（只读拼包）

- **Route:** `GET /wp-json/npcink-toolbox/v1/site-ops/insight-pack`
- **QA Route:** `POST /wp-json/npcink-toolbox/v1/site-ops/insight-pack-qa`（body `{ "question": "..." }`；`manage_options`；本地拼包 + Hosted AI content-support，与 CLI 同路径）
- **Admin UI:** Npcink AI → 流程工具箱 → **站点检查** → **当前检查** → **关于本次检查提问**（不要去「图片处理」）
- **Cap:** `manage_options`（注册为 `cap.toolbox.admin`）
- **行为:** 本地 `Site_Ops_Snapshot_Collector` + `Site_Ops_Insight_Builder`；**不调 Cloud**、不写站
- **契约:** `contract_version = site_ops_insight_pack.v1`，`write_posture = suggestion_only`
- **实现:** `includes/Rest_Site_Ops_Insight_Bridges.php`（由 `Rest_Controller` 注册）

本机若 `-u 登录名:密码` 401，见下方鉴权说明；WP-CLI `rest_do_request` / 本脚本更稳。

---

## 脚本路径

| 文件 | 作用 |
| --- | --- |
| [`scripts/insight-pack-cloud-qa.sh`](../scripts/insight-pack-cloud-qa.sh) | 推荐入口：设 Local PHP/socket、默认 admin、写结果 JSON |
| [`scripts/insight-pack-cloud-qa.php`](../scripts/insight-pack-cloud-qa.php) | `wp eval-file` 主体：拼包 + `execute_runtime` |

---

## 后台薄 UI（内部试用）

**中文点击路径（面板不在「图片处理」里）：**

1. 左侧：**Npcink AI** → **流程工具箱**
2. 顶部页签点 **站点检查**（不是「图片处理」，也不是「设置」）
3. 站点检查内再点 **当前检查**
4. 页面上方卡片 **关于本次检查提问** → 改问题 → 点 **提问**

直达链接（magick-ai）：

`https://magick-ai.local/wp-admin/admin.php?page=npcink-toolbox&toolbox_tab=operations-insights&site_check_tab=current-check`

确认本机在 `#289` 分支（magick-ai 插件目录是本仓 symlink）。看答复与 `run_id`（仅建议，不写站）。答复区会把 Markdown（标题 / 列表 / **加粗** / `代码` / 链接）渲染成可读排版；HTML 先转义，不含原始 `**` 纯文本。若 Cloud 返回并发 429：等挂起 run 释放后重试。

验证 Markdown 展示：硬刷新后台（必要时清缓存）→ 提问 → 确认加粗/列表正常、没有露出 `**`，且恶意 HTML 不会执行。

## 怎么跑

在仓库根（本机 Local 站点默认 magick-ai）：

```bash
cd /Users/muze/gitee/npcink-workflow-toolbox
./scripts/insight-pack-cloud-qa.sh
./scripts/insight-pack-cloud-qa.sh "站点优先该处理什么？"
./scripts/insight-pack-cloud-qa.sh "问题" "/Users/muze/Local Sites/magick-ai/app/public"
```

环境变量（可选）：

| Env | 含义 | 默认 |
| --- | --- | --- |
| `WP_PATH` | WordPress 根 | `/Users/muze/Local Sites/magick-ai/app/public` |
| `WP_CLI_BIN` / `WP_CLI_PHP` / `WP_DB_SOCKET` | Local WP-CLI | Homebrew wp + Local PHP 8.2 + 站点 socket |
| `NPCINK_INSIGHT_QA_USER` | WP 用户 **ID 或 login** | `178` |
| `NPCINK_INSIGHT_QA_QUESTION` | 问题文案 | 脚本内默认「优先 1–3 件事…」 |
| `NPCINK_INSIGHT_QA_OUT` | 完整 JSON 输出路径 | `tmp/insight-pack-cloud-qa-result.json` |

Stdout/stderr 会打印 `pack_run_id`、Cloud `run_id`、答案摘要；完整载荷见 OUT。

联调排障优先用 Cloud **`run_id`**，不要指望本地 prefix 能直接 grep Cloud 日志。

---

## 鉴权（magick-ai）

- WP **登录名** `2` / 密码 `2` 对应的管理员 **用户 ID 是 `178`**（ID=`2` 往往是 subscriber）。
- 脚本默认 `--user=178`。Basic auth `-u 2:2` 对 REST 常 **401**；请用 cookie 登录、Application Password，或 WP-CLI（本脚本）。
- 需要 `manage_options`。

---

## 禁止塞给 Cloud 的字段

Cloud `RuntimePayload` 为 `extra=forbid`。以下键 **不要** 放进 runtime 请求（Addon 本地校验可能放行，转发 Cloud 会失败）：

- `summary_prompt_mode`
- `http_timeout_seconds`
- `connect_timeout_seconds`

本脚本已刻意省略；正式接线时同样收口。

---

## 429 / 并发槽

站点常为 `max_active_runs=1`。若返回商业并发 / `commercial.concurrency_exceeded` 或挂起 run 占槽：

1. 等槽位释放，或在 Cloud 侧结束挂起 run  
2. 再重跑脚本（PHP 侧会对部分并发错误短重试）

这不是字段错误。

---

## Pause 与写边界

- **`cloud_inspection` 仍暂停**（`Settings::$paused_cloud_features`）。不要走 Admin「Cloud detail」/`analyze-site-ops` 做本验证。
- 本链路：本地 insight-pack + **Hosted AI content-support**。
- **`suggestion_only`**：建议仅供人工复核；`direct_wordpress_write=false`；不自动建 Core proposal。
- SEO/AEO/GEO discoverability 暂停与本 MVP 无关；对齐时默认保持暂停。

---

## 临时输出路径

| 路径 | 内容 |
| --- | --- |
| `tmp/insight-pack-magick-ai.json` | 仅拼包样例（REST/WP-CLI） |
| `tmp/insight-pack-cloud-qa-result.json` | CLI 试问完整结果（含 Cloud 答复） |

`tmp/` 一般不入库；本地可留作对照。

---

## 给其它 AI 的最短路径

1. 读本页 + [deidentified-assistant-boundary-v1.md](deidentified-assistant-boundary-v1.md)  
2. 优先用后台 **站点检查 → 当前检查 → 关于本次检查提问**，或在当前 master 跑 `./scripts/insight-pack-cloud-qa.sh`  
3. 排障带上 Cloud `run_id`；遇 429 先清槽再跑  
4. **不要**恢复 `cloud_inspection`；**不要**合入未获用户确认的 PR  
5. 不要把 `summary_prompt_mode` / timeout 字段塞进 runtime payload  
