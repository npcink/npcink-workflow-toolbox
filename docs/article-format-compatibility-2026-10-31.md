# Formatting compatibility follow-up — 2026-10-31

Scope: Toolbox v2 preservation validators only. No Cloud rules, Addon contract,
WordPress write authority, model selection, OSS integration or production release.
This follows the two safe refusals in the
[original acceptance](article-format-preservation-acceptance-2026-10-30.md).
See the [body-free receipt](article-format-compatibility-evidence-2026-10-31.json).

## Diagnosis and behavior

Article 280942 contains unsupported spans and unbalanced legacy inline HTML.
The old PHP projection failed the whole article, even when Cloud changed only
a separate safe paragraph. Each unsupported paragraph now becomes one opaque
serialized block. It must remain exact and ordered; it does not prevent safe
sibling formatting. The browser also preserves unchanged invalid blocks and
their client IDs. It never repairs the legacy markup or adds a new parser engine.

Article 280861 combines structural repairs with heading/quote spacing. The
old v2 projection treated the latter as wholly immutable despite v1 supporting
that spacing. Fixed-layout supported blocks now reuse v1's bounded spacing
validation: exact layout/attributes and protected subtrees, with only one
CJK/Latin-or-digit boundary space allowed. Browser validation separately checks
block attributes, inline attributes and protected link/code/media nodes.
Punctuation, words, original whitespace, links and multimedia remain unchanged.
Metadata-bound and unsupported blocks cannot use this allowance.

## Verification and practical limits

- Real WordPress tokenizer tests cover useful mixed results plus mutations of
  opaque text, punctuation, spacing, heading attributes, URLs, link labels and
  quote text. Existing media/code protection regressions pass.
- Both saved sources pass independent task PHP validation against the existing
  offline Cloud formatter. Saved database SHA-256 values match the original
  2026-10-30 evidence, and no original is written.
- A first browser probe correctly rejected testing raw saved HTML as native
  editor serialization. Native Gutenberg serialization was then exported locally,
  formatted offline and independently accepted by the real browser validator.
  Two subsequent real signed Addon/Cloud calls returned exactly the same
  candidate hashes and passed task PHP validation: both PARTIAL, one structural
  change each, respectively zero and 42 inserted spaces. No model call is used.
- The existing rich-paragraph/table/code disposable browser fixture passed
  real signed formatting, visible application, stable protected IDs, native
  Undo/Redo, one-shot Undo, late-response rejection, Cloud-failure preservation,
  report freshness and 390px layout. No post-write request or page error occurred.
  Two native preference attempts were blocked; synthetic drafts/sessions were
  removed, including the failed probe. Task assets were intercepted; this is
  not yet a new-source installed PHP/browser receipt.
- `composer test:all`, `composer lint:standards`, `composer analyse:php`,
  actual WordPress tokenizer smoke and `git diff --check` passed. Initial
  indentation lint findings were corrected. Existing PHP dynamic-property
  deprecations remain recorded debt.

These results close the two known validator compatibility refusals. They do
not claim every legacy article can be formatted, that invalid HTML is fixed,
or that production is deployed. Cloud source revision was not independently
recorded for the two deterministic signed calls; no shared runtime was changed.
A new incompatible/uncertain input remains safely blocked.

Next: expand language-quality evaluation, then specify and evaluate bounded
logic/content-risk suggestions. All corrections remain the author's work.
Rollback reverts the Toolbox validator/test slice; no article migration exists.
