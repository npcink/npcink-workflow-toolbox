# 三项目生产前验收、预检与回滚材料核验

日期：2026-10-19，Asia/Shanghai。状态：**候选 promotion 预检通过；正式部署预检未就绪**。
没有生产分支合并、Deploy Production、插件目录分发、服务切换、迁移或清理。
本记录不将候选检查、CNA范围检查或旧版本bundle的扫描记录冒充新候选的正式构建/扫描。

## 1. 冻结源码与最终包

| 项目 | 源码/评审链 | 发布物 SHA256 |
| --- | --- | --- |
| Cloud | e8877e19c31779e4a54bed73fede8c5ca05b918c，tree c44a3cb389913672eb9a5d06aff0755c1a647d39；SEO草稿PR1143 | 尚无该候选的正式production bundle |
| Addon0.4.3 | fbb2b3393b55d173aa196d25171462c924c6c00e，组合分支codex/release-addon-seo-i18n-20261019；独立汉化PR292及master REST回补PR293已合并 | 6576603c6272934936b7dd840d00257aeef14fc493108c78615516e5cdb123f6 |
| Toolbox0.6.1 | 应用源码437925233e612dfb12cbb6eab6c3d582dc6ee1a7；本记录提交仅更新文档，精确文档HEAD见同日receipt；草稿PR278 | 7ff6a5c4d48bb833963272e80528c81932460dedc5005c301bf84de307efabd3 |

Addon SEO-only PR291仍冻结26e58bb62ccf27e9359a2dd354750c7401981055；汉化没有混入其原始差异。
两个最终包分别52/100个常规文件，CRC校验通过，实际隔离站安装文件逐字节匹配。
ZIP目录项不计入文件数。官网重新核实仍为Addon0.4.1、Toolbox0.6.0，拟发版本未分发。
已有精确版本的相关CI、严格UI0、WordPress7.1.3/AI1.4.0中英文浏览器及签名集成证据继续复用，
没有为本轮文档/预检重复业务调用。确定性Provider证据不代表真实模型质量或真人满意度。

## 2. 已执行的Cloud预检

- 最新部署解析器与公开health/live仍指向b8cc6049da7d1c33cbfffc9f8e5521f332a1adb5，
  release-641bf54c7c2a0524-20261018112143-2192，source_dirty=false，health正常。
- 从该实际部署基线重新生成candidate-release-plan：11路径、backend-only，
  backend_image_required=true，frontend_image/migration/runtime_config均false。
- production:promotion:preflight=ready：干净精确候选、Ruff、发布策略、合并兼容、
  部署secret名称元数据和无活动部署检查通过。证书就绪run
  [39153789566](https://github.com/npcink/npcink-ai-cloud/actions/runs/39153789566)通过。
- 当前运行API的受保护Runtime Config、PostgreSQL18/TLS连接与Alembic可升级检查通过：
  [39154252521](https://github.com/npcink/npcink-ai-cloud/actions/runs/39154252521)。
  仅在既有容器执行SELECT/SHOW诊断，没有迁移、重建或写数据库。
- 磁盘报告[39154255062](https://github.com/npcink/npcink-ai-cloud/actions/runs/39154255062)通过：
  根分区约24G可用、使用51%，当前release目录仍在。只报告，没有prune或删除。
- 新执行的权威CNA受影响版本范围检查通过；不是新镜像CVE扫描。
- 候选原CI的backend静态、4个完整pytest分片和聚合实际通过；7个skipped包含production
  bundle/evidence/plan等，不能算生产门禁通过。

运行正式 `production:release:preflight --sha e8877e19... --wait-seconds 0` 得到明确失败：
`requested SHA does not match the current production branch`。当前production仍为b8cc6049。
仓库CI只在production push且runtime计划下生成正式扫描/bundle，所以本轮没有伪造
snapshot、临时工作流或本机Docker替代这条链路。**当前dispatch_expected_sha未形成**；
不能把e8877e19候选或b8cc6049旧基线直接当作下一次SEO部署输入。

## 3. 回滚材料核验结果

### Cloud：离线完整归档已验证，主机恢复能力未完全证明

从成功production CI run39118967085下载上一稳定b8cc6049的正式bundle：
`deploy-bundle.tgz`，Linux/AMD64，源码tree7e442d74579d4eeb0a7d97f514fa8ca3e3af8347。
SHA256：`641bf54c7c2a052464f8f9dde42f951bca3c986afe725770f60eae8ea60cc898`。
仓库 `deploy/verify-release-bundle.sh --archive` 通过：外层checksum、tar路径/类型、完整
文件集、内部hash/大小、Docker归档和绑定扫描证据校验有效。已另存独立本地副本。

其API/worker/callback/ops镜像ID为
`sha256:0549f15b50ecb45db0a569e6db88989a35c9e8af4d69803fe288d00fa78d43b5`；
frontend为`sha256:83fddd376c66cfd91423d072cd8d4d550bfdfe6a112690b7abdc69cdedc9df61`。
归档也包含对应外部镜像。其扫描时间为2026-10-18T10:25:36Z，仅绑定b8cc6049，不能给
新候选API镜像背书。原release目录被当前公开health和本日磁盘报告交叉确认仍存在。

直接主机核验的两把本机SSH密钥均被拒绝，已停止尝试；既有GitHub保护凭据可完成上述
证书/数据库/磁盘诊断，未导出凭据。尚未逐项核对目标daemon的实际image-ID映射、完整
恢复路径以及受保护shared/config的灾备可恢复性，也没有执行生产恢复演练。
因此“离线回滚包完整”已证实，“生产主机完整恢复已演练”仍未完成。正式切换前应通过
现有授权只读身份或运维提供的验收记录补齐，不需要发送私钥、密钥或配置内容。

### 插件：官网归档与隔离站恢复均已验证

| 回滚用途 | 包 | SHA256 |
| --- | --- | --- |
| 仅撤回汉化、保留SEO暂停 | Addon SEO-only0.4.3 | e66b2f1dde91b5fa95ebb3d83845d3f3077ba3b31d26dabf67ee64f7d63c3c9a |
| Addon整轮技术回退 | 官网0.4.1，51常规文件 | 23166c2e969533e8686d461a57694c64ca4e3a9fabf6a753ee77ac5fddb54596 |
| Toolbox整轮技术回退 | 官网0.6.0，100常规文件 | a95bd2e83b017a34a2f529fead84774250d7f196c85e77f7a6bd65d0dee00724 |

重新下载两份官网版本ZIP，与保留归档SHA256完全相同。独立fixture实际加载0.4.1/0.6.0，
再恢复最终0.4.3/0.6.1：8组隐藏SEO配置、历史sentinel和原Meta开关前后相同。没有调用卸载、
删除option、保存业务配置或生成内容。恢复后文件与最终ZIP再次逐字节匹配；站点保持停止。
这证明本fixture的代码回退/恢复与已覆盖配置保留，不能替代每个真实站点的数据库/文件备份。

## 4. 待确认与后续执行边界

1. **是否仅推进Cloud PR1143到production验证，不部署**：需要明确这一范围后，才能生成
   最终production SHA、精确push CI/CodeQL、Linux/AMD64新API镜像、fresh scan及正式bundle。
   当前没有执行合并；正式发布前再跑live release preflight，只有ready结果能给dispatch SHA。
2. **主机回滚核验**：提供可用的现有只读SSH用户名/本机私钥路径（不是私钥内容），或提供
   运维完成的image-ID/恢复路径/受保护配置可恢复性记录。本地离线归档不替代这些证明。
3. **非AI验收与分发范围**：由真人审阅精确候选的关键UI/通用能力并记录结论；确认插件是
   公开目录分发还是指定站点安装，以及首批站点、现有版本、备份和恢复权限。本轮未操作
   任何真实WordPress生产站点，也不把AI生成的截图检查当成真人验收。
4. **正式smoke安排**：保护secret元数据仅缺NPCINK_CLOUD_PORTAL_LOGIN_CODE，其余要求
   名称齐全但不证明值/时效正确。该码短时且单次，应在部署后临近Release Smoke时通过
   保护流程生成；当前不索要或提前保存，以免过期。未执行的formal smoke仍记为未通过。
5. 完成上述生产证据后，仍需对**最终production SHA、正式bundle和两个插件指纹**的最终
   部署/分发授权。上线顺序Cloud→Addon→Toolbox、阶段验收和回滚触发条件沿用最终方案。

当前应停止在“材料与候选准备”状态，不进入服务切换。没有扩大到其他项目或修复无关
基础设施。付费业务Provider调用0、M4操作0、生产部署/迁移/目录分发0。

完整receipt、归档、日志和包比较：
`/Users/muze/.codex/task-receipts/npcink-ai-cloud/2026-10-19-preproduction-preflight/`。
