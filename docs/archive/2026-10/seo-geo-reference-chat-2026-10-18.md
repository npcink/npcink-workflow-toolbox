# SEO GEO 下架参考会话归档

归档日期：2026-10-18（Asia/Shanghai）。

来源：[下架SEO相关功能](thread://01a1390b-c4af-7c81-bda0-5087fee297c6?hostId=local)。
会话标识：`01a1390b-c4af-7c81-bda0-5087fee297c6`，host：`local`。

本归档保存该会话已完成轮次中的用户要求和最终答复，省略工具日志、内部推理和重复进度。原会话另有一个被中断轮次，内容为本次三个仓库的暂停咨询，没有最终结论。

这是历史调查记录。下方“18 个 Git 项目”、能力位置、行号及状态属于原答复，不代表 2026-10-18 重新验收。特别是独立编辑器 `discoverability` intent 已退役，当前残留调用需按代码核对。当前三仓库核查与可恢复暂停建议见[SEO 相关功能暂停检查与建议](../../seo-feature-pause-review-2026-10-18.md)。

## 原用户要求

1. 系统的检查下，当前的各个项目中，哪些地方涉及到SEO，GEO的能力的，我准备下掉相关能力，给我建议

2. 下掉相关能力或者移除相关效果保证

3. 先检查，不要动代码，具体要不要下掉我还没想好

## 原会话最终答复

以下保留原答复，结论强度与范围均维持原样。

已完成只读检查，覆盖当前登记的 **18 个 Git 项目，没有修改代码、配置或文档**。以下结论基于当前本地源码，线上是否启用尚未核实。

**我的建议是：优先取消效果承诺和容易误导的评分，再决定是否下线建议、检查和元数据写入能力。** 目前确有 SEO/GEO 功能，但多数只能生成建议或检查内容结构，不能据此保证排名、收录、流量或 AI 引用。

具体分布如下：

| 项目 | 涉及的能力 | 判断 |
|---|---|---|
| **npcink-abilities-toolkit** | GEO 分析、SEO/GEO 缺口报告、发布准备度、文章优化报告；`set-post-seo-meta` 可实际写入标题和描述。[能力注册](/Users/muze/gitee/npcink-abilities-toolkit/includes/Packages/Core_Read_Package.php:576)、[写入实现](/Users/muze/gitee/npcink-abilities-toolkit/includes/Packages/Core_Write_Package.php:1555) | 核心能力层，完整下线必须处理这里 |
| **npcink-workflow-toolbox** | SEO/AEO/GEO 上下文、Discoverability 编辑器入口、标题/描述/slug/摘要建议、SEO 提案预览。[建议服务](/Users/muze/gitee/npcink-workflow-toolbox/includes/Provider_Discoverability_Service.php:83)、[提案交接](/Users/muze/gitee/npcink-workflow-toolbox/includes/Publish_Preflight_Service.php:86) | 最主要的用户入口 |
| **npcink-governance-core** | SEO 元数据提案的验证、审批和执行治理。[允许的能力](/Users/muze/gitee/npcink-governance-core/includes/Governance/Plan_Contract_Validator.php:1948) | 下线 SEO 写入时需要同步调整 |
| **npcink-ai-client-adapter** | OpenClaw 的 SEO/GEO/AEO 配方及调用、验收链路。[配方](/Users/muze/gitee/npcink-ai-client-adapter/docs/recipes/openclaw-content-discoverability-recipe.md:6) | 外部客户端入口 |
| **npcink-ai-cloud** | SEO 描述生成、slug 建议、元数据完整性巡检；图片搜索也有 SEO/AEO/GEO 词语转换。[运行时](/Users/muze/gitee/npcink-ai-cloud/app/domain/wordpress_ai_connector/runtime.py:285) | 提供运行时支持；[GEO task pack 已退休](/Users/muze/gitee/npcink-ai-cloud/docs/cloud-task-pack-boundary-v1.md:3) |
| **npcink-cloud-addon** | 桥接 WordPress AI 能力，包含 SEO 描述、slug、编辑建议等兼容文案和媒体投影。[相关文案](/Users/muze/gitee/npcink-cloud-addon/includes/class-ai-plugin-localization.php:497) | 连接与兼容层，单删文案不能停止调用 |
| **npcink-eval-lab** | SEO 描述质量样本、Discoverability 和 SEO handoff 验收。[验证脚本](/Users/muze/gitee/npcink-eval-lab/editor-followup/run-wp-trial.php:397) | 测试依赖，能力退役后需同步调整 |
| **npcink-site-toolbox** | 首页 TDK、文章/分类/标签 SEO、SEO 健康检查、搜索引擎验证。[设置入口](/Users/muze/gitee/npcink-site-toolbox/vite/admin/src/components/function/seo.tsx:36) | 独立的传统 SEO 模块，需要单独决策 |
| **Blog** | canonical、robots、sitemap、结构化数据等基础设施；另有 SEO 服务描述、GEO 策略材料和第三方产品效果话术。[页面描述](/Users/muze/gitee/blog/src/components/About/ReferDoc.tsx:52) | 技术设施与宣传内容应分别处理 |

**最值得优先处理的三个地方：**

1. **Toolkit 的“AI 可见度评分”。**
   [`ai_visibility_score`](/Users/muze/gitee/npcink-abilities-toolkit/includes/Packages/Core_Read_Package.php:3850) 实际由问题数量、问答数量等本地规则计算；正文长度、摘要和 FAQ 也参与检查。它没有测量 AI 是否引用或推荐内容。建议移除这种效果评分，或改成明确的“内容结构检查”，展示具体检查项。

2. **Site Toolbox 文档中的效果断言。**
   例如“[提升分类页的搜索引擎排名](/Users/muze/gitee/npcink-site-toolbox/docs-site/features/seo/category-seo.md:9)”和“[提高搜索点击率](/Users/muze/gitee/npcink-site-toolbox/docs-site/features/seo/home-tdk.md:35)”。这些是效果暗示，并非发现了严格的保证条款。建议改成可验证的功能描述，例如“设置分类页标题与描述”。

3. **Blog 的 GEO 表述和产品介绍。**
   [GEO 策略材料](/Users/muze/gitee/blog/seo/GEO.md:24)把“被 AI 引用/推荐”“AI 回答里出现你”作为效果目标；部分第三方产品文章还写有“[全面提高搜索收录及排名](/Users/muze/gitee/blog/src/content/npcink-batch-articles/2752.md:39)”。自家服务文案应优先审查，第三方历史介绍可以另行整理；仓库材料的存在也不代表已经对外展示。

你现在可以考虑三种范围：

| 选择 | 处理范围 | 影响 |
|---|---|---|
| **仅取消效果承诺，建议优先考虑** | 改写宣传、提示和评分解释，保留人工审核的建议与元数据功能 | 最小 |
| **取消 GEO/AEO 专项能力** | 移除 GEO 分析、专项摘要、相关入口与配方，保留普通元数据建议 | 中等 |
| **完整退役 SEO/GEO 产品能力** | 同步退出 UI、能力注册、工作流、提案路径、运行时映射和验收测试；Site Toolbox 单独退役 | 跨仓库契约变更 |

**建议保留网站基础设施。** Blog、site-xgif、sofile-web-page、frontend-showcase 中的 canonical、robots、sitemap、分享元数据，以及文章的 indexability 和预览页 noindex，承担 URL 规范化、抓取控制和发布隔离职责。图片 ALT 也有无障碍用途，不宜因为名称包含 SEO 就一起删除。

其余项目中，未发现独立 SEO/GEO 产品能力：Local Content Workbench 主要管理发布字段；WordPress-theme-ref 明确排除 SEO 插件功能；npcink-ad 的相关命中主要是预览页禁止索引；Eval 和 ai-doc 中还包含研究、测试及历史资料。

如果后续决定完整下线，应先停止入口和新请求，再按依赖链退役，保留历史元数据与文章内容。**目前更有依据的第一步，是去掉效果暗示和“AI 可见度”包装，保留可验证、由人工审核的内容辅助功能。**

## 本次需求的变化

用户现在计划暂停 SEO 相关开发、关闭相关入口，允许视情况保留能力，以便以后恢复，并要求先检查和给建议。当前文件归档不等于功能已关闭；“暂停后可恢复”也不等于完整删除所有 SEO、GEO 或 AEO 实现。

