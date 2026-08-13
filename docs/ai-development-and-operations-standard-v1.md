# AI 开发与运行验收规范 v1

## 状态

Active。适用于 Toolbox 及其与 Toolkit、Core、Adapter、Cloud Addon、Cloud
协作的功能、契约、真实 WordPress 验收和运行故障处理。

## 核心原则

1. 先边界，后代码：先说明 owner、非目标、公共契约、文件范围、门禁和回滚。
2. 先证据，后判断：故障必须先复现、保存错误码/HTTP 状态/运行阶段和环境
   事实，不能用 UI 症状猜根因。
3. 先窄门，后全门：先跑能证明当前变化的最小检查，再跑默认完整门禁。
4. 运行时与产品面分离：本地 tunnel、M4、Cloud liveness、签名传输和
   provider 配置由相应 owner 维护，不在 Toolbox 内复制 runtime 修复。
5. 投影必须防漂移：只读元数据、文档、固定按钮表和真实 JS 流程必须由同一
   契约验收；新增或隐藏入口必须同时更新静态测试和翻译。
6. 不为通过测试扩张边界：不得用重试、队列、直接写入、隐式确认或本地
   fallback 掩盖 Cloud/Core/Toolkit 的真实失败。

## 标准变更流程

### A. 启动与变更包络

```text
git status --short --branch
读取 README、边界、架构、路线图、开发流程和相关 ADR
声明 focused module、owner、非目标、契约、文件、门禁、回滚
```

可见工作树 dirty、分支落后或属于其他任务时，从最新 `origin/master` 建立
独立 `codex/*` worktree，并立即执行：

```bash
git worktree lock --reason "codex:<task-id>" <absolute-worktree>
```

不得用 reset、stash、checkout 清理他人的工作。

### B. 边界审查

逐项回答：

- 本次行为是建议、计划、Core handoff，还是实际 WordPress 写入？
- 如果是写入，是否命中 ADR-006/ADR-010/ADR-011 的明确例外？
- 是否会新增 Ability/Workflow registry、approval store、queue、scheduler、
  provider key/log、indexing lifecycle 或 Cloud WordPress write？
- 是否复用了 Toolkit ability id、Core proposal、Adapter profile 和 Cloud
  scenario facade，而不是复制一份本地定义？
- 是否需要跨仓库契约矩阵？若需要，是否保留兄弟仓库 dirty 状态？

任何答案超出 Toolbox 边界时，停止实现并写 boundary note/ADR。

### C. 故障诊断六步

1. Reproduce：用最小真实请求稳定复现；
2. Preserve：保存响应 JSON、错误码、HTTP status、时间和非秘密运行事实；
3. Localize：区分 UI、REST、Addon transport、Cloud runtime、WordPress 和
   外部服务层；
4. Reduce：缩小到一个附件、一个请求、一个连接或一个能力；
5. Fix：修复拥有故障的层，不把 runtime 问题塞进产品层；
6. Guard/Verify：新增回归契约，跑真实端到端和默认门禁。

对于媒体预览类错误，必须分别检查：

```text
WordPress connection state
-> Cloud Addon readiness
-> base_url host/port
-> local tunnel listener
-> M4 remote /health/live
-> signed read
-> Toolbox preview route
```

### D. 真实 WordPress 与 Cloud 验收

所有需要本地 WordPress 的命令都必须复用项目 Composer 的 PHP 和 socket
参数：

```bash
WP_PATH="..." \
WP_CLI_PHP="..." \
WP_DB_SOCKET=".../mysqld.sock" \
composer smoke:<focused-target>
```

不要直接运行没有 `mysqli.default_socket` 的 WP-CLI 诊断来替代项目 smoke。
Cloud 真实验收必须明确 tunnel、M4 candidate、operation lock 和 owner；
没有 shared-runtime ownership 时只能做只读状态检查。

### E. 契约投影检查

新增/隐藏 editor flow 时，至少同步检查：

- `assets/editor-content-support.js` 的 `defaultVisible`；
- `includes/Ability_Surface_Metadata.php` 的 `surface/default_visible`；
- `docs/fixed-button-contract-table.json`；
- README、architecture、boundary、roadmap 和相关产品文档；
- `tests/run.php` 的数量、可见性、owner、write posture 检查；
- PO/MO 翻译条目。

默认入口数量必须由真实 UI 流程决定，不能从过时的聚合元数据反推。

## 门禁分层

### 文档/契约变更

```bash
php tests/run.php
composer check:platform-contracts
composer validate --no-check-publish
composer check:wporg
git diff --check
```

### 媒体优化或恢复变更

```bash
composer test:all
composer smoke:media-derivative-core
composer smoke:media-derivative-batch-execute
composer smoke:single-image-media-restore
```

### 跨仓库里程碑

```bash
composer quality:matrix:run
```

矩阵失败必须区分：真实 gate failure、dirty worktree、ahead/behind、缺少
兄弟 checkout、M4 blocked environment。不得把 dirty sibling 当成代码失败，
也不得清理其文件来“变绿”。

## 提交与发布

提交前：

```bash
git status --short
git diff --stat
git add <exact-files>
git diff --cached --stat
git diff --cached --name-only
git commit
git show --name-status --stat HEAD
```

完整里程碑应使用仓库标准：

```bash
composer pr:publish -- --title "<title>" --body-file <body-file>
```

PR 正文必须包含 Scope、Boundary、Verification、Risk；合并前确认必需检查，
不要绕过 branch protection 或隐式宣称 M4 已接受。

## 关闭清单

- [ ] 变更文件、门禁、失败项和跳过原因已记录；
- [ ] 分支 ahead/behind 已报告；
- [ ] 工作树无未解释修改或 untracked 文件；
- [ ] sibling dirty 文件未被清理或暂存；
- [ ] runtime owner、M4 candidate、tunnel 和 lock 已释放或明确交接；
- [ ] 文档索引和静态契约可发现本次决策；
- [ ] 后续阶段只有一个主攻方向，未同时启动多个高冲突实现。
