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
9. **Review-set adoption data (feeds the batch-apply decision).** Open
   each admin review-set panel (Media ALT, Taxonomy/Tag, Internal-Link,
   Comment Moderation, Flagged Media), run one bounded sample, and ask:
   "of these suggestions, which would you actually accept?" Record the
   accepted count per set and the operator's rough weekly volume of
   review-set suggestions. A stable two-digit weekly accepted total is
   the signal to open the media ALT adoption-loop arc (the cross-repo
   governed path already exists: `media_alt_apply_plan.v1`); below that,
   the review-set family stays review-only steady state.
10. **Owner-matrix and permission copy.** On the Site Knowledge status
    panel, confirm the owner matrix shows the full row set (source
    content, delivery bridge, index execution and lifecycle, freshness
    policy, diagnostics detail, vector storage, embedding execution,
    approval, final write; plus index/freshness/diagnostics truth rows).
    With an `edit_posts` author, click an admin-gated sidebar action and
    confirm the error reads "This action needs a site administrator.
    Nothing was written." (the raw REST denial stays preserved on the
    error object for technical inspection; not every panel folds it into
    a visible details section yet).

## Record

For each step: pass / confusion (what did they read?) / fail. Confusions
about wording are catalog fixes; failures are code issues. File results in
this document's "Findings" section before the next release tag.

## Findings

### Automated pre-pass - 2026-10-07

An automated browser pre-pass ran on the local five-plugin site
(~/wp-sites/npcink-five, Toolbox at master 66d76cc) before the operator
session. It covers the mechanically checkable parts; the human questions
stay open for the operator.

- **Step 1 (fresh-install path): pass.** With the Cloud Addon deactivated,
  the Overview renders the "AI service is not connected" banner with an
  install action link, and the Getting started card leads with "Three
  steps to your first AI-assisted task. Nothing is written without your
  review." plus the Cloud Addon step link. Both are self-explanatory
  without help; wording judgment still belongs to the operator.
- **Step 9 (review-set sampling): partially verified.** The Media ALT
  review set runs locally end to end (bounded sample controls, stats,
  clean empty state when no missing ALT exists). The four Cloud-dependent
  sets (taxonomy/tag, internal-link, comment moderation, flagged media)
  fail closed with "Npcink Cloud is not configured." plus the
  `cloud_runtime_unconfigured` code folded into the error payload - the
  local site has no Cloud credentials, so adoption-rate data collection
  must happen on a Cloud-connected site during the operator session.
- **Step 10 (owner matrix and permission copy): partially verified.**
  Live rendering of the full owner matrix requires a Cloud boundary
  payload; without Cloud the matrix correctly renders nothing instead of
  half-rows (the renderer is gated on the boundary block as designed, and
  the full-row contract is pinned in `tests/run.php`). The author-side
  permission split is verified in three parts: an `edit_posts` author sees
  the Content Support sidebar and runs allowed flows with clean empty
  states (live); a gated route (`flows/media-brief`) returns HTTP 403
  `rest_forbidden` with the raw "Sorry, you are not allowed to do that."
  message for the author (live - the exact wrapper input shape, and not a
  nonce failure); the wrapper rewrite for that code and the plain copy are
  pinned by static contract. One operator click on a Cloud-connected site
  (a metadata handoff submit as an author) closes the loop visually.
- **Steps 2-8: not re-run manually.** They are covered by the existing
  browser smokes (`smoke-ux-hardening-browser`,
  `smoke-core-handoff-receipt-ui`) in `composer test:all`; the operator
  session should still walk them for wording judgment.
