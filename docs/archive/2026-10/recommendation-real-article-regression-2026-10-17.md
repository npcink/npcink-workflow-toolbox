# 真实文章推荐回归 — 2026-10-17

状态：开发者回归，非人工 gold set，非生产验收。范围为 Toolbox 推荐观测投影
与浏览器验证；保持 [Ranking Baseline v1](../../recommendation-ranking-baseline-v1.md)
的 `related_content.v1` / `internal_links.v1` 不变。

## 环境与方法

- 实际站点 `https://magick-ai.local/`，复用 Addon 已有的 M4 preview 隧道。
  本轮没有同步、部署或 promotion M4，没有部署生产。
- 起点为 Toolbox `d253bdb110be353f959b27d5b6d93682db5b2df8`（PR #273）。
  本轮不修改 Cloud、Toolkit、Addon，不修改召回、source passages、权重、
  过滤、去重、Top K、rerank 或候选顺序。实际 `rerank_applied=false`。
- 选择 10 篇新增公开真实文章，另用 4207 / 18817 作控制样本。先以保存正文
  构造 bounded PHP context，执行两族检索；再逐篇用真实 Gutenberg 浏览器
  验证内链展示、已有链接校验、Apply、Undo、无正文持久化和反馈回执。
- PHP 解析块与浏览器 Gutenberg 块的投影不同，尤其旧 list/shortcode。
  不将这两种不同输入产生的候选差异解释成排序漂移。PHP 源段中的 shortcode
  字符曾成为候选，但真实浏览器 9358 未复现；未据此加入推测性过滤。
- 以下结果是结构安全与开发者定性观察。没有独立人工标注源文章、目标文章和
  锚文本三元组，不计算人工 Precision、Recall、自然度或采纳率。

## 样本与结果

“相关/内链”列为 PHP 两族检索候选数；“可插入”列来自浏览器对当前可见候选
  的实时 Apply 检查，与后端候选数不等同。所有检索可用且来源为 Cloud vector。

| 文章 ID | 内容 | 相关/内链 | 浏览器可插入 | Apply / Undo | 浏览器反馈回执 |
| --- | --- | --- | --- | --- | --- |
| 19025 | intro.js 网站引导 | 6 / 8 | 4 | 2 / 成功 | 5 / 5 |
| 14593 | Advanced Custom Fields | 6 / 8 | 0 | 安全阻止 | 2 / 2 |
| 11926 | 个人网站存在的意义 | 6 / 8 | 7 | 2 / 成功 | 5 / 5 |
| 7957 | 前端面试手册 | 6 / 8 | 3 | 2 / 成功 | 5 / 5 |
| 9358 | Alvar 摄影作品集主题 | 6 / 8 | 7 | 2 / 成功 | 5 / 5 |
| 5825 | Calibre Web 书籍 CMS | 5 / 7 | 4 | 2 / 成功 | 5 / 5 |
| 7201 | No Category Base | 6 / 8 | 2 | 2 / 成功 | 5 / 5 |
| 22055 | Query Monitor | 6 / 8 | 5 | 2 / 成功 | 5 / 5 |
| 21807 | Stb Image Hosting | 6 / 8 | 5 | 2 / 成功 | 5 / 5 |
| 280965 | 短促销文 | 2 / 8 | 2 | 2 / 成功 | 5 / 5 |
| 4207 | YARPP（控制） | 6 / 8 | 0 | 安全阻止 | 2 / 2 |
| 18817 | CSS-Inspiration（控制） | 6 / 8 | 1 | 1 / 成功 | 4 / 4 |

合计 12 篇浏览器通过，10 篇完成 19 条 Apply 和 10 次 Undo。2 篇没有安全匹配，
正确保持 review-only；4207 包含已有链接与文章链接密度限制。Apply/Undo 仅改变
当前编辑器状态，原文数据库正文不变。53 条浏览器事件为 12 impression、
12 copy、19 applied-to-editor、10 undone，均 HTTP 200、accepted 且有 event id。

部分文章的历史外站图片不可达或附件不存在；4207 另有旧 `core/code` markup
验证警告。按资源来源检查，Toolbox / Cloud Addon 请求无 HTTP 错误；不声称
所有文章的控制台或外部资源零错误。

## 相关性与锚文本观察

- 19025 首条为 20974「intro.js 引导插件 - JS 实战」；11926 返回建个人博客、
  为什么做网站等文章；21807 返回 Lsky / Joe / SM.MS 图床；7201 首条为去掉
  category 前缀的教程。这些目标有明确主题联系，仍不等于人工 gold 命中率。
- 9358 返回多款 WordPress 主题，其中包含作品集主题，也有通用多用途主题。
  5825 返回 filebrowser、Piwigo、Grav 等广义 CMS，eLibPro 更接近书籍管理。
  同类目相近不足以保证读者下一步需求相同。
- 280965 仅有的两个相关结果为「微漫 app 的脱敏代码」「用户协议」，与奶茶促销
  的阅读目的弱相关。其内链 `即可享受`、`用户注册` 可以物理插入，但不能因此
  认定为优质推荐。
- 真实浏览器中有 `intro.js`、`JavaScript`、`插件开发者`、`数据库查询` 等
  可理解锚文本，也存在 `的HTML`、`css的`、`来的文章就`、`有一定的`、
  `一篇文章`、`的完美结合`、`售价：49美元` 等片段/泛化表达。精确 source match
  和安全 Apply 只证明位置可用，不能替代语义用途与语言自然度审核。
- 这些问题涉及 Cloud anchor/排序质量，不通过修改冻结 baseline、添加样本专属
  停用词或在 Toolbox 隐式重排解决。下一阶段先人工标注这些反例的源/目标/锚文本
  三元组，再在 Cloud 评估显式升级版本的方案。

## 本轮修复与 metadata-only 完整性

相关文章 Provider section 用 `status` / `retrieval_source`，原反馈 context 只读
内链的 `retrieval_status` / `candidate_source`，导致 12 个真实结果形状回放均把
已知来源写成 `retrieval_unknown / source_unknown`。修复仅在显式字段缺省时
读取 Provider 字段，得到 `retrieval_ready / source_cloud_vector`；显式证据
继续优先，真正未知仍保留 unknown。未改变候选或排序。

对 12 个真实 Provider 形状分别用实际 JS helper 构造 impression 和协议 copy，
24 / 24 经 Provider → Addon → Cloud 接受。该 copy 为协议测试，未冒充相关
文章 UI 点击；独立 related-content 编辑器 intent 已退役，本轮没有恢复它。

检查项：两族正确 `ranking_version`、候选动作的 `candidate_rank` / `score_bucket`、
真实 `retrieval_source` / boolean rerank、随机 recommendation session；impression
不伪造位置，批量 Undo 用 reason codes 保留各位置。反馈不携带正文、锚文本、
source match、标题、URL、prompt、精确分数、Provider blob、人工备注或 evidence refs。
Cloud 汇总报告 `raw_content_stored=false`、`raw_anchor_stored=false`、
`provider_output_stored=false`，两族 `orphan_event_count=0`。

最终浏览器与协议事件均带 `developer_smoke`，协议 copy 另带 `protocol_smoke`。
浏览器标记仅由测试 transport 添加，不改变产品事件 builder。当前 Cloud 汇总
仍包括开发流量，早期探索回归也存在未标记 smoke；不能使用累计 copy/apply/saved
率证明真实人工采纳，后续评估需按标记及测试会话排除。

4207 的 RichTextData 包含循环格式缓存，暴露原浏览器 `JSON.stringify(getBlocks())`
断言自身错误。测试改用 WordPress 序列化正文与 block identity，既验证 Undo 内容
和 block 恢复，又不序列化瞬态缓存；增加循环对象及内容/identity 变化的回归测试。

## 后续与验证

源码回归覆盖 Provider 字段投影、显式证据优先、未知证据、循环 RichTextData、
Undo identity/content；静态契约验证反馈字段回退及开发测试 Cloud receipt 门禁。

19025 原正文另复制到临时草稿 282816，验证 2 条 Apply 后数据库不变，显式
WordPress 原生保存后正文与可见编辑器内容一致，收到 `internal_link_saved_unchanged`
且保留 `candidate_rank_1 / candidate_rank_2`，5 / 5 反馈接受；该草稿已删除。
与上表 53 条及相关文章协议 24 条合计，本轮最终带标记验收为 82 / 82 条回执。

通过 `composer test:all`（静态契约 4218 项）、`composer lint:standards`、
`composer analyse:php`、`composer validate --no-check-publish`、
`composer check:wporg`、12 篇真实浏览器回归和上述临时草稿保存复验。

合并前五轴复核：字段回退不覆盖更具体证据；改动简单且局限观测投影；没有新
运行时、注册表或写入口；权限与隐私边界不变；无新增产品请求/排序开销。
测试 transport 只增加 bounded 标记与回执验证。实时核对发现仓库 OpenCodeReview
workflow 为 `disabled_manually`，采用发布器显式记录的未交付审查例外；仍必须
通过受保护分支的精确 head CI，不能把该例外当作 CI 或质量豁免。

开放质量问题已登记到 [Known Issues](../../known-issues.md)，不以本次结构验收关闭。
