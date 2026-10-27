# Internal-link anchor eligibility v3

Active editor policy: `internal_link_anchor_eligibility.v3`. This extends
[v2](internal-link-anchor-eligibility-v2.md) with one caption label:
`解决办法`. All other v2 checks remain in force. Ranking, candidate order,
targets, supplied anchors and server matching are unchanged.

## Why this change

The full-corpus development replay found source 7201 → target 5280 eligible
on the standalone paragraph `解决办法：`. The source concerns category-base
plugin deactivation and a 404; the target concerns category pagination 404s.
The caption does not identify the destination's particular solution. Exact
matching is insufficient evidence for direct insertion here.

Reuse the existing `anchor_reference_requires_review` guard, only where
v2 already recognizes a colon or standalone caption position. Keep the item,
source preview, Copy link and Open article actions available for review;
omit selection and direct Apply. This labels insufficient destination evidence,
not bad grammar. It neither rewrites nor extends the anchor.

Ordinary prose `本文介绍解决办法和注意事项。`, named `缓存解决办法：`, and
the specific action `清除缓存` in `解决办法：清除缓存。` retain their previous
eligibility, subject to every existing safety check.

## Version and evidence

New internal-link events use `anchor_eligibility_v3` in the existing bounded
reason-code slot. Saved follow-up seeds preserve their original v2/v3 label.
Related-content events and feedback schemas are unchanged. No raw article or
anchor is added to feedback.

The frozen v2 comparison is Toolbox
`2ff18492630af157144f6a8f80a0853b0b85eaf9`. Development replay of 20 sources /
119 candidates changed exactly one eligibility outcome (43 → 42 eligible).
The other 118 outcomes, including the useful 19025 → 20974 `intro.js`, stayed
unchanged. Seven caption/prose contrasts accompany the existing controls.
These are developer checks, not human relevance gold or adoption evidence.

See [Eval Lab's cycle record](https://github.com/npcink/npcink-eval-lab/blob/59b31d6b38428c2123d6450940d8d64e570afba9/docs/link-recommendation-solution-caption-2026-10-27.md)
for source hashes, frozen holdout, live-editor checks, model failures and limits.
The rule was frozen before examining holdout; holdout must not tune this rule.
After the initial embedding timeout, the same 10-source/full-corpus holdout
completed: all 80 candidate eligibility outcomes and reasons match v2, with
26 eligible under either version. No candidate triggered the new caption guard;
this supports no observed collateral eligibility regression, not new semantic
quality or release acceptance. The cycle records a new container receipt and
the exact two-line budget-path difference excluded from the isolated fixture.

## Limits and verification

This does not solve generic `你也可以` anchors or the `管理工具` /
`管理工具栏` destination mismatch. Those remain in
[Known Issues](known-issues.md). Avoid expanding the caption list from holdout
or replacing destination/task analysis with an increasingly broad blacklist.

Run `composer test:internal-link-editor-js`, `composer test:all`,
`composer validate --no-check-publish` and `composer check:wporg`.
Real RichText, UI reference-only rendering and useful-positive Apply/Undo
evidence supplement the Node contracts; intercepted response replay does not
prove Cloud API or Addon integration. No new write route, background executor,
shared index synchronization or WordPress.org release is part of this change.
Revert this scoped source change to roll back to the recorded v2 behavior.
