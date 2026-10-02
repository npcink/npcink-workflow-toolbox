# UX Hardening Operator Trial - 2026-10

Status: trial script pending a 30-minute session with one or two real
operators before the next package release.

Purpose: the 2026-10 UX hardening pass changed confirmation, feedback, and
recovery behavior in flows static tests cannot fully judge (dialog
wording, recovery instructions a human can actually follow). This script
validates those changes with a real operator on a staging or local
five-plugin site.

## Setup

- Five-plugin site (Toolbox, Governance Core, Adapter, Abilities Toolkit,
  Cloud Addon) with Cloud connected, at least 10 Media Library images, and
  one article in the editor.
- Operator has `manage_options`. A second run with an `edit_posts` author
  (no admin) is recommended for step 6.

## Steps (watch, then ask the question)

1. **Fresh-install path.** Deactivate the Cloud Addon, open Toolbox
   Overview. Ask: "what would you do first?" — expect the three-step
   getting-started card and the Cloud Addon action link to be understood
   without help. Reactivate the Addon.
2. **Batch start confirmation.** Image Handling -> Batch Optimize Images,
   check images, click Start. Expect: the confirm dialog names the exact
   count and the originals-stay-restorable promise; wording must read
   naturally, not legalese. Cancel once, then confirm.
3. **Close-tab guard.** Start a run with several images, try closing the
   tab mid-run. Expect the browser warning; after staying, the run
   continues and progress shows "x of y (z%)".
4. **Interrupted run recovery.** Kill the network (offline mode) mid-run.
   Expect the interrupted-run message to lead the operator back to
   history and "Continue optimization" without asking for help.
5. **Whole-batch restore failure.** Restore a whole batch; if any item
   fails (or via the fixture smoke), the final message must name the
   failing images and say they can be retried individually. Ask: "did you
   trust the success/failure report?"
6. **Editor sidebar cancel.** Open the editor sidebar, run a hosted image
   candidate request or a draft generation, click Cancel while running.
   Expect: the request stops, "Request cancelled. Nothing was written." is
   understood, and the buttons unlock.
7. **Copy feedback.** Copy buttons in the discoverability panel and the
   admin Copy JSON blocks. Expect visible "Copied" feedback; a clipboard
   failure must show the manual-select hint.
8. **Receipt readability.** Trigger any Core handoff (e.g. SEO proposal).
   Expect the next-action sentence to be understandable; technical codes
   live behind "Technical details" and support accepts them.

## Record

For each step: pass / confusion (what did they read?) / fail. Confusions
about wording are catalog fixes; failures are code issues. File results in
this document's "Findings" section before the next release tag.
