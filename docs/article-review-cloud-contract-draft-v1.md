# Cloud 文章检查消费方契约草案 v1

2026-11-01：逻辑/风险扩展已进入源代码候选，严格字段与兼容方式以
[本次扩展契约](article-review-context-v1.md) 为准。下文的“仅 language”描述
保留为此前已交付的兼容基线；不能据此宣称新候选已完成运行或生产验收。

状态：2026-10-31，中文 language 切片已合入 Toolbox #349、Addon #338、
Cloud #1331，并通过真实安装编辑器验收和长期本地安装；见
[会话收尾](article-review-session-closeout-2026-10-31.md)。逻辑/内容风险仍未启用，
未做生产或 WordPress.org 发布。文件名保留 draft 以兼容历史引用；下文扩展设计仍是草案。
文中的 `article_review_cloud_request.v1` / `article_review_cloud_result.v1`
是已实现语言切片的契约名称，不代表新增工作流或第三种摘要包。Cloud
后端、Addon facade 和 Toolbox 消费方必须以同一 revision 的测试结果为准，
不能从文档推定生产可调用。
调研依据见[一手来源与取舍](article-review-reference-research-2026-10-30.md)。

## 当前已实现的语言切片

当前切片仅接受 `checks=["language"]`，不接受逻辑或内容风险请求。
Cloud `input` 只含 `snapshot_sha256/language/checks/offset_unit/blocks`；
`contract_version=article_review_cloud_request.v1` 位于外层 runtime envelope。
Addon 的公开 facade 接受含契约名的本地请求，验证后移除 input 中的契约名。

结果精确字段为 `contract_version/snapshot_sha256/checks/findings/invalid_finding_count/result_limit_reached`。
`language` 仅为 `completed/none` 或 `partial/invalid_output`；`logic` 与
`content_risk` 固定 `not_checked/not_supported`。提示只接受
`sentence/punctuation/reference/redundancy`，`check=language`、
`severity=warning`、`uncertainty=context_needed` 和固定只读 action policy。
无效身份/结构使整个 Cloud 区段不可用，无效引文使整条提示隐藏且报告部分完成。

独立 `article_review_snapshot`、原样采集、运行前说明、覆盖计数和单调编辑
代次失效机制已在候选接线中实现；见[当前消费实现](article-review-report-v1.md)
及[离线跨仓/浏览器验收](article-language-integration-acceptance-2026-10-30.md)。
下文包含早期的**总体设计与后续扩展提案**，尤其逻辑/风险的状态、字段与示例，
不得据此放宽当前严格语言 schema。逻辑示例不是当前可执行请求。

## 目标与职责

对作者主动提交的文章文本给出语句、逻辑、正文风险疑点。当前
[本地报告](article-review-report-v1.md)继续如实声明未实施的检查。
只有新的分析路径在命名 revision 上通过测试后，才可把相应项目标为已检查。

| 所有者 | 拟承担职责 |
| --- | --- |
| Toolbox | 作者主动运行现有发布预检；构建瞬时文本快照/位置映射；校验并展示报告；过期提示和只读定位 |
| Cloud Addon | 现有签名传输、受限请求与响应验证、`pii`/`no_store` 路径；不拥有提示词或 WordPress 写入 |
| Cloud | 模型/分析配置、结构化只读分析、超时与拒绝结果；无 WordPress 发布/更改权 |
| 作者 | 判断建议是否成立，自行修改文字、符号、标点与多媒体，使用原生保存/发布 |

不增加本地能力/工作流/审批注册表、后台队列、持久忽略词典或模型选择器。
不改变排版契约。提示没有 `replacement`、`fix`、补丁、全文改写、Apply 或写能力。
不增加发布阻断；“没有提示”不能显示成文章正确、无风险或已获批准。

## 触发、正文与隐私

- 沿用 `publish_preflight` 操作入口和现有逐文章权限检查；不恢复退役 intent，
  不放松路由能力，不在按键输入、保存钩子或后台任务自动调用。
- 实现接线时在运行前说明：本次选定文本将临时送 Cloud 检查；若受限或截断，
  显示范围。既有旧客户端未提供新快照时，返回该项未检查，不静默用摘要代替。
- 仅传本次明确范围的可见纯文本。媒体二进制、附件元数据、代码/HTML 属性、
  私有评论、用户信息、WordPress 对象 id、client id、文章 URL、密钥均不传。
  正文自身可能含个人信息，仍按 `pii`，不能称为脱密数据。
- `no_store` 必须覆盖新调用的请求、响应、provider、错误与观测路径：正文、
  原句、模型解释和原始输出不得进入日志、缓存、数据库或调试响应。只保留
  允许的无内容运行元数据；排障优先 Cloud `run_id`。第三方 provider 的保留
  政策/配置也要核对；仅设置本站响应头不能证明第三方未留存。
- 复用已有文本 runtime 边界，不新增索引、检索、外部搜索或事实核验调用。
  不把该路径接到暂停的 `cloud_inspection`、媒体优化或脱密摘要包。

## 拟议请求

只在内存中创建顺序文本块和本次映射：`b1 -> 当前 client id / 原始可见文本`。
Cloud 仅看到不含站点身份的 `block_ref`。标题/摘要如需检查，也以明确的
`kind=title|excerpt` 文本块提交；不推定存在或已覆盖。正文块用 `kind=body`。

请求字段：

| 字段 | 约束 |
| --- | --- |
| contract_version | 拟定为 `article_review_cloud_request.v1`，精确匹配 |
| snapshot_sha256 | Cloud 边界由规范化请求计算/核对并绑定结果；不是模型自报的真实性证明 |
| language | 首版显式 `zh-CN`，不以自动识别或模型宣传替代中文评估 |
| checks | 非空子集：`language`、`logic`、`content_risk` |
| offset_unit | 固定 `utf16_code_units`；与浏览器字符串切片一致 |
| blocks | 唯一 `block_ref`、`kind`、原样 `text` 的有序数组 |

初始资源上限提议：80 块、每块 1,600 个 UTF-16 单元、合计 30,000 个 UTF-16
单元、正文文本合计 UTF-8 不超过 100,000 字节；任一边界先到即停止采集。
这是一组待 Cloud/Addon 联合验证的上限，不宣称等于旧上下文的字符计数。
取前缀时不得切开代理对；不能删除内部空白、改标点、NFKC/NFC 归一化后再
声称引文来自原文。被截断块保留边界标记；代码/媒体等不参与文字检查的块
明确列为排除。空输入或无法构造一致快照时不发模型请求。

对外部传输的规范快照固定为以下有序数组的 UTF-8 JSON，无额外空白，
保留 Unicode、不转义斜线：

```json
["article_review_cloud_request.v1","zh-CN",["language","logic"],[["b1","body","原样文本"]]]
```

`checks` 按 `language,logic,content_risk` 排序，块按文章顺序。哈希只绑定这份
提交内容；本地另记当前文章身份、完整原稿、标题/摘要及排除/截断信息，不能把
提交前缀的哈希当整篇文章哈希。Cloud 校验哈希与实际请求一致后再分析。
更换文章、改正文/标题/摘要或块身份时，即使后来 Undo 恢复文字，也使在途结果
失效；实现必须使用订阅/变更代次检测，不能只在响应时比较最后的字符串。

现有 `content_blocks` 已经去标签/清理/截断，不能直接冒充这份原样快照。
当前候选已使用独立采集器和严格 Addon/Cloud schema；后续扩展仍须另行验证。

## 拟议结果与证据

结果只包含精确契约名、Cloud 绑定的 `snapshot_sha256`、每类检查状态、
最多 12 条 `findings`、`result_limit_reached`。所有对象字段采用白名单；
额外的更正、执行、HTML、URL 或原始模型输出字段应拒绝，不向界面透传。
Cloud 从 provider 输出提取后验证，Toolbox 再独立核对证据与当前原稿。

每条提示：

| 字段 | 约束 |
| --- | --- |
| id | 本次唯一、长度受限的提示 id，不是审计/批准记录 |
| check | 已请求的 `language` / `logic` / `content_risk` |
| kind | 语句：`sentence`、`punctuation`、`reference`、`redundancy`；逻辑：`contradiction`、`numeric_consistency`、`inference_gap`、`needs_verification`；风险：`sexual_context_review` |
| severity | `info` / `warning`，仅供作者排序，不是发布允许级别 |
| evidence | 1–3 条 `{block_ref,start,end,quote}`，引文最长 240 UTF-16 单元 |
| reason | 最长 600 UTF-16 单元，解释疑点而非命令 |
| review_direction | 最长 400 UTF-16 单元，建议作者核对什么，不给替换全文 |
| uncertainty | `clear_signal` / `context_needed`，模型自报，未经校准不能显示成准确概率 |
| context | 仅风险项必需：`medical`、`educational`、`news`、`literary`、`commercial`、`unknown` |
| action_policy | 固定 `operator_review_only_no_insert` |

`start` 为从 0 开始、包含的 UTF-16 偏移，`end` 为不包含的结束偏移。
必须满足 `0 <= start < end <= text.length`，不得切开代理对，且原样
`text.slice(start,end) === quote`。不能先做空白/标点/Unicode 归一化来凑匹配。
例如 `😀原文，，保留。` 的 `，，` 范围为 `[4,6)`；重复原句可用范围明确
第几处，但实现前现有唯一引文导航仍保持原规则，不自动放宽。

逻辑矛盾必须给出至少两处不同的证据；声称跨段时必须来自至少两个 body 块。
数字/口径提示要解释时期、单位、总体/样本是否一致。`inference_gap` 给出证据
与超出它的结论。`needs_verification` 表示材料不足，不能自称外部事实已核验。
医学/教育等标签不自动排除风险，出现敏感词也不自动构成风险；都要看原文。

各类检查状态独立为 `completed`、`partial`、`not_checked`、`failed` 或 `refused`，
并说明缺口。每个状态对象只含 `status` 和 `reason_code`；后者白名单为
`none`、`not_requested`、`not_supported`、`input_truncated`、`provider_refused`、
`timeout`、`invalid_output`、`analysis_incomplete`，不回显原始 provider 错误。
只有被请求且已完成相应部分分析的类别可带结果；未请求/失败/拒绝不能伪装
为完成且零条提示。`partial` 可以显示已验证的提示，但必须显示缺口。
模型的完成声明不证明其穷尽理解了文本，界面应表述为“本次范围内分析完成”。

未知 block、错误偏移、伪造引文或不合类别的证据使该提示不可展示/定位，
计入内容为空的无效提示计数，报告降为 `partial`。非法顶层身份/结构、快照
不一致、任意写入字段或超限输出使整个 Cloud 区段不可用；本地提示继续可用。
拒绝/超时不自动重试、不回退到未声明的模型或放宽 schema，也不阻止作者发布。
解释、方向和引文都以纯文本显示，不执行或渲染模型提供的 HTML、Markdown
链接、脚本或工具指令；只显示证据来自原文不代表解释本身一定正确。

## 覆盖、新鲜度与界面

覆盖范围由客户端/服务端实际提交清单推导，不采用模型虚构的“全文”计数。
应显示：可采集与已提交块数/文本单元、截断块、排除类型、每项分析状态、
有效提示数、是否达到结果上限、无效提示数。不能声称已找到的总问题数，
因为未返回的问题没有可验证总量。逻辑分析只覆盖已提交窗口，跨窗关系未核验。

每条提示显示原句、疑点、检查方向；跨段提示同时显示相关原句。定位只选择
本地映射回的块，不执行文本替换。点击时重新核对当前文章身份、变更代次、
完整原稿快照、块身份和引文。报告过期后保留说明但禁用定位，作者可重新运行。
允许本次会话忽略一条提示；不新增持久词典、批准状态、采纳训练记录或自动反馈。

Cloud 结果拟作为独立只读区段加入现有发布预检，不覆盖
`sections.local_article_checkup` 或把其 `bounded_local_heuristics` 冒充 AI 覆盖。
拟议 `article_review_cloud_result.v1` 不更改现有 `article_checkup.v1` 的含义。
段落/媒体/格式化状态与报告始终分离；本功能没有编辑或保存调用。

## 合成请求/结果示例

以下只演示边界和报告形状，不是运行结果。原句中的 `50%` 保留在证据中；
报告没有提供可执行修正。未请求的语句/风险检查明确未检查。

请求：

```json
{
  "contract_version": "article_review_cloud_request.v1",
  "snapshot_sha256": "675e35da03c5b4f7d6b80fd85e5a0f236350825da1c77a6b6502466d54e5a22f",
  "language": "zh-CN",
  "checks": [
    "logic"
  ],
  "offset_unit": "utf16_code_units",
  "blocks": [
    {
      "block_ref": "b1",
      "kind": "body",
      "text": "数量从100增加到120，因此增长率是50%。"
    }
  ]
}
```

结果：

```json
{
  "contract_version": "article_review_cloud_result.v1",
  "snapshot_sha256": "675e35da03c5b4f7d6b80fd85e5a0f236350825da1c77a6b6502466d54e5a22f",
  "checks": {
    "language": {
      "status": "not_checked",
      "reason_code": "not_requested"
    },
    "logic": {
      "status": "completed",
      "reason_code": "none"
    },
    "content_risk": {
      "status": "not_checked",
      "reason_code": "not_requested"
    }
  },
  "findings": [
    {
      "id": "f1",
      "check": "logic",
      "kind": "numeric_consistency",
      "severity": "warning",
      "evidence": [
        {
          "block_ref": "b1",
          "start": 2,
          "end": 12,
          "quote": "从100增加到120"
        },
        {
          "block_ref": "b1",
          "start": 15,
          "end": 22,
          "quote": "增长率是50%"
        }
      ],
      "reason": "相同基数下，原文的数量变化与增长比例可能不一致。",
      "review_direction": "请作者核对原始数量、统计口径与增长比例。",
      "uncertainty": "clear_signal",
      "action_policy": "operator_review_only_no_insert"
    }
  ],
  "result_limit_reached": false
}
```

## 验证样本与实施顺序

[中文合成样本种子](article-review-evaluation-seeds-v1.json)是人工编写的开发样本，
不是真实客户稿件、独立金标准或模型测试结果。正例核对能否给出有证据的提示；
反例核对是否无故指责正常语境。另行测试结构/身份/Unicode/重复引文/修改后
过期/拒绝/超时/注入/多媒体不变，不用语言质量分数掩盖协议失败。

1. **语句切片**：Cloud/Addon 的真实 schema、接线和 `no_store` 证明先行；
   在 Eval Lab 用命名模型/提示词/源 revision 跑中文正反例和未见样本，再接入
   Toolbox 的独立报告区段与只读定位。其他两类明确为 `not_checked`。
2. **逻辑切片**：增加同段数字及跨段双证据；先验证正常时期/单位差异不被
   报错，再验证应发现的矛盾。不给外部事实正确性承诺。
3. **风险切片**：先验证医学、教育、新闻、文学的正常敏感语境和不确定语境，
   再验证非图像的成人色情商业招揽等有明确线索的样本。只提示，始终人工判断。

每阶段独立列出逐例输出、预期提示命中、无谓提示、漏报、引文验证失败、
拒绝/超时、覆盖、延迟与资源消耗。共享种子只作开发回归，另建不可见于调参的
中文留出集；不预填准确率或用模型自评当人工金标准。

必要确定性门：严格 schema/证据校验、无写入、无正文留存、权限与新鲜度、
本地失败仍可用、PC/移动端定位与过期状态。语义质量门在每阶段 Eval Lab 的
命名样本/任务定义中固定后执行，不因一次少量样本全对就宣称全面能力。
跨仓接线完成后跑 exact-SHA `composer quality:matrix:run`；只有需要实际 M4
证据时才用显式 M4 门，不让矩阵部署或同步运行时。更新跨仓兼容文档的实际
契约状态，不增加 Addon 版本号运行时硬门。本草案不授权上线或生产数据试跑。

## 本轮变更与回退

本轮实现仅增加 Cloud/Addon transport 与 Toolbox 只读报告消费；没有数据库、
文章迁移、自动修改或发布路径。Cloud 端已验证结构化证据，Addon 与 Toolbox
仍需各自门禁和跨仓 exact-SHA 复核；模型质量与作者采纳率仍未被本轮证明。
