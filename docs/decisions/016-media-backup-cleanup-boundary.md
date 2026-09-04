# ADR-016: Media Backup Cleanup Boundary

Status: accepted for development

## Decision

Expired media-operation backups may be cleaned only after an administrator
reviews a current read-only preview and confirms the exact cleanup action in
the same WordPress session. Toolbox owns the operator surface and confirmation
receipt; Abilities Toolkit owns path validation, deletion, and `backup_expired`
history marking. The existing Toolkit host-governed commit gate remains the
approval boundary.

The cleanup action may remove files only below the dedicated
`npcink-abilities-toolkit-backups/` uploads directory and only after the
existing retention policy marks them expired. Current attachment files,
generated derivatives, and unrelated uploads are never cleanup targets.

## Non-goals

- no new approval store, queue, scheduler, database table, or backup registry;
- no automatic deletion of current media or immediate deletion after replace;
- no cleanup route that accepts arbitrary paths or bypasses Toolkit;
- no change to Core or Adapter contracts.

## Verification

The preview and commit paths must be idempotent, administrator-gated, and
covered by route, permission, and Toolkit cleanup behavior tests.
