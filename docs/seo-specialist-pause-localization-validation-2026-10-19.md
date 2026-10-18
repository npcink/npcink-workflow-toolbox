# SEO 候选的独立汉化修复与发布续记

最终收口：汉化PR292及REST master回补PR293已独立评审、CI、合并；当前源码候选和发布方案以[最终候选记录](seo-specialist-pause-final-candidates-2026-10-19.md)为准。下文保留当时准备状态。

日期：2026-10-19，Asia/Shanghai。仅准备候选并验证，不部署生产，不发布目录版本。
这是独立的汉化评审层；原 SEO 暂停策略、旧配置和历史数据保持不变。

## 候选、评审和精确发布物

| 层 | 评审 | 源码与状态 |
| --- | --- | --- |
| Cloud SEO | [草稿 PR #1143](https://github.com/npcink/npcink-ai-cloud/pull/1143) | e8877e19c31779e4a54bed73fede8c5ca05b918c，未改动 |
| Addon SEO | [草稿 PR #291](https://github.com/npcink/npcink-cloud-addon/pull/291) | 26e58bb62ccf27e9359a2dd354750c7401981055，未改动 |
| Addon 汉化增量 | [独立草稿 PR #292](https://github.com/npcink/npcink-cloud-addon/pull/292) | c2ccd22ac5758fe042d537ad3d25d3429d3987e8，base 为 PR291 的候选分支，仅展示汉化差异 |
| Toolbox SEO | [草稿 PR #278](https://github.com/npcink/npcink-workflow-toolbox/pull/278) | 应用源码 437925233e612dfb12cbb6eab6c3d582dc6ee1a7；后续只有发布材料文档 |

所有 PR 保持 draft、auto-merge 关闭。没有合入 production、发行基线或 master。
汉化源自已合入 master 的 PR289；本次另修复浏览器发现的 REST 消息路径，
该新增 REST 增量仍需在正式发行前经独立评审回补 master，不能把旧发行树合回 master。
使用普通 git push 和 draft PR，保留完整模板，避免 publisher 自动申请合并。

两层评审通过后拟选 Addon 0.4.3 组合候选 ZIP（尚未公开发行）：
`npcink-cloud-addon-0.4.3-seo-i18n-candidate.zip`，52 文件，SHA256
`6576603c6272934936b7dd840d00257aeef14fc493108c78615516e5cdb123f6`。
与 SEO-only ZIP 相比，只变更 `includes/class-ai-plugin-localization.php` 和
`assets/ai-plugin-localization.js`。旧 SEO-only ZIP 的 `e66b2f1d...` 仍保留，不覆盖旧证据。
不能仅用 0.4.3 版本号识别这两份候选，必须核对完整 hash。

Toolbox 0.6.1 包保持 SHA256
`7ff6a5c4d48bb833963272e80528c81932460dedc5005c301bf84de307efabd3`。
Cloud 生产部署解析器本日再次确认实际稳定基线仍为
`b8cc6049da7d1c33cbfffc9f8e5521f332a1adb5`，DeepSeek run39122857578；
不是把 production 分支位置当成已部署证据。Cloud 相对该基线仅 11 个 SEO 路径，
backend-only，无 migration、frontend image 或 runtime config 改动。

## 修复与严格门禁

旧候选和官网 0.4.1 的同源审计均为 12 个固定 UI + 6 个 default-domain 缺口。
此次增加遗漏固定 UI 文案，并为 AI 1.4.0 内置 DataViews 的 16 个源 key 提供限域兜底：
只在 AI 设置、请求日志两屏生效，匹配精确复数对和上下文；官方译文优先。
不将 AI 全字典注册到 default 域，不翻译动态 Ability 元数据。

实际 AI 1.4.0 扫描：998 个 ai-domain 源字符串，681 条 shim，fixed UI 0，
未覆盖 default-domain 0，严格 UI 缺口 **18 → 0**。余下 333 条分组经检查为
动态 Ability 元数据140、结构/JSON说明184、提示词9；没有新增 ignore 或放宽门禁。
字典和源代码审计不能单独作为实际页面翻译证据。

浏览器补验发现 REST 导入版本错误仍是英文：REST 不处于 is_admin，UI 直接使用
服务端 message。新增响应兜底仅匹配 POST `/ai/v1/settings/import` 的
unsupported_schema_version，以及 POST `/wp-abilities/v1/abilities/ai/slug-generation/run`
的 no_results 固定错误；要求现有对应权限及中文用户 locale，保持 route/code/status/
权限/数据字段和官方译文不变。六个真实 WordPress response-filter 用例覆盖目标路径、
其他路由、官方译文、成功响应及匿名请求；无真实 Provider 调用。

PHP lint/契约/boundary、PHPStan、WP.org guard、21 项 JS 测试、POT、最终 ZIP build/verify
及严格 Plugin Check 通过。最初 release:verify 因未设置 AI_PLUGIN_PATH 中止于审计，
此前 test:all 已成功；随后选择实际 AI 1.4.0 树逐项完成剩余门禁，没有将原聚合调用说成通过。
本次 REST 修复改变 PHP 后重新执行相关源码、包和浏览器检查；未重复不变的 Cloud/M4 大门禁。
CI 以最终 c2ccd22 的有效检查记录为准，不能沿用最初 84ff894 的全绿状态。
Playground 不因本地化增量单独触发：bootstrap、激活、公开 connector API、默认凭据及
WP/PHP 基线未变；CI 仍执行原有 activation 和 WordPress AI 兼容性 smoke。

## 实际 ZIP 浏览器验收

独立站 `/Users/muze/wp-sites/npcink-seo-pause-validation/public`；WP7.1.3、AI1.4.0，
PC Chromium 1440×1000，实际安装最终 ZIP，逐文件 byte 比较。只操作本任务新建 fixture。

- zh_CN：真实请求日志菜单的排序、顺序、密度三选项、每页条目、属性、筛选和空状态为中文。
  能力浏览器帮助的两段说明、Markdown 描述、评论价值筛选标签、短文翻译门槛为中文。
- 通过实际文件选择器导入无效导出文件，错误提示为中文；版本999的空 providers/settings
  文件被实际 REST 拒绝（422），提示为“不支持的结构版本：999。”，不改写设置。
- 真实 wp.i18n 运行时验证12条固定 UI、两个复数对（1和4）、错误字段上下文。
  编辑器无 DataViews 兜底钩子；其他域/上下文不改变；官方译文不被覆盖。
- en_US：没有 Addon 汉化 payload/钩子，英文显示保持。页面无横向溢出。
- 罕见的空 slug/模板缺内容区块等分支以运行时/响应过滤器测试验证，没有调用模型制造失败；
  不把这部分说成所有异常都通过真人点击重现。
- 422 是有意触发的拒绝请求，会留下浏览器网络 error；编辑器仍有两条既有宿主弃用/样式 warning。
  最初请求日志403来自 fixture 使用错误 feature id，改为 ai-request-logging 后可访问；未改产品权限。

浏览器证据不等于真人验收、外部模型质量或生产观察。付费调用0、M4操作0、生产操作0。

## 部署顺序、影响与回滚

顺序仍为 **Cloud → Addon → Toolbox**，每层完成后再推进下一层。

1. Cloud：最终保护 CI、fresh scan、bundle、preflight 和回滚归档确认后，按最终 production
   SHA 的明确授权执行。仅 backend 切换，旧插件调用专项会被拒绝；通用能力须做 smoke。
   构建/扫描/传输/验收预算15–30分钟，切换预留1–3分钟，均为计划估算。
2. Addon：两份独立评审通过后分发上述精确组合 ZIP。隐藏专项入口并本地拒绝调用；汉化只
   改固定 UI 及两个窄错误响应，旧 options/history 保留。每站安装验收预留5–10分钟。
3. Toolbox：安装原0.6.1候选，隐藏专项入口；通用保存不得清除8组旧SEO字段。每站再留
   5–10分钟检查，实际总时长取决于站点数量和目录传播，未测量。

只回滚汉化：恢复保留的 SEO-only Addon0.4.3 ZIP `e66b2f1d...`，保持SEO暂停；
版本相同，必须按完整hash和文件检查确认，不能依赖普通自动升级降版。
整轮回滚：Cloud参考点b8cc6049，须先证明当前主机仍保留对应可恢复镜像/bundle/config；
插件使用归档官方Addon0.4.1、Toolbox0.6.0 ZIP。不得删除options、历史或执行卸载清理。
旧插件会恢复部分入口，Cloud暂停guard应保留；技术回滚不自动授权恢复SEO业务。

正式发布前仍需：独立评审、REST增量master回补、非AI验收锚点、官网版本占用复核、
fresh安全扫描/准确bundle/preflight、生产回滚可恢复性确认，以及最终SHA和插件分发的明确授权。
本任务不会因候选全绿触发上述生产动作。

## 证据

本次证据位于 `/Users/muze/.codex/task-receipts/npcink-cloud-addon/2026-10-19-ai-ui-localization/`，
包括最终PR/check清单、严格审计、包hash/byte比较、响应测试、截图和隔离站清理回执。
10月18日集成、M4精确Cloud候选与SEO保存兼容证据继续按原 revision/hash 复用，
本日没有重新操作M4，也没有更改 Cloud/Toolbox 应用源码。
