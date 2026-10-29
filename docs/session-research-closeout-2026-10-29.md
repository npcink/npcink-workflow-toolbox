# 推荐与模型研究：当前会话关闭审计 — 2026-10-29

状态：已交付工作与后续归属核对。本报告是源码/文档收尾，不是生产部署或全部历史问题清零的证明。
本报告自身合并、专题分支删除和 managed worktree 归档须在交付末尾核对；最终本地操作收据另存主检出忽略目录。

## 关闭结论与范围

本会话的推荐改进、原站恢复、语料补齐、刷新安全和模型研究已有主线代码、数据及文档。
用户可以在本报告发布并完成本会话 worktree 收尾后关闭会话；未完成产品方向及条件研究已保留交接，
无需依靠聊天记忆继续，也不要求用户逐篇标注。

核对范围：本会话附带的 48 个 PR、现有五会话历史审计、当前本地 Git 和远端 live heads。
本次重新读取原五会话最近 turn，未逐条重读全部历史；早期完整讨论的核对沿用
[2026-10-26 五会话审计](session-closeout-audit-2026-10-26.md)，不把当年的状态当当前运行证据。
“已合并”按 squash 后的 merge SHA 判断，不能拿原专题 SHA 判断漏合并。

## 代码与文档交付

下列 PR 均为 GitHub `MERGED`；逐个确认其 merge SHA 是已核对默认分支的祖先，
对应 48 个 head 分支在远端均不存在。逐项 merge SHA 与核对时间见
[精简源码回执](session-research-closeout-evidence-2026-10-29.json)。本报告的新 PR 不计入这 48 个。

| 仓库 | PR 数 | 已合并 PR |
| --- | --- | --- |
| npcink-workflow-toolbox | 15 | #328、#329、#330、#331、#332、#333、#334、#335、#336、#337、#338、#339、#340、#341、#342 |
| npcink-eval-lab | 28 | #123、#124、#125、#126、#127、#129、#130、#131、#132、#133、#134、#135、#136、#137、#138、#139、#140、#143、#144、#145、#146、#147、#148、#149、#150、#152、#153、#154 |
| npcink-ai-cloud | 4 | #1294、#1298、#1300、#1304 |
| npcink-cloud-addon | 1 | #335 |

这里不声称所有曾尝试的代码都合并：已停止的 OCR 门禁实验继续按五会话审计保留恢复 refs，
不因清理需要把废止尝试送入主线。源码合并也不等于 WordPress.org 发版或生产已更新。

| 主题 | 已完成的内容与证据 |
| --- | --- |
| 正文内链与相关阅读 | 分别服务正文锚点选择和阅读列表选择；候选供作者挑选，Apply/Undo 与原生保存有[原站验收](recommendation-original-site-acceptance-2026-10-28.md)；不自动插入或发布，不因本轮重排研究删除选项 |
| 语料覆盖与套餐诊断 | 账号套餐与站点 quota 分开核对；升级后恢复 1,999/1,999 公开来源；[最终恢复回执](recommendation-corpus-recovery-final-evidence-2026-10-28.json)保留中断与修复，不是零失败历史 |
| 后续刷新和自动增量 | [刷新安全](recommendation-refresh-safety-2026-10-28.md)、[知识库限定 Cron](recommendation-knowledge-cron-recovery-2026-10-28.md)、[1,000-ID 状态边界](recommendation-status-bounds-2026-10-28.md)已交付；各项仍以其记录版本为准 |
| Laya、重排和同行方法 | [Eval 研究交接](https://github.com/npcink/npcink-eval-lab/blob/main/docs/reranker-research-handoff-2026-10-29.md)保存官方/开源用法、路径差异、停试理由、调用来源及后继消费者 |
| 六题支撑片段试验 | [报告](https://github.com/npcink/npcink-eval-lab/blob/main/docs/site-knowledge-passage-rerank-screen-2026-10-29.md)：36 组有效，五题向量基线已命中 Top1，重排未增加覆盖；额外中位数 2.05 秒，无答案材料仍可获约 0.976 高分 |
| 经验和开发方式 | [Eval 开发规范](https://github.com/npcink/npcink-eval-lab/blob/main/docs/evaluation-development-practice.md)保留小试预算、冻结对照、失败回执、共享资源与证据保全；AI 主导开发，用户正常使用反馈并最终决定 |

## 未结项、停止项及后继负责人

以下不是当前会话漏提交的代码，也不在本轮重启研究；统一从 [Known Issues](known-issues.md)及其 owner 文档发现。

| 事项 | 状态、负责人及下一步 | 完成证据或重开条件 |
| --- | --- | --- |
| 推荐实际相关度与当前 8B 链路 | Cloud 当前模型/索引任务负责验收，Eval 负责同口径证据，Toolbox 负责展示和编辑回归 | 在当时实际 revision/语料上验证真实检索；本次 0.6B 人工小池不代表新 8B 索引；本审计不复测共享运行时 |
| 重排完整 logits、量化数值等价 | 未验证；Eval 记录、Cloud 为潜在消费者；当前无产品接入 | 实际出现“池内有支撑却排得太后”等问题，再提出固定输入、预算和停止线；不自动安装服务或改共享主机 |
| Laya 新旧路径等价与其他用途 | 旧摘要/分类未达质量条件，已按停止线结束；新路径最小探针不能推翻旧结果；保留条件研究 | 有真实短请求、少量选项的消费者及新证据后再评估；不为模型造功能 |
| 历史审查 HTTP 400、独立 Laya 服务 busy/503 | 根因没有因此修复；旧服务用途已停止，云端审查按用户决定退役；后继代码采用本机 Qwen 脚本及确定性 CI | 旧服务重新成为实际所需时再排查；不为收尾重开云端审查或无边界重试 |
| 文章辅助统一检查、站点效果复测 | Toolbox/Cloud 的产品后续，按现有 Known Issues 保留；不是当前已交付功能 | 先确定具体报告/证据契约、真实验收和边界；复用现有入口，不恢复暂停功能或新开 pack |
| Typecho/Astro 接入 | 仍为后续平台适配，不等于 WordPress 展示层已完成迁移 | 由 Cloud 与对应 CMS 适配所有者定义鉴权、来源、展示/保存及验收；见[跨平台差距](recommendation-coverage-diagnosis-2026-10-28.md) |
| 全量自动 reconciliation、其他 Cron 与存储故障窗口 | Cloud/Addon 与本地环境所有者继续维护；限定增量成功不能证明所有后台流程可靠 | 现有 Known Issues 的完整 sweep、其他 16 组 hook、并发缓冲和 SQL/向量部分失败边界仍有效 |

## 分支、worktree 与原始证据保全

核查时 Toolbox 只有干净同步的 master 与本会话 detached managed worktree；Eval 只有干净同步的 main。
两仓远端只有各自默认分支。本次收尾文档另建从最新 master 出发的专题分支，合并后删除。

Cloud 另有其他会话 worktree、分支及主检出的修改/未跟踪目录；Addon 保留有独有提交的本地
`master` 和 `docs/playground-bootstrap-flake-note`。这些不属于本会话，未重置、删除、解锁或合并。
所以可以说“当前会话收尾”，不能说“整个 Cloud/Addon 所有工作区都已清空”。

本会话 managed worktree 中 334 个测试证据文件（14,103,710 字节）已复制到主检出
`tests/artifacts/session-closeout-2026-10-29/link-recall-quality-artifacts/`，逐文件 SHA-256/链接目标一致；
清单为同级 `evidence-preservation.json`。Eval 的原始正文、向量、响应仍在该仓 ignored generated 目录，
六题远程进程和 scratch 已按原试验收据清理。`vendor/` 是可重装依赖，不需随证据备份。

WordPress `magick-ai` 与 `magick-toolbox` 插件 symlink 均指向现存主检出，不依赖待归档 worktree。
归档须使用当前会话 app managed-worktree 工具；它的 Git snapshot 不含 ignored 文件，故先做上述外部保全。
本地 ignored 原始证据及 retired refs **不等于云端备份**；Git 保存的精简报告/回执已进入远端，
原始材料不能只靠聊天归档或 GitHub 重建，后继删除主检出前须先另行保全。

## 收尾与开发经验

- 区分已修复、未交付设计、停止的实验及条件研究，关闭会话不等于技术问题全部解决。
- 数据归 Cloud，Toolbox 展示并承接作者可见选择；用户选择与正常保存边界不因模型接入而改变。
- 用法核对、任务适配、样本天花板及实际收益分别记录；接口成功或高分都不是质量证明。
- 冻结的小试无新增收益就停，不追加题目追分；模型/索引改变后先按当前版本验证，旧报告保留适用范围。
- 先核实 PR/merge SHA/live heads，再保全 ignored 文件，最后归档当前 worktree；保留其他会话资产。
- 用户只负责实际使用和反馈，AI 完成样本核对、开发复核、修正与回归，不把标注表变成用户新工作。

本次文档门禁为 `composer test:all`、Composer metadata、WordPress.org 静态规则、文档本地链接和精确 diff 的本地 Qwen 审查。
门禁的实际执行结果、该文档 PR 合并与最后清理状态以本次交付及主检出
`tests/artifacts/session-closeout-2026-10-29/closeout.json` 为准；不因写下门禁就提前声称通过。
