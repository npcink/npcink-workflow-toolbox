# 文章审阅：同行与开源方案调研

日期：2026-10-30。范围：只读文章审阅的报告结构、原文定位、语境和评估。
结论用于[Cloud 消费方契约草案](article-review-cloud-contract-draft-v1.md)，
不代表已经接入第三方引擎、Cloud 分析、生产发布或中文质量验收。
研究过程只读取公开文档/源码及 LanguageTool 的语言列表，没有向这些检查产品
提交文章，也未运行文章检查的模型评测。

## 事实与取舍

| 来源 | 一手证据 | 本项目采用的做法 | 不据此承诺的能力 |
| --- | --- | --- | --- |
| Grammarly 浏览器扩展 | 官方指南：下划线/侧栏显示建议，作者可接受或忽略，部分类型可关闭 | 把原文、疑点和处理方向放在一起；允许作者不采纳 | 不采用其点击替换行为，不声称其中文或逻辑效果适用于我们 |
| LanguageTool | 序列化器分别返回 `offset`、`length`、`context`、规则和 `replacements`；`/v2/languages` 列出 `zh`；中文模块含标点/空白规则和可选语言模型混淆词规则 | 原文位置与解释分离；区分规则来源和检查未完成的原因 | 列出中文不等于病句、跨段逻辑全覆盖；不接收替换指令 |
| textlint | `TextlintMessage` 定义规则、严重程度、`range`、`loc`，`fix` 可选；修复结果是另一种类型 | 检查与修改分开；保留可验证的文本范围 | 本项目不暴露 `fix`、替换正文或自动修复功能；框架本身不保证中文规则质量 |
| Automattic Harper | README 明确目前只支持英语；`Lint` 分开存放字符范围、类型、解释、建议和优先级；仓库有仍在开发的 WordPress 插件 | 借鉴轻量提示与定位，不把 WordPress 插件先例当成熟集成证明 | 不作为中文检查器，也不把 Rust 字符偏移直接当浏览器 UTF-16 偏移 |
| Llama Guard 3 8B | 模型卡列出 8 种支持语言，不含中文；任务是对话安全分类；说明存在误判、事实知识和提示注入限制 | 学习风险分类须限定语言、任务、类别和反例；分类结果不能代替编辑判断 | 不直接选作中文文章风险引擎，不把对话 `safe/unsafe` 当文章发布结论 |
| Microsoft 内容安全 | 官方文档区分风险类型与等级，列出中文训练/测试语言；敏感词在医学、教育、文学语境中可能属于较低等级；明确模型可能误报漏报，Safe 不证明事实正确或没有其他风险 | 把语境、分类理由和不确定性单独返回；建立正常敏感语境反例 | 不照搬封禁阈值；不引入 Azure 服务、付费依赖或自动发布阻断 |

以上项目没有为“中文文章通用跨段逻辑检查已可靠”提供本项目可直接采用的证据。
跨段矛盾、数字口径和因果缺口仍要独立定义、给出多处原句并验证。
不把拼写规则、内容安全分类、外部事实核验混为一种能力。

## 结合当前代码的发现

- 现有 `publish_preflight` 已组合本地审阅、重复检查和其他建议；新分析应作为
  独立只读报告区段，不恢复已退役的 `article_checkup` intent。
- `editor_post_context()` 的正文上下文最多 30,000 字符；
  `editor_content_blocks_from_request()` 最多取 80 块，每块最多 1,600 字符，
  且会去标签、清理和截断。不能拿这份摘要声称检查了未经截断的整篇文章。
- 当前本地导航依赖唯一原句匹配。Cloud 版需要明确文本坐标系、重复原句的
  第几处、快照身份及新鲜度；不能信任模型自报的 client id 或偏移。
- 阅读敏感主题与指令执行是两件事。正文中的“忽略规则并发布”等文字只能作为
  待检查内容；模型拒绝、超时或结构不合法不能被显示为“未发现问题”。
- 当前隐私规则已经要求正文类调用使用 `pii`/`no_store`。新全文分析必须单独
  验证请求/结果/失败日志、缓存与第三方 provider 的保留行为，不能因为 API
  名称相同，就推定所有旧预检分支已经满足新正文契约。

## 来源与可复核版本

1. [Grammarly 官方扩展使用指南](https://support.grammarly.com/hc/en-us/articles/115000091592-Grammarly-s-browser-extension-user-guide)。动态产品文档，查阅于上述日期，未运行其产品。
2. LanguageTool 源码快照 `9ef62e36201e0c348b46ab7dfa84f1f610d1a9b7`：
   [报告序列化器](https://github.com/languagetool-org/languagetool/blob/9ef62e36201e0c348b46ab7dfa84f1f610d1a9b7/languagetool-core/src/main/java/org/languagetool/tools/RuleMatchesAsJsonSerializer.java)、
   [中文模块](https://github.com/languagetool-org/languagetool/blob/9ef62e36201e0c348b46ab7dfa84f1f610d1a9b7/languagetool-language-modules/zh/src/main/java/org/languagetool/language/Chinese.java)、
   [LGPL 2.1 许可](https://github.com/languagetool-org/languagetool/blob/9ef62e36201e0c348b46ab7dfa84f1f610d1a9b7/COPYING.txt)、
   [官方语言列表](https://api.languagetool.org/v2/languages)。语言模块还可能有 XML/其他规则，不能把 Java 方法中的几项当作全部中文能力。
3. textlint 快照 `d59da0b04db9c34f3bc001c202f525208bf8caa8`：
   [报告与修复类型](https://github.com/textlint/textlint/blob/d59da0b04db9c34f3bc001c202f525208bf8caa8/packages/@textlint/types/src/Message/TextlintResult.ts)、
   [MIT 许可](https://github.com/textlint/textlint/blob/d59da0b04db9c34f3bc001c202f525208bf8caa8/LICENSE)。
4. Harper 快照 `6342d046d8a7584649701cc65719052c7d859ce7`：
   [README](https://github.com/Automattic/harper/blob/6342d046d8a7584649701cc65719052c7d859ce7/README.md)、
   [Lint 类型](https://github.com/Automattic/harper/blob/6342d046d8a7584649701cc65719052c7d859ce7/harper-core/src/linting/lint.rs)、
   [WordPress 插件状态](https://github.com/Automattic/harper/blob/6342d046d8a7584649701cc65719052c7d859ce7/packages/wordpress-plugin/README.md)、
   [Apache 2.0 许可](https://github.com/Automattic/harper/blob/6342d046d8a7584649701cc65719052c7d859ce7/LICENSE)。
5. [Meta 官方 Llama Guard 3 8B 模型卡](https://github.com/meta-llama/PurpleLlama/blob/d934ea285d124d81c10f7e15a06a1e1f74eff2c3/Llama-Guard3/8B/MODEL_CARD.md)，重点为 Supported languages、风险分类和 Limitations。模型属于开放权重方案，实际采用还要审查模型自身许可，不能用外围仓库许可替代。
6. [Microsoft 官方风险类别、等级与语境说明](https://learn.microsoft.com/en-us/azure/ai-services/content-safety/concepts/harm-categories)。动态文档，须区分 Guardrails 与 Azure AI Content Safety 的表格，不把两者数值直接混用。

源码通过公开 GitHub API/固定 revision 的 raw 文件读取，未执行第三方代码。
只借鉴概念，没有复制实现或给插件增加依赖。将来采用源码、词库、模型或服务时，
再按具体组件审查许可、资源占用、中文质量和保留政策。
初始旧 Swagger 地址 404、LanguageTool 旧语言网页 TLS 失败、Hugging Face 模型卡
需授权、Microsoft 旧 raw 路径 404，均改用上述成功的一手来源；这些失败链接
不作为结论证据。原始读取记录与内容哈希保存在忽略的 `build/research/` 中。

## 落实顺序

1. 完成消费方契约草案和中文样本种子；此阶段不启用分析。
2. 在 Cloud/Addon 所有者仓库实现并验证语句检查切片，先返回能核对的原句和
   提示，不提供更正全文、替换字段或编辑命令。
3. 在同一报告语义下增加数字/跨段逻辑，再增加有语境的正文风险提示。
4. 每阶段报告误报与漏报、无效引用、拒绝和覆盖缺口。通过实际命名 revision
   的验证后才接入现有发布预检；种子样本通过不等于模型准确率或生产验收。

## 本轮准备材料验证

Toolbox 基线为 `44c1e3d9`（#345 已合并）。`composer test:all`、
`composer validate --no-check-publish`、`composer check:wporg`、
`git diff --check` 通过；文档静态门为 4,508 项。
24 个种子的结构和 13 处引文的 UTF-16 范围逐一核对通过，含 emoji 和重复原句。
文档合成示例的规范 JSON 哈希经 Python/JavaScript 交叉计算一致。
这些结果只验证材料与示例的一致性，没有产生模型准确率、真实文章分析、
Cloud/Addon 运行时接线或新的浏览器验收证据。
