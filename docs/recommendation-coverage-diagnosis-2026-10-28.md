# 推荐语料覆盖诊断与跨平台准备 — 2026-10-28

状态：只读诊断完成；语料恢复、刷新保护修复和第二 CMS 接入尚未实施。
这是[原站编辑器验收](recommendation-original-site-acceptance-2026-10-28.md)
的后续，不能代替推荐质量或恢复验收。

## 已核实的结果

`https://magick-ai.local/` 有 1,966 篇公开文章、33 个公开页面，共 1,999 条。
与同一 Cloud 站点的可检索 post/page 键逐一比较，交集只有 100 条，缺 1,899 条，
多余条目为 0；当前文章/页面覆盖率为 **5.00%**。SQL 清单与本地完整 WP_Query
均返回 1,999 条，没有发现当前枚举限额或查询钩子造成漏采。

核心证据已提交在[脱敏回执](recommendation-coverage-evidence-2026-10-28.json)，
包含计数、集合摘要、历史 run、快照和当前采集检查；不包含正文、密钥或签名。
集合摘要按类型及数字 ID 排序，对无末尾换行的 `type:id` 行计算 SHA-256。

| 统计口径 | 文档 | 片段 | 含义 |
| --- | ---: | ---: | --- |
| 可推荐公开文章/页面 | 100（99 post + 1 page） | 119 | 当前推荐文本池 |
| 活跃媒体 | 69 | 69 | 不进入文章推荐池 |
| 状态页活跃总数 | 169 | 188 | 上述两项之和，不是文章覆盖量 |
| 历史隔离记录 `post_status=stale` | 64 | 78 | 保留以供审计，不应重新启用来凑数 |
| 所有存储记录 | 233 | 266 | 包括历史隔离；类型计数求和使用此口径 |

64 条历史记录的隔离是既有资格修正，见
[Eval Lab 来源资格记录](https://github.com/npcink/npcink-eval-lab/blob/main/docs/link-recommendation-source-eligibility-v2-2026-10-17.md)。
它们不在当前 1,999 条公开来源集合内，不能解释这次缺口。
`has_stale_content` 比较修改时间与索引时间，和资格标记 `post_status=stale`
是不同含义；不能用前者为 false 推断后者不存在。

## 历史删除链路与证据边界

历史快照显示，2026-10-18 资格修正后仍有 2,068 条活跃文档（1,999 条公开
post/page + 69 条媒体）。10 月 19 日 06:51–12:10 UTC，一系列 `refresh`
将其降到 169。窗口内 17 个有删除的成功 run 中，16 个接受/入库文档均为 0，
失败文档计数均为 0；最后一个接受并入库 100 条。代表性证据如下，时间均为 UTC：

| 时间 | Cloud run_id | 接受/入库 | 删除条目 | 活跃文档变化 |
| --- | --- | ---: | ---: | --- |
| 10-19 06:51 | `run_a82e4030963549da81fd84609b047aaf` | 0/0 | 52 | 2068 → 2043 |
| 10-19 10:30 | `run_8c859cf0f82b460c850c3743cdd44f32` | 0/0 | 430 | 1868 → 1669 |
| 10-19 12:07 | `run_c275b566473a4f40af6b5a3b4b64eb31` | 0/0 | 432 | 469 → 269 |
| 10-19 12:10 | `run_597e315eff254bf2aec302ed34e410ec` | 100/100 | 432 | 269 → 169 |

`deleted_entries` 合计文档和片段，不能当作删除文章数。
后续 10 月 22 日批次刷新了这 100 条，没有补齐整个来源集合。

Cloud `app/domain/site_knowledge/service.py` 的 `sync` 先按 `post_ids`
删除旧条目，再处理传入的文档。缺少替换正文时，刷新可完成为“删除成功、入库 0”。
`tests/api/test_site_knowledge_runtime.py` 的
`test_targeted_refresh_prunes_missing_public_document` 明确测试空正文清理语义，
因此不能直接把所有空刷新改成错误：文章转私密或删除后的撤索引同样需要支持。

**已证实的是索引减少发生在哪些刷新 run，以及先删除、未补回的机制；
尚未证实当时上游为什么没有补回正文。** 这 17 个 run 的明文输入、加密执行输入
和结果正文均已不在运行记录中，不能还原具体 `documents` 数组、调用方或当时
WordPress 公开状态。当前代码和计数不够证明某个历史调用方有 bug，也不足以排除
当时来源暂时不可见、过滤或采集缺失。不能把“空正文请求”当作已恢复的原始事实。

## 当前恢复障碍及独立问题

- 当前 Addon 的私有纯采集方法 `collect_documents` 在无登录用户上下文中，按
  200 条分批读取全部公开来源，得到 **1999/1999**、缺失 0；没有发送刷新请求。
  当前公开资格和采集路径可以生成完整正文清单，但这不代表运输/索引已恢复。
- 本地已有临时 MU 文件 `000-npcink-local-validation-cron-guard.php` 定义了
  `DISABLE_WP_CRON=true`；健康检查确认 Cron 关闭、调和任务逾期。它阻碍当前自动
  投递，但没有证据表明它造成了 10 月 19 日的删除。未移除此文件，因为会同时
  放开其他任务。
- Addon 缓冲为 0、没有活动全量投递，但调和游标已经位于最新公开修改时间。
  `total_sent=2000` 是累计投递计数，不是 Cloud 确认覆盖。仅恢复 Cron 或看见
  “投递成功”不能保证重新遍历并修复所有旧文档。
- Toolbox 状态生产方允许比较最多 1,000 个 `post_ids`，但
  `Provider_Client::execute_site_knowledge_cloud_request` 调用通用
  `sanitize_payload`，递归数组上限为 80；实际 Cloud 回报
  `indexed_post_ids_requested=80`。返回 coverage 也使用此通用清洗器。
  本地完整比较检查会拒绝不匹配回执，这是缺少逐篇状态的独立原因，不能解释
  Cloud 丢失 1,899 条。修复需覆盖请求和响应两端、保留其他载荷限额。

## 环境与复核方法

- Toolbox `98ed7ae47e76a1f97ec04e91ee58e3a06a608209`；Addon
  `3e35df4b38dbcf304b0ca969b178b6b9ef615f49`；Eval Lab
  `84019e4fe6bb930198b2a66395ddb0ea70b2b3bd`。
- M4 接受的 backend 为 `1bc9435b5ce3de2b2094c7b8f1c27adfba0aa8ee`，
  `source_dirty=false`，来源 PR #1300；bundle SHA-256
  `1193be651e81d994cc200c2c0eabdfe8313ab01d24b3a83e062f53201b985757`。
  Cloud 本地开发目录存在其他会话修改，未使用它作为部署或修改目标。
- 经实际 WordPress → Toolbox → Addon 状态请求取得
  `run_c7eef308f0d54df0b2f560da3c1b304d`，再在 M4 同一站点运行记录中确认；
  站点 scope SHA-256 见回执。排除了隧道/另一站点身份混淆。
- WordPress 使用公开 `post/page`、`publish`、无密码条件，SQL 与 WP_Query
  对照；Cloud 使用 REPEATABLE READ / READ ONLY 事务，按
  `(source_type, source_id)` 对照 `publish` post/page；单独统计媒体和 stale。
  历史对照来自 `site_knowledge_index_job_metrics`、
  `site_knowledge_index_snapshots` 与 `run_records`，未解密或输出凭据。
- ignored 执行材料位于 `tests/artifacts/coverage-investigation-2026-10-28/`；
  本文与提交的 JSON 保留了核心结论，后续不能依赖 ignored 文件永远存在。
  本次未变更共享索引、Cron 配置、运行服务或 WordPress 文章；未独立审计
  外部向量库与 SQL 的逐条一致性，覆盖率表示 SQL 检索资格集合。

## 下一阶段顺序与完成条件

1. **先封住歧义（Addon / Cloud）。** 用现有投递与维护边界区分：有正文的更新、
   来源明确转私密/删除的撤索引、采集失败或不完整快照。先校验，再替换旧数据；
   缺正文且没有明确撤索引证据时保留旧数据并报错。保留正常下架能力，禁止把
   本轮诊断解释为已批准破坏性重建。测试完整批次、空/部分/无效批次、明确下架、
   重试和失败后旧数据保留。具体接口变更由 owner 另立契约。
2. **恢复完整来源投递（Addon / Cloud）。** 从新鲜的完整 1,999 条来源快照出发，
   通过现有有界公开刷新恢复，保留 64 条历史隔离和媒体；先小批验证，再扩展，
   比较每批预期键与实际 Cloud 入库键。处理临时 Cron guard 和游标时限于该恢复
   任务，不以直接 SQL 写索引或全站 delete/rebuild 替代。
3. **修正状态比较（Toolbox）。** 解决 80/1000 截断并覆盖响应列表、数量不符拒绝
   及 >1000 的有界采样说明。大站需要分页/完整覆盖证据，不把第一页当全站。
4. **再比较推荐效果（Eval Lab / Toolbox）。** 冻结新运行版本与语料键/正文摘要，
   重跑既有真实文章及有用正例/无关对照。报告来源覆盖、请求成功、非空候选和
   AI 判定相关性，分别验收正文内链与相关阅读；继续由开发 agent 处理分歧，
   用户只需正常使用反馈。覆盖增加本身不证明相关性提高。

恢复验收要求：当前公开来源缺失数归零（或逐项解释合法排除），历史隔离不复活，
批次失败不擦除无关/旧数据，同一来源键不串站；实际编辑器两种动作回归通过。
恢复和语义效果必须分别报告，不再用“run succeeded”代替覆盖验收。

## Typecho / Astro：有可复用基础，尚未可直接接入

遵循 Cloud 的唯一权威
[跨平台连接器边界](https://github.com/npcink/npcink-ai-cloud/blob/master/docs/multi-platform-connector-boundary-v1.md)。
现行 `platform_kind` 验证仍只接受 WordPress；这里是准备项，不新增第二 CMS
实现或公共接口，也不把 Toolbox 改成通用运行时。

| 层 | 已有基础 | 尚缺准备 |
| --- | --- | --- |
| Cloud 运行通道 | `cloud_connector_runtime.v1` 与 `wordpress_operation.v1` 分离；签名、站点隔离、run、幂等、结果与存储姿态可复用 | 第二 CMS 真实契约及验收；当前枚举/验证只有 WordPress |
| 对象身份 | connector `object_ref.object_id/object_revision` 是不透明字符串 | Site Knowledge 仍依赖数字 `post_id/source_id/current_post_id`、post/page/attachment、publish；尚无通用内容来源契约 |
| 检索与推荐 | Cloud 搜索/向量/排序职责集中，可保留同一执行核心 | CMS 内容投影、分类/可见性、自身排除、片段位置与 URL 的明确映射 |
| 本地应用 | WordPress 原生编辑器选择、插入、Undo、普通保存已验收 | Typecho 编辑器适配；Astro Markdown/MDX 及文件修订校验；不能复用 Gutenberg clientId/块类型 |

后续内容来源契约应明确稳定字符串身份、canonical URL、修订、公开可见性、
正文片段、快照完整性/分页与明确删除标记。不要只把 WordPress 字段改名，
也不要让漏传列表意味着全库删除。本轮仅记录要求，实际迁移留给 Cloud owner。

Typecho 需要本地插件负责权限、公开内容读取/变更钩子、签名、候选审阅和编辑器
应用，保存仍由作者在 CMS 完成。Astro 需要内容集合/文件路径与修订映射、
Markdown/MDX 安全位置定位及人工审阅的文件变更流程；是否由构建或 CI 触发
刷新须另定契约，密钥留在服务端，不在每次页面渲染时调用 Cloud。

推进顺序仍是先关闭 WordPress 的覆盖与恢复问题。Cloud 已有跨平台计划的首个
Typecho PoC 仅限标题建议、摘要、选中文字改写，不能直接宣称它包含文章推荐。
后续先验证一个薄适配器，复用一套 Cloud；不并行复制 Typecho/Astro runtime、
队列、能力/工作流注册表。用户当前不需要逐篇标注或同时测试多套平台。
