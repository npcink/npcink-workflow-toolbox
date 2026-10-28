# 套餐升级后的推荐语料恢复 — 2026-10-28

状态：套餐升级后已部分恢复，M4 Docker 启动故障阻断续跑；不是全库恢复完成。
范围：用户授权升级套餐后重试文章入库；保持既有 Addon 签名投递与 Cloud
索引执行分工。仅补缺失的公开文章/页面，不变更 WordPress 正文或共享服务代码。

## 套餐核查与前次结论纠正

用户判断套餐限制可能造成 100 篇上限。只读核查发现：

- 关键历史运行 `run_a82e4030963549da81fd84609b047aaf`、
  `run_597e315eff254bf2aec302ed34e410ec` 的 `commercial_decision_events`
  均记录 `plan_version_id=free_v1`；其计划版本 `max_vector_documents=100`。
- 历史权益快照保留了 `free_v1`；当前订阅在 2026-10-28 03:49:50 UTC 更新为
  active `agency_v1`，计划版本文章向量额度 10,000。
- 原状态页的 `max_indexed_documents_per_site=10000` 是独立的站点运行限额，
  不能代表账号实际套餐额度。Cloud runtime 从商业层取得账号
  `vector_documents` 上限，入库时另做检查。

因此上一轮依据站点 quota=ok 排除套餐因素的判断不充分；“接受/入库 0”也不等于
“没有传正文”。当前代码的顺序是先按刷新 IDs 清理，随后才检查额度。账号中
尚有超过 100 条时，删除一批后仍可能超额，于是替换文档全部被跳过；到最后一批
清理出容量，才重新入库最多 100 条。这与历史逐批减少到 100 的形态一致。
历史请求正文和结果已不保留，故不补造逐批 skipped 数，也不声称排除了所有其他
历史影响因素。套餐限制是现有证据强支持的解释；先删除后检查仍是需修复的风险。

## 有界恢复方法

恢复前再次确认：WordPress 公开 post/page 共 1,999 条，Cloud 仅有 100 条，
缺失 1,899 条；旧媒体 69 条、历史隔离 64 条单独保留。

从本地新鲜来源清单与 Cloud 文档键差集中，只取缺失的来源。使用 Addon 原有
`collect_documents` 生成公开正文投影，先验证文档数与 ID 顺序完整，再经
`Npcink_Cloud_Site_Knowledge_Runtime_Bridge::dispatch_runtime` 的
`site_knowledge_sync.v1` 投递。运行仍由现有 Cloud worker 执行。

本次采用现有 refresh 的新增用法：携带完整 documents，`post_ids=[]`，没有
清理目标，避免在恢复过程中触发“先删旧数据”。仅传入差集中从未存储的键，
不覆盖已有公开文档、媒体或历史隔离数据。Addon 原有生命周期抑制与幂等逻辑
仍生效。这是一次性受监督恢复操作，不新增 Toolbox 索引器或产品后台队列。

先 5 条试投，之后按 25 条串行投递；每批终态都要求：

- `accepted_documents=indexed_documents=本批来源数`；
- `failed_documents=skipped_documents=skipped_due_to_quota=deleted_entries=0`；
- Cloud 活跃文档总数等于初始 169 加累计新增数。

任何数量不符、失败或配额跳过即停止，不用成功状态掩盖缺口。每批保留原始
run/result 和不含正文的 manifest 摘要。Cloud 使用 M4 上既有
`ollama-m4-embedding` / `qwen3-embedding:0.6b`；这是实际语料恢复，不是为制造
观测数据另行付费调用模型。

本地 Cron 临时 guard 未移除，也未改游标。恢复由本次操作主动驱动，不能因此
宣称将来的自动增量投递已恢复。没有 delete/rebuild、直接 SQL 写索引、迁移、
服务同步/重启或生产部署。

## 验收与后续

[已提交的脱敏断点回执](recommendation-corpus-recovery-evidence-2026-10-28.json)
保留了套餐绑定、61 个完成 run、来源指纹及未确认批次。

| 指标 | 重试前 | 已确认断点 |
| --- | ---: | ---: |
| 公开 post/page 总数 | 1,999 | 1,999（起始清单） |
| 已确认可检索公开文档 | 100 | 至少 1,605 |
| 覆盖率 | 5.00% | 至少 80.29% |
| 本次已确认补入 | 0 | 1,505 |
| 已完成批次 | 0 | 61 |
| 失败 / 配额跳过 / 删除 | — | 0 / 0 / 0 |

最后确认成功的 run 是 `run_f8e93081bdde4948ba329623fff90cf9`，报告活跃文档
1,674（1,605 公开 post/page + 69 媒体）。下一批 25 条的 run
`run_ad5e8b35be5b4fd098edd855348cf06b` 已接受，但结果读取中断；它包含在剩余
394 条未完成确认的来源中，不能算失败，也不能重复发新请求来替代状态核查。

约 04:11 UTC，M4 Docker VM 发生启动；本会话未发起重启。随后 Cloud 8010
和本机隧道 18010 均超时，Docker `_ping` 与 `docker ps` 无响应，主机 SSH/uptime
正常。VM 日志持续等待 vsock、disk、eth0、fs、resolv.conf，并报告
`vpnkit-data.sock` 缺失。只凭此不能确定为何发生启动，也不能断言文章索引丢失。
驱动在 240 秒读取期限后停止，未继续投递后续批次。

WordPress 全部 post/page 正文及修改时间的聚合指纹与恢复前一致。61 个完成 run
均无删除；原始 Cloud 行和向量的最终保留核验、完整来源差集、恢复后的两类推荐
探针尚未执行，不能把本断点计数冒充最终独立审计。

Cloud AGENTS 的共享主机变更授权规则适用；已经请求用户确认是否仅重启 M4
Docker Desktop、恢复现有容器后继续，不部署程序、不删除数据卷或索引。
获得授权或环境自行恢复后，先检查上述未确认 run、同站点当前键集和 worker
状态；再从差集续跑。若失败只按现有运行重试契约恢复，不能盲目重放有未知结果
的请求。最终必须补做完整覆盖和旧数据核验，再跑两种推荐意图。

覆盖恢复不等于推荐相关性已经提高；语义比较继续使用 Eval Lab 同版本样本。

仍需独立收口：Cloud 刷新先删后检查额度/输入的保护、账号与站点双层额度的
诊断展示、Toolbox 状态列表 80/1000 截断、临时 Cron guard 与增量投递验收。
Typecho/Astro 仍遵循已有跨平台边界，不在本次恢复中增加实现。
