# Article / OSS referenced-chat closeout — 2026-11-01

两个历史会话可以结束，已实现的任务没有遗留在未合并的文章/OSS 主题分支上。
这里的关闭结论包含明确延期和安全阻断，不表示全部历史设想、真实 OSS 写入、
普通作者价值或生产发布已经完成。当前核查只读 Git/安装引用，不重跑模型或部署。

## Change envelope

- Repository/module: Toolbox documentation, referenced-chat closeout.
- Change: reconcile the two actual chat histories with merged source, retained resources and maintained owner ledgers; correct the stale documentation index.
- Non-goals: product/runtime changes, OSS SDK integration, M4 deployment, production release, other chats' cleanup or local vision-overlay publication.
- Boundary/contracts: unchanged; author-only article review and Toolkit/Adapter storage preflight remain with their existing owners.
- Files: this audit, current-state, documentation index and known-issues.
- Gates: GitHub PR metadata, merge ancestry against fetched master, live remote heads, worktree/mount inventory, test:all, metadata/wporg, exact-diff local Qwen and protected CI.
- Rollback: revert these documentation files; installed product bytes and provider settings are untouched.

## 历史问题逐项去向

已读完「一键整理文章」(`01a13888-fd0f-7270-8f37-88e17b9a3e90`)
和「OSS 替换研究」(`019f12a8-7d15-7322-aca2-14c1fbf577a0`) 的实际回合。
两者本身是检查/建议会话；后续开发成果以当前仓库和验收记录为准。

| 历史事项 | 当前处置 / 维护入口 |
| --- | --- |
| 排版文档仍写未合并 | 已修正；[当前格式契约](editor-content-format-v1.md)记录 #141 和后续 v2 |
| 真实文章的原文、链接、多媒体保全 | [12-source 验证](article-format-preservation-acceptance-2026-10-30.md)已完成；原始文章未写入；不是所有输入都适配 |
| 两个已知格式验证拒绝 | #356 已解决受保护兼容；不修复原有无效 HTML，也不放宽文字保护 |
| 统一结果结构、语句/逻辑/成人语境提示 | [报告契约](article-review-report-v1.md)及[扩展契约](article-review-context-v1.md)已交付；只提示和定位，作者修改 |
| 中文质量、文风误报和正常语境 | [56 次评估](article-language-quality-evaluation-2026-11-01.md)保留原始误报/标签错误；后续文风控制通过，通用准确率仍未获证 |
| 真实安装、过期报告、引文偏移、正文隐私 | [最新验收](article-review-context-acceptance-2026-11-01.md)有安装浏览器、UTF-16/编辑代次、no-store 成功/失败审计 |
| 新闻样本超时 | Addon #340 增加接收余量；#361 独立单次补验成功；原首轮仍为 23/24 完成 |
| 20–30 篇真实文章的作者价值/采纳评估 | 未完成；普通作者反馈和长文一般质量仍由 Toolbox/Cloud 跟进，不把合成开发样本当独立 gold |
| OSS 风险识别、阻断、guard 转发和 mock | Toolkit 三个提交、Adapter 一个提交已在主线；见下表 |
| 真实 OSS/offload 读取、替换、备份、恢复、CDN 一致性 | 用户决定延期；[问题台账](known-issues.md)明确所有者/重启门；媒体优化入口仍暂停 |
| 生产和 WordPress.org 后续发布 | 未执行；本地安装、M4 开发验收与公开发布分开记账 |

## 提交、合并和远端核查

本轮直接读取 GitHub PR 元数据，以下 19 项均为 MERGED，且每个完整 merge SHA
都是重新 fetch 后对应 `origin/master` 的祖先。必需确定性检查通过；
历史取消的重复检查和条件跳过的生产检查不算新增通过。

| PR | Merge SHA |
| --- | --- |
| workflow-toolbox #141 | `68db923ee8d531b668852efa0e4117465334a85a` |
| workflow-toolbox #345 | `44c1e3d97ce4ff5a2f30d236822c7ab32f6cc7b6` |
| workflow-toolbox #346 | `147d2da6873fb638d5e3f290b413c41e2d722a5c` |
| workflow-toolbox #349 | `d5b153dfc7798a971abfab93238625c431e705bd` |
| workflow-toolbox #350 | `ad47196ec79a003b090d73a65bea2b74a73d88b1` |
| workflow-toolbox #351 | `f94618809d07a963864e3415e48de4b4e87de13f` |
| workflow-toolbox #352 | `63f6a0756361f2ae4a399eed4c3f10dde300a804` |
| workflow-toolbox #353 | `a20a5733c852e36c6a1dbc0dae9b90ddf7625753` |
| workflow-toolbox #354 | `9797229717d6e478685aa322407dee09b1ee6fec` |
| workflow-toolbox #356 | `79dee13ee9fa035f386b090888f9a38e4e0a70e0` |
| workflow-toolbox #358 | `5440f7a22b427dc23e91fef11afd4e6a3e43cf47` |
| workflow-toolbox #359 | `c4b2534b15b42036d8e48d8676021eb523384b45` |
| workflow-toolbox #360 | `b9bd81ba368fe18b4c165ab39799622ed970f695` |
| workflow-toolbox #361 | `55459384b569868ca2e4305192966ba67b38cd71` |
| cloud-addon #338 | `e8f7d7e748eeb3538e7937566ba670ad3f3bc775` |
| cloud-addon #339 | `b80de5ff19b246692186a7e0fc5379edc4bc6c45` |
| cloud-addon #340 | `7249a210f7653bccb0f712d6eb40ba452b2e9b9b` |
| ai-cloud #1331 | `7002bd56f080bf3fa045fbe3132e9b7a13ee093f` |
| ai-cloud #1335 | `b37618d830a08b19c141c39a160d3d071aed032c` |

| OSS 原始提交 | 已确认在远端 master 历史中 |
| --- | --- |
| Toolkit `02474db` | storage preflight guards |
| Toolkit `8515884` | mock OSS storage preflight proof |
| Toolkit `49b13d0` | shim 文档及示例 |
| Adapter `a480362` | storage guard 转发 |

五仓默认分支已刷新；Toolbox 的 HTTPS 远端头查询、Addon 的首次 fetch 超时，
随后分别通过同一 GitHub 仓库的 SSH 读取/刷新成功，未改永久 remote 配置。
通过 `git ls-remote --heads` 核对的以上 PR 主题分支，在本地和远端均已不存在；
Toolkit/Adapter 也没有遗留 OSS/storage 主题分支。

基线：Toolbox `55459384`、Toolkit `0b89dd2`、Adapter `11b7991`、
Addon `7249a21`、Cloud `f3843095`。Toolbox/Toolkit/Adapter 主工作区
干净、master 对 origin/master 为 0/0；Addon master 对 origin/master 也是 0/0。
本次新增文档 topic 的发布与清理在本回执之后独立完成，不把自身最终 SHA 写成循环证据。

## 分支、worktree 和安装保留项

文章/OSS 历史任务没有待清理的已登记主题 worktree。历史文章验收材料仍在
`~/.codex/session-archives/article-review-2026-10-31/`，恢复 refs 仍保留。19 个审阅 topic head 另存于各仓
`refs/archive/article-oss-20261101/pr-*`；本轮 Git 元数据归档在
`~/.codex/session-archives/article-oss-closeout-2026-11-01/`。
本轮核对实际站点插件 symlink：Toolbox、Addon 都指向各自
`/Users/muze/gitee/` 主 checkout，不指向本轮临时 worktree。

不能宣称五仓的所有分支、worktree、未提交改动都已清空：

| 保留项 | 当前事实 / 所有者与下一步 |
| --- | --- |
| Addon 本地安装 | 干净 detached `ae0a3d4`，比合并 master 多 1 个 vision 等待补丁；恢复 ref `refs/local-install/20261101-context-review-final-vision` 存在。属于已有本地安装例外，保持本地、不代为合并/推送 |
| Addon 其他工作区 | audit-fixes 和 local-vision-timeout 仍存在且干净；由原会话决定合并/清理 |
| Toolbox content-loop/site-answer 分支 | 属于其他会话；其中本地 acceptance 分支有 ahead 1 提交。其受锁 source-identity worktree 路径缺失，由[原验收所有者](existing-content-loop-acceptance-2026-10-30.md)先查恢复材料/安装引用 |
| Cloud admin 工作区 | 主 checkout 有 2 个未跟踪目录；admin-services-vector-fixes 工作区有 11 个已跟踪改动和 3 个未跟踪项，且受锁。由 admin 会话处理，不能混入本任务 |
| Cloud 其他登记工作区 | admin、M4 ops 等仍在；legacy-image-limitation 和 vision-timeout 登记路径缺失且受锁，由原会话核对后处理，不解锁或 prune |
| 既有被保护任务工作区 | 历史回执曾记录应用保护而保留；当前 Git inventory 不作为删除未登记/应用资源的授权 |

这些是明确保留/交接项，不是文章/OSS 代码丢失，也不是全局清理完成。
本轮只归档自己新建的辅助 worktree、清理自己已合并的 topic。

## 工作审视与可复用经验

原定目标是让两个旧会话的问题、源码和材料能独立追溯，结束会话后仍能接续。

- [x] 实际聊天、19 个 PR、4 个 OSS 原始提交、远端头、登记 worktree 和安装引用已核查。
- [x] [文章交付规范](article-review-delivery-lessons-v1.md)已积累边界、失败、坐标、隐私、安装、验收与清理经验。
- [x] OSS 契约已落在 Toolkit 的 [shim 规范](https://github.com/npcink/npcink-abilities-toolkit/blob/master/docs/oss-storage-compatibility-shim.md)、示例、public-api 和 third-party guide；Adapter 文档记录只转发 guard。
- [x] 本轮补齐统一收尾入口及 OSS 后续所有者。
- [ ] 真实 OSS 操作、独立作者价值和公开发布继续按台账保留，不算已实现。

| 级别 | 具体问题 | 方法原因 | 改进 |
| --- | --- | --- | --- |
| 应当改正 | `docs/README.md` 在 #359 合入后仍写逻辑/风险未启用 | 新验收与旧索引没有一并反查 | 本轮修正；收尾搜索相反状态描述，保留带日期的历史正文 |
| 必须避免 | 用三个干净 master 推断 Cloud/Addon 全部资源干净 | 核查范围与结论范围不一致 | 分开报告任务 topic、主 checkout、辅助 worktree、安装 overlay 和其他所有者 |
| 必须避免 | 把 OSS mock 放行当作真实远端写入证明 | 把契约可组合性与基础设施行为混为一谈 | 真实插件逐项证明 read/write/backup/restore/cache；没有证据的 mode 继续 blocked |

下次从本审计、[问题台账](known-issues.md)和最新验收进入。文章以普通使用反馈
触发有界修正；OSS 先由站点所有者指定真实插件/测试媒体并验证只读存储事实，
再由 Toolkit/Adapter/Core 所有者设计受治理写入验收。保持现有暂停与人工修改边界。
