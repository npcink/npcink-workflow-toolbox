# UX Hardening Development Lessons - 2026-10

Status: closeout record for the 2026-10 operator-experience pass
(PR #175, PR #176, and the four advisory review rounds around them).

This record turns the working method of that pass into reusable rules.
The audit found roughly twenty user-facing defects across four surfaces;
every fix below shipped with a regression guard, and the guards caught
real regressions during the same pass.

## 1. Audit Method: Parallel Surface Sweeps, Then Verify Before Fixing

Fan out one read-only audit per user surface (editor sidebar, admin page,
dashboard widget, frontend, i18n/error text) and merge findings by theme
(silent failures, missing guards, jargon, truncated results). Two rules
kept the pass honest:

- **Verify every finding against the source before editing.** Two audit
  findings were false positives (a claimed `t()` around wp.data store
  names did not exist; a "dead helper" was real but its callers needed
  checking). Fixing either blindly would have added churn, not value.
- **Order by user loss, not by code smell.** The highest-severity items
  were silent failures (translations never loading, refresh feedback
  never rendered, a panel users could enter but not leave) — none of them
  crashes.

## 2. Feedback Beats Elegance: The P0 Pattern

The three P0 fixes shared one shape: **a code path that succeeds while
telling the user nothing**. Checklist for new operator actions:

- Every explicit user action produces a visible outcome (success, empty,
  fallback, failure) — the hot-topic refresh redirect parameter existed
  but was never rendered.
- Every long-running browser loop needs a close-tab guard, a progress
  indicator with counts, and an interruption message that names the
  recovery path ("reload and use Continue optimization from history").
- Confirmation weight scales with blast radius: replacing 1000 files
  asks first; clearing a settings form asks first; resuming an already
  confirmed run does not ask again (the `resuming` branch in the media
  batch start).
- Failures inside a loop are itemized, never swallowed into a summary
  success (whole-batch restore now lists each failed image).

## 3. Cancellation Semantics: Three Cases, Not One

When adding AbortController to shared request paths, distinguish:

1. **User cancel** — neutral info notice ("Request cancelled. Nothing was
   written."), spinner unlocked, no error styling.
2. **Superseded** (a newer run aborted the old one) — stay completely
   silent; the newer run owns the UI state, and the old catch must not
   overwrite it.
3. **Timeout** — retryable message with nothing-written reassurance.

Collapsing these into one AbortError produced the exact bug the advisory
review caught (a media brief silently aborting a running draft flow and
showing a red "cancelled" error the user never caused). Implementation:
mark `signal.__toolboxSuperseded` before supersede-abort, check it in the
shared catch, and clear the active-request ref when a run settles.

## 4. Layered Regression Guards

Each layer catches what the previous one cannot:

- `tests/run.php` static contracts — pin source shapes (the wp-i18n
  dependency, the re-entry guard attribute, "M4" must not return) and
  enforce literal catalog coverage per file.
- Node behavior tests — logic without a browser (format staleness,
  internal-link transactions).
- **Fixture browser smokes** (`smoke-ux-hardening-browser`,
  `smoke-core-handoff-receipt-ui`) — load the real script into a mocked
  DOM with `window.fetch` replaced; they assert dialogs, request counts,
  and rendered outcomes without needing a live site.
- Live-site browser smokes — end-to-end against the five-plugin install
  for flows that cross PHP rendering and real REST.

Two working rules from this pass: hardened `window.confirm` flows need
`page.on('dialog', (d) => d.accept())` in every browser smoke (the
fixture stands in for a present, confirmed operator), and fixture state
must match the flow's branch decisions (a batch fixture with
`status: 'running'` silently skips the confirm dialog that the test was
written to assert).

## 5. Translation Discipline

- Every literal `__()`/`t()` string in a covered file must exist in the
  bundled zh_CN catalog; the static gate prints the exact missing list,
  so catalog sync is mechanical, not manual hunting.
- JED files are hand-maintained with single-line arrays
  (`"msgid": ["msgstr"]`); regenerating with a naive
  `json.dump(indent=2)` breaks the substring contracts. Collapse
  multi-line arrays back before committing.
- Rebuild order: edit source -> add po entries -> `msgfmt` the .mo ->
  update the per-handle JED -> regenerate the .pot with
  `wp i18n make-pot`. New strings always use English msgids; see the
  Translation Source Language Policy for the migration plan.

## 6. Environment Forensics: When the Test Site Is Down

The browser smokes exposed a fully broken local site (503 on every PHP
page). Root cause was two stacked faults, worth remembering because
either alone points at the wrong fix:

1. **macOS php-fpm fork crash** — workers SIGABRT on
   `objc initializeAfterForkError`. Fix: append
   `env[OBJC_DISABLE_INITIALIZE_FORK_SAFETY] = YES` to the Local site's
   php-fpm pool config and reload; do not try to out-race Local's
   supervisor by hand-starting a master.
2. **Stale `.maintenance` file** — the crash left
   `app/public/.maintenance` behind, so even a healthy fpm kept serving
   the maintenance page. Delete it once the timestamp is older than
   WP's ten-minute window.

Diagnostic order that worked: control page (dashboard also 503?),
php-fpm log (SIGABRT lines), then site-state markers (.maintenance),
then a single curl per fix.

## 7. Advisory Review As A Gate, Not A Rubbish Bin

`ocr review --from origin/master --to HEAD` before every publish found
five real defects across two PRs (batch double-run, unwrapped advice
string, a misleading "Agent" translation, superseded-vs-cancelled
conflation, duplicate cancel buttons) — all fixed pre-merge. It also
produced one finding already fixed and one stylistic suggestion;
verifying against the current HEAD before acting is part of the loop.
Run it before `composer pr:publish`, fix or record, and let the optional
CI review post the same result.

## 8. What Was Deliberately Not Done

Recorded here so the next pass starts from decisions, not archaeology:

- Fresh-install onboarding shipped as notices plus a three-step card;
  a full guided tour needs product design.
- Legacy Chinese-source msgids migrate file-by-file under the
  Translation Source Language Policy, not in bulk.
- Cross-plugin terminology ("Core proposal" wording) waits on the
  Operator Terminology Standard being accepted in all five repos.
- The Site Knowledge status owner-matrix rows and author/admin error
  audience split remain follow-ups; the operator trial script
  (ux-hardening-operator-trial-2026-10) is the acceptance loop for
  everything in this pass.
