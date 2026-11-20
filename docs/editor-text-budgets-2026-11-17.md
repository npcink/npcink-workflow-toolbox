# Editor and Smart Ask text budgets — 2026-11-17

Status: operator-approved local development candidate on `codex/editor-text-budgets`,
based on `b6729167bcaa096b4032a5a41b0e8612866d81a7`. This is correctness maintenance
of existing editor / five answer paths; current freezes and pauses remain.

| Consumer | Bound and handling |
| --- | --- |
| Smart Ask + four insight QA bridges | 2,000 Unicode characters + 8,000 UTF-8 bytes; preserve full question. The front end shows a Chinese limit/retry instruction before sending invalid input. REST schema and callback validation remain independent. |
| Local article-check context | 50,000 plain-text Unicode characters; preserve existing report coverage / truncation disclosure and bounded paragraph checking. This is not the 30k UTF-16 Cloud language-review snapshot. |
| Full-context summary | 50,000 normalized plain-text characters; direct oversized input rejects before transport. Fast brief stays deliberately compressed; short output budgets stay unchanged. |
| Paragraph review | 12,000 characters; reject oversized selections before Cloud, use actual selected text before whole-block fallback, and preserve that exact content into the final prompt instead of the old 420-word crop. |
| Formatting | 50,000 raw HTML Unicode characters including markup and 256 KiB input; 512 KiB UTF-8 result. Existing immutable-source, hash, semantic protection and native reviewed adoption checks remain. |

`Provider_Client_Support::bounded_text` no longer falls through from a successful
character check into byte clipping. Its slice/count/trim helpers and the editor
trim helper count/slice Unicode even without mbstring. Comment/ALT/retrieval/audio
budgets and source-drafting pause remain unchanged.

Verification: `composer test:all`; focused editor, provider and Smart Ask suites
also pass `php -n`; Chinese / emoji / ASCII exact boundaries and +1 are covered.
Provider tests now honor JSON flags/depth and inspect the actual final source
prompt, not only input. Real installed WordPress preserves 50k contexts / 12k
selections, rejects oversized selections, and accepts signed 50k Chinese/emoji
formatting results from M4 with zero Provider calls; article 279790 fingerprint
remains unchanged. Real PC Smart Ask 2001-character submission shows the Chinese
limit with a concrete retry instruction. No paid generation or article saving.

The authoritative cross-repo budgets, token/completion policy and evidence states
are in Cloud's [follow-through contract](https://github.com/npcink/npcink-ai-cloud/blob/master/docs/wordpress-text-budget-followthrough-2026-11-17.md)
and [compatibility matrix](https://github.com/npcink/npcink-ai-cloud/blob/master/docs/compatibility-matrix.md).
Source + local/M4 candidate evidence do not establish CI, merge, M4 accepted,
production or real-model quality/latency acceptance. External group:
`text-budgets-20261117`.

Regression rule: trace a budget through all sanitizers and consumers, test final
prompt/result units, preserve Unicode boundaries without optional extensions,
and show users a specific remedy. Intentional summaries must say what they
sample; whole transformations must reject incomplete output. Do not restore
paused features to prove a numeric cap. Rollback these budget/Unicode/error
hunks only; no option, content, ability, governance or write ownership migration.
