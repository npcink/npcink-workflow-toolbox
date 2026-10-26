> Historical source preserved on 2026-10-26 from `d05dcb6cb42e70e9caf300b7c634a6c7ac5e606b`.
> The text below records the 2026-10-15 state, including then-open PR #269.
> For later merges, current policy and recoverable refs, read the
> [five-session audit](../../session-closeout-audit-2026-10-26.md).
> This archive does not resume the stopped Provider route or its schedule.

# Local OCR Final Feasibility Closeout — 2026-10-15

Status: the final authorized Provider attempt failed. Stop this Provider route
for the proposed local publication gate. No automatic retry, further splitting,
budget increase, model switch or workflow activation follows from this record.
Shared local configuration remains useful; the proposed publication migration
is not accepted or merged. ADR-020 and the existing publisher remain authoritative.

## Scope And Authorization

The operator explicitly brought the previously scheduled final attempt forward
to 2026-10-15. The invocation used a 120,000-token aggregate budget and a daily
attempt limit of four, preserving the existing three attempts. Both overrides
were process-local; the 60,000-token and three-attempt defaults were unchanged.
No accounting file was reset. The next-day heartbeat `slice-1-ocr` was paused
before execution and a one-shot marker prevents consuming this authorization
again.

The clean review range was
`444128a516b072e4ddc87c4ac759ea96daefa7bb..60062a18d9022011a4107bae2dfa3f41ada78eac`:
two files and 292 added lines. It used the existing shared provider and model,
temporarily setting only `extra_body.enable_thinking=false`. Concurrency stayed
one, effort low, prompt ceiling 64,000, task deadline ten minutes and the gate's
outer deadline 660 seconds. No model, endpoint or credential was changed.

## Observed Result

| Evidence | Result |
| --- | --- |
| Shanghai start / finish | 2026-10-15 19:28:49 / 19:29:59 |
| Wrapper elapsed | 69.7 seconds |
| Gate exit | 1; incomplete review |
| Manifest status / terminal state | `partial` / `partial` |
| Selected / completed / reused / failed / waived | 2 / 1 / 0 / 1 / 0 |
| Failed item | `scripts/local-ocr-gate.sh`, classification `budget` |
| Budget exceeded | `true` |
| CLI-reported input / output / total tokens | 146,975 / 4,251 / 151,226 |
| CLI-reported cache-read tokens | 98,048 |
| Comments | 3; inspected individually below |

The Python item completed, but the shell item did not. No complete exact-range
receipt exists, and the result cannot authorize publication. This attempt did
not time out. It does not prove that the custom gateway honors the reasoning
control, nor establish an authentication failure.

The reported total exceeds the configured 120,000-token threshold. This CLI
budget setting did not enforce a strict token ceiling in the observed run.
Cache-read tokens are reported separately and must not be added to the total;
the report does not establish billed tokens, prices or monetary spend.

The previous 60,000-token Slice 1 attempt also completed one of two files; its
CLI-reported total was 85,797 tokens. Review size in changed lines alone is
therefore insufficient to predict the aggregate context cost. Raising the
budget again without a verified admission/accounting model is not justified.

## Finding Disposition For The Frozen Diagnostic HEAD

Incomplete coverage invalidates publication evidence; it does not invalidate
individual findings. These dispositions are based on direct source inspection,
not on treating a failed review as zero findings.

| Finding | Assessment and disposition |
| --- | --- |
| Corrupt cooldown timestamp produces generic `ValueError` diagnostic | Accept for this diagnostic HEAD: the top-level handler fails closed before Provider execution. Future production work should improve the diagnostic and atomic state writes. Do not adopt the suggested nonnumeric-state skip, which could bypass cooldown. |
| Finding-specific regex built inside the comment loop | Accept: the finding id changes on each iteration, so each pattern differs. No unbounded production review was demonstrated; this is not a correctness defect. |
| Comment string shadows the earlier `identity()` helper | Accept for this diagnostic HEAD: all helper calls finish before the comment loop and no later path calls it. A future maintained implementation should use distinct names. |

No source fix was made merely to obtain a different review outcome. Any future
source change creates a new HEAD and needs its own explicitly authorized review.

## Publication And Split Limitations

The four local slices remain preserved, not published:

| Slice | HEAD | Incremental code/config scope |
| --- | --- | --- |
| Gate core | `60062a1` | 2 files / 292 lines |
| Evidence tests and Composer wiring | `d74ffab` | 3 files / 84 added-plus-removed lines |
| Publisher wiring | `2f69d7b` | 3 files / 96 added-plus-removed lines |
| Documentation and template | `a6842ee` | No reviewable code/config files |

The final tree equals the original `d068af2` bootstrap tree, including modes.
That equivalence is not review coverage. The original `13bc85a` combined branch,
`d068af2` bootstrap and `b101e78` compatibility candidate remain unchanged.

Slice 1 still contains the legacy publisher, which waits for a GitHub review.
The GitHub `ocr-review.yml` workflow is `disabled_manually`; a complete local
receipt alone would not satisfy that publisher. Borrowing the unmerged Slice 3
publisher would not make Slice 1 independently publishable. Also, Slice 1's
`--self-test` launcher refers to the evidence test introduced only in Slice 2.
The split stack passed its combined deterministic tests, but that does not prove
each slice is independently ready for publication. Do not publish this stack
without resolving those dependencies through an explicit reviewed plan.

## Recovery And Current Boundaries

- Shared configuration was restored equal to its pre-run parsed contents;
  `extra_body` was absent again and file mode remained `0600`.
- The shared review lock was released. The daily count became four; failed
  attempts remain counted. No retry occurred.
- The next-day heartbeat is paused, and the one-shot marker is retained outside
  all repositories. No additional spending is scheduled by this closeout.
- No push, PR, auto-merge request, workflow enablement, sibling edit, root
  worktree switch, reset or cleanup occurred.
- PR #269 remains a separate product acceptance task. Its checks were green,
  but no complete candidate/Apply browser acceptance or merge is claimed.

The latest PR #269 smoke used a missing default WordPress root named `npcink`
and stopped before any `wp-json` request. Local `magick-ai` and `magick-toolbox`
roots do exist. The failed default path does not prove that all local WordPress
sites are unavailable. A future owner should select the documented site,
runtime and socket explicitly before retrying the smoke, and confirm the Cloud
call budget separately. No new Cloud or Provider call was made for that check.

## Development Lessons And Re-entry Criteria

1. Verify the entire review-to-publisher path before spending on a new split.
   Small independent commits must include the tests they invoke and must have
   a publisher able to consume their accepted evidence.
2. Distinguish configured budget, admission decisions, reported usage and
   billed cost. Establish overshoot semantics offline before another paid run;
   an aggregate CLI parameter alone is not a hard spending guarantee.
3. A local config source is reusable across projects without making an unstable
   Provider a mandatory publication dependency. Keep credentials on the machine
   and keep the local-configuration decision separate from gate activation.
4. Preserve failed counts, exact base/HEAD, sanitized coverage and individual
   finding decisions. An incomplete review must never become a passing receipt.
5. Cancel a scheduled attempt before bringing it forward, and retain an atomic
   one-shot marker. Restore only the temporary configuration control while
   preserving concurrent unrelated edits.
6. Validate environment paths before browser tests. A wrong default is a test
   setup problem; it is not evidence that the application or Cloud is down.

Only offline comparisons are next: a provider/model with documented strict
usage behavior, or a controlled manual CI lane with explicit secret mirroring,
exact-SHA receipt and triage. Neither route is activated here. Re-entry needs
an independently publishable implementation, an explicit review policy and a
new bounded spending authorization; do not raise budgets or resume this route
automatically.
