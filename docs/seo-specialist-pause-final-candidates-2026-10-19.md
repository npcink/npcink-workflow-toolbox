# 三项目最终源码候选与发布方案（汉化独立合并收口）

生产前核验续记：见[预检与回滚材料核验](seo-specialist-pause-preproduction-preflight-2026-10-19.md)。候选promotion预检通过，正式部署预检仍未就绪；未部署。

日期：2026-10-19，Asia/Shanghai。状态：汉化及 REST 回补已经独立评审、CI、合并；
仅冻结待发布源码候选，没有生产 promotion、镜像构建、服务器更新、目录分发或 tag。
此文件取代前两份准备记录中的“汉化待评审/REST待回补”状态，原记录保留为历史。

## 候选与评审链

| 项目 | 最终源码候选 | 评审与发布状态 |
| --- | --- | --- |
| Cloud | e8877e19c31779e4a54bed73fede8c5ca05b918c | SEO草稿 PR1143，未修改；相对已安装 b8cc6049 为11路径 backend-only |
| Addon | fbb2b3393b55d173aa196d25171462c924c6c00e | 独立汉化 PR292 已 squash 合入 codex/release-addon-seo-i18n-20261019；包版本0.4.3未公开发行 |
| Toolbox | codex/release-seo-pause-20261018 的本文件提交；应用源码437925233e612dfb12cbb6eab6c3d582dc6ee1a7 | 草稿 PR278，运行源码/0.6.1 ZIP未变化；精确文档HEAD见同日外部交付清单 |

- [Addon SEO PR291](https://github.com/npcink/npcink-cloud-addon/pull/291) 仍为草稿，head
  26e58bb62ccf27e9359a2dd354750c7401981055，不将汉化混入原SEO差异。
- [汉化 PR292](https://github.com/npcink/npcink-cloud-addon/pull/292) 的新合并目标在26e58bb6
  建立，只有汉化增量；squash fbb2b339 的整树与已验收 c2ccd22 完全相同。
- [REST回补 PR293](https://github.com/npcink/npcink-cloud-addon/pull/293) 单独合入master，
  merge SHA 1b078b0aace27f40c517f7b514f20b1ea0a1970a，仅3文件/83行增量。没有把旧发行树反向合入master。
- 原通用汉化已通过master PR289；SEO已通过master PR290。三层审查记录独立可追溯。
- Cloud [PR1143](https://github.com/npcink/npcink-ai-cloud/pull/1143)、Addon SEO PR291、
  Toolbox [PR278](https://github.com/npcink/npcink-workflow-toolbox/pull/278) 仍不自动合并或部署。

## 最终包与证据

| 文件 | SHA256 | 文件数 |
| --- | --- | --- |
| Addon0.4.3组合候选 | 6576603c6272934936b7dd840d00257aeef14fc493108c78615516e5cdb123f6 | 52 |
| Toolbox0.6.1候选 | 7ff6a5c4d48bb833963272e80528c81932460dedc5005c301bf84de307efabd3 | 100 |

Addon从合并后干净源码重建，ZIP与已完成浏览器验收的组合包逐字节一致，实际隔离站安装
文件无差异。汉化相对SEO-only ZIP只改本地化PHP/JS两个发行文件；其余为测试/门禁/说明。
不能仅用同一个0.4.3版本号识别两份候选，必须核对完整hash。官网本日仍为Addon0.4.1、
Toolbox0.6.0；Addon0.4.2其他功能不在本候选，正式发行前仍需重新核对版本占用。

严格AI1.4.0 UI缺口18→0。确定性测试、最终ZIP严格Plugin Check、真实WP7.1.3/AI1.4.0
PC Chromium中英文验收沿用同日精确字节证据；12条固定UI、16个有界组件键、复数/上下文、
官方译文优先、其他页面/域隔离，以及实际导入错误422均有记录。REST错误码、状态、权限、
响应数据保持不变；未触发真实模型调用来制造空slug等异常。

独立只读审查者完整审查26e58bb6..c2ccd22，结论Approve、0阻断；另独立执行JS2/2、
diff检查和严格UI0。一次本地OCR在预算预检停止（2208规划token、0文件审查），明确记为
不可用证据；托管OCR原为disabled_manually，没有启用或重试。没有把OCR未执行写成无发现。
完整评审记录见PR292评论；REST回补与组合候选的运行时PHP/JS逐字节相同。

合并前相关CI全部通过（含PHP8.0/8.2/8.4、AI兼容矩阵、Release static、PHPStan及body）；
master合并后的CI和最终三仓库矩阵以同日交付JSON绑定最终revision，不以skipped检查替代。
Cloud未改源码，沿用e8877e19精确SHA完整CI与10月18日M4/签名HTTP集成证据，不重复构建。

## 发布顺序与影响

1. **Cloud先行**：重新解析实际部署基线，本日解析仍为DeepSeek b8cc6049、部署run39122857578。
   完成fresh scan、bundle、preflight和回滚归档确认后，才可按最终production SHA的明确授权
   执行。仅backend更新，无migration、frontend image或runtime config改动；专项请求在Provider前
   被拒绝，通用能力须做smoke。构建/扫描/传输/验收预留15–30分钟、切换1–3分钟，属估算。
2. **Addon组合包**：安装精确hash的0.4.3，关闭专项入口与本地调用；汉化只作用于固定UI、
   两个页面的组件键和两个窄REST错误。保留options/history。每站预留5–10分钟检查。
3. **Toolbox原0.6.1包**：隐藏专项入口，通用定位保存不得清除8组旧SEO规则/开关/字段；
   每站再留5–10分钟。总时长取决于站点数和目录传播，目前未测量。

每阶段验证后再推进；通用能力回归、权限或配置保留失败即停止下一阶段。

## 回滚与剩余生产门禁

- **只回滚汉化**：恢复保留的SEO-only Addon0.4.3包，完整SHA256
  e66b2f1dde91b5fa95ebb3d83845d3f3077ba3b31d26dabf67ee64f7d63c3c9a。
  同版本包需手工核对hash/文件，不依赖自动降版；保留SEO暂停与全部配置/历史。
- **整轮技术回滚**：Cloud参考b8cc6049，须先证明主机仍有可恢复镜像/bundle/config。
  插件回退已归档官网Addon0.4.1、Toolbox0.6.0 ZIP，不卸载、不删除options/history。
  旧插件可能重现部分入口，保留服务端暂停guard；技术回滚不自动批准恢复SEO业务。

代码独立审查、REST master回补与合并已经完成。生产仍需非AI验收锚点、目录版本占用复核、
fresh安全扫描、正式bundle/preflight、主机回滚可恢复性确认，以及最终production SHA和
插件分发的明确授权。候选CI全绿不触发生产发布，本任务部署次数为0。

证据根目录：`/Users/muze/.codex/task-receipts/npcink-cloud-addon/2026-10-19-localization-merge/`。
同日先前严格UI/浏览器记录和10月18日三项目集成记录按原revision/hash继续保留。
付费业务Provider调用0、M4操作0，普通会话没有改五日观察表。
