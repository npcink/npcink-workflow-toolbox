# Toolbox 阶段收尾与开发历史总结（2026-08-13）

## 状态

已完成并合并。本记录把本阶段的实现事实、故障诊断、验证结果和后续
开发方法整理为可复用的项目知识；它不是新的运行时契约，也不改变现有
产品边界。

## 本阶段完成的工作

### 1. 单图媒体恢复闭环

单图媒体工作台完成了从预览、确认、替换到恢复的闭环：

- Toolbox 从 Media Library 的单附件上下文进入工作台；
- Cloud Addon 负责签名传输，Cloud 负责衍生图运行时与短期产物；
- Abilities Toolkit 负责本地执行、备份、引用修复、校验和恢复；
- Core/Adapter 仍负责批量、后台、外部客户端和多对象写入的治理路径；
- 替换前自动备份，恢复前再次备份当前文件；
- 备份、MIME、文件指针、文章引用、历史记录和复制后字节均有失败补偿
  与并发保护。

Toolkit 侧完成提交 `f4ce469d7bcf98d700c9ec12eec00d1ea3af6347`，并已合并为
`6015706199b0153c64a5cdd42bc9543640e52f92`。Toolbox 侧完成提交
`e7e8dc4e8371fa3f0d4b6d31b5d25299b60ca2c4`，并已合并为
`85f35b20db60d123af437cbb5191bfc3b5d40537`。

### 2. Cloud 媒体预览 502 诊断与恢复

故障稳定复现为 Toolbox 预览 HTTP 502，错误码为
`cloud_runtime_request_failed`。逐层取证得到：

- Cloud connection 为 `configured_valid`，凭据槽位已就绪；
- readiness 的 signed transport 和 service liveness 为 unavailable；
- Cloud Addon 的 base URL 指向 `127.0.0.1:18010`；
- 本机该端口没有监听，M4 远端 `127.0.0.1:8010/health/live` 实际为 200；
- 因此根因是前台 M4 SSH tunnel 缺失，而不是 Toolbox REST 契约、媒体
  事务或 Cloud 运行时代码错误。

按 M4 共享运行规范只读检查了远端候选、健康状态和 operation lock，确认
没有抢占冲突后运行：

```bash
pnpm run m4:preview:tunnel -- --auto
```

恢复后 readiness 为 `ready`，预览恢复为 HTTP 202；真实
`composer smoke:media-derivative-core` 全部通过。该结论的运维动作属于
M4/Cloud 运行环境，不应把 tunnel、Docker、连接状态或 runtime 修复塞进
Toolbox。

### 3. 历史契约债务修复

`Ability_Surface_Metadata` 曾遗漏真实编辑器默认的来源材料起草、分类建议
和标签建议，同时错误地把文章音频标成默认入口。编辑器实际代码已经将
朗读和音频摘要设置为 `defaultVisible: false`。

修复已在 PR #108 合并（merge commit
`ea798a8e5c89b9d3d1a52e42f85a3e0c813aec1b`）：

- 只读 Workflow readiness 投影与真实七个默认编辑器流程一致；
- 文章朗读、音频摘要保留兼容调用，但明确为隐藏兼容；
- 同步架构文档、编辑器推荐逻辑、静态契约和 zh_CN 翻译；
- 未删除音频 REST/Cloud 路径，未改变写入或治理行为。

## 验证证据

本阶段使用的主要门禁：

- `composer test:all`；
- `php tests/run.php`：3494 项静态契约通过；
- `composer check:platform-contracts`：83 项通过；
- `composer check:wporg`；
- `composer validate --no-check-publish`；
- `git diff --check`；
- `composer smoke:single-image-media-restore`：真实 WordPress 22 项通过；
- `composer smoke:media-derivative-core`：预览、Core 提案、替换、恢复闭环通过。

中央 `composer quality:matrix:run` 的四个核心仓库门禁通过；当时整体矩阵
因 `npcink-cloud-addon` 和 `npcink-ai-cloud` 的既有用户 dirty 文件而退出
1。该类外部脏工作树被保留，未清理、未暂存、未修改。

## 当前产品结论

- `media_optimization_v1` 已达到当前 V1 阶段的冻结条件；
- 单图替换与恢复属于严格限定的 `strong_local_confirmation` 例外；
- 批量、后台、外部客户端、多对象和跨资源写入仍必须走 Core/Adapter；
- Toolbox 仍是固定按钮和审阅交接产品面，不是 workflow runtime、队列、
  审批库、provider 控制面或第二注册表；
- 下一项产品候选仍是媒体 ALT/caption review set，其后才是
  taxonomy/tag review set，再后是 internal-link review set。

## 遗留风险与后续动作

1. M4 tunnel 是前台运维依赖。应在本地验收 runbook 中明确“连接不可用”和
   “runtime 不健康”的分层诊断，避免误改 Toolbox。
2. Cloud、Cloud Addon 和 M4 共享状态必须继续使用唯一 owner；发现候选、锁
   或 dirty worktree 所有权不明时，保持只读并请求协调。
3. 下一开发阶段应先做 taxonomy/tag 审阅集的边界审查，确认仅推荐已有术语、
   新术语创建 fail-closed、选择后停在 Core 审阅，不实现直接写入或队列。

## 复盘

做得好的地方：先复现并保留错误证据，再逐层定位连接、Addon、Cloud 和
WordPress；跨仓库工作使用精确 SHA 和独立工作树；真实 smoke 覆盖了恢复
路径，而非只验证静态字符串；契约投影修复同步更新测试和翻译。

需要改进的地方：一次补充 WP-CLI 诊断没有按项目的 PHP/socket 启动方式执行，
产生了环境连接错误；以后所有本地 WordPress 取证必须复用 Composer 中的
`mysqli.default_socket` 与 `pdo_mysql.default_socket` 约定，并把命令本身的
环境失败与产品失败分开记录。
