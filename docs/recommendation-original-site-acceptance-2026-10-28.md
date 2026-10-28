# 原站文章推荐验收 — 2026-10-28

## 结论与范围

原站恢复后，正文内链和相关阅读均完成真实 Cloud 候选的编辑器验收。
正文内链可以应用、撤销并经 WordPress 正常保存；相关阅读可以选择文章、
插入普通区块列表、撤销、保存并重载。原站 502 的环境阻塞解除。
本轮不修改产品运行代码、排序、模型配置或索引；不代表全库覆盖、
推荐准确率提升、人工采纳或 WordPress.org 新版本发布。

原站 `https://magick-ai.local/`，WordPress 7.1.3，Toolbox 激活源码为
`ab0255a120603dc5d5dc2ec3e7bf885fe0b243cb`（PR #335），本地已发布文章
1,966 篇。站点插件指向主检出目录。浏览器使用真实 Gutenberg 和 Cloud 请求，
没有候选响应替身；临时请求令牌仅绕过 Toolbox 查询缓存读取，不删除缓存。
所有持久化验证只针对可删除草稿；已发布源文不执行保存。

运行时报告 `internal_links.v2` / `related_content.v2`、
`reading_task_abstention.v2`、锚点资格 v3；向量模型为
`ollama-m4-embedding:qwen3-embedding:0.6b`，reranker 未启用。
托管源码 SHA 未在本轮状态响应中确立，因此这不是跨版本质量对比。

## 六次只读真实检索

每篇分别调用两个 intent；6/6 返回成功且缓存为 miss。
下表耗时是本机请求观测，不是服务 SLA；候选计数不代表正确率。

| 源文章 | 正文内链 ms / 候选 | 相关阅读 ms / 候选 | 实际目标文章 ID |
| --- | --- | --- | --- |
| 6624：VOID 主题使用文档 | 4808 / 3 | 717 / 3 | 3962、3868、2805 |
| 21776：Nicen Localize Image | 737 / 1 | 598 / 1 | 2927 |
| 22055：Query Monitor | 654 / 1 | 655 / 1 | 3487 |

目标分别为 Next.Mist、Kibou、使用帮助、特色图片开发、WordPress 调试模式。
VOID 的主题备选文章具有相邻阅读价值，但不等于 VOID 专属教程；图片本地化与
特色图片开发仅是宽泛相邻任务，不能凭运行时 `strong` 标签认定推荐充分准确。
Query Monitor 的调试目标可以形成有用的编辑正例。上述判断属于开发者评估，
没有独立人工 gold，不计算 precision/recall，也不宣称质量提升。

排障可使用原始 Cloud run：
`run_c839dcb885b647529b036fc52faf5e0b`（VOID 内链）、
`run_98e035f20e2242ccbbf9583573e0e46f`（VOID 阅读）、
`run_00d02b582a5b4ba2a3177a6c21fd9858`（Query Monitor 内链）。
CLI 未提供浏览器区块 client ID，不能用它的零可应用数代替浏览器 Apply 结论。

## 真实浏览器结果与失败分母

| 尝试 | 结果 |
| --- | --- |
| 相关阅读 1 | 草稿标题追加测试后缀，改变有界查询；HTTP 200 / 857ms / 0 候选，健康 abstention，未完成插入验收 |
| 相关阅读 2 | 保留原始标题/摘要/正文；851ms / 3 候选，选择 3962、3868，7 项编辑检查通过 |
| 相关阅读 3（最终脚本） | 880ms / 3 候选，选择相同两篇；8 项检查通过，含服务端反馈接收 |
| 正文内链 1 | 脚本默认 v1，与运行时 v2 不符；反馈回调未捕获断言导致中断，草稿和临时凭据随后精确清理 |
| 正文内链 2 | v2 / HTTP 200 / 1097ms / 1 候选；旧界面标题选择器超时，未完成编辑验收 |
| 正文内链 3 | v2 / miss / 1043ms / 1 候选；“数据库查询”→3487，应用 1、拒绝 0，原生草稿保存通过 |
| 正文内链 4 | v2 / miss / 1040ms / 1 候选；相同目标应用后撤销，区块序列及身份恢复，数据库未改变 |

保留全部 7 次浏览器请求尝试，其中 4 次完整验收通过。另有两次脚本启动错误
发生在检索前（CLI PHP 的 wp 路径、只读探针构造方式），不算 Cloud 请求。
不把修复后成功当作此前失败不存在，也不把脚本失败算成 Cloud 可用性失败。

相关阅读验证：插入仅改变可见编辑器，保存前数据库不变；撤销恢复组数量；
再次插入后原生保存完整草稿，重载区块序列一致；源文内容哈希不变；
零 JavaScript pageerror。测试草稿 282961、282962、282969 均已删除。
正文内链验证：选择不修改内容；Apply 不发送文章写请求；普通保存才写入当前草稿；
独立撤销运行不写数据库。成功保存、撤销运行分别获得 4/4 Cloud 反馈回执
（36299–36302、36307–36310），全部携带 `developer_smoke`，排除人工采纳统计。
测试草稿 282964、282965、282966、282968 均已清理。

Query Monitor 的旧正文有 6 个第三方图片资源连接失败；Toolbox/Addon 请求无错误。
这是原文外链资源问题，本轮不修图、不据此宣称浏览器零网络错误。
已检查截图中的实际内链和阅读列表。原始截图、响应、日志保留在忽略目录
`tests/artifacts/original-site-2026-10-28/`；本文件保留可随 Git 归档的关键事实。

## 覆盖范围是下一主项

只读状态 run `run_390e6c6f448147fda1818837e91c1050` 报告 ready：
`indexed_posts=169`、`indexed_chunks=188`；类型分项为 post 149、page 15、
attachment 69（合计 233），而本轮推荐候选池为 119 个片段。
这些是不同字段且尚未对齐，不能据此算出准确覆盖率，也不能认为全部
1,966 篇文章可被检索。配额报告正常（169/10000 文档、188/200000 片段，
无配额跳过）；这次证据不支持“配额已满”的解释。

同日后续修正：这里的 quota 是站点级限制，不能排除独立的账号套餐限制。
后续已查到关键历史 run 绑定免费套餐 `free_v1`（向量文档限额 100）；用户升级
到 Agency 后重试。原先依据此状态页排除套餐限制的判断不充分。见
[恢复记录](recommendation-corpus-recovery-2026-10-28.md)。

Addon change bridge 缓冲为 0，已配置并验证；同时报告 `wp_cron_disabled=true`、
`reconcile_overdue=true`。这只是排查线索，尚未确立根因。本轮未运行同步、
Cron、重建或改动共享 Cloud 服务。

下一阶段由 **Cloud / Addon** 对齐站点身份、可公开且可读的源文清单、
索引计数与筛选后检索池，确定遗漏原因并经现有所有者流程修复。
**Eval Lab** 在命名语料/运行时上复跑有用正例和空结果；**Toolbox** 复用本轮
编辑验收。验收指标为同口径的应收录/已收录/遗漏/排除原因，以及同样本的候选
命中与耗时；语义改进另报 AI 判断及其局限。用户只需正常使用并给最终反馈，
无需逐篇标注。覆盖事实未对齐前，不再开启阈值或 prompt 调整循环。

## 复现与经验

正文内链复用 `smoke:editor-internal-link-batch-browser`，设置
`NPCINK_RECOMMENDATION_VERSION=v2`、
`NPCINK_INTERNAL_LINK_FIXTURE_SOURCE_POST_ID=22055`、
`NPCINK_INTERNAL_LINK_REQUIRE_APPLY=1` 和
`NPCINK_INTERNAL_LINK_REQUIRE_FRESH_CLOUD=1`；省略 `POST_ID`，确保只建临时草稿。
加 `NPCINK_INTERNAL_LINK_NATIVE_SAVE_SMOKE=1` 验证保存；去掉该标志验证撤销。

相关阅读命令（仅本地 `.local`，必须使用新的证据目录）：

```sh
WP_PATH=/absolute/wordpress WP_BASE_URL=https://magick-ai.local \
WP_CLI_PHP=/absolute/php WP_CLI_BIN=/absolute/wp WP_DB_SOCKET=/absolute/mysql.sock \
SOURCE_POST_ID=6624 NODE_PATH=/absolute/node_modules \
SMOKE_ARTIFACT_DIR=/absolute/new-evidence-directory \
composer smoke:related-reading-real-site-browser
```

保留原标题、摘要、正文，测试身份写入回执而非查询文本；否则短文本预算会改变
检索。异步浏览器回调的验证失败必须回到主流程，保证 `finally` 清除临时登录、
缓存探针与草稿。将检索可用性、编辑安全、全库覆盖和语义质量分别报告；
页面可用、向量已用或标签为 strong 都不能替代后两项证据。

本轮本地门禁：`composer test:all`、`composer validate --no-check-publish`、
`composer check:wporg`、`composer lint:standards`、`composer analyse:php` 均通过；
静态契约 4,435 项通过，两份浏览器脚本 `node --check` 通过。
PHP 工具临时复用主检出的开发依赖，完成后移除依赖软链接。
