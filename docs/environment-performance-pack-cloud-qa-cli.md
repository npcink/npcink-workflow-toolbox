# 环境与性能清单 → Cloud CLI 验证（第四包）

面向人与其它 AI：用**脱密环境清单**（`environment_performance_insight_pack.v1`）经 Addon 问 Cloud 一题。

产品边界：[deidentified-assistant-boundary-v1.md](deidentified-assistant-boundary-v1.md)。规格：[environment-performance-insight-pack-v1.md](environment-performance-insight-pack-v1.md)。

**不要**把本包当成「一键测速」；`measurement_kind=config_and_inventory_only`。后台入口在 **智能提问 → 环境与性能**。

---

## 目的

| 要验证 | 不要做 |
| --- | --- |
| 本地拼装环境/配置清单脱密包 | 不调用已暂停的 `cloud_inspection` |
| Addon 签名 → Cloud 归纳（`suggestion_only`） | 不写 WordPress、不改 wp-config、不建 Core proposal |
| 操作员/AI 可复现问法 | 不塞 Cloud 拒收字段；不上传密钥/路径/phpinfo |
| Cloud 失败只报中文错误 + 可重试 | **不做**本地假答复或伪测速分数 |

通道：`hosted_ai_content_support.v1`（与既有 Ask 包相同）。

---

## REST

- `GET /wp-json/npcink-toolbox/v1/environment-performance/insight-pack`
- `POST /wp-json/npcink-toolbox/v1/environment-performance/insight-pack-qa` body: `{ "question": "…" }`

## CLI

```bash
scripts/environment-performance-pack-cloud-qa.sh
scripts/environment-performance-pack-cloud-qa.sh "根据这份环境与性能相关摘要，站长先检查哪 1–3 件事更合适？用大白话，不要假装做过测速。"
NPCINK_ENVIRONMENT_PERFORMANCE_QA_OUT=/tmp/env-perf.json scripts/environment-performance-pack-cloud-qa.sh
```

默认 WP 路径与用户与其它 insight-pack CLI 相同（Local magick-ai / user 178）。
