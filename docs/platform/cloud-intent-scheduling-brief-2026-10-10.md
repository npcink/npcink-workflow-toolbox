# Cloud Intent 排期说明:评论分类与媒体安全状态

状态:已确认的排期输入(2026-10-10)。Toolbox 端两条功能链已全部合并,只等
Cloud 侧实现两个 hosted AI intent。本文是给 Cloud 服务团队的排期说明与验收
标准;两个 intent 的**权威契约**以 Toolbox 仓库的两份文档为准,本文只摘要、
不复述细节:

- 评论分类:`npcink-workflow-toolbox` `docs/comment-moderation-review-set.md`
- 媒体安全状态:`npcink-workflow-toolbox` `docs/flagged-media-review.md`

## 背景

Toolbox 已合并四个 PR(#164 契约、#165 路由与 PII 通道、#166 界面与冒烟、
#167 违规媒体清单),两条链路的 WordPress 端完整就绪。Cloud 未接通前,两个
界面如实显示 `cloud_required`,无任何本地兜底。删除闭环(违规媒体治理删除)
已向 Toolkit/Core 提出契约 PR,与 Cloud 本期无关。

## 任务一(优先):`comment_moderation_suggestions` 评论分类

**Cloud 要做的事**:执行一次文本模型调用并返回结构化分类。Toolbox 已组装好
prompt(含 ≤50 条待审评论的"批准即公开"字段样本),按样本量预留了输出
token(400–4000),并以 `data_classification=pii` / no-store 模式送达——
Cloud 不得留存这些载荷与结果。

**应答形状**(在现有 `hosted_ai_site_helper.v1` runtime 应答的 `result` 内):

```json
{
  "classifications": [
    {
      "comment_id": 123,
      "classification": "spam | legitimate | uncertain",
      "confidence": 0.0,
      "reasons": ["简短理由"],
      "suggested_action": "open_in_wordpress_moderation_queue | review_manually"
    }
  ]
}
```

**硬规则**:只分类 prompt 里出现的 comment_id,不发明;`uncertain` 是合法
终态,不许硬猜;不请求邮箱/IP/UA;不承诺任何写入(WordPress 写入永远不经
Cloud)。建议输出 JSON,模型温度 0.2。

**上线前校准(必须)**:按 eval-lab 既有模式导出 50–100 条真实站点待审评论,
三模型交叉判。建议验收线(可调,需与产品确认):spam 判定精确率 ≥ 0.85、
legitimate 召回 ≥ 0.90(误杀真评论是最伤的失败模式)、uncertain 占比落在
15%–35% 区间(过高=没用,过低=乱猜)。校准报告归档后再放开站点流量。

## 任务二(搭车):`flagged_media_suggestions` 媒体安全状态

**Cloud 要做的事分两步**:

1. **视觉索引产出字段**:在既有媒体视觉识别/投影管线里,识别时顺带产出
   `content_safety`(safe / flagged / unknown)+ confidence + reasons,存入
   投影。这是本任务的实际工作量;存量索引若无此字段,应答时如实返回
   unknown 即可,不要求回刷;
2. **hosted intent 应答**:收到 Toolbox 的元数据样本(≤50 张,无图片字节,
   同样走 pii/no-store)后,从投影读取对应 attachment 的安全状态并按
   `content_safety_statuses[]` 形状应答(结构与任务一的 classifications
   同构,字段换为 attachment_id / content_safety)。

**硬规则**:不发起新的视觉调用来补答;unknown 是合法终态;不做删除、不做
替换、不写 WordPress。

## 价值判断摘要(为什么先做任务一)

评论分类是日频痛点、单次成本近零、中文语义垃圾是 Akismet 类统计过滤的
盲区、且误杀保护直接可感知;媒体安全是审计型低频功能,边际成本依附于已有
图片索引,适合同迭代捎带而非单独排期。若只能排一个:先评论分类。

## 验收定义

- [ ] 任务一:真实站点冒烟(Toolbox 侧 `smoke:comment-moderation-trial`
      已就绪,换掉 mock 即为真验);校准报告达标;
- [ ] 任务二:有图片索引存量的站点冒烟 `smoke:flagged-media-trial` 同理;
      无投影条目一律答 unknown;
- [ ] 两 intent 的 pii/no-store 行为有服务端证据(不留存、无日志明文);
- [ ] Toolbox 两个界面从 `cloud_required` 变为出真结果,且
      `comment_status_unchanged` / `media_unchanged` 全程成立。

## 边界重申

Cloud 是托管运行时,不是写入方:不持有 WordPress 写权限、不存审批、不建队
列;两个 intent 均为 suggestion-only。删除闭环属 Toolkit/Core 契约
(见两仓的候选/政策 PR),Cloud 本期不涉及。
