# Article review follow-up acceptance — 2026-11-01

本轮依次完成两个排版兼容案例、扩大中文语句评估，以及有界逻辑/成人内容
语境提示。最后一项已合入 Toolbox #359、Addon #339、Cloud #1335；Addon
#340 修复接收超时回执的期限竞争。排版仍保留原文和多媒体；审阅只提示，
作者自行判断并手动修改。真实 OSS 写入/备份/恢复与生产发布按用户决定延后。

## 交付与证据状态

| 层 | 已核实状态 | 证据边界 |
| --- | --- | --- |
| 排版兼容 | Toolbox [#356](https://github.com/npcink/npcink-workflow-toolbox/pull/356) | 280942/280861 的受保护兼容验证通过；不放宽文字、标点、链接或媒体保护 |
| 中文扩大评估 | Toolbox [#358](https://github.com/npcink/npcink-workflow-toolbox/pull/358) | 56 次真实调用；保留原始文风误报及四个开发标签错误，不宣称通用准确率 |
| Toolbox 扩展 | [#359](https://github.com/npcink/npcink-workflow-toolbox/pull/359)，`c4b2534b` | 契约/行为、PHPCS/PHPStan、元数据/wporg、离线桥接与浏览器通过 |
| Addon 扩展 | [#339](https://github.com/npcink/npcink-cloud-addon/pull/339)，`b80de5ff` | 严格检查选择/hash、PHP 8.0/8.2/8.4、PHPStan、WP AI 兼容和 release static CI 通过 |
| Cloud 扩展 | [#1335](https://github.com/npcink/npcink-ai-cloud/pull/1335)，`b37618d8` | 本机 changed Python 类型/质量门及 73 项相关测试通过；精确候选 GitHub CI 通过 |
| M4 开发候选 | 干净 `13ecb4a1`，bundle `cb8ceb375379a00d9fce01f6cb74cbe043ba95eb595cc13f38141725e222e309` | 73 项现场测试、24 次首轮模型调用、安装浏览器；不是生产 |
| M4 开发验收 | 干净 master `b37618d8`，accepted / PR 1335，bundle `ec10684cc1d2e6eef457c15285535cb06b44a668a0c1d5cb996b95a5cd5f6476` | 合并候选 tree 相同；health 与状态版本一致 |
| 超时传输 | Addon [#340](https://github.com/npcink/npcink-cloud-addon/pull/340)，`7249a210` | 模型仍 30 秒；HTTP 接收 40 秒，无重试；全部最终 CI 通过 |
| 本地安装 | 稳定主 checkout，Toolbox 合并 master；Addon 合并 master 加保留的 vision overlay | 完整版本与最终中央矩阵见下述 JSON；不把本地例外冒充上游已合并 |
| 生产 / WordPress.org / OSS 真写入 | 未执行 | 用户明确延后 |

中央矩阵首轮两仓 PHP 门通过，Cloud 合并 SHA 缺少独立 backend 检查记录；
随后仅复查 Cloud 已审候选 `13ecb4a1`，精确 GitHub 源码门通过。其 tree
`a6544933131e18f7014a19f015812d81bba87723` 与合并 `b37618d8` 相同。
没有重复两仓 PHP 套件，也没有以部署替代源码门。两次原始矩阵均保留。

可机器核对的版本、run_id、计数及布尔值见
[脱正文回执](article-review-context-evidence-2026-11-01.json)。原始结果、模型文字、
审查回执与日志保存在本机私有归档，不进入 Git。

## 冻结模型评估

[24 例](article-review-context-cases-v1.json)在调用前冻结，SHA-256
`16aa0827a75d2220a4fa9482731c96c6fb799466c5301d45e73a11e0f4a6c786`。
先跑 2 例确认链路，再跑其余 22 例；每例仅一次，不改变标签、不选择性重试。
使用真实已安装 Toolbox `1de1114e` / Addon `665b5a6f`（保留 vision overlay）
→ 签名 Addon → M4 候选 → 当前 `openai / gpt-6.1-sol`，未改模型或供应商绑定。

| 样本 | 首轮结果 |
| --- | --- |
| 5 个逻辑正例 | 矛盾、跨段金额、推断过度、待核实疗效主张、单段净额均给出预期类型提示；成对原文和核对方向可用 |
| 2 个成人推广正例 | 均给出商业语境核对提示，无替换文案或发布裁决 |
| 2 个标点正例 | 包括旧 language-only 客户端，均给出预期提示 |
| 15 个正常对照 | 14 个完成且零提示；新闻对照达到 30 秒模型期限，未取得报告，不算通过 |
| 已观察到的文风误报回归及两个新控制 | 本轮均零提示；原 56 例中的误报不被改写为不存在 |
| 引文/写入 | 所有返回报告均通过独立 UTF-16 原样核对；无无效引用、无 WordPress 正文写入 |

预先目标为正例命中至少 80%、正常对照误报至多 10%、无无效引用/写入。
观察到 9/9 正例命中、14 份完成的对照零误报；可用率为 23/24。不可用样本
不能充作正确拒绝或零误报成功，因此不能宣称 24/24 通过。逐条开发审阅认为
正例的理由/核对方向有用，但开发者自选合成样本不是独立 gold，也不能换算成
真实长文、通用中文准确率、召回率或用户价值证明。

## 超时诊断与修复

新闻对照原报告显示 unavailable、没有 run_id。只读数据库在相邻两次目标
调用之间定位到唯一目标 run `run_31b8cc0e052f47eb8862e13d75acfa64`：
failed/provider.timeout，provider latency 30000 ms，1 次调用、0 次重试。
WordPress HTTP 同样在 30 秒退出，先于 Cloud 约 30.13 秒的失败回执到达。

Addon #340 仅对文章审阅留出十秒接收余量。确定性行为测试验证 30 秒执行载荷、
40 秒 HTTP 等待、超时原因与 run_id 保留及仅一次请求；不通过延长模型预算或
自动重试来制造成功。没有重跑该新闻质量样本，新闻语境首轮仍未验证。

该补丁首次 CI 的 release static 门发现 POT 源行号陈旧。已刷新 POT 引用并
保留失败日志，最终精确 head `d60bbe34` 的全部 CI 通过。Qwen 两次回执及
最终意见均按真实 guard/常量核对和处置；未采纳不可达输入的假设性建议。

## 真实编辑器与隐私

安装浏览器使用真正 enqueued JS 和实际 REST/Cloud 输出，禁止注入实现或
替换结果。运行 `run_9dd3bf9b078e4859b80865610bb918d9` 返回标点、跨段金额
两项提示；两处引文均能只读定位，编辑/Undo 后旧报告不能恢复定位。代码与
图片排除计数可见，三项检查完成但覆盖为 partial，不能误读为全文已检查。
编辑器标题/摘要/正文和数据库原始草稿不变，零 post 写请求、零页面错误；
临时草稿和登录 session 已删除。1440×1000 截图已检查，状态、原文、理由和
定位均在侧栏可见。这是开发验收，不是用户人工视觉验收。

只读事务审计全部 25 次调用（24 个样本与 1 次浏览器）：全部 pii/no_store，
持久输入为空、encrypted input 不存在；24 项结果为 omitted marker，失败项
为 null。失败的错误文字不含提交正文/审阅文字。所有目标 run 仅 1 次模型调用、
0 次重试、0 次 fallback；有界 usage 元数据保留。该批记录共 14,848 输入/
3,614 输出 token、Cloud metered cost 0；不据此推定上游零计费或零保留。

## 收尾与后续

中央跨仓矩阵、最终安装 overlay、恢复 refs 和清理状态写入脱正文回执。
保留受应用保护的当前 Toolbox 工作区及其他会话工作区；仅清理本任务已合并
分支与辅助工作树。原 Addon vision overlay 始终保留在本地恢复 ref，不推送。

后续以普通作者使用反馈决定有界修正/补充样本；不自动开展新一轮广泛模型
评估。仍未覆盖独立作者价值、长文一般准确率、全面内容风险、外部事实核验、
上游保留政策、真实 OSS 写入/备份/恢复和生产发布。零提示不等于正确、安全
或获准发布。当前[严格契约](article-review-context-v1.md)与
[开发经验](article-review-delivery-lessons-v1.md)是后续实现依据。
