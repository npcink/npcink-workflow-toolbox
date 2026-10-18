# SEO 暂停生产候选验收记录

日期：2026-10-18，Asia/Shanghai。范围仅 Cloud、Addon、Toolbox。候选 CI、确定性
HTTP 集成和 PC 浏览器检查已执行；不等同于生产就绪或人工验收。SEO 未部署生产。

## 发布范围与 DeepSeek 实际状态

开始验收时 DeepSeek 只合入 production，部署仍是 4a0a305e。其独立发布会话随后完成
Deploy Production run [39122857578](https://github.com/npcink/npcink-ai-cloud/actions/runs/39122857578)。
只读部署解析器校验成功运行及其 timing artifact，当前实际安装基线已经变为
`b8cc6049da7d1c33cbfffc9f8e5521f332a1adb5`。本会话没有 dispatch 或操作该生产部署。

Cloud SEO 候选仍为 `e8877e19c31779e4a54bed73fede8c5ca05b918c`，tree
`c44a3cb389913672eb9a5d06aff0755c1a647d39`。相对当前已安装 b8cc6049 仅 11 路径，
不再是“待安装 DeepSeek + SEO”的 15 路径并集。新 release plan 为 backend-only，
frontend_image_required=false、migration_required=false、runtime_config_required=false。
0088、commercial/rollup 等 master 上其他工作没有带入本发布候选。

下一次真正执行前仍应再次解析实际安装基线。Cloud 当前回滚参考点变为 b8cc6049；
旧 4a0a305e 仅保留历史回滚档案，不再作为本轮 SEO 的默认上一稳定版本。
本轮未读取生产主机验证 b8 的归档/镜像恢复能力，没有将部署运行成功代替恢复演练。

## GitHub CI 与分支边界

| 项目 | 候选评审入口 | 证据与范围 |
| --- | --- | --- |
| Cloud | [草稿 PR #1143](https://github.com/npcink/npcink-ai-cloud/pull/1143)，head e8877e19，base production | 完整 backend 分片、静态、前端、Secret scan、CodeQL、production PR contract 等相关检查通过；没有沿用 skipped lane 作为健康证据 |
| Addon | [草稿 PR #291](https://github.com/npcink/npcink-cloud-addon/pull/291)，head 26e58bb6 | PHP 8.0/8.2/8.4、WordPress AI compatibility 阻断矩阵、Release static、PHPStan、PR body 通过；发布基线专用分支为 codex/release-baseline-addon-0.4.1 |
| Toolbox | [草稿 PR #278](https://github.com/npcink/npcink-workflow-toolbox/pull/278)，应用源码 43792523，随后只有文档提交 | PHP contracts、锁定依赖 PHPStan/PHPCS 和 PR body 通过；发布基线专用分支为 codex/release-baseline-toolbox-0.6.0；文档续记后的 CI 以最终交付清单为准 |

三份 PR 均保持 draft，auto-merge 关闭，不合入 production、发行基线或 master。
为遵守“只验证、不合并”，使用普通 Git push 和 draft PR，没有调用会申请 auto-merge 的
publisher。保留仓库 PR 模板。Addon 首次 body gate 因未填 Residual risk/Rollback plan
失败，已补全实际内容，仅重跑 body gate，未重跑已通过的兼容矩阵。

两个插件发行分支从官网已发布源码回移 SEO 暂停，不能把旧发行树反向合入当前 master。
Addon 0.4.2 配对功能和 Toolbox 后续 master 功能继续排除。三个候选的运行源码及两个最终 ZIP 未变化。

## M4 精确候选验证

共享 M4 仍为 accepted master `5fa1d47c`、PR1141，数据库已到 `20261017_0088`。
生产候选仍是较早迁移链，直接 source sync 可能迫使共享数据库处理不存在的 revision。
因此没有执行 sync/deploy 或降级数据库，也没有将 master 预览冒充精确候选。

本轮通过操作者已选定的蒲公英直传，将干净 e8877e19 的 Git archive 和独立验收 harness
传到唯一临时目录。校验 SHA256 后，复用依赖输入相同的 M4 runtime image
`sha256:72d1bcadc95bf4760dafa76c4017267bf6f59037d89a91f1fffcc2a5101339f2`，
以只读源码挂载运行一次性容器。聚焦用例在 network=none 下执行：31 passed，7.59 秒，
一个 Starlette 弃用 warning。没有重复完整 domain/contract gate，也没有本机 Docker 替代。

随后一次性 HTTP 进程运行同一候选 app，使用独立 SQLite 和现有确定性测试 Provider。
只发布 M4 的 127.0.0.1:18011，通过前台 SSH 隧道供 WordPress 使用；没有公开端口、
没有复制任何生产/预览密钥、没有修改 accepted source/数据库/worker、没有新常驻栈。
进程拒绝外部 TCP，测试 Provider 没有外部模型调用。测试密钥仅属于临时数据库。

20 个直接签名 HTTP 用例覆盖 10 个专项 task 的有/无 task contract 路径，均返回
HTTP 400 / wordpress_operation.feature_paused；前后 Provider、run、usage 计数没有增加。
这些调用绕过 Addon 的本地暂停 guard，确实到达 Cloud。

## WordPress 集成与浏览器

独立新站：`/Users/muze/wp-sites/npcink-seo-pause-validation/public`，仅监听
`http://127.0.0.1:8097`，新 MySQL 数据库 `npcink_seo_pause_20261018`。
实际环境 WordPress 7.1.3、AI 1.4.0、Addon 0.4.3、Toolbox 0.6.1。
两个插件从已记录 SHA256 的最终 ZIP 解压，逐文件复核，未修改其安装源码。
现有 magick-ai 和 five-plugin 站点、其他项目的源码及配置没有改动。

- Addon 的 5 个专项 task 和旧 mixed editorial 请求在本地被拒绝，HTTP 发送计数为 0。
- 普通 title_generation、content_summary、slug_generation 使用真实签名 HTTP 到精确候选，
  全部 succeeded。WordPress 官方 ai/summarization Ability 通过 SDK → Addon → Cloud
  链路返回 HTTP 200；ai/meta-description 不注册。
- 新站初始没有通用 feature 启用状态，harness 仅在该新站启用通用 feature；模拟 key
  原先仅有 runtime scopes，entitlement 读被正确拒绝。补足临时 key 的 entitlement:read
  并清理新站 entitlement 缓存后，能力快照变为 configured，原生链路通过。没有放宽应用权限。
- PC Chromium 1440×1000：Toolbox Site Profile 的可选区域没有 SEO/AEO/GEO 设置字段。
  通过真实浏览器保存通用定位后，8 组隐藏规则/开关/字段选择值逐项相同，历史 sentinel 和
  原 Meta Description 启用 option 均保留。
- 官方 AI 页面中英文均没有 Meta Description 控件；slug 和编辑建议固定说明不再宣传
  SEO，通用标题、摘要、翻译、ALT 等仍在。新站 entitlement 配置完成后，连接器告警消失。
- 实际加载发行 ZIP 的 .min 资产。Gutenberg 的 Npcink 内容支持面板没有专项操作，
  起草、发布预检、分类、标签、站内引用和图片入口保留。仅查看，没有生成、发布或采用正文。
- 页面没有横向溢出。编辑器无 JS error，有两个宿主弃用/样式 warning。Toolbox 页面
  查询空图片批次返回已存在的 npcink_toolbox_media_batch_not_found / 404，属于该基线
  无历史批次状态，不是新增 SEO 失败；没有把整个浏览器日志写成零 error。

M4 Provider 是确定性测试替身，付费调用为 0。以上证明链路、拒绝语义和界面/配置兼容，
不证明真实模型内容质量、外部 Provider 可用性或人工满意度。独立站中的示例文章和配置
仅为新建 fixture，不操作用户内容。真正发行仍需仓库要求的非 AI 证据锚点。

## 严格本地化门禁：既有问题，未带入 SEO 修复

旧候选自带 audit 的 fixed UI candidate 为 0，但新严格审计扩大了检查范围。新审计依赖
master 新增的 bundled_ui_fallback 接口，不能直接拿该脚本加载旧类而宣称运行成功。
外部验收 harness 仅加版本兼容判断：旧类没有此接口时按“不覆盖”处理，未抑制缺口。

同一审计器、同一 AI 1.4.0 源码分别对候选 ZIP 和官网下载的 0.4.1 运行：两者均失败，
均为 fixed UI 12 + uncovered default-domain 6 = **18 gaps**。已保存完整两份输出和比较。
候选的 SEO 固定说明已在实际 zh_CN 页面验证。这 18 项是相同基线问题，不是可忽略的
全绿结果；本轮不把无关本地化实现回移进 SEO 发行包，不增加 ignore 或放宽 gate。

因此状态为：相关候选 CI 和 SEO 行为集成/浏览器验收通过；整体严格 UI gate 未通过，
不能报告“所有生产发布门禁已完成”。应另作有界本地化修复/发行决定后再复核该 gate。
旧手工 smoke 脚本仍有 meta-description 正向用例，后续完整能力验收时需按暂停契约更新；
本轮使用明确覆盖暂停与通用路径的独立 harness，没有执行旧脚本去制造被暂停调用。

## 交付与下一步

完整证据、harness、JSON、截图、最终 CI rollup、release plan 和清理回执位于：
`/Users/muze/.codex/task-receipts/npcink-ai-cloud/2026-10-18-seo-pause-validation/`。
最终 revision 与清理状态见该目录 validation-receipt.json；文档提交不改变两个发行 ZIP。

保持 PR 草稿和候选工作树锁，等待正式发布范围、剩余门禁与部署授权。下次上线顺序仍为
Cloud → Addon → Toolbox；Cloud 技术回滚参考点为实际上一稳定 b8cc6049。不会自动恢复
SEO，也不会由候选 CI 成功触发生产部署。
