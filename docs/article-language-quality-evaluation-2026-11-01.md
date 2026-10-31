# Chinese language quality evaluation — 2026-11-01

Module: existing read-only language review. Started 2026-10-31, completed
2026-11-01. This is bounded developer evidence using synthetic text, not
independent gold, customer acceptance, broad real-article accuracy or release.
No Cloud prompt, model binding, Addon, provider settings, WordPress source,
OSS integration or production deployment changed in this evaluation.

## Decision and frozen material

The previous six seeds established a useful path, not enough quality coverage.
Before new calls, freeze [40 balanced cases](article-language-quality-cases-v1.json):
five positive and five negative examples each for punctuation, redundancy,
sentence structure and reference. The initial eight-case screen passed before
the remaining 32 were called once. The declared targets were at least 80%
positive case hits, at most 10% negative-case false alarms, zero invalid quote
ranges and zero automatic writes. Category hit means a verified quote and the
expected kind; it does not prove every finding in that case is useful.

Next, freeze [12 context cases](article-language-context-cases-v1.json) covering
multiple blocks, longer passages, emoji/U+2028/whitespace, news, medicine,
education, literature, technology and finance. Review found that four supposed
negative cases contained literal repeated boilerplate, so their no-hint labels
were wrong. The model's duplicate-content hints were valid. Original fixtures,
outputs and failed label expectations remain recorded; do not silently edit
labels or count these as model false alarms. Four new, nonduplicated
[control passages](article-language-context-control-holdout-v1.json) were frozen
before separate calls. They are fresh controls, not reruns of failed cases.

## Observations and disposition

| Slice | Observed result | Interpretation |
| --- | --- | --- |
| 40 basic cases | 20/20 positives hit expected kinds; 19/20 negatives had zero hints | Meets the declared developer screen; one style-only false alarm remains |
| 12 original context cases | 4/4 target cases hit; 4 valid controls had zero hints; 4 mislabeled controls correctly exposed real duplicates | Keep the four label errors separate from model-quality counts |
| 4 fresh context controls | 4/4 had zero hints | Supports normal technology/medical/educational/financial prose within this sample |
| All 56 explicit calls | One signed pii/no_store execute per case, retry zero; ready reports; zero invalid findings/ranges; no post writes | No selective rerun, fabricated quote or automatic correction |

The remaining false alarm is `sentence-control-5`: the normal sentence
“我们应当认真发现并解决工作中存在的问题。” received a suggestion that
“认真” was an unnatural modifier for “发现”. This is a style preference and
should be suppressed by the Cloud analysis owner. Keep its exact run and
outcome as a regression; do not change the original negative label. Subsequent
prompt work should also check fresh normal coordination/modifier controls.
The development agent reviewed all returned reasons/directions: hints remain
manual inspection suggestions and contain no executable replacement field.

The [body-free receipt](article-language-quality-evidence-2026-10-31.json)
retains every case outcome, original label error and run_id plus fixture hashes.
Raw synthetic reports stay local. Actual loaded Toolbox source was `97972297`,
Addon was local overlay `7c1483c3`. Cloud liveness observed clean `31a67a42ac5c`
during/after the run; the first screen has no independent pre-run revision
receipt. This task changed neither shared M4 nor provider configuration.
Request no-store flags are not a new upstream-provider retention guarantee.

## Reuse and next step

`tests/smoke-article-language-quality.php` is a bounded manual WP-CLI development
runner, outside `test:all`. It requires a new output file and allows selected
known case IDs. It uses the installed Toolbox/Addon and existing loopback
runtime; it blocks post writes and unrelated outbound requests, checkpoints
results, and performs no automatic retry. Do not feed it customer articles.
Example (the shell must point WP-CLI at the intended local PHP/site/socket):

```sh
NPCINK_ARTICLE_QUALITY_OUTPUT=/tmp/new-language-quality-run.json \
NPCINK_ARTICLE_QUALITY_IDS=punctuation-positive-1,punctuation-control-1 \
wp --path=/path/to/wordpress eval-file tests/smoke-article-language-quality.php
```

Use `NPCINK_ARTICLE_QUALITY_CASES` for a named committed context fixture. All
fixture identities and the receipt are checked by offline static contracts;
normal CI must not spend model calls. `composer test:all`, metadata/wporg and
`git diff --check` are the local source gates for this evaluation slice.

Next: suppress style-only language nitpicks in the Cloud prompt while defining
bounded logic and context-sensitive content-risk checks. Keep existing language
compatibility, strict evidence/privacy/freshness guards and author-only changes.
This sample does not finish broad quality work; ordinary user feedback and
new independently frozen cases remain useful, without a mandatory human worksheet.
