# 三项目 SEO 暂停：生产候选与发布方案

更新：DeepSeek 已由独立发布完成安装，候选 CI 和专项集成/浏览器验证已执行；严格 UI 仍有 18 个既有缺口。当前证据以[候选验收续记](seo-specialist-pause-candidate-validation-2026-10-18.md)为准。下面保留首次准备快照。

日期：2026-10-18，Asia/Shanghai。状态：本地候选准备完成，尚未授权本轮生产发布。
风险 L2。范围仅 AI Cloud、Cloud Addon、Workflow Toolbox。

## 1. 当前结论与授权边界

本次准备了隔离源码候选、两个插件 ZIP、已发布版本的回滚 ZIP、逐文件差异与 SHA256、
Cloud 两种基线的 release plan，以及发布材料草稿。没有推送候选、创建生产 PR、发布
WordPress.org/SVN/GitHub Release 或 tag、构建生产镜像、访问生产主机、操作 M4、部署生产。
本轮资源实际使用：付费 Provider 调用 0，生产镜像构建 0，生产部署尝试 0。

候选不是可立即上线的批准。后续发布必须重新核对生产分支、实际安装版本和已部署基线，
完成本文件列出的验证，再由操作者批准具体发布范围和最终生产 SHA。

原专项变更已在 master 合并：Cloud [PR #1141](https://github.com/npcink/npcink-ai-cloud/pull/1141)、
Addon [PR #290](https://github.com/npcink/npcink-cloud-addon/pull/290)、
Toolbox [PR #277](https://github.com/npcink/npcink-workflow-toolbox/pull/277)。
[调查与历史会话归档](archive/2026-10/seo-geo-reference-chat-2026-10-18.md)继续保留；
[实施记录](seo-specialist-pause-implementation-2026-10-18.md)中的首次候选状态属于历史证据。
本文件及同日独立交付清单描述本轮生产候选，不把不同基线的验证混用。

## 2. 已核对的基线与候选

| 项目 | 实际发布/部署基线 | 本地候选 | 分支 |
| --- | --- | --- | --- |
| Cloud | 安装源码 `4a0a305ed116d0b1de99bb7bdba79d67e3b14e55`，成功 Deploy Production run `39049119345` | `e8877e19c31779e4a54bed73fede8c5ca05b918c`，tree `c44a3cb389913672eb9a5d06aff0755c1a647d39` | `release-fix/seo-specialist-pause-20261018` |
| Addon | WordPress.org 0.4.1，对应打包源码 `0ae75febbfb391a7b405ddddad62d70fd4a7d578` | 0.4.3，源码 `26e58bb62ccf27e9359a2dd354750c7401981055` | `codex/release-seo-pause-20261018` |
| Toolbox | WordPress.org 0.6.0，对应打包源码 `db76e03f7493cf9256f86cb2f7ed7c56f970c58a` | 0.6.1，应用源码 `437925233e612dfb12cbb6eab6c3d582dc6ee1a7`；后续文档提交不改变 ZIP 内容 | `codex/release-seo-pause-20261018` |

官网版本是发行基线，不能证明每个用户站点都安装该版本。Addon 官网 ZIP 的 51 个文件
与所选 Git 基线逐字节一致；Toolbox 的 91 个非生成文件逐字节一致，9 个压缩资产在候选
打包时重新生成。候选 Toolbox ZIP 的 100 个文件包含这些生成资产。
所有候选非压缩文件均已与对应候选提交逐字节比较；生成资产保留打包日志。

Addon 0.4.2 已留作另一套配对功能候选，包含 reconciliation、ranking feedback 等内容，
因此本轮暂拟 0.4.3，不复用 0.4.2 包或覆盖其材料。以后若发布原 0.4.2 功能，必须基于
最新生产版本重新编号、集成暂停策略并验证，不能再把旧包作为升级发送给用户。
Toolbox master 的后续编辑器、站点检查和 ranking 改动也没有混入本候选。
版本号尚未占用公开 tag；正式发布前需再次核对官网及其他发布任务。

### Cloud 必须分清的两个范围

当前 `origin/production=b8cc6049da7d1c33cbfffc9f8e5521f332a1adb5`，包含独立 DeepSeek
修复 PR #1142；部署记录仍指向 `4a0a305e`。本候选从当前 production 衍生，只回移
SEO PR #1141；其稳定 patch-id 与 master 原改动相同。

- `b8cc6049 -> e8877e19`：11 个路径，SEO 暂停增量。
- 实际安装 `4a0a305e -> e8877e19`：15 个路径，DeepSeek + SEO 的并集。
- 没有把 master 上累计的 commercial/rollup、0088 migration 等其他工作一起带入。

默认推荐：等 DeepSeek 的独立发布完成并重新证明实际安装基线，再按 SEO 增量评审。
本任务不会代替该发布任务部署 DeepSeek。若希望一次安装当前候选，必须明确批准
15 路径的合并范围；不能把它标成“只上线 SEO 暂停”。若生产分支或部署基线变化，
原计划和回滚点即需重新计算。禁止为了规避这一判断把 production 倒退到旧分支。

两份本地 release plan 均为 `backend`：backend image required，frontend image 不需要，
migration=false，runtime_config=false，static_payload=false。需要在受控 CI 中重建 Linux/AMD64
后端应用镜像并更新 API/相关 Python workers；不是只同步源码。保持现有 frontend、Redis、
Nginx 和数据库数据；最终服务清单以精确 SHA 生成的正式 release plan 为准。

## 3. 发布物与回滚材料

统一证据目录：
`/Users/muze/.codex/task-receipts/npcink-ai-cloud/2026-10-18-seo-pause-production/`。
`delivery-manifest.json` 是本地交付索引；它记录候选提交、包摘要、工作树和验证结果，
不替代 Git、Actions、实际主机状态或站点安装清单。

| 文件 | SHA256 |
| --- | --- |
| `npcink-cloud-addon-0.4.3-candidate.zip` | `e66b2f1dde91b5fa95ebb3d83845d3f3077ba3b31d26dabf67ee64f7d63c3c9a` |
| `npcink-workflow-toolbox-0.6.1-candidate.zip` | `7ff6a5c4d48bb833963272e80528c81932460dedc5005c301bf84de307efabd3` |
| `npcink-cloud-addon-0.4.1-published.zip` | `23166c2e969533e8686d461a57694c64ca4e3a9fabf6a753ee77ac5fddb54596` |
| `npcink-workflow-toolbox-0.6.0-published.zip` | `a95bd2e83b017a34a2f529fead84774250d7f196c85e77f7a6bd65d0dee00724` |

Addon 候选 52 文件，只新增暂停策略类，未删除原包文件；Toolbox 候选 100 文件，
没有新增或删除原包文件。具体变更成员见各自 `*-candidate-package-manifest.json`。
Cloud 只有本地源码候选及 release-plan，没有伪造可部署镜像包。

当前可引用的 Cloud 旧部署档案来自 4a0a305e 的独立发布记录：
`/Users/muze/.codex/task-receipts/npcink-ai-cloud/2026-10-18-frozen-production-deploy/`。
该档案记录的旧 archive SHA256 为
`0d266295f1f006c5e9b019dc467148dffa26b8dd09d81870a28997ffe216066d`。
本轮未读取主机验证该归档、镜像和配置仍可恢复，不能将此历史记录说成当前回滚演练通过。

## 4. 部署顺序与阶段验收

1. **冻结并复核范围**：核对三个候选 SHA/tree/ZIP hash、官网版本、目标站点真实插件版本；
   重新运行 Cloud 已部署基线解析器。确认上一节 DeepSeek 的处理方案。保存安装清单和
   插件目录备份，不包含凭据；已有私有 0.4.2 或较新 master 安装的站点另作比对，不能直接
   安装本次从官网基线制作的候选，避免无意降级通用功能。
2. **完成候选验证与发布审批**：见第 7 节。先在预览/测试站安装两个候选配合精确 Cloud
   候选验收；不拿 master 的旧浏览器/M4 记录冒充隔离候选的集成验收。
3. **Cloud 先上线**：取得独立生产 PR/验证授权后按仓库 publisher、保护分支和 CI 流程办理。
   精确 production SHA 的 CI、CodeQL、Linux/AMD64 build、fresh CVE scan、bundle、只读
   preflight 全部满足后，向操作者报告服务范围、持续时间、回滚点与最终 SHA；取得该 SHA
   的明确部署授权才 dispatch `Deploy Production`。不能仅凭本方案或模板中的确认语句执行。
4. **Cloud 阶段验收**：健康、版本和 worker 状态；签名且无 Provider 执行的专项负向请求，
   核对暂停码以及无新增 usage/run；保留通用路径正常。真实通用生成可能收费，需事先给出
   有界调用预算并获得该阶段授权；本轮没有为了验收制造调用。失败先停止后续插件推广。
5. **Addon 0.4.3**：先选已核对版本的受控站点更新，再小批推广；检查官方 AI 的 Meta
   Description 入口消失、通用功能仍在、旧配置保留、旧专项请求不出站及连接正常。
   通过后才办理公开目录发布/分发；目录传播和用户自动更新是异步过程。
6. **Toolbox 0.6.1**：相同受控站点、小批、公开分发顺序。检查站点画像专项设置隐藏，
   简化表单保存不擦除旧专项值，编辑器/材料准备不带专项 brief，旧直接调用拒绝，普通
   摘要、分类、标签、内链、ALT、素材和图片能力继续可用。观察新巡检分数版本及旧记录读取。
7. **收尾观察**：分别记录 Cloud 版本、每个站点安装版本和插件传播状态。公开入口关闭
   不能仅凭 Cloud 成功就宣布完成；旧页面需刷新。保留阶段性失败证据与本次发布时序回执。

预计安排：Cloud 构建、扫描、传输、切换及验收预留 15–30 分钟，服务切换预留 1–3 分钟；
这些是排期估算，不是本候选实测或可用性保证。历史 4a 发布记录的 build/scan 217 秒、
transfer 76 秒、cutover 分类 51 秒、health 16 秒仅作参考。插件每个受控站点预留
5–10 分钟安装与检查；全量完成时间依赖站点规模和目录传播，当前未测量。
生产执行前再次申明最大构建/部署次数和 Provider 调用预算；连续两次相同传输失败即停止自动重试。

## 5. 用户影响与兼容边界

- 暂停 SEO/AEO/GEO 专项入口与新增调用，包括 meta description、seo_headline 等旧别名、
  专项 brief/元数据提案及专项编辑检查。Cloud 在 Provider 前拒绝；Addon 会更早阻断。
- Cloud 先更新到插件传播结束期间，旧 UI 仍可能显示入口，点击得到暂停错误（各接口的
  HTTP 400/409 和 `feature_paused`/`cloud_feature_paused` 码保持各自契约）。不可把旧端
  的预期拒绝当成故障并重新启用 Provider 路径。
- 普通标题、摘要、翻译、分类、标签、易读 slug、ALT、内链及图片等通用能力保留；
  正文中出现“SEO”文字不触发关键词封禁。混合编辑建议只保留通用类型；旧低层混合请求
  按各自协议拒绝，不能声称每种旧客户端都自动改写成功。
- 不删除历史数据、旧配置、已采用文章元数据、URL、提案或 run/result，不重写历史分数。
  简化表单保存保留隐藏专项值；缓存版本区分暂停策略。新巡检不因缺少 SEO 描述扣分，
  使用 `nightly_content_quality_score.v3`；新旧分数不能直接作为同一规则下的趋势比较。
- 已开始的请求、排队任务和历史重试不作追溯性取消承诺；发布前检查实际队列/worker 的
  执行边界，必要时先暂停新的专项入口并等待已有工作结束。不能凭 API 拦截测试断言所有
  在途或队列内任务都被撤销。
- 仅覆盖这三个仓库的链路，不宣称其他插件、独立能力或外部客户端全部停用。
  没有新迁移、批量回填、权限放宽或生产配置改写。

## 6. 回滚方式与触发条件

触发：通用能力回归、连接/签名失败、运行时健康持续异常、配置值丢失、错误地执行专项
Provider、或发布包损坏。先停止下一批分发并保存版本、错误码和时间；不要打印正文/凭据。

**插件代码回退**：对确认原来就是官网版本的站点，恢复上表对应已校验 ZIP 或该站点发布前
目录备份；采用运维安装/覆盖过程，不卸载插件，不调用 uninstall，不清理 options/历史。
先回退本次最后更新的 Toolbox，再按需要回退 Addon，逐站核对连接、通用功能和历史数据。
如果原来是私有新版，必须恢复该站点自己的备份，不能用官网旧 ZIP 代替。
公开目录中已经发布的版本不覆写 tag；修复后用更高版本走正式发布，必要时暂缓升级。

**Cloud 代码回退**：本次没有数据库迁移，正常代码问题不需要数据库降级或恢复数据库。
部署前冻结实际上一稳定 release、source SHA、manifest/image digest、匹配环境和镜像归档，
校验它们仍可用。当前历史目标为 4a0a305e；若 DeepSeek 先完成部署，则上一稳定点必须改成
其实际安装版本并重新形成回滚材料。由授权运维按 `deploy/PRODUCTION_GITHUB_DEPLOY.md`
和 `deploy/OPS_PLAYBOOK.md` 的匹配恢复流程恢复该稳定应用，不手工改服务器源码、拼接镜像
或仅切换符号链接。部署器在激活前失败时按其已有证明执行匹配恢复；若运行时已健康、
只剩 terminalization 清理失败，保持健康运行时，保留锁和证据，走受限恢复流程，不能误回滚。
本轮没有编造一个无需核对主机状态就能运行的 rollback 命令。

若必须反向撤销整轮发布，则通常按 Toolbox → Addon → Cloud 的逆序处理。回退插件后，
Cloud 仍可阻断未更新客户端的专项调用。**回退 Cloud 到暂停前版本会重新开放旧客户端的
专项能力**；保留两个新版插件也不能覆盖所有旧客户端。如业务仍要求全入口暂停，应在
单独评审中保留服务端暂停 guard 或发布有界修复，不能把技术回滚当成业务恢复授权。
以后恢复 SEO 需要另行决策和三仓库完整回归，不只翻转一个常量。

## 7. 当前验证与发布前剩余条件

| 证据 | 本轮结果与限制 |
| --- | --- |
| Cloud 定向测试 | 342 passed，33.67 秒；1 个 Starlette 弃用警告。覆盖 WordPress runtime、batch、专项拒绝/通用保留等；不等于整套 CI |
| Cloud 静态/策略 | 改动 Python Ruff、定向 mypy、release-policy 通过；SEO patch-id 与 master 原改动相同 |
| Cloud master/M4 历史 | PR1141 master `5fa1d47c` 已 accepted，属于上一阶段；本轮隔离候选没有新的 M4 acceptance，不复用为精确候选证据 |
| Addon | test:all、PHPStan、composer validate、wporg、JS、POT freshness、打包/52 文件校验、最终 ZIP 严格 Plugin Check、Playground 激活与真实 WP 游标检查通过 |
| Addon AI 本地化 | 现装 AI 插件 998 个 ai-domain 字符串，基线 audit 固定 UI 缺口 0，domain-less rescue 6；旧 audit 不等同 master 新严格 UI gate，尚需逐组复核及候选实际浏览器验收 |
| Toolbox | test:all、锁定依赖 PHPStan 2.2.16、PHPCS、composer validate、wporg、打包通过；源码暂存包 Plugin Check 无 ERROR，有 2 个数据库查询 WARNING；最终 ZIP 同样无 ERROR、有上述 2 个 WARNING |
| 中央三仓库矩阵 | 两 PHP 仓库 passed；Cloud blocked_environment：未发布的 `e8877e19` 没有精确 SHA GitHub CI。矩阵整体不是绿色 |
| 人工/真实集成 | 本轮未把实际站点已安装插件换成候选，未触发真实收费生成；候选浏览器、Local Cloud/Provider surface 验收与本次发行的非 AI 证据锚点仍需完成 |
| 生产 | 未建生产 PR，未运行候选 production CI/CodeQL、镜像构建/新漏洞扫描、正式 bundle/preflight、主机回滚验证或部署 |

Addon 从官网旧基线回移时，仅把新增 scene policy 归入暂停类，保持 request guard 原有
2020 行预算，没有放宽 ratchet；没有引入 0.4.2 的 Agent Feedback Metadata 依赖。
Toolbox 初次 PHPStan 曾错误共享 master 的 2.3.1，出现 3 条与基线规则不匹配的诊断；
改用候选 composer.lock 的 2.2.16 后通过，未扩大 baseline 或加 ignore。前次失败日志保留。
测试日志中的 PHP 新版 dynamic-property 弃用提示及 Plugin Check WARNING 没有被写成零警告。

后续至少补齐：

1. 上一节的安装/部署基线确认，DeepSeek 单独先行或合并范围批准；精确候选的保护 CI。
2. L2 对应的候选环境验证、实际插件浏览器与通用能力集成检查；Addon 发布要求的非 AI
   验收/真实观察证据锚点。不得仅凭自动化全绿宣布可发布。
3. 当前安全策略与时效内的 Linux/AMD64 fresh scan、准确 bundle、生产 preflight 和匹配
   回滚点证明。现有 CVE 例外窗口不是永久豁免；过期或新增阻断项即停止。
4. Cloud production PR 的明确验证授权及最终部署 SHA 授权；WordPress 目录/托管站点分发
   的明确授权。当前只准备材料，不提前填入“已批准”事实或触发 auto-merge。

未来 Cloud 流程使用仓库模板和 `pnpm run pr:publish`，显式以 production 为目标，并遵守
release-fix 回补要求。SEO 行为已在 master，Addon 小型基线适配应在将来合并时核对等价；
插件发行分支不直接套用默认指向 master 的 publisher，避免把旧发行树反向合入正在开发的
master。先选定仓库支持的发行/评审路径再发布，不为了得到 CI 破坏分支边界。
正式部署只按已批准的生产 SHA 运行 `production:release:preflight`，再使用它返回的
`dispatch_expected_sha`；本地 e8877e19 不自动等于未来 production merge SHA。

## 8. 保留与复用

候选工作树均保持锁定，原因 `codex:01a14dad-d573-7be1-974a-67b21ec4cdd5`：

- `/Users/muze/.codex/worktrees/seo-pause-production-candidate/npcink-ai-cloud`
- `/Users/muze/gitee/.worktrees/seo-pause-addon-release`
- `/Users/muze/gitee/.worktrees/seo-pause-toolbox-release`

三个日常 master 工作区保持不变。候选没有 upstream，提交有意仅留本地；因为本轮只要求
准备发布，且生产范围/实际验收尚未闭环，没有调用会 push 并申请 auto-merge 的发布工具。
源码 patch、包、hash、门禁日志、PR 草稿和交付 JSON 另存独立证据目录，便于以后恢复工作。
待发布完成或明确放弃、材料保全且工作树干净后，按各仓库生命周期规则办理清理；锁不是
删除或放弃候选的授权。普通会话未修改 M4 五日观察表。
