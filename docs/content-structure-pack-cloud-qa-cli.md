# 内容结构摘要 → Cloud CLI 验证（第二包）

面向人与其它 AI：用**脱密内容结构 / SEO·内容健康摘要**（`content_structure_insight_pack.v1`）经 Addon 问 Cloud 一题。

产品边界：[deidentified-assistant-boundary-v1.md](deidentified-assistant-boundary-v1.md)。第一包（巡检摘要）手册：[insight-pack-cloud-qa-cli.md](insight-pack-cloud-qa-cli.md)。

**不要**把本包当成第二套对话产品；薄 UI 挂在 **站点资料（Site Profile）**，与 Site Check Ask **双轨**。

---

## 目的

| 要验证 | 不要做 |
| --- | --- |
| 本地拼装内容结构脱密包 | 不调用已暂停的 `cloud_inspection` |
| Addon 签名 → Cloud 归纳（`suggestion_only`） | 不写 WordPress、不建 Core proposal |
| 操作员/AI 可复现问法 | 不塞 Cloud 拒收字段（见下） |
| Cloud 失败只报中文错误 + 可重试 | **不做**本地假答复 |

通道：`hosted_ai_content_support.v1`（与第一包相同）。

---

## Pack 字段（出站 / 不出站）

**可出站（有界）：**

- `post_counts`：按类型/状态的聚合计数（含 draft 等数量，**不含**草稿标题/正文）
- `taxonomies`：category / post_tag 总量、空项、低频项、`top_terms`（名称 + count）
- `structure_signals`：抽样公开篇的 excerpt / meta / featured / 字数桶 / 新鲜度桶；最多若干**公开标题**样本（比率为原始样本率）
- `sampling`：按发布时间把已发布文章+页面分成最早/中间/最新三批，每批等间隔抽取（默认共 60 篇，filter `npcink_toolbox_content_structure_sample_limit` 可调，范围 6–120）；给出每批的年份范围、总体数 vs 抽样数、每批样本率，以及按总体加权的 `weighted_rates`。只用 ID 查询总体，正文只在本地数字数，不外发。答复首行提示形如「按发布时间分三批共抽查 60 篇（全站已发布 2000 篇），结论仅供参考。」
- `top_findings`：结构 / SEO 健康建议（suggestion_only）
- 操作员问题文案

**不得出站：**

- 文章/页面**正文**、修订差、私有草稿全文
- 评论原文、邮箱、IP、UA
- 密钥 / 签名材料 / 原始日志
- 非公开标题（`safety.non_public_titles_returned=false`）

本地为算 `word_count` 可读正文，但 pack / Cloud 载荷**不携带**正文。

---

## REST

| 方法 | 路由 | 说明 |
| --- | --- | --- |
| GET | `/wp-json/npcink-toolbox/v1/content-structure/insight-pack` | 只读拼包；不调 Cloud |
| POST | `/wp-json/npcink-toolbox/v1/content-structure/insight-pack-qa` | body `{ "question": "…" }`；本地包 + Hosted AI |

Cap：`manage_options`（`cap.toolbox.admin`）。实现：`includes/Rest_Content_Structure_Insight_Bridges.php`。

---

## 后台薄 UI（中文路径）

1. 左侧：**Npcink AI** → **流程工具箱**
2. 顶部页签：**站点资料**（Site Profile；**不是**「站点检查」，也不是「图片处理」）
3. **智能提问** → 选择「内容结构」 → 改问题 → **提问**

直达（magick-ai）：

`https://magick-ai.local/wp-admin/admin.php?page=npcink-toolbox&toolbox_tab=context`

与「本次站点检查」在同一个 **智能提问** 页用下拉切换；包不同，共享 hosted AI 通道。

硬刷新后验证：进行中态、Markdown 答复、忙位/429 中文提示。

---

## CLI

```bash
cd /Users/muze/gitee/npcink-workflow-toolbox
./scripts/content-structure-pack-cloud-qa.sh
./scripts/content-structure-pack-cloud-qa.sh "内容结构优先该处理什么？"
```

| Env | 含义 | 默认 |
| --- | --- | --- |
| `WP_PATH` | WordPress 根 | Local magick-ai |
| `NPCINK_CONTENT_STRUCTURE_QA_USER` | WP 用户 ID/login | `178`（可回退 `NPCINK_INSIGHT_QA_USER`） |
| `NPCINK_CONTENT_STRUCTURE_QA_QUESTION` | 问题 | 脚本默认中文问法 |
| `NPCINK_CONTENT_STRUCTURE_QA_OUT` | 结果 JSON | `tmp/content-structure-pack-cloud-qa-result.json` |

---

## 禁止塞给 Cloud 的字段

与第一包相同：`summary_prompt_mode`、`http_timeout_seconds`、`connect_timeout_seconds`。

---

## 给其它 AI 的最短路径

1. 读本页 + [deidentified-assistant-boundary-v1.md](deidentified-assistant-boundary-v1.md)  
2. 后台 **智能提问** → 选择「内容结构」，或跑 `./scripts/content-structure-pack-cloud-qa.sh`  
3. 排障带 Cloud `run_id`；遇 429 先清槽  
4. **不要**恢复 `cloud_inspection`；**不要**开第三包；**不要**合入未获用户确认的 PR  
