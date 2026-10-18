# SEO 专项功能暂停实施记录

日期：2026-10-18（Asia/Shanghai）。工作流：merge（操作者后续授权），风险：L2。

操作者已同意可恢复暂停方案。本次仅在 Workflow Toolbox、Cloud Addon、AI Cloud
落实专项入口和新增调用的暂停；普通内容辅助保留。开发候选已完成本地验证，后续操作者已授权提交、PR、CI、合并和 M4 验证；
下方保留首次候选的历史证据，最终交付以对应 PR 和当前 M4 状态为准。原[调查建议](seo-feature-pause-review-2026-10-18.md)和
[参考会话归档](archive/2026-10/seo-geo-reference-chat-2026-10-18.md)继续保留。

## 实际行为

| 项目 | 暂停行为 | 保留行为 |
| --- | --- | --- |
| Toolbox | Site Profile 不渲染 SEO/AEO/GEO 专项配置；专项 brief 不再注册，直接调用返回暂停；发布检查、摘要组合、写作准备不再附带专项候选、GEO 指令或 SEO 提案；旧结构化候选不能借通用 metadata planner 生成新 SEO 提案 | 定位、受众、语气、事实边界、摘要、分类、标签、内链、相关文章、ALT、图片处理、普通材料准备；通用上下文读取保留兼容 ability id |
| Addon | 官方 Meta Description 功能从宿主 feature 列表退出；任务投影、场景传输及旧 execute_runtime 路径拒绝专项请求；专项编辑检查不出站，混合检查排除专项类型 | 原连接、签名、凭据、通用标题、摘要、翻译、分类、slug、ALT 和媒体传输 |
| Cloud | legacy 和 task-contract 路径都在 Provider 前返回 `wordpress_operation.feature_paused`；专项编辑检查被拒绝，混合结果过滤 SEO/AEO/GEO；新巡检不因 SEO 描述缺失或过短扣分 | 普通 runtime、历史 run/result、元数据与审计读取；新分数标识为 `nightly_content_quality_score.v3`，旧结果不回写 |

Addon 的固定界面说明将 slug 表述为易读 URL 别名，将编辑建议表述为无障碍、可读性和语法。
不修改宿主动态 Ability 定义、schema 或第三方插件源码。普通文本和图片请求可以讨论 SEO
这个主题，暂停判断不扫描正文关键词。

## 配置和数据保留

Toolbox 的原始站点配置仍由原 option 保存。暂停期读取时只投影有效的通用规则；
保存精简表单时保留隐藏字段原值，包括 SEO/AEO/GEO 规则、原布尔开关和候选字段选择。
缓存键加入暂停策略版本，避免沿用旧编辑器结果；旧记录不作破坏性清理。

Addon 通过宿主的 `wpai_default_feature_classes` 和
`wpai_feature_meta-description_enabled` 过滤器关闭入口，原启用 option 不改写。
现有配置、已采用文章元数据、URL、历史提案、历史输出和日志不删除。
没有数据库迁移、批量回填、新服务、额外 Provider 调用或权限放宽。

## 验证证据

- Cloud：开发门禁包含 Ruff、定向 mypy 和 WordPress connector、batch runtime、暂停行为测试；233 项通过；追加验证了暂停请求不产生 Provider 调用、usage meter 或 run 记录。
- Toolbox：`composer test:all`、专项配置保存、原生和 Toolkit wrapper 两条注册路径、旧请求拦截、通用材料准备回归；静态契约为 4222 项。
- Addon：`smoke:playground` 激活与真实 WordPress 游标检查通过；`composer test:all`、专项 task 与旧传输零 HTTP 断言、官方 feature 列表过滤、保存选项保留；`ai:i18n:check` 的固定 UI 缺口为 0，`check:js` 的 21 项测试通过。
- Addon 发布包：`package:release` 和 `package:verify`，包含新暂停策略文件。打包不代表发布。
- 本地 PC 浏览器：Toolbox 站点画像的高级区域不再出现 SEO/AEO/GEO 页签；WordPress AI 设置中没有 Meta Description 功能，通用标题、摘要、翻译、分类、slug、编辑建议和 ALT 仍可见。未保存表单、未触发付费生成。
- 跨仓库矩阵只选择上述三个仓库，没有运行其他仓库 gate。WordPress 两仓库 gate 已通过；Cloud 精确 SHA 的 GitHub gate 要求干净提交，因此本地未提交状态为 `needs_validation`，不能把矩阵整体报告为绿色。
- 文档与格式：682 个改动文档中的本地链接全部存在，JSON、边界词汇及 `git diff --check` 通过。

源码位置与恢复边界分别见 [Cloud 暂停契约](../../npcink-ai-cloud/docs/seo-specialist-pause-v1.md)
和 [Addon 暂停契约](../../npcink-cloud-addon/docs/seo-specialist-pause-v1.md)。

## 首次候选的 M4 和交付状态（历史快照）

局域网 SSH 连续两次关闭连接，停止重试该地址。文档规定的 Pgy 路径可读状态；
在前一候选任务结束后，本次尝试一次 Pgy 加私有源码中继同步。中继 SSH 超时，
同步在取得中继锁之前失败，没有替换远端候选。没有擅自切换直传，没有本机 Docker 替代，
没有镜像构建、M4 聚焦测试、promotion 或生产部署。

因此最高证据状态为本地源码验证和本地 WordPress 界面检查；M4 候选证据未完成。
当前三仓库均在 `codex/pause-seo-specialist-20261018` 分支，改动保持未提交，分支未设置 upstream；本轮没有新增提交。
首次候选阶段未创建 PR、未请求合并、未修改生产。后续 merge 授权仍不包含生产部署。源基线：Cloud `67a94d6d`，Addon `a8e732b3`，Toolbox `8f186e9c`。原调查快照不代替本次源状态。

## 后续 merge 阶段进展

操作者随后明确授权三个仓库提交、PR、CI 和合并，仍不部署生产。当前跟踪入口：
[Cloud PR #1141](https://github.com/npcink/npcink-ai-cloud/pull/1141)、
[Toolbox PR #277](https://github.com/npcink/npcink-workflow-toolbox/pull/277)。
PR 的最终合并和精确 SHA CI 结果以各自 GitHub 记录为准。

中继排查发现旧默认节点 `100.90.87.36` 离线；文档中的国内节点
`100.86.14.81` 在线，其 SSH 密钥与本机已信任的服务器记录匹配，但当前本机认证
无法登录。因此没有修改中继服务或扩大 SSH 授权。操作者明确选择蒲公英直传恢复：
`muze@172.16.3.35`，`NPCINK_CLOUD_M4_SOURCE_TRANSFER_MODE=direct`。
原中继登录问题仍需以后提供正确用户名与身份文件，不将直传成功写成中继已修复。

Cloud 干净候选 `7ac4d680` 已完成源码同步，无镜像重建；M4 Python 3.14.8
聚焦测试 233 项通过，测试执行 50.41 秒、命令反馈 55 秒。候选不代表 accepted；
正式验收仍需要 Cloud 合并后从最新干净 master promotion 并核对状态。

Cloud CI 发现的两处行宽问题已修正；两 PHP 仓库通过统一策略读取保留恢复分支，
没有增加 PHPStan baseline 或 ignore。修正后两仓库完整确定性 gate 与 PHPStan 均通过。
Addon 的最终包校验 SHA256：`2ead76418ac0448abe62e64bab969ee66d93f85e32a9c2107d923d85d1b05f37`。
Cloud advisory OCR 因单组预估 82308 超过 80000 预算，在零模型调用时停止，属于不完整证据；
Toolbox 与 Addon 的 CI OCR 工作流处于 `disabled_manually`，未自行启用。
Toolbox 使用发布工具支持的有理由审查例外；确定性 CI 和保护分支约束继续生效。

## 恢复方式与范围限制

后续恢复应复核宿主插件版本和三个仓库的契约，然后按本次任务改动逐项回退或作新实现，
重新执行正向、负向和通用能力回归。暂停期间保留的实现和配置不是重新启用授权。
不只修改某个布尔值就宣称三个仓库均已恢复，不沿用旧缓存、旧审批或历史分数作为新执行依据。

Toolkit、Core、Adapter、Eval 以及其他项目没有修改。它们已有的独立调用或写入链路
不受本次范围内暂停的全平台保证；历史内容和治理真值仍归原拥有者。

M4_OBSERVATION_RECEIPT date=2026-10-18; route=Pgy+private-relay; sync=blocked by relay SSH timeout; focused=not occurred; promotion=not occurred; operations=sync1 attempted/0 completed,deploy0; stable_502=not measured; m4_only=not observed; coordination=previous candidate preserved

## 合并前审查补充

Addon 对应 [PR #290](https://github.com/npcink/npcink-cloud-addon/pull/290)。本地有界
OCR 为部分覆盖，发现的装载、旧混合调用和 React 固定说明问题均修正并加回归；
未增加预算或重复整轮调用。目录 POT/PO/MO 已按实际源码刷新。

GitHub 的独立 Codex 审查指出 `seo_headline` 是既有已注册专项任务别名；Cloud 与
Addon 已将其加入暂停集合，并覆盖有无 task contract 两条路径的零调用断言。
通用自定义任务的正向样例改为 article_headline，继续验证通用投影能力。
Toolbox 状态投影不再宣传 discoverability intent，SEO gap 明确标为 paused 且不可用；
写作包不再携带已暂停 brief ability 或专项 next step。普通网页搜索诊断复用保留的
通用写作材料组成，两个历史 scenario 均有可调用回归。以上变化仍不触及其他项目。
