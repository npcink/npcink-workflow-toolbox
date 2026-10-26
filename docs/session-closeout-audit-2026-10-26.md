# 五个会话的源码、清理与文档收口审计 — 2026-10-26

状态：开发交接审计；用户同意先核实提交、清理和知识沉淀，再关闭对应会话。
本报告记录读取时的源码与 Git 事实，不证明当前生产状态或推荐质量，也不解除暂停。
本报告自身仍须走正常文档 PR、合并验证及主题分支清理。

后继分工更新（2026-10-26）：本报告已随 PR #328 合并。使用者随后明确选择
AI 通过 Eval Lab 主导评估、分歧核查与开发回归，人工正常使用、反馈并作最终决定。
下文「五例人工试点」保留为历史建议，由
[当前待办](known-issues.md)中的 AI 开发闭环取代，不再要求使用者逐条标注。

## 1. 本轮变更范围

- 目标仓库：仅 Toolbox 的文档与开发交接；其余四仓只读核验。
- 非目标：产品代码、权限、排序、Provider 调用、生产/M4 部署和新 pack。
- 边界权威：`AGENTS.md`、[boundary.md](boundary.md)；本次用户的「不开第三包」范围继续有效。现存第三/第四包及旧文档中的历史放开记录不构成本次扩包授权。
- 门禁：文档链接/格式、`composer validate --no-check-publish`、`composer test:all`、`composer check:wporg`；发布仍执行精确 HEAD 的正常 PR 门禁。
- 回退：仅回退本次文档提交；保留归档 refs、其他会话工作树和已有运行数据。

## 2. 五会话处理与未结项归属

| 会话（原始 ID） | 归纳与结果 | 收口及后继 |
| --- | --- | --- |
| 归纳会话并制定下一阶段建议（`01a14348-1a6b-70a0-84d4-915d3cfcf03d`） | 从排序基线、12 篇真实文章、引用证据和双模型复核，推进到锚点资格、源证据与阅读任务 abstention；最终报告曾因模型容量错误中断。报告现已落盘，Eval #122 补清证据边界 | 文档交付后可归档；人工推荐验收由下一行承接，旧 M4 accepted 与 smoke 仅用于其记录版本 |
| 文章内链和相关文章推荐（`01a1390a-7657-78e0-8934-ce36dbc9b60e`） | 同类插件研究形成召回/排序/资格/编辑器安全分层；最后一次「冻结并观测」请求中断，但后续 #271 已完成，不再重复实现 | 保留为推荐人工验收主线；沿用 Eval 既有五例试点，明确当前可用入口与精确版本 |
| 整理项目待办与阶段建议（`01a12e21-029c-7ed1-bd49-774d153d00a0`） | UX 六里程碑、依赖文案、contextual ALT 修复、共享本地 OCR 配置和内链拆分已合入；拟将本地 OCR 变为发布门禁的实验明确停止 | 文档交付后可归档；停止实验保留可恢复 refs，余下产品/结构债务回到 Known Issues；旧 Cloud 超时和 #269 未合并状态已被后续记录取代 |
| 讨论 AI 提交功能方案（`01a1390d-4efd-7bd1-829c-ff0dda50a2bf`） | 从性能修复提交讨论收敛为有限脱密证据、方法建议、客户自行处理及黑盒复测；原规划文档已随 #269 进入主线 | 讨论可收口；接续既有 Site & Ask。真正性能测量、方法库与效果复测仍为有 owner 的后续设计，不能称为已交付 |
| 一键整理文章（`01a13888-fd0f-7270-8f37-88e17b9a3e90`） | 核实 #141 已合并格式整理；讨论扩展现有发布前检查。会话本身没有新增代码 | 保留为文章辅助后续；先补当前浏览器保真/撤销验收，再定义问题定位、语句、跨段逻辑与正文风险契约 |

未结项统一入口为 [Known Issues](known-issues.md)，不只留在聊天历史。
Admin L3 折叠、自动选包（非本阶段）与部署决策继续由
[Admin 开放待办](admin-ia-open-todos-2026-10-23.md)维护。

## 3. 提交与合并证据

完整读取两个长会话的 54 / 51 个 turns，以及其余三个会话；核验下列 45 个相关
PR 的 GitHub `MERGED` 状态，并用 `git merge-base --is-ancestor` 确认每个
merge SHA 均进入所属仓库的 `origin/master`（Eval 为 `origin/main`）。
使用 squash 后的 merge SHA，不能因为原 topic SHA 不在主线上就误报漏合并。

| 仓库 | PR → 已合入的 merge SHA |
| --- | --- |
| npcink-workflow-toolbox | [#141](https://github.com/npcink/npcink-workflow-toolbox/pull/141) → `68db923ee8d5`；[#256](https://github.com/npcink/npcink-workflow-toolbox/pull/256) → `db76e03f7493`；[#257](https://github.com/npcink/npcink-workflow-toolbox/pull/257) → `b9dd155238ea`；[#258](https://github.com/npcink/npcink-workflow-toolbox/pull/258) → `a9c49aac2654`；[#259](https://github.com/npcink/npcink-workflow-toolbox/pull/259) → `80f84b0dfe46`；[#260](https://github.com/npcink/npcink-workflow-toolbox/pull/260) → `d7c3825dab85` |
| npcink-workflow-toolbox | [#261](https://github.com/npcink/npcink-workflow-toolbox/pull/261) → `9dfbdc8e1406`；[#262](https://github.com/npcink/npcink-workflow-toolbox/pull/262) → `5654cebe3e2f`；[#263](https://github.com/npcink/npcink-workflow-toolbox/pull/263) → `7e3136048eaa`；[#264](https://github.com/npcink/npcink-workflow-toolbox/pull/264) → `882df9433a9a`；[#265](https://github.com/npcink/npcink-workflow-toolbox/pull/265) → `a04c129681b6`；[#266](https://github.com/npcink/npcink-workflow-toolbox/pull/266) → `7ec1aaf4f9de` |
| npcink-workflow-toolbox | [#267](https://github.com/npcink/npcink-workflow-toolbox/pull/267) → `a7dc1edc908e`；[#269](https://github.com/npcink/npcink-workflow-toolbox/pull/269) → `ee814e04de54`；[#270](https://github.com/npcink/npcink-workflow-toolbox/pull/270) → `569da58106c8`；[#271](https://github.com/npcink/npcink-workflow-toolbox/pull/271) → `135090bdc2c8`；[#272](https://github.com/npcink/npcink-workflow-toolbox/pull/272) → `3ae12c4a6a16`；[#273](https://github.com/npcink/npcink-workflow-toolbox/pull/273) → `d253bdb110be` |
| npcink-workflow-toolbox | [#274](https://github.com/npcink/npcink-workflow-toolbox/pull/274) → `a2ad8981e7d3`；[#275](https://github.com/npcink/npcink-workflow-toolbox/pull/275) → `bfeec7e35184`；[#276](https://github.com/npcink/npcink-workflow-toolbox/pull/276) → `8f186e9c8b2b`；[#281](https://github.com/npcink/npcink-workflow-toolbox/pull/281) → `2dfc3fefa4e5`；[#282](https://github.com/npcink/npcink-workflow-toolbox/pull/282) → `e76c61758720`；[#283](https://github.com/npcink/npcink-workflow-toolbox/pull/283) → `573bb4c8f6ef` |
| npcink-ai-cloud | [#925](https://github.com/npcink/npcink-ai-cloud/pull/925) → `86c00610b4a3`；[#1121](https://github.com/npcink/npcink-ai-cloud/pull/1121) → `a40625b59e60`；[#1149](https://github.com/npcink/npcink-ai-cloud/pull/1149) → `45c6014e5399`；[#1150](https://github.com/npcink/npcink-ai-cloud/pull/1150) → `6f3a1daeaafb`；[#1162](https://github.com/npcink/npcink-ai-cloud/pull/1162) → `ca557f8904cc` |
| npcink-cloud-addon | [#283](https://github.com/npcink/npcink-cloud-addon/pull/283) → `b80b966151f2`；[#288](https://github.com/npcink/npcink-cloud-addon/pull/288) → `fee44b55e608`；[#297](https://github.com/npcink/npcink-cloud-addon/pull/297) → `60dfe43694f1` |
| npcink-abilities-toolkit | [#262](https://github.com/npcink/npcink-abilities-toolkit/pull/262) → `b09ad3008a02` |
| npcink-eval-lab | [#111](https://github.com/npcink/npcink-eval-lab/pull/111) → `09fdd4d0bf3c`；[#112](https://github.com/npcink/npcink-eval-lab/pull/112) → `dcf6eb48a4ec`；[#113](https://github.com/npcink/npcink-eval-lab/pull/113) → `58ac1f072f80`；[#114](https://github.com/npcink/npcink-eval-lab/pull/114) → `68732e6d2cf9`；[#115](https://github.com/npcink/npcink-eval-lab/pull/115) → `d2c73dd0d464`；[#116](https://github.com/npcink/npcink-eval-lab/pull/116) → `f6d5ef5c9dec` |
| npcink-eval-lab | [#117](https://github.com/npcink/npcink-eval-lab/pull/117) → `c9bcccc93659`；[#118](https://github.com/npcink/npcink-eval-lab/pull/118) → `8f170fcc5704`；[#119](https://github.com/npcink/npcink-eval-lab/pull/119) → `ea9f8e18e085`；[#120](https://github.com/npcink/npcink-eval-lab/pull/120) → `e903affba338`；[#121](https://github.com/npcink/npcink-eval-lab/pull/121) → `32a37de757c7`；[#122](https://github.com/npcink/npcink-eval-lab/pull/122) → `bd901e0d031b` |

审计默认分支快照：Toolbox `b38c1dd39287`、Cloud `57bf88271668`、
Addon `3e35df4b38db`、Toolkit `0b89dd27553b`、Eval `bd901e0d031b`。
以上是本轮文档 PR 之前的状态；GitHub 默认分支头与本地跟踪引用一致。

### 明确未合入：停止的本地 OCR 发布门禁实验

「所有交付代码已合并」不等于「所有尝试过的代码都应合并」。旧四切片实验
未获完整审查，且 Slice 1 依赖后续切片的测试/发布接线，不能独立发布。
其最终授权尝试失败后已停止，不能因关闭聊天而重启 Provider、预算或旧排程。
共享本地配置 #267 已合并；这不等于整套发布门禁迁移获准。

恢复入口保留在本地 Git：

- `refs/archive/cleanup-20261016/codex/local-ocr-bootstrap-slice-1` → `60062a18`
- 同前缀 `local-ocr-bootstrap-slice-2` → `d74ffab9`；`slice-3` → `2f69d7ba`；`slice-4` → `a6842ee2`
- 同前缀 `local-ocr-publisher-bootstrap` → `d068af20`
- 同前缀 `local-ocr-final-closeout` → `d05dcb6cb42e`

可用 `git show <ref>:<path>` 读取，不必恢复活动分支。原收尾报告已逐字保存于
[历史 OCR 报告](archive/2026-10/local-ocr-final-feasibility-closeout-2026-10-15.md)，
并加上后继状态提示；当前发布规则仍以现行 AGENTS 和 publisher 为准。
这些 refs 是本机恢复证据，不应误报成已推送的备份。

## 4. 分支、worktree 与范围边界

- GitHub live heads 核查显示：上述 PR 的历史 head 分支均已不存在。
  Toolbox、Toolkit、Eval 远端只有默认分支；Cloud/Addon 另有 production 或
  Dependabot 分支，均保留。判断删除不能只靠 stale 的 remote-tracking ref。
- Toolbox、Toolkit、Eval 在本次文档分支创建前，各只有干净且同步的主工作区。
  旧推荐、Cloud retrieval/performance、OCR 四切片等 worktree 已不在 Git 注册表；
  已检查的历史路径在磁盘上也不存在。本次没有把旧清理归功于新操作。
- `magick-ai` / `magick-toolbox` 的 Toolbox 插件 symlink 均指向现存主仓库，
  没有指向已删除的推荐工作树。忽略目录中的测试证据没有被清空。
- Cloud 仍有其他任务的六个工作区，含锁定的 M4/只读窗口及 daytime 开发树；
  daytime 树存在其他任务的已修改/未跟踪文件。保留其文件、锁和分支。
- Addon 主工作区干净、detached 于 `origin/master`；另外两个本地分支
  `master` 和 `docs/playground-bootstrap-flake-note` 与上游有差异，不属于
  本次五会话的未交付变更，未重置、合并或删除。

因此可确认这些历史会话的活动分支/worktree已清理；不能宣称整个 Cloud/Addon
开发环境「全部分支与工作树都干净」。本次仅用 Git fetch/prune 刷新跟踪引用，
没有清理其他会话。关闭聊天也不替代本报告自身主题分支的最终清理。

## 5. 可复用开发经验与规范入口

1. **证据分层。** 45 个 merged PR 证明源码进入主线；12 篇 smoke 证明记录
   步骤。两者都不能证明生产已部署或推荐已被独立人工认可。冻结 v1 的 hold
   保留；行为事件、AI 判断、candidate worksheet 和独立 gold 各有口径。
2. **推荐分层。** 向量召回、阅读任务资格、锚点自然度与当前编辑器 Apply 安全
   分开判断；健康 abstention 不能被 fallback 填满，超时/HTTP/解析失败不能
   冒充「健康无候选」。修改一个因素时单独版本化，保留对照。
3. **环境与测试语义。** 历史 PHP-FPM/Nginx 旧缓存通过一次刷新后维持稳定窗口
   验证；反复重启会制造假故障。仅正向样本要求必须 Apply；无安全候选应验证
   fail-closed。Apply/Undo 不写数据库，native-save 用可清理副本独立验证。
4. **功能与便利。** 复用现有站内引用、发布前检查和 Site & Ask。Cloud 推理与
   Addon 运输不等于 WP 写权限；环境清单不等于 Lighthouse，建议不等于修复。
5. **实验及时止损。** 拆小代码前验证每片能独立测试和发布；Token 参数不自动
   等于严格消费上限。保存失败与逐 finding 判断，完整性不足就不能作通过回执。
6. **关闭前沉淀。** 保留决策、替代方案与失败理由，迁移未结项，核验 merge/
   live heads/worktree 后再关闭会话；不得以强制重置或删除他人改动求整洁。

复用规范：[推荐开发规范](related-article-and-internal-link-recommendation-standard-v1.md)、
[编辑器与环境验证规范](editor-recommendation-image-and-runtime-validation-standard-v1.md)、
[会话关闭门槛](development-workflow.md#referenced-session-closeout-gate)。
本轮补正 [Current State](current-state.md)、格式整理状态及文档索引；不另造产品
契约或第二套待办库。后续主攻仍是现有推荐的五例人工试点，文章检查和效果复测
分别按既有范围排队，不扩展排名实验、自动选包或自动修复。
