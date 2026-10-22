# 环境性能分析 Insight Pack 草案 — `environment_performance_insight_pack.v1`

> **Status:** DRAFT ONLY（供产品审阅；**未**授权实现、**未**开 PR）  
> **Date:** 2026-10-22（Asia/Shanghai）  
> **Aligned with:** [deidentified-assistant-boundary-v1.md](../deidentified-assistant-boundary-v1.md)（§6.3 已点名「环境版本 pack」意向）、既有 insight packs、`Insight_Pack_Answer_Guidance`  
> **Related drafts:** [page-quality-insight-pack-v1.md](page-quality-insight-pack-v1.md)

---

## 1. 目的

给站长一个**脱密、只读、建议型**的「环境与性能估算」能力切片：汇总本机 WordPress / PHP / 主题插件版本与若干**可本地观测的粗信号**（内存上限、对象缓存 drop-in 是否存在、调试开关、内容体量级等），用白话说明「环境是否偏旧、是否像容易拖慢后台的配置」，并给出最多 3 件**人工可做的检查或升级准备**——**不是**实验室测速报告。

产品意图：

- Pack = 后端能力切片，服务自由问（站点速度、排障、环境估算等）；**不**做成固定「一键测速」按钮。
- 正式 UI：智能提问**单框**；pack **对站长不可见**。
- `suggestion_only`；从不直写 WP；从不本地编假测速分数。

## 2. 非目标（明确不做清单）

| 不做 | 说明 |
| --- | --- |
| 不跑真实 Lighthouse / GTmetrix / WebPageTest / CI | 不安装探针、不调外部测速 API、不输出伪造「98 分」 |
| 不声称真实用户性能 / Core Web Vitals 实测 | 无 RUM 则禁止谈「你的 LCP 是…」 |
| 不自动升级 / 不改配置 | 不改 PHP、不改 wp-config、不启停插件、不写对象缓存 |
| 不上传密钥与路径 | 不出 DB 密码、盐、绝对路径、完整 `phpinfo` |
| 不上传原始错误日志 / 访问日志 | 最多错误**码级**或「调试开关是否打开」类布尔 |
| 不做本地假答复 | Cloud 失败 → 中文错误 + 可重试 |
| 不做主机厂商控制面 | 不登录 CDN/主机面板；只给站长「去主机面板看什么」的白话指引 |
| 不复活 `cloud_inspection` | 同边界 |

## 3. 命名与通道

| 项 | 取值 |
| --- | --- |
| 合同 / contract_version | `environment_performance_insight_pack.v1` |
| artifact_type | `environment_performance_insight_pack` |
| 内部 pack key（不对站长展示） | `environment_performance` |
| REST（拟定） | `GET …/environment-performance/insight-pack`；`POST …/environment-performance/insight-pack-qa` |
| Cloud ability | `npcink-toolbox/ai-content-support` |
| Hosted 合同 | `hosted_ai_content_support.v1` |
| 写姿态 | `suggestion_only`；禁止 WP 写与自动 Core proposal |
| 答法 | 扩展 `Insight_Pack_Answer_Guidance`（`PACK_ENVIRONMENT_PERFORMANCE` + 术语表） |

> 边界文档 §6.3 曾写「环境版本 pack」——本草案即该意向的正式命名候选；实现前需在定稿边界中升格条目（并处理「第三包门禁」政策）。

## 4. 采集什么 / 不采集什么

### 4.1 允许进入脱密包（有界环境快照）

**运行时（粗粒度）**

- WordPress 版本字符串（如 `6.6.2`）
- PHP 版本 `major.minor`（可带 patch；**不要** Zend 扩展完整清单）
- `memory_limit`、`max_execution_time` 的**字符串/整数原值**（配置级，非进程实测）
- `is_multisite` 布尔
- `wp_is_writable` 类信号：仅「上传目录是否可写」等布尔（若已有安全采集路径）
- HTTPS：home/siteurl 是否 `https`
- 调试：`WP_DEBUG` / `WP_DEBUG_DISPLAY` / `SCRIPT_DEBUG` 等**布尔**（不读 debug.log 内容）

**主题 / 插件（有界）**

- 当前主题：`stylesheet` slug + `Version`（不要主题目录绝对路径）
- 活跃插件：**上限列表**（建议 ≤40）：`slug` + `Version`；另给 `active_plugin_count`
- 可选：必须插件/常见缓存插件是否活跃的**布尔**（如 redis/memcached drop-in 文件是否存在——只布尔）

**体量级粗信号（非测速）**

- 已发布文章/页面数、附件数（聚合）
- 可选：`autoload` 选项近似体积（若能廉价计算；超阈值则标 `unavailable`）
- 可选：计划任务（cron）事件数量级
- 对象缓存：`wp_using_ext_object_cache()` 布尔；`object-cache.php` drop-in 是否存在

**明确标注**

- `measurement_kind=config_and_inventory_only`（或等价字段）——提示模型：这是配置/清单，**不是**实验室测速

### 4.2 明确不采集 / 不出站

- `wp-config.php` 全文、DB 凭证、盐值、API 密钥
- 服务器绝对路径、系统用户名、内网 IP
- 完整 `phpinfo()`、加载的全部 Zend 扩展表（除非未来另开极窄合同）
- 原始 PHP/Web 错误日志、access log、邮件队列正文
- 真实页面 HTML 响应时间序列、CrUX/RUM、Lighthouse JSON
- 插件私有 option 内容、许可证密钥
- 用户邮箱与登录会话

## 5. 抽样方式

- **不做内容分层抽样**；一次环境清单快照即可
- 插件列表：活跃插件按 slug 排序后 **截断**（保留 count 全量数字 + 列表上限）
- 生成 `generated_at`；可选 `sample_note`：若插件列表被截断，首行写「只列出了前 N 个插件名称（共 M 个在用），结论仅供参考。」
- 不把「慢」写成已测量事实；finding 用「可能偏旧 / 调试开着可能拖慢 / 无对象缓存」等**条件语气**

## 6. 回答形态（复用 Guidance）

同一套结构（caveat → 结论 → ≤3 项做什么/为什么/怎么做）：

- 禁止输出「你的网站得分 xx」「LCP=」等伪测速
- 禁止点名未在摘要中出现的主机商内幕数据
- 引导站长去：**主机面板看 PHP 版本**、**关掉前台调试显示**、**找专业缓存/主机支持**——不假装 Toolbox 能一键修好
- 术语表示例：
  - `memory_limit` → PHP 可用内存上限  
  - `object cache drop-in` → 对象缓存（一种加快重复查询的机制）  
  - `WP_DEBUG` → 调试模式（排错用，平时不宜对着访客开）  
  - `active_plugin_count` → 正在使用的插件数量  

默认内部问题示例：  
「根据这份环境与性能相关摘要，站长先检查哪 1–3 件事更合适？用大白话，不要假装做过测速。」

## 7. UX

与页面质量包相同目标态：

- **单提问框**；pack 名对站长不可见  
- 编排归属 Toolbox；本包适合「慢 / 报错 / PHP 版本 / 插件太多」类意图  
- 过渡期若出现在 select，标签用「环境与性能」而非合同 id  
- 回答可深链到：站点健康（若 WP 自带）、插件列表、主机文档——**不**深链到伪造测速页

## 8. 风险

| 风险 | 缓解 |
| --- | --- |
| 被理解成「官方测速产品」 | 合同字段 `measurement_kind` + Guidance 禁伪分数 + 文案「估算/清单」 |
| 泄露路径/密钥 | 合同测试黑名单；Collector 单测 |
| 插件列表过长撑爆 payload | 硬截断 + count |
| 版本建议过时或危险 | 只建议「查阅官方/主机是否支持」，不指定具体小版本补丁号除非摘要内已有 |
| 与边界「第三包门禁」及 §6.3 意向 | 实现前更新定稿边界；建议本包作为**第四能力切片**或「v1.1 环境包」显式登记 |
| 双包并发烧配额 | 编排一次只带一包；不与 page_quality 默认叠乘 |

## 9. 验证计划（实现后）

1. 合同测试：无路径、无密钥、无 log 正文；有 `measurement_kind`；插件列表 ≤ 上限  
2. 答法冒烟：无「分」「LCP」「PageSpeed」伪实测；有白话环境建议  
3. 失败路径：断 Cloud → 无本地假分数  
4. 权限：`manage_options`  
5. CLI/REST 手册镜像现有 insight-pack QA  
6. 证据：`~/npcink-qa-evidence/env-perf-pack-YYYYMMDD/`

## 10. 实现切片（预告，非授权）

- `Environment_Performance_Insight_Builder`（纯本地清单）+ REST + CLI  
- Guidance 扩展  
- 编排注册（隐形）  
- **不**引入外部测速依赖

---

*本稿为审阅草案。批准前禁止合入产品代码。*
