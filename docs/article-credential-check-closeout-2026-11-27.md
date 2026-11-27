# Article Credential Check Closeout (2026-11-27)

This handoff covers Toolbox credential checks and the local installation used
to review them. It does not record a production release or change the
[v1 contract](article-credential-check-v1.md).

## Decisions and source

| PR | Merge on `master` | Disposition |
| --- | --- | --- |
| [#435](https://github.com/npcink/npcink-workflow-toolbox/pull/435) | `79a84d80c05077f6b3beee8bd9466c59520f8bcd` | Preserved the Toolbox admin layout and Article Check sidebar improvements. |
| [#436](https://github.com/npcink/npcink-workflow-toolbox/pull/436) | `ba52432d58301048909f9eaab2518c20a0b08488` | Preserved explicit FAQ insertion at the end of the article and native manual save. |
| [#437](https://github.com/npcink/npcink-workflow-toolbox/pull/437) | `d2e727190e0be13b5f049daeea1186ef69432e8a` | Added both local checks, recovery before outbound transfer, and coverage for inline formatting. |
| [#438](https://github.com/npcink/npcink-workflow-toolbox/pull/438) | `d805811ade6a17dac75b407f71ed5800d1f3a3ca` | Resolved PHP coding-standard findings. This is the installed acceptance revision. |

- Keep the global scan in Toolbox Start. The old Articles URL redirects an
  authorized administrator before WordPress rejects an unregistered plugin
  page. It is bookmark compatibility, not a menu item or a permission grant.
- Keep inspection local and deterministic. Known credential shapes pause
  Article Check and body FAQ before transfer; uncertain generic assignments
  are advisory. Recheck the server's derived outbound text before dispatch,
  because text conversion can join fragments the raw scan did not match.
- Report types and locations, never values. Use explicit replacement only for
  unambiguous editable text; require manual edits for other matches. Editing,
  rechecking and continuing are separate steps. None saves or publishes the
  article. If a credential was exposed, revoke or rotate it first.
- Do not treat a clean scan as general privacy assurance. No live credential
  validation, broad entropy scan, automatic site-wide replacement or guard
  for unrelated intents is included. No Cloud Addon change was required.

## Evidence and installation

- The PHP and JavaScript behavior checks for credential inspection and FAQ
  passed. An isolated installed-package smoke with synthetic articles and a
  simulated provider covered partial/resumed scanning, access, per-article
  locate/replace/undo, and both guarded intents. It verified no outbound call
  on pause or replacement, and a clean request only after explicit continue.
- The `magick-ai.local` installation used a package built from `d805811a`,
  independent of the dirty checkout. PHP CLI loaded the new source, but the
  logged-in browser received CSS/JS URLs containing the previous snapshot's
  filesystem path. Direct HTTP requests to valid asset URLs were insufficient
  to catch this failure. A real plugin directory and a site-specific PHP-FPM
  reload restored the URLs used by the page. In the logged-in browser the
  old URL redirected, the stylesheet loaded, and the global scan button was
  enabled after JavaScript initialization. The previous snapshot was retained.
- That installed check did not run a scan against real site content, exercise a
  real provider, edit an article, or validate production. Preserve the exact
  accepted package unless a separate site update is authorized. See the
  [installed asset checklist](development-workflow.md#installed-wordpress-asset-verification).

## Recovery and remaining scope

The original topic checkout was five commits behind at the time of sync. Its
22 modified tracked files and nine untracked files were compared with the
merged implementation and saved in a checksum-verified local archive and
stash before fast-forwarding `master`. No additional implementation was
needed: the merged tree retained the FAQ append behavior and 45-second client
wait, the improved admin layout, and handling for credentials split by inline
formatting. Do not replay the old patch onto `master` as a unit.

The two original PR heads have the same file trees as their squash merges.
Retain their original history and the local source baseline in a verified
bundle before retiring their refs. Retain the local recovery archive and the
site rollback package independently of Git branch or worktree cleanup.
Unrelated refs and the rest of the repository's
[open issues](known-issues.md#open) are outside this handoff.

There is no pending runtime change for this scope. If ordinary use surfaces
a missed credential shape or a false positive in a supported path, Toolbox
owns the follow-up: use a synthetic reproducer, verify both local and server
guards for the affected projection, and preserve the no-write/no-disclosure
boundary. Do not collect real keys, schedule broad scans or expand the feature
merely to complete acceptance.
