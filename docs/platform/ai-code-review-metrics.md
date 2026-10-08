# AI Code Review Effectiveness Metrics

Companion record to
[`ai-code-review-standard-v1.md`](ai-code-review-standard-v1.md)
("Effectiveness Metrics - 2026-10-07"). Three numbers per month, per
enrolled repository, collected by hand; no dashboard and no automation.

## The Three Metrics

1. **Delivery rate** = merged pull requests with at least one delivered
   review round over all merged pull requests. A delivered round is the
   sticky `<!-- ocr-summary -->` bot comment (any finished outcome:
   findings, zero findings, or selection skip) or posted bot review
   comments. A failed run does not deliver; a merged pull request that
   failed its round and carried no recorded exception is a gap and is
   listed by number, never silently passed.
2. **Findings by severity** = delivered inline findings grouped by their
   badge category and severity (`bug-high`, `security-medium`, ...).
3. **Triage ratio** = maintainer thread replies starting "Fixed in"
   versus replies starting "Declined" on those inline findings.

## Collection Recipe

One pass over the pull requests merged since the previous record. Adapt
the range and the maintainer login; expect roughly three API calls per
pull request.

```bash
for pr in $(gh pr list --repo <owner>/<repo> --state merged --limit 100 \
              --json number --jq '.[].number'); do
  # delivery: sticky summary comment
  gh api "repos/<owner>/<repo>/issues/$pr/comments" \
    --jq '[.[] | select(.user.login=="github-actions[bot]")
           | select(.body|contains("<!-- ocr-summary -->"))] | length'
  # review rounds: submitted bot reviews
  gh api "repos/<owner>/<repo>/pulls/$pr/reviews" \
    --jq '[.[] | select(.user.login=="github-actions[bot]")] | length'
  # findings and triage: inline comments, bots and maintainer
  gh api "repos/<owner>/<repo>/pulls/$pr/comments" \
    --jq '{findings: [.[] | select(.user.login=="github-actions[bot]")
                      | .body | scan("badge/([a-z]+)-(high|medium|low)-")
                      | join("-")],
           fixed: [.[] | select(.user.login=="<maintainer>")
                   | .body | select(test("^Fixed in"))] | length,
           declined: [.[] | select(.user.login=="<maintainer>")
                      | .body | select(test("^Declined"))] | length}'
done
```

Known fidelity limits, accepted at this tier: findings are counted per
inline comment, so one finding repeated across re-review rounds of the
same pull request counts twice; and the triage ratio describes only the
threads where the per-thread Fixed/Declined protocol applied (the pilot's
early rounds predate it).

## Records

### 2026-10 - pilot baseline (npcink-abilities-toolkit, #141-#210)

Window: 2026-09-29 (enrollment pull request #141) through 2026-10-07
(#210, governance and 0.5.8 closeout).

| Metric | Value |
| --- | --- |
| Delivery rate | 65/68 merged (96%) |
| Findings | 146 inline comments |
| Triage | 52 fixed : 9 declined (~5.8:1) |

Findings by severity: bug 64 (10 high / 34 medium / 20 low),
maintainability 41 (1/9/31), documentation 16 (-/7/9), test 10 (1/2/7),
other 8, security 4 (2 high / 2 medium), performance 2, style 1.

Delivery gaps, all accounted for:

- **#141** (the enrollment pull request itself): `pull_request_target`
  executes the workflow from the base branch, where it did not exist yet;
  by design, no run could register for its own enabling pull request.
- **#185**: the silent provider-side failure that started the hardening
  arc; merged unreviewed before the failure marker existed. That gap is
  the reason delivery confirmation is now a rule.
- **#196**: failed run 37558997969; the `<!-- ocr-review-failed -->`
  marker was posted (its first live exercise after hardening) and the
  pull request merged with the marker present without a separately
  recorded unreviewed-merge exception. This record covers that gap; from
  the publisher-gate repositories onward the exception recording is
  mechanized.

Triage honesty: 52 + 9 = 61 recorded thread replies against 146 findings.
Rounds delivered before the per-thread Fixed/Declined protocol hardened -
the pilot's first week, and the #181 release-preparation round in
particular - carry findings, including bug-medium and bug-high ones,
without recorded per-thread replies, so the 5.8:1 ratio describes only
the threads where the protocol applied. Those rounds were dispositioned
outside the thread record; this baseline records the gap rather than
assuming either outcome for the un-replied remainder.

### 2026-10 window 2 - 0.5.9 release cycle (npcink-abilities-toolkit, #211-#216)

Window: 2026-10-07 (#211, advisory workflow re-syncs) through 2026-10-08
(#216, 0.5.9 publication closeout).

| Metric | Value |
| --- | --- |
| Delivery rate | 6/6 merged (100%) |
| Findings | 6 inline comments |
| Triage | 3 fixed : 3 declined (1:1) |

Findings by severity: maintainability 6 (all low).

Predictive-value case: three of the six findings on #213 flagged the same
defect class — scenario-card strings rendered through dynamic `esc_html__()`
calls, invisible to extraction tooling. The thread was initially declined as
a deliberate render-layer design, then validated as a real defect within 24
hours when the packaged-plugin Plugin Check gate rejected the identical
pattern with ERROR-level
`WordPress.WP.I18n.NonSingularStringLiteralText` findings during 0.5.9
release verification; the shipped fix adopted the reviewer's extraction
concern through a literal translation map
(`Admin\Scenario_Translations`). The advisory review surfaced a WordPress.org
review rule before the official gate ran.

The three declines (contract-data localization posture twice, one
harness-only URL fallback) carry evidence-backed rationale in the thread
record; one fixed thread was a partial accept (brand heading kept, wrapped
in the existing plugin-name msgid). Zero-finding windows on #214, #215
(release preparation), and #216.
