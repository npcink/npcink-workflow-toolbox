# ADR-013: M4 Tunnel 故障归属与 Toolbox 诊断边界

## Status

Accepted

## Date

2026-08-13

## Context

Cloud 媒体预览曾稳定返回 HTTP 502，错误码为
`cloud_runtime_request_failed`。WordPress 连接凭据已验证，但 Cloud Addon
readiness 报告 `127.0.0.1:18010` 无法连接；M4 远端服务和
`127.0.0.1:8010/health/live` 正常。继续修改 Toolbox 会把运行环境故障误判为
产品契约故障，并可能引入不必要的重试、fallback 或本地 runtime。

## Decision

将 Cloud 预览链路按以下层次诊断，并保持 owner 分离：

```text
WordPress UI/REST
  -> Cloud Addon readiness and signed transport
  -> local M4 tunnel 127.0.0.1:18010
  -> M4 proxy/API 127.0.0.1:8010
  -> Cloud runtime/artifact
```

- Toolbox 负责请求桥接、输入验证、结果审阅和用户提示；
- Cloud Addon 负责连接配置、签名传输和 readiness；
- M4 tunnel/容器/operation lock 由 Cloud M4 运行规范负责；
- Cloud 负责 runtime 和 artifact；
- 发生 502 时，先取证并检查 tunnel、远端健康、锁和 owner；
- 只有在确认传输和运行环境健康后，才把剩余错误归入代码契约或 runtime
  revision；
- 不在 Toolbox 增加自动 tunnel、Docker、队列、重试或第二运行时。

## Consequences

- 本地 smoke 必须记录 tunnel 前后的 readiness 和 HTTP 结果；
- M4 共享操作遵守唯一 owner，发现冲突时只读并停止；
- 运维恢复命令属于 Cloud/M4 runbook，而不是 Toolbox 产品代码；
- 真实 smoke 通过后即可证明产品链路恢复，但不能宣称 Cloud candidate 已被
  merge/promote/accept，除非对应 M4 证据明确存在。

## Rejected Alternatives

- 在 Toolbox 内添加重试或 fallback：会掩盖 transport owner 的真实故障；
- 用本机 Docker 替代 M4：违反 M4 preview authority；
- 清理或抢占其他 Cloud worktree/candidate：破坏共享运行证据和用户工作；
- 将 502 映射为成功或静默降级：会让 operator 误以为衍生结果可用。
