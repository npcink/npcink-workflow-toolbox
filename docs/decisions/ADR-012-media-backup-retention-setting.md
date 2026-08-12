# ADR-012: Expose a Bounded Media Backup Retention Setting

## Status

Accepted

## Context

Automatic cleanup now provides a bounded restore window, but operators need a
simple way to choose between the default 30-day window and a longer 90-day
window. A backup manager, arbitrary duration input, or Cloud-side setting would
add unnecessary product and ownership complexity.

## Decision

Add one Settings API field under Toolbox **Image Handling → Original image
backup retention** with exactly two values:

- 30 days (recommended);
- 90 days.

Toolbox sanitizes the value to this allowlist and projects it through
`npcink_abilities_toolkit_media_backup_retention_days`. Toolkit remains the
owner of backup files, history, restore execution, and cleanup. Cloud and Core
do not receive or own this setting.

## Consequences

- Operators can change the restore window without code edits.
- Invalid or legacy values fail closed to 30 days.
- The UI remains a single compact choice with no backup-management center.
- Existing deployments retain the 30-day default after upgrade.
